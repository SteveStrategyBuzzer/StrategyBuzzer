const express = require('express');
const crypto = require('crypto');
const jwt = require('jsonwebtoken');
const { GoogleGenAI } = require('@google/genai');
const { router: aiRouter, validation: aiValidation } = require('./providers');

// Imagen (image generation) is out of scope for the text router. We keep a
// direct GoogleGenAI client so /generate-image-question keeps working using
// the first configured Gemini key.
function pickImagenApiKey() {
  const list = process.env.GEMINI_API_KEYS;
  if (list && list.trim()) return list.split(',')[0].trim();
  return process.env.GEMINI_API_KEY || '';
}
let _imagenClient = null;
function getImagenClient() {
  if (_imagenClient) return _imagenClient;
  const key = pickImagenApiKey();
  if (!key) throw new Error('No Gemini key configured for image generation');
  _imagenClient = new GoogleGenAI({ apiKey: key });
  return _imagenClient;
}

const app = express();

// Healthcheck (Nginx + monitoring) — extended with AI router stats so we can
// see provider/key health, last failover and last contract rejection.
app.get("/health", (req, res) => {
  const aiHealth = aiRouter.getHealth();
  res.status(200).json({
    ok: true,
    ts: Date.now(),
    ai_router: aiHealth,
  });
});

// We need the raw bytes of the request body so the admin JWT can prove the
// body it received is exactly the body the caller signed (sha256 hex match).
// Capturing the raw buffer in express.json's `verify` is the cheapest way
// to do this without parsing twice.
app.use(express.json({
  verify: (req, _res, buf) => {
    if (buf && buf.length) {
      req.rawBody = buf;
    }
  },
}));

// ============================================================================
// #94 — ADMIN-JWT GUARD for composition tools.
//
// After #88 the AI router was reachable only by callers that knew the static
// MASTER_API_ADMIN_TOKEN shared secret. #94 replaces that with short-lived
// per-call JWTs minted by Laravel (App\Services\QuestionApi\QuestionApiClient)
// so we get: per-user identity (sub claim), per-call audit, and natural
// rotation/revocation (60s TTL).
//
// Token rules (HS256, secret = QUESTION_API_JWT_SECRET, fallback
// GAME_SERVER_JWT_SECRET so a single-secret deploy keeps working):
//   - aud === 'question-api'
//   - purpose === 'qapi_admin'
//   - endpoint === <request path>          (binds token to one route)
//   - payload_hash === sha256_hex(rawBody) (binds token to one body)
//   - exp <= iat + 60s, jti seen at most once within its TTL
//
// If the secret is missing or weaker than 16 chars, EVERY request is rejected
// (fail-closed) so an accidentally-deployed worker without auth material
// cannot expose AI generation publicly.
// ============================================================================
// Pure function so tests can exercise the resolver (and especially the
// "missing/weak secret" branch) without having to re-require the whole
// module. The actual ADMIN_JWT_SECRET below is `resolveAdminJwtSecret(process.env)`.
function resolveAdminJwtSecret(env) {
  const source = env || {};
  const candidates = [
    source.QUESTION_API_JWT_SECRET || '',
    source.GAME_SERVER_JWT_SECRET || '',
  ];
  for (const raw of candidates) {
    if (!raw) continue;
    const value = raw.startsWith('base64:')
      ? Buffer.from(raw.slice('base64:'.length), 'base64').toString('utf8')
      : raw;
    if (value.trim().length >= 16) return value;
  }
  return '';
}

const ADMIN_JWT_SECRET = resolveAdminJwtSecret(process.env);

const ADMIN_JWT_AUDIENCE = 'question-api';
const ADMIN_JWT_PURPOSE = 'qapi_admin';
const ADMIN_JWT_MAX_LIFETIME_SECONDS = 60;
const ADMIN_JWT_CLOCK_SKEW_SECONDS = 5;

// Replay protection — Redis-backed.
//
// #110 — A captured admin JWT (60s TTL) used to be remembered in a per-process
// `Map`. That broke down across restarts and across replicas behind a load
// balancer: the same token could be replayed, up to its `exp`, on a fresh
// instance. We now claim each `jti` atomically in Redis with
// `SET key 1 NX EX <remaining_ttl>`:
//   - The first request that presents a given jti wins ('OK').
//   - Any later request with the same jti gets `null` (key already exists)
//     and is rejected as a replay.
//   - The key auto-expires when the JWT does, so the keyspace stays bounded.
// If Redis is unavailable, the middleware fails closed (HTTP 503) — there is
// no silent fallback to in-memory state, because that would re-open the very
// hole this task closes.
const ADMIN_JWT_JTI_PREFIX = 'qapi:admin-jti:';
let _adminJwtRedisClient = null;
function getAdminJwtRedisClient() {
  if (_adminJwtRedisClient) return _adminJwtRedisClient;
  const Redis = require('ioredis');
  const RedisCtor = Redis.default || Redis;
  const url = process.env.REDIS_URL || 'redis://127.0.0.1:6379';
  _adminJwtRedisClient = new RedisCtor(url);
  return _adminJwtRedisClient;
}
function setAdminJwtRedisClient(client) {
  _adminJwtRedisClient = client;
}

// Atomically claim a jti for the remainder of its lifetime. Returns
// 'fresh' the first time the jti is presented, 'replay' on every subsequent
// call (until the key expires). Throws on Redis errors so the caller can
// fail the request closed.
async function claimJti(jti, exp) {
  const ttl = Math.max(1, exp - Math.floor(Date.now() / 1000));
  const key = ADMIN_JWT_JTI_PREFIX + jti;
  const result = await getAdminJwtRedisClient().set(key, '1', 'NX', 'EX', ttl);
  return result === 'OK' ? 'fresh' : 'replay';
}

function denyAdmin(res, status, error, details) {
  return res.status(status).json({ success: false, error, details });
}

async function requireAdminJwt(req, res, next) {
  if (!ADMIN_JWT_SECRET) {
    return denyAdmin(res, 503, 'admin_jwt_not_configured',
      'QUESTION_API_JWT_SECRET (or GAME_SERVER_JWT_SECRET) must be set on the question-api service.');
  }

  const authHeader = req.header('authorization') || '';
  const match = /^Bearer\s+(.+)$/i.exec(authHeader.trim());
  if (!match) {
    return denyAdmin(res, 401, 'missing_admin_jwt',
      'Authorization: Bearer <jwt> header is required for AI composition endpoints.');
  }
  const token = match[1].trim();

  let claims;
  try {
    claims = jwt.verify(token, ADMIN_JWT_SECRET, {
      algorithms: ['HS256'],
      audience: ADMIN_JWT_AUDIENCE,
      clockTolerance: ADMIN_JWT_CLOCK_SKEW_SECONDS,
    });
  } catch (err) {
    return denyAdmin(res, 403, 'invalid_admin_jwt',
      `JWT verification failed: ${err && err.message ? err.message : 'unknown'}`);
  }

  if (!claims || typeof claims !== 'object') {
    return denyAdmin(res, 403, 'invalid_admin_jwt', 'JWT payload is not an object.');
  }
  if (claims.purpose !== ADMIN_JWT_PURPOSE) {
    return denyAdmin(res, 403, 'invalid_admin_jwt', `purpose must be "${ADMIN_JWT_PURPOSE}".`);
  }

  const requestPath = req.path || req.originalUrl || '';
  if (claims.endpoint !== requestPath) {
    return denyAdmin(res, 403, 'invalid_admin_jwt',
      `endpoint claim "${claims.endpoint}" does not match request path "${requestPath}".`);
  }

  const iat = typeof claims.iat === 'number' ? claims.iat : 0;
  const exp = typeof claims.exp === 'number' ? claims.exp : 0;
  if (!iat || !exp || exp - iat > ADMIN_JWT_MAX_LIFETIME_SECONDS + ADMIN_JWT_CLOCK_SKEW_SECONDS) {
    return denyAdmin(res, 403, 'invalid_admin_jwt',
      `token lifetime must be <= ${ADMIN_JWT_MAX_LIFETIME_SECONDS}s.`);
  }

  const jti = typeof claims.jti === 'string' ? claims.jti : '';
  if (!jti) {
    return denyAdmin(res, 403, 'invalid_admin_jwt', 'jti claim is required.');
  }

  // Bind the token to the exact bytes of the body the caller signed. If
  // anything (a proxy, a man-in-the-middle, a buggy retry) mutated the body,
  // the hash will not match and we reject — even if the JWT is otherwise
  // valid. Empty body => hash of "".
  const rawBody = req.rawBody || Buffer.alloc(0);
  const computedHash = crypto.createHash('sha256').update(rawBody).digest('hex');
  if (typeof claims.payload_hash !== 'string' ||
      claims.payload_hash.length !== computedHash.length ||
      !crypto.timingSafeEqual(
        Buffer.from(claims.payload_hash, 'hex'),
        Buffer.from(computedHash, 'hex'),
      )) {
    return denyAdmin(res, 403, 'invalid_admin_jwt',
      'payload_hash claim does not match sha256(body).');
  }

  // Atomic claim — also serves as the replay check. Done last so that a
  // request rejected for any earlier reason does not burn its jti.
  let claimResult;
  try {
    claimResult = await claimJti(jti, exp);
  } catch (err) {
    console.error('[admin-jwt] replay-store error', err && err.message ? err.message : err);
    return denyAdmin(res, 503, 'admin_jwt_replay_store_unavailable',
      `Replay-protection store is unavailable: ${err && err.message ? err.message : 'unknown error'}`);
  }
  if (claimResult !== 'fresh') {
    return denyAdmin(res, 403, 'invalid_admin_jwt', 'jti has already been used (replay).');
  }

  req.adminCaller = {
    sub: typeof claims.sub === 'string' ? claims.sub : null,
    jti,
    endpoint: claims.endpoint,
    payloadHash: claims.payload_hash,
    iat,
    exp,
  };

  console.log('[admin-jwt] accepted', {
    endpoint: claims.endpoint,
    sub: req.adminCaller.sub,
    jti,
    payloadHashPrefix: computedHash.slice(0, 12),
  });

  return next();
}

// Backwards-compat alias used by the route definitions below.
const requireAdminToken = requireAdminJwt;

// Exposed for unit tests.
module.exports.__test = {
  requireAdminJwt,
  claimJti,
  setAdminJwtRedisClient,
  resolveAdminJwtSecret,
  ADMIN_JWT_SECRET,
  ADMIN_JWT_AUDIENCE,
  ADMIN_JWT_PURPOSE,
  ADMIN_JWT_MAX_LIFETIME_SECONDS,
  ADMIN_JWT_JTI_PREFIX,
};

// Mapping des langues supportées avec traductions vrai/faux
const LANGUAGES = {
  'fr': { name: 'Français', dict: 'français', true: 'Vrai', false: 'Faux' },
  'en': { name: 'English', dict: 'English', true: 'True', false: 'False' },
  'es': { name: 'Español', dict: 'español', true: 'Verdadero', false: 'Falso' },
  'it': { name: 'Italiano', dict: 'italiano', true: 'Vero', false: 'Falso' },
  'el': { name: 'Ελληνικά', dict: 'grec', true: 'Αληθές', false: 'Ψευδής' },
  'de': { name: 'Deutsch', dict: 'allemand', true: 'Wahr', false: 'Falsch' },
  'pt': { name: 'Português', dict: 'portugais', true: 'Verdadeiro', false: 'Falso' },
  'ru': { name: 'Русский', dict: 'russe', true: 'Правда', false: 'Ложь' },
  'ar': { name: 'العربية', dict: 'arabe', true: 'صحيح', false: 'خطأ' },
  'zh': { name: '中文', dict: 'chinois', true: '正确', false: '错误' }
};

const THEMES_FR = {
  'general': 'culture générale',
  'geographie': 'géographie',
  'histoire': 'histoire',
  'art': 'art et culture',
  'cinema': 'cinéma et films',
  'sport': 'sport',
  'cuisine': 'cuisine et gastronomie',
  'faune': 'animaux et nature',
  'sciences': 'sciences'
};

// Catalogue RESTRUCTURÉ : 8 thèmes × 15 sous-thèmes = 120 sous-thèmes
// Culture générale pioche dans les 120 sous-thèmes des autres thèmes
const SUBTHEME_CATALOG = {
  // ============ 1. GÉOGRAPHIE (15 sous-thèmes) ============
  'géographie': [
    'les capitales du monde',
    'les fleuves et rivières',
    'les chaînes de montagnes',
    'les déserts',
    'les océans et mers',
    'les îles célèbres',
    'les volcans',
    'les lacs',
    'les frontières et territoires',
    'les climats et zones climatiques',
    'les parcs nationaux',
    'les forêts et jungles',
    'l\'écologie et environnement',
    'les records géographiques',
    'les drapeaux et symboles nationaux'
  ],
  'geography': [
    'world capitals',
    'rivers and streams',
    'mountain ranges',
    'deserts',
    'oceans and seas',
    'famous islands',
    'volcanoes',
    'lakes',
    'borders and territories',
    'climates and climate zones',
    'national parks',
    'forests and jungles',
    'ecology and environment',
    'geographical records',
    'flags and national symbols'
  ],
  
  // ============ 2. HISTOIRE (15 sous-thèmes) ============
  'histoire': [
    'l\'Antiquité (Égypte, Grèce, Rome)',
    'le Moyen Âge',
    'la Renaissance',
    'les grandes guerres mondiales',
    'les révolutions',
    'les grands explorateurs',
    'les civilisations précolombiennes',
    'la mythologie grecque',
    'la mythologie nordique',
    'la mythologie égyptienne',
    'les empires (Ottoman, Mongol, etc.)',
    'les grandes inventions historiques',
    'les personnages politiques majeurs',
    'les traités et accords historiques',
    'l\'histoire contemporaine (XXe siècle)'
  ],
  'history': [
    'Antiquity (Egypt, Greece, Rome)',
    'the Middle Ages',
    'the Renaissance',
    'World Wars',
    'revolutions',
    'great explorers',
    'pre-Columbian civilizations',
    'Greek mythology',
    'Norse mythology',
    'Egyptian mythology',
    'empires (Ottoman, Mongol, etc.)',
    'great historical inventions',
    'major political figures',
    'treaties and historical agreements',
    'contemporary history (20th century)'
  ],
  
  // ============ 3. SPORTS (15 sous-thèmes) ============
  'sport': [
    'le football',
    'le basketball',
    'le tennis',
    'les Jeux Olympiques',
    'la Formule 1',
    'le rugby',
    'l\'athlétisme',
    'la natation',
    'les sports de combat',
    'le cyclisme',
    'le golf',
    'les sports d\'hiver',
    'les records sportifs',
    'les légendes du sport',
    'les coupes du monde'
  ],
  'sports': [
    'football/soccer',
    'basketball',
    'tennis',
    'Olympic Games',
    'Formula 1',
    'rugby',
    'athletics',
    'swimming',
    'combat sports',
    'cycling',
    'golf',
    'winter sports',
    'sports records',
    'sports legends',
    'World Cups'
  ],
  
  // ============ 4. SCIENCES (15 sous-thèmes) ============
  'sciences': [
    'la physique',
    'la chimie',
    'la biologie',
    'l\'astronomie et l\'espace',
    'les mathématiques célèbres',
    'la médecine et santé',
    'la technologie moderne',
    'l\'informatique et programmation',
    'les inventions scientifiques',
    'les prix Nobel',
    'l\'écologie et climat',
    'la génétique',
    'les grandes découvertes',
    'l\'intelligence artificielle',
    'l\'énergie et ressources'
  ],
  'science': [
    'physics',
    'chemistry',
    'biology',
    'astronomy and space',
    'famous mathematics',
    'medicine and health',
    'modern technology',
    'computer science and programming',
    'scientific inventions',
    'Nobel Prizes',
    'ecology and climate',
    'genetics',
    'great discoveries',
    'artificial intelligence',
    'energy and resources'
  ],
  
  // ============ 5. CINÉMA (15 sous-thèmes) ============
  'cinéma': [
    'les films oscarisés',
    'les réalisateurs légendaires',
    'les acteurs et actrices iconiques',
    'les films d\'animation',
    'les franchises cultes (Marvel, Star Wars, etc.)',
    'les films français',
    'le cinéma d\'horreur',
    'les comédies musicales',
    'les films de science-fiction',
    'les documentaires célèbres',
    'les répliques cultes',
    'les bandes originales',
    'les films des années 80-90',
    'le cinéma québécois',
    'les records du box-office'
  ],
  'cinema': [
    'Oscar-winning films',
    'legendary directors',
    'iconic actors and actresses',
    'animated films',
    'cult franchises (Marvel, Star Wars, etc.)',
    'French films',
    'horror cinema',
    'musicals',
    'science fiction films',
    'famous documentaries',
    'cult movie quotes',
    'soundtracks',
    'films from the 80s-90s',
    'Quebec cinema',
    'box office records'
  ],
  
  // ============ 6. ART (15 sous-thèmes) ============
  'art': [
    'la peinture classique',
    'la sculpture',
    'l\'architecture célèbre',
    'la musique classique',
    'le rock et pop',
    'le jazz et blues',
    'la littérature mondiale',
    'la poésie',
    'la photographie',
    'le street art',
    'la mode et haute couture',
    'le design',
    'les musées célèbres',
    'les mouvements artistiques',
    'la danse'
  ],
  'art et culture': [
    'la peinture classique',
    'la sculpture',
    'l\'architecture célèbre',
    'la musique classique',
    'le rock et pop',
    'le jazz et blues',
    'la littérature mondiale',
    'la poésie',
    'la photographie',
    'le street art',
    'la mode et haute couture',
    'le design',
    'les musées célèbres',
    'les mouvements artistiques',
    'la danse'
  ],
  
  // ============ 7. ANIMAUX (15 sous-thèmes) ============
  'animaux': [
    'les mammifères',
    'les oiseaux',
    'les reptiles',
    'les insectes',
    'les animaux marins',
    'les animaux en danger',
    'les records animaux',
    'les animaux domestiques',
    'les prédateurs',
    'les animaux nocturnes',
    'les migrations animales',
    'les animaux préhistoriques',
    'les animaux venimeux',
    'les comportements animaux',
    'les animaux mythiques et légendaires'
  ],
  'faune': [
    'les mammifères',
    'les oiseaux',
    'les reptiles',
    'les insectes',
    'les animaux marins',
    'les animaux en danger',
    'les records animaux',
    'les animaux domestiques',
    'les prédateurs',
    'les animaux nocturnes',
    'les migrations animales',
    'les animaux préhistoriques',
    'les animaux venimeux',
    'les comportements animaux',
    'les animaux mythiques et légendaires'
  ],
  'animaux et nature': [
    'les mammifères',
    'les oiseaux',
    'les reptiles',
    'les insectes',
    'les animaux marins',
    'les animaux en danger',
    'les records animaux',
    'les animaux domestiques',
    'les prédateurs',
    'les animaux nocturnes',
    'les migrations animales',
    'les animaux préhistoriques',
    'les animaux venimeux',
    'les comportements animaux',
    'les animaux mythiques et légendaires'
  ],
  
  // ============ 8. CUISINE (15 sous-thèmes) ============
  'cuisine': [
    'la gastronomie française',
    'la cuisine italienne',
    'la cuisine asiatique',
    'les desserts du monde',
    'les épices et herbes',
    'les vins et spiritueux',
    'les fromages',
    'les chefs étoilés',
    'les plats traditionnels',
    'la cuisine latino-américaine',
    'les fruits et légumes exotiques',
    'la pâtisserie',
    'les boissons du monde',
    'la cuisine moléculaire',
    'les records culinaires'
  ],
  'cuisine et gastronomie': [
    'la gastronomie française',
    'la cuisine italienne',
    'la cuisine asiatique',
    'les desserts du monde',
    'les épices et herbes',
    'les vins et spiritueux',
    'les fromages',
    'les chefs étoilés',
    'les plats traditionnels',
    'la cuisine latino-américaine',
    'les fruits et légumes exotiques',
    'la pâtisserie',
    'les boissons du monde',
    'la cuisine moléculaire',
    'les records culinaires'
  ]
};

// Collecter tous les 120 sous-thèmes des 8 thèmes principaux pour Culture générale
const ALL_SUBTHEMES_FR = [
  ...SUBTHEME_CATALOG['géographie'],
  ...SUBTHEME_CATALOG['histoire'],
  ...SUBTHEME_CATALOG['sport'],
  ...SUBTHEME_CATALOG['sciences'],
  ...SUBTHEME_CATALOG['cinéma'],
  ...SUBTHEME_CATALOG['art'],
  ...SUBTHEME_CATALOG['animaux'],
  ...SUBTHEME_CATALOG['cuisine']
];

const ALL_SUBTHEMES_EN = [
  ...SUBTHEME_CATALOG['geography'],
  ...SUBTHEME_CATALOG['history'],
  ...SUBTHEME_CATALOG['sports'],
  ...SUBTHEME_CATALOG['science'],
  ...SUBTHEME_CATALOG['cinema'],
  ...SUBTHEME_CATALOG['art et culture'],
  ...SUBTHEME_CATALOG['faune'],
  ...SUBTHEME_CATALOG['cuisine et gastronomie']
];

// Ajouter Culture générale qui pioche dans les 120 sous-thèmes
SUBTHEME_CATALOG['culture générale'] = ALL_SUBTHEMES_FR;
SUBTHEME_CATALOG['general knowledge'] = ALL_SUBTHEMES_EN;
SUBTHEME_CATALOG['general'] = ALL_SUBTHEMES_EN;

// Fonction de mélange déterministe (Fisher-Yates avec seed)
function seededShuffle(array, seed) {
  const shuffled = [...array];
  let currentSeed = seed;
  
  // Générateur pseudo-aléatoire simple basé sur le seed
  const random = () => {
    currentSeed = (currentSeed * 1103515245 + 12345) & 0x7fffffff;
    return currentSeed / 0x7fffffff;
  };
  
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }
  
  return shuffled;
}

// Fonction pour obtenir un sous-thème basé sur le numéro de question
// gameSeed permet d'avoir un ordre aléatoire différent pour chaque jeu
function getSubthemeForQuestion(theme, questionNumber, gameSeed = null) {
  // Normaliser le thème (minuscules, sans accents pour la recherche)
  const normalizedTheme = theme.toLowerCase().trim();
  
  // Chercher les sous-thèmes correspondants
  let subthemes = null;
  
  // Recherche exacte d'abord
  if (SUBTHEME_CATALOG[normalizedTheme]) {
    subthemes = SUBTHEME_CATALOG[normalizedTheme];
  } else {
    // Recherche par mot-clé
    for (const [key, values] of Object.entries(SUBTHEME_CATALOG)) {
      if (normalizedTheme.includes(key) || key.includes(normalizedTheme)) {
        subthemes = values;
        break;
      }
    }
  }
  
  // Si pas de sous-thèmes trouvés, utiliser culture générale
  if (!subthemes) {
    subthemes = SUBTHEME_CATALOG['culture générale'];
  }
  
  // Si un seed est fourni, mélanger les sous-thèmes de façon déterministe
  if (gameSeed) {
    // Convertir le seed en nombre si c'est une chaîne
    const numericSeed = typeof gameSeed === 'string' 
      ? gameSeed.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
      : gameSeed;
    subthemes = seededShuffle(subthemes, numericSeed);
  }
  
  // Rotation: utiliser le numéro de question pour choisir le sous-thème
  const index = (questionNumber - 1) % subthemes.length;
  return subthemes[index];
}

// Fonction pour déterminer le niveau de difficulté
function getDifficultyDescription(niveau) {
  if (niveau <= 10) {
    return 'très facile - questions basiques pour débutants';
  } else if (niveau <= 25) {
    return 'facile - questions de culture générale accessible';
  } else if (niveau <= 50) {
    return 'moyen - questions nécessitant une bonne culture générale';
  } else if (niveau <= 75) {
    return 'difficile - questions détaillées et précises';
  } else {
    return 'très difficile - questions d\'expert avec détails complexes';
  }
}

// Fonction pour déterminer la longueur de question adaptée au niveau
function getQuestionLengthConstraint(niveau) {
  // Déterminer le Boss de référence (arrondir au multiple de 10 supérieur)
  const bossLevel = Math.ceil(niveau / 10) * 10;
  
  // Vitesses de lecture par Boss (mots par minute)
  const speeds = {
    10: 120, 20: 130, 30: 130, 40: 140, 50: 140,
    60: 140, 70: 145, 80: 145, 90: 150, 100: 155
  };
  
  const readingSpeed = speeds[bossLevel] || 120;
  const wordsPerSecond = readingSpeed / 60;
  
  // Distribution : 85% <6s, 10% 7s, 5% >7s
  const random = Math.random() * 100;
  
  if (random < 85) {
    // 85% : questions courtes (<6s de lecture)
    const maxWords = Math.floor(wordsPerSecond * 6);
    return `Question COURTE de maximum ${maxWords} mots (lisible en moins de 6 secondes)`;
  } else if (random < 95) {
    // 10% : questions moyennes (7s de lecture)
    const targetWords = Math.floor(wordsPerSecond * 7);
    return `Question MOYENNE d'environ ${targetWords} mots (lisible en 7 secondes)`;
  } else {
    // 5% : questions longues (>7s de lecture)
    const minWords = Math.floor(wordsPerSecond * 7.5);
    return `Question LONGUE de ${minWords} mots ou plus (nécessite plus de 7 secondes)`;
  }
}

// =============================================================================
// GÉNÉRATION D'IMAGES-MÉMOIRE POUR LE MODE MAÎTRE DU JEU
// =============================================================================

// Éléments visuels organisés par catégories pour les scénarios
const VISUAL_ELEMENTS = {
  nature: {
    present: ['arbre', 'fleur', 'buisson', 'herbe', 'pierre', 'rocher', 'champignon', 'mousse', 'fougère', 'lierre'],
    absent: ['cactus', 'palmier', 'bambou', 'baobab', 'séquoia', 'bonsaï', 'lotus', 'nénuphar', 'orchidée', 'tulipe']
  },
  animaux: {
    present: ['corbeau', 'papillon', 'écureuil', 'lapin', 'oiseau', 'chat', 'chien', 'renard', 'hérisson', 'coccinelle'],
    absent: ['pigeon', 'aigle', 'hibou', 'perroquet', 'canard', 'cygne', 'paon', 'coq', 'poule', 'moineau']
  },
  objets: {
    present: ['clôture', 'banc', 'lanterne', 'pot de fleurs', 'arrosoir', 'brouette', 'échelle', 'tonneau', 'caisse', 'seau'],
    absent: ['fontaine', 'statue', 'balançoire', 'toboggan', 'parasol', 'hamac', 'barbecue', 'table', 'chaise', 'vélo']
  },
  paysage: {
    present: ['colline', 'sentier', 'prairie', 'clairière', 'bosquet', 'talus', 'fossé', 'haie', 'muret', 'portail'],
    absent: ['montagne', 'ruisseau', 'cascade', 'lac', 'étang', 'pont', 'moulin', 'grange', 'puits', 'cabane']
  },
  météo: {
    present: ['nuage', 'soleil', 'arc-en-ciel', 'brume légère'],
    absent: ['pluie', 'neige', 'orage', 'brouillard épais', 'grêle', 'tornade']
  }
};

// Traductions des éléments pour multi-langue
const ELEMENT_TRANSLATIONS = {
  // Nature
  'arbre': { en: 'tree', es: 'árbol', it: 'albero', de: 'Baum', pt: 'árvore', ru: 'дерево', ar: 'شجرة', zh: '树', el: 'δέντρο' },
  'fleur': { en: 'flower', es: 'flor', it: 'fiore', de: 'Blume', pt: 'flor', ru: 'цветок', ar: 'زهرة', zh: '花', el: 'λουλούδι' },
  'buisson': { en: 'bush', es: 'arbusto', it: 'cespuglio', de: 'Busch', pt: 'arbusto', ru: 'куст', ar: 'شجيرة', zh: '灌木', el: 'θάμνος' },
  'herbe': { en: 'grass', es: 'hierba', it: 'erba', de: 'Gras', pt: 'grama', ru: 'трава', ar: 'عشب', zh: '草', el: 'γρασίδι' },
  'pierre': { en: 'stone', es: 'piedra', it: 'pietra', de: 'Stein', pt: 'pedra', ru: 'камень', ar: 'حجر', zh: '石头', el: 'πέτρα' },
  'rocher': { en: 'rock', es: 'roca', it: 'roccia', de: 'Felsen', pt: 'rocha', ru: 'скала', ar: 'صخرة', zh: '岩石', el: 'βράχος' },
  'champignon': { en: 'mushroom', es: 'hongo', it: 'fungo', de: 'Pilz', pt: 'cogumelo', ru: 'гриб', ar: 'فطر', zh: '蘑菇', el: 'μανιτάρι' },
  'mousse': { en: 'moss', es: 'musgo', it: 'muschio', de: 'Moos', pt: 'musgo', ru: 'мох', ar: 'طحلب', zh: '苔藓', el: 'βρύο' },
  'fougère': { en: 'fern', es: 'helecho', it: 'felce', de: 'Farn', pt: 'samambaia', ru: 'папоротник', ar: 'سرخس', zh: '蕨类', el: 'φτέρη' },
  'cactus': { en: 'cactus', es: 'cactus', it: 'cactus', de: 'Kaktus', pt: 'cacto', ru: 'кактус', ar: 'صبار', zh: '仙人掌', el: 'κάκτος' },
  'palmier': { en: 'palm tree', es: 'palmera', it: 'palma', de: 'Palme', pt: 'palmeira', ru: 'пальма', ar: 'نخلة', zh: '棕榈树', el: 'φοίνικας' },
  // Animaux
  'corbeau': { en: 'crow', es: 'cuervo', it: 'corvo', de: 'Krähe', pt: 'corvo', ru: 'ворона', ar: 'غراب', zh: '乌鸦', el: 'κοράκι' },
  'papillon': { en: 'butterfly', es: 'mariposa', it: 'farfalla', de: 'Schmetterling', pt: 'borboleta', ru: 'бабочка', ar: 'فراشة', zh: '蝴蝶', el: 'πεταλούδα' },
  'écureuil': { en: 'squirrel', es: 'ardilla', it: 'scoiattolo', de: 'Eichhörnchen', pt: 'esquilo', ru: 'белка', ar: 'سنجاب', zh: '松鼠', el: 'σκίουρος' },
  'lapin': { en: 'rabbit', es: 'conejo', it: 'coniglio', de: 'Kaninchen', pt: 'coelho', ru: 'кролик', ar: 'أرنب', zh: '兔子', el: 'κουνέλι' },
  'oiseau': { en: 'bird', es: 'pájaro', it: 'uccello', de: 'Vogel', pt: 'pássaro', ru: 'птица', ar: 'طائر', zh: '鸟', el: 'πουλί' },
  'chat': { en: 'cat', es: 'gato', it: 'gatto', de: 'Katze', pt: 'gato', ru: 'кошка', ar: 'قطة', zh: '猫', el: 'γάτα' },
  'chien': { en: 'dog', es: 'perro', it: 'cane', de: 'Hund', pt: 'cão', ru: 'собака', ar: 'كلب', zh: '狗', el: 'σκύλος' },
  'pigeon': { en: 'pigeon', es: 'paloma', it: 'piccione', de: 'Taube', pt: 'pombo', ru: 'голубь', ar: 'حمامة', zh: '鸽子', el: 'περιστέρι' },
  'aigle': { en: 'eagle', es: 'águila', it: 'aquila', de: 'Adler', pt: 'águia', ru: 'орёл', ar: 'نسر', zh: '鹰', el: 'αετός' },
  'hibou': { en: 'owl', es: 'búho', it: 'gufo', de: 'Eule', pt: 'coruja', ru: 'сова', ar: 'بومة', zh: '猫头鹰', el: 'κουκουβάγια' },
  'renard': { en: 'fox', es: 'zorro', it: 'volpe', de: 'Fuchs', pt: 'raposa', ru: 'лиса', ar: 'ثعلب', zh: '狐狸', el: 'αλεπού' },
  'hérisson': { en: 'hedgehog', es: 'erizo', it: 'riccio', de: 'Igel', pt: 'ouriço', ru: 'ёж', ar: 'قنفذ', zh: '刺猬', el: 'σκαντζόχοιρος' },
  'coccinelle': { en: 'ladybug', es: 'mariquita', it: 'coccinella', de: 'Marienkäfer', pt: 'joaninha', ru: 'божья коровка', ar: 'دعسوقة', zh: '瓢虫', el: 'πασχαλίτσα' },
  'canard': { en: 'duck', es: 'pato', it: 'anatra', de: 'Ente', pt: 'pato', ru: 'утка', ar: 'بطة', zh: '鸭子', el: 'πάπια' },
  // Objets
  'clôture': { en: 'fence', es: 'cerca', it: 'recinzione', de: 'Zaun', pt: 'cerca', ru: 'забор', ar: 'سياج', zh: '栅栏', el: 'φράχτης' },
  'banc': { en: 'bench', es: 'banco', it: 'panchina', de: 'Bank', pt: 'banco', ru: 'скамейка', ar: 'مقعد', zh: '长凳', el: 'παγκάκι' },
  'lanterne': { en: 'lantern', es: 'farol', it: 'lanterna', de: 'Laterne', pt: 'lanterna', ru: 'фонарь', ar: 'فانوس', zh: '灯笼', el: 'φανάρι' },
  'fontaine': { en: 'fountain', es: 'fuente', it: 'fontana', de: 'Brunnen', pt: 'fonte', ru: 'фонтан', ar: 'نافورة', zh: '喷泉', el: 'σιντριβάνι' },
  'statue': { en: 'statue', es: 'estatua', it: 'statua', de: 'Statue', pt: 'estátua', ru: 'статуя', ar: 'تمثال', zh: '雕像', el: 'άγαλμα' },
  // Paysage
  'colline': { en: 'hill', es: 'colina', it: 'collina', de: 'Hügel', pt: 'colina', ru: 'холм', ar: 'تل', zh: '小山', el: 'λόφος' },
  'sentier': { en: 'path', es: 'sendero', it: 'sentiero', de: 'Pfad', pt: 'caminho', ru: 'тропа', ar: 'مسار', zh: '小路', el: 'μονοπάτι' },
  'montagne': { en: 'mountain', es: 'montaña', it: 'montagna', de: 'Berg', pt: 'montanha', ru: 'гора', ar: 'جبل', zh: '山', el: 'βουνό' },
  'ruisseau': { en: 'stream', es: 'arroyo', it: 'ruscello', de: 'Bach', pt: 'riacho', ru: 'ручей', ar: 'جدول', zh: '小溪', el: 'ρυάκι' },
  'cascade': { en: 'waterfall', es: 'cascada', it: 'cascata', de: 'Wasserfall', pt: 'cachoeira', ru: 'водопад', ar: 'شلال', zh: '瀑布', el: 'καταρράκτης' },
  'lac': { en: 'lake', es: 'lago', it: 'lago', de: 'See', pt: 'lago', ru: 'озеро', ar: 'بحيرة', zh: '湖', el: 'λίμνη' },
  'pont': { en: 'bridge', es: 'puente', it: 'ponte', de: 'Brücke', pt: 'ponte', ru: 'мост', ar: 'جسر', zh: '桥', el: 'γέφυρα' },
  // Météo
  'nuage': { en: 'cloud', es: 'nube', it: 'nuvola', de: 'Wolke', pt: 'nuvem', ru: 'облако', ar: 'سحابة', zh: '云', el: 'σύννεφο' },
  'soleil': { en: 'sun', es: 'sol', it: 'sole', de: 'Sonne', pt: 'sol', ru: 'солнце', ar: 'شمس', zh: '太阳', el: 'ήλιος' },
  'pluie': { en: 'rain', es: 'lluvia', it: 'pioggia', de: 'Regen', pt: 'chuva', ru: 'дождь', ar: 'مطر', zh: '雨', el: 'βροχή' },
  'neige': { en: 'snow', es: 'nieve', it: 'neve', de: 'Schnee', pt: 'neve', ru: 'снег', ar: 'ثلج', zh: '雪', el: 'χιόνι' }
};

// Fonction pour traduire un élément
function translateElement(element, language) {
  if (language === 'fr') return element;
  const translations = ELEMENT_TRANSLATIONS[element];
  if (translations && translations[language]) {
    return translations[language];
  }
  return element; // Fallback au français
}

// Endpoint pour générer une question Master (texte uniquement)
// ============================================================================
// MJ (Maître du Jeu) endpoint — used for off-line quiz preparation by hosts.
// Per task #83 spec, MJ "hors match live" preparation is an authorized
// router caller. Live MJ matches read questions from the persistent bank
// and never call this endpoint.
// ============================================================================
app.post('/generate-master-question', requireAdminToken, async (req, res) => {
  const { theme = 'Culture générale', language = 'fr', questionType = 'multiple_choice', questionNumber = 1, previousQuestions = [], gameSeed = null, domainType = 'theme', schoolLevel = null, schoolGrade = null, schoolSubject = null, schoolCountry = null, mode = 'standard', totalQuestions = 20 } = req.body;
  
  // Obtenir le sous-thème basé sur la rotation (avec seed aléatoire si fourni)
  // Le seed garantit que chaque jeu a un ordre différent de sous-thèmes
  const actualSeed = gameSeed || Date.now(); // Utiliser timestamp si pas de seed
  const subtheme = getSubthemeForQuestion(theme, questionNumber, actualSeed);
  
  console.log(`\n📝 Génération question Master #${questionNumber} (${questionType}, langue: ${language})`);
  console.log(`📋 Thème: ${theme}`);
  console.log(`🎲 Seed du jeu: ${actualSeed}`);
  console.log(`🎯 Sous-thème assigné: ${subtheme}`);
  console.log(`🚫 Questions précédentes à éviter: ${previousQuestions.length}`);
  
  try {
    // Construire le prompt selon le type de question avec les 5 règles strictes
    let systemPrompt = `Tu es un expert en création de questions de quiz éducatives et divertissantes.

RÈGLES OBLIGATOIRES:
1. Sois COHÉRENT avec le niveau de difficulté demandé
2. Génère des Questions/Réponses INÉDITES et originales (pas les faits les plus connus)
3. Ne fais AUCUNE répétition dans les questions/réponses
4. Sois AVANT-GARDISTE - propose des angles surprenants et des faits méconnus
5. Ne déroge JAMAIS du thème et sous-thème demandés

Tu réponds UNIQUEMENT au format JSON demandé, sans texte supplémentaire.`;
    
    const languageNames = {
      'fr': 'français',
      'en': 'anglais',
      'es': 'espagnol',
      'it': 'italien',
      'de': 'allemand',
      'pt': 'portugais',
      'ru': 'russe',
      'ar': 'arabe',
      'zh': 'chinois',
      'el': 'grec'
    };
    const langName = languageNames[language] || 'français';
    
    // Construire l'instruction de sous-thème (OBLIGATOIRE)
    const subthemeInstruction = `\n\n🎯 SOUS-THÈME OBLIGATOIRE: Tu DOIS générer une question spécifiquement sur "${subtheme}". Ne parle PAS d'autre chose que ce sous-thème précis.`;
    
    // Construire la liste des questions à éviter (en plus du sous-thème)
    let avoidText = '';
    if (previousQuestions.length > 0) {
      avoidText = `\n\nQuestions déjà générées (pour éviter les doublons exacts):\n${previousQuestions.slice(-5).map((q, i) => `- ${q}`).join('\n')}`;
    }
    
    let userPrompt;
    if (questionType === 'true_false') {
      userPrompt = `Génère une question Vrai/Faux sur le thème "${theme}" en ${langName}.${subthemeInstruction}${avoidText}

Réponds UNIQUEMENT avec ce JSON (pas de texte avant ou après):
{
  "question": "Ta question ici",
  "answers": ["Vrai", "Faux"],
  "correct_index": 0
}

Où correct_index est 0 pour Vrai ou 1 pour Faux.`;
    } else {
      userPrompt = `Génère une question à choix multiples avec 4 réponses sur le thème "${theme}" en ${langName}.${subthemeInstruction}${avoidText}

Réponds UNIQUEMENT avec ce JSON (pas de texte avant ou après):
{
  "question": "Ta question ici",
  "answers": ["Réponse correcte", "Mauvaise réponse 1", "Mauvaise réponse 2", "Mauvaise réponse 3"],
  "correct_index": 0
}

La bonne réponse doit être à l'index 0.`;
    }

    let contextBlock = '';
    if (domainType === 'school') {
      contextBlock = `
CONTEXTE SCOLAIRE OBLIGATOIRE:
- Niveau: ${schoolLevel || 'non précisé'}
- Année: ${schoolGrade || 'non précisée'}
- Matière: ${schoolSubject || 'non précisée'}
- Pays: ${schoolCountry || 'non précisé'}

RÈGLES SCOLAIRES OBLIGATOIRES:
1. La question doit correspondre au programme scolaire du pays et du niveau indiqués.
2. La difficulté doit être adaptée à l'année scolaire demandée, sans niveau universitaire si le niveau est secondaire.
3. Utilise des références, formulations et connaissances cohérentes avec un contexte scolaire réel.
4. Si le pays est Canada et la langue est français, privilégie un contexte scolaire francophone canadien.
5. Ne mélange jamais des périodes historiques incompatibles entre elles.
6. Si la question parle de la Nouvelle-France, elle ne doit jamais être placée au Moyen Âge.
7. Vérifie la cohérence chronologique, géographique et institutionnelle avant de répondre.
`;
    }

    const masterUserPrompt = contextBlock + "\n\n" + userPrompt;

    const routedMaster = await aiRouter.generate({
      systemPrompt,
      userPrompt: masterUserPrompt,
      temperature: (domainType === 'school') ? 0.1 : 0.3,
      maxOutputTokens: 500,
      responseMimeType: 'application/json',
    });

    let content = (routedMaster.text || '').trim();
    console.log('📥 Réponse brute:', content.substring(0, 100) + '...');
    
    // Parser le JSON de la réponse
    let parsedData;
    try {
      // Nettoyer le contenu (enlever les backticks markdown si présents)
      let cleanContent = content;
      if (cleanContent.startsWith('```json')) {
        cleanContent = cleanContent.slice(7);
      }
      if (cleanContent.startsWith('```')) {
        cleanContent = cleanContent.slice(3);
      }
      if (cleanContent.endsWith('```')) {
        cleanContent = cleanContent.slice(0, -3);
      }
      cleanContent = cleanContent.trim();
      
      parsedData = JSON.parse(cleanContent);
    } catch (parseError) {
      console.error('❌ Erreur parsing JSON:', parseError.message);
      // Fallback : générer une question par défaut
      parsedData = {
        question: questionType === 'true_false' 
          ? (language === 'fr' ? 'Le ciel est bleu.' : 'The sky is blue.')
          : (language === 'fr' ? 'Quelle est la capitale de la France ?' : 'What is the capital of France?'),
        answers: questionType === 'true_false' 
          ? ['Vrai', 'Faux']
          : ['Paris', 'Lyon', 'Marseille', 'Bordeaux'],
        correct_index: 0
      };
    }
    
    // Valider et normaliser les données
    if (!parsedData.question || typeof parsedData.question !== 'string') {
      parsedData.question = 'Question générée';
    }
    
    if (!Array.isArray(parsedData.answers)) {
      parsedData.answers = questionType === 'true_false' 
        ? ['Vrai', 'Faux']
        : ['Réponse 1', 'Réponse 2', 'Réponse 3', 'Réponse 4'];
    }
    
    if (typeof parsedData.correct_index !== 'number') {
      parsedData.correct_index = 0;
    }
    
    console.log(`✅ Question générée: "${parsedData.question.substring(0, 50)}..."`);
    
    res.json({
      success: true,
      question: {
        text: parsedData.question,
        answers: parsedData.answers,
        correct_index: parsedData.correct_index
      }
    });
    
  } catch (error) {
    console.error('❌ Erreur génération question Master:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Fonction pour générer un scénario aléatoire
function generateVisualScenario() {
  const scenario = {
    presentElements: [],
    absentElements: [],
    description: ''
  };
  
  // Sélectionner 4-6 éléments présents (parmi différentes catégories)
  const categories = Object.keys(VISUAL_ELEMENTS);
  const shuffledCategories = categories.sort(() => Math.random() - 0.5);
  
  for (let i = 0; i < 4 && i < shuffledCategories.length; i++) {
    const category = shuffledCategories[i];
    const presentOptions = VISUAL_ELEMENTS[category].present;
    const randomPresent = presentOptions[Math.floor(Math.random() * presentOptions.length)];
    if (!scenario.presentElements.includes(randomPresent)) {
      scenario.presentElements.push(randomPresent);
    }
    
    // Ajouter un élément absent de la même catégorie
    const absentOptions = VISUAL_ELEMENTS[category].absent;
    const randomAbsent = absentOptions[Math.floor(Math.random() * absentOptions.length)];
    if (!scenario.absentElements.includes(randomAbsent)) {
      scenario.absentElements.push(randomAbsent);
    }
  }
  
  scenario.description = `A peaceful countryside scene with ${scenario.presentElements.join(', ')}. The style should be realistic and detailed, with good visibility of all elements. Natural lighting, clear day.`;
  
  return scenario;
}

app.post('/generate-image-question', requireAdminToken, async (req, res) => {
  const { questionNumber = 1, language = 'fr' } = req.body;

  console.log(`\n🖼️ Génération question image-mémoire #${questionNumber} (langue: ${language})`);

  try {
    const scenario = generateVisualScenario();
    console.log(`📋 Scénario: ${scenario.presentElements.join(', ')}`);
    console.log(`❌ Éléments absents: ${scenario.absentElements.join(', ')}`);

    console.log('🎨 Génération de l\'image avec Imagen...');

    const imageResponse = await getImagenClient().models.generateImages({
      model: 'imagen-4.0-generate-001',
      prompt: scenario.description,
      config: {
        numberOfImages: 1,
        aspectRatio: '1:1',
        outputMimeType: 'image/png',
      },
    });

    if (!imageResponse.generatedImages || imageResponse.generatedImages.length === 0) {
      console.error('❌ Imagen: aucune image générée (filtrage sécurité possible)');
      return res.status(502).json({
        success: false,
        error: 'IMAGE_GENERATION_FAILED',
        details: 'Imagen returned no images (safety filter may have blocked the prompt)'
      });
    }

    const generatedImage = imageResponse.generatedImages[0];
    const imageBase64 = generatedImage.image.imageBytes;
    const imageMime = 'image/png';

    console.log(`✅ Image générée avec Imagen (${Math.round(imageBase64.length * 0.75 / 1024)} KB)`);

    const correctElement = scenario.presentElements[Math.floor(Math.random() * scenario.presentElements.length)];

    const shuffledAbsent = scenario.absentElements.sort(() => Math.random() - 0.5);
    const wrongElements = shuffledAbsent.slice(0, 3);

    while (wrongElements.length < 3) {
      const allAbsent = Object.values(VISUAL_ELEMENTS).flatMap(cat => cat.absent);
      const randomWrong = allAbsent[Math.floor(Math.random() * allAbsent.length)];
      if (!wrongElements.includes(randomWrong) && randomWrong !== correctElement) {
        wrongElements.push(randomWrong);
      }
    }

    const translatedCorrect = translateElement(correctElement, language);
    const translatedWrong = wrongElements.map(el => translateElement(el, language));

    const answers = [translatedCorrect, ...translatedWrong];

    const questionTexts = {
      'fr': 'Quel élément était visible dans l\'image ?',
      'en': 'Which element was visible in the image?',
      'es': '¿Qué elemento era visible en la imagen?',
      'it': 'Quale elemento era visibile nell\'immagine?',
      'de': 'Welches Element war im Bild sichtbar?',
      'pt': 'Qual elemento era visível na imagem?',
      'ru': 'Какой элемент был виден на изображении?',
      'ar': 'ما العنصر الذي كان مرئيًا في الصورة؟',
      'zh': '图片中可见的是什么元素？',
      'el': 'Ποιο στοιχείο ήταν ορατό στην εικόνα;'
    };

    const questionText = questionTexts[language] || questionTexts['fr'];

    res.json({
      success: true,
      type: 'image_memory',
      image_base64: imageBase64,
      image_mime: imageMime,
      image_url: null,
      question: {
        text: questionText,
        type: 'image',
        answers: answers,
        correct_index: 0,
        explanation: null,
        scenario: {
          present: scenario.presentElements,
          absent: scenario.absentElements
        }
      }
    });

    console.log(`✅ Question image-mémoire générée avec succès`);

  } catch (error) {
    console.error('❌ Erreur génération image-mémoire:', error.message);
    res.status(502).json({
      success: false,
      error: 'IMAGE_GENERATION_FAILED',
      details: error.message
    });
  }
});

// =============================================================================
//
// This endpoint is the documented entry point for the continuous bank refill
// worker (#82). It is NEVER on the critical path of a live match: gameplay
// reads from the persisted bank exclusively. The endpoint produces the rich
// JSON contract (concept_id, translations, saviez_vous, ...) and rejects
// any provider output that doesn't match it.
//
// Body:
//   {
//     "domain": string, "sub_domain": string,
//     "cognitive_type": "recognition" | "reasoning" | "deceptive_trap",
//     "question_type": "qcm" | "true_false",
//     "difficulty_depth": 1..10,
//     "languages": ["fr", "en", ...],          // optional, default ["fr"]
//     "difficulty_level": 1..99,               // Solo segments — XOR with boss_level
//     "boss_level":       10..100,             // Boss segments — XOR with difficulty_level
//     "concept_hint": string,                  // optional
//     "preferred_provider": "gemini"|"openai"  // optional
//   }
//
// Level-context XOR: callers MUST send EITHER `difficulty_level` (Solo)
// OR `boss_level` (Boss), never both, never neither. The router itself
// does not act on these today — they are forwarded so future prompt
// tuning and downstream tooling have the full match context. The DB
// CHECK on `question_groups` enforces the same XOR on storage.
app.post('/generate-bank-question', async (req, res) => {
  const {
    domain,
    sub_domain,
    cognitive_type,
    question_type = 'qcm',
    difficulty_depth,
    languages = ['fr'],
    concept_hint = '',
  } = req.body || {};

  if (!domain || !sub_domain || !cognitive_type || !difficulty_depth) {
    return res.status(400).json({
      ok: false,
      error: 'missing required fields: domain, sub_domain, cognitive_type, difficulty_depth',
    });
  }
  if (!['recognition', 'reasoning', 'deceptive_trap'].includes(cognitive_type)) {
    return res.status(400).json({
      ok: false,
      error: `cognitive_type must be one of recognition|reasoning|deceptive_trap (got: ${cognitive_type})`,
    });
  }

  const langList = Array.isArray(languages) && languages.length > 0 ? languages : ['fr'];
  const langSchema = langList
    .map(
      (l) =>
        `    "${l}": { "question_text": "...", "answer_a": "...", "answer_b": "...", "answer_c": "...", "answer_d": "...", "correct_answer_key": "A|B|C|D", "explanation": "...", "saviez_vous": "..." }`
    )
    .join(',\n');

  const cognitiveExplain = {
    recognition: 'fait direct, mémorisation pure ; pas de raisonnement multi-étapes',
    reasoning: 'requiert une déduction, comparaison ou calcul léger ; pas un simple rappel',
    deceptive_trap: 'distracteurs très plausibles ; confusion classique ; bonne réponse contre-intuitive',
  }[cognitive_type];

  const isTF = question_type === 'true_false';
  const answersHint = isTF
    ? '`answer_a` = libellé "Vrai" dans la langue principale, `answer_b` = libellé "Faux", `answer_c` et `answer_d` = null.'
    : 'Fournis exactement 4 réponses non-vides (answer_a/b/c/d), une seule correcte.';

  const systemPrompt =
    'Tu es un générateur de questions de quiz pour StrategyBuzzer. Tu réponds UNIQUEMENT en JSON valide (pas de markdown, pas de prose).';

  const userPrompt = `Génère UNE question de quiz dans le format JSON exact ci-dessous.

CONTRAINTES:
- Domaine: ${domain}
- Sous-domaine: ${sub_domain}
- Type cognitif: ${cognitive_type} — ${cognitiveExplain}
- Difficulté (depth ${difficulty_depth}/10): adapte la complexité
- Type de question: ${question_type}
- ${answersHint}
- ${concept_hint ? `Indice concept: ${concept_hint}` : 'Choisis un fait précis et vérifiable.'}
- correct_answer_key DOIT être la même lettre dans TOUTES les langues
- saviez_vous OBLIGATOIRE, anecdote concrète d'au moins 30 caractères

Format JSON exact attendu:
{
  "question_text": "...",
  "answer_a": "...",
  "answer_b": "...",
  "answer_c": "...",
  "answer_d": "${isTF ? 'null' : '...'}",
  "correct_answer_key": "A|B|C|D",
  "explanation": "...",
  "saviez_vous": "...",
  "domain": "${domain}",
  "sub_domain": "${sub_domain}",
  "question_type": "${question_type}",
  "cognitive_type": "${cognitive_type}",
  "difficulty_depth": ${difficulty_depth},
  "concept_id": "<kebab-case unique>",
  "concept_family": "<kebab-case famille plus large>",
  "translations": {
${langSchema}
  }
}`;

  // Validation runs INSIDE the router retry loop: parse + rich-contract
  // validation failures are treated as provider errors so the router can
  // try the next key / failover to the next provider before bubbling up.
  const validate = (text) => {
    let raw = (text || '').trim();
    if (raw.startsWith('```json')) raw = raw.slice(7);
    if (raw.startsWith('```')) raw = raw.slice(3);
    if (raw.endsWith('```')) raw = raw.slice(0, -3);
    raw = raw.trim();

    let parsed;
    try {
      parsed = JSON.parse(raw);
    } catch (e) {
      return { ok: false, reason: `invalid JSON: ${e.message}` };
    }

    const v = aiValidation.validateRichContract(parsed);
    if (!v.ok) return { ok: false, reason: v.reason };
    return { ok: true, value: v.payload };
  };

  let routed;
  try {
    routed = await aiRouter.generate({
      systemPrompt,
      userPrompt,
      temperature: 0.85,
      maxOutputTokens: 2000,
      responseMimeType: 'application/json',
      validate,
    });
  } catch (err) {
    if (err.name === 'NoProvidersConfiguredError') {
      return res.status(503).json({ ok: false, error: 'no_providers_configured', detail: err.message });
    }
    if (err.name === 'AllProvidersExhaustedError') {
      // The router has already recorded reject reasons for any
      // contract-validation failures encountered along the way.
      return res.status(503).json({ ok: false, error: 'all_providers_exhausted', detail: err.message });
    }
    return res.status(502).json({ ok: false, error: 'router_error', detail: err.message || String(err) });
  }

  // routed.validated holds the parsed + validated payload.
  const enriched = {
    ...routed.validated,
    source: routed.provider,
    provider_key_index: routed.keyIndex,
    latency_ms: routed.latencyMs,
  };
  return res.json({ ok: true, payload: enriched });
});

// =============================================================================
// POST /translate-bank-question
//
// Receives a validated master question (source_language, default="fr") and
// a list of target languages. Produces exact linguistic translations —
// no content changes are permitted whatsoever. The prompt explicitly
// forbids reformulation, answer permutation, key change, concept drift,
// and cultural substitution.
//
// Body:
//   {
//     "master": {
//       "question_text": "...",
//       "answer_a": "...", "answer_b": "...",
//       "answer_c": "...", "answer_d": "...",   // null for true_false
//       "correct_answer_key": "A",              // fixed across all languages
//       "explanation": "...",
//       "saviez_vous": "...",
//       "question_type": "qcm"                 // or "true_false"
//     },
//     "source_language": "fr",
//     "target_languages": ["en","es","it","de","pt","ru","zh","ar","el"]
//   }
//
// Response:
//   { ok: true, translations: { "en": {...}, "es": {...}, ... }, source, latency_ms }
//
// Each translation carries: question_text, answer_a/b/c/d,
// correct_answer_key (== master), explanation, saviez_vous.
// =============================================================================
app.post('/translate-bank-question', async (req, res) => {
  const { master, source_language = 'fr', target_languages } = req.body || {};

  // ── Input validation ──────────────────────────────────────────────────────
  if (!master || typeof master !== 'object') {
    return res.status(400).json({ ok: false, error: 'master payload required' });
  }
  if (!Array.isArray(target_languages) || target_languages.length === 0) {
    return res.status(400).json({ ok: false, error: 'target_languages must be a non-empty array' });
  }
  const REQUIRED_MASTER = [
    'question_text', 'answer_a', 'answer_b', 'answer_c',
    'correct_answer_key', 'explanation', 'saviez_vous',
  ];
  for (const f of REQUIRED_MASTER) {
    if (!master[f] || typeof master[f] !== 'string' || !master[f].trim()) {
      return res.status(400).json({ ok: false, error: `master.${f} missing or empty` });
    }
  }

  const isTF = (master.question_type || 'qcm') === 'true_false';
  const correctKey = String(master.correct_answer_key || '').toUpperCase();

  // Count how many answers the master has (source of truth for validation).
  const masterAnswerCount = ['answer_a', 'answer_b', 'answer_c', 'answer_d']
    .filter(f => typeof master[f] === 'string' && master[f].trim().length > 0)
    .length;

  // ── Build prompt ──────────────────────────────────────────────────────────
  const langLines = target_languages
    .map(l => `- ${l} (${(LANGUAGES[l] || {}).name || l})`)
    .join('\n');

  const answersBlock = [
    `  answer_a = "${master.answer_a}"`,
    `  answer_b = "${master.answer_b}"`,
    `  answer_c = "${master.answer_c}"`,
    !isTF && master.answer_d ? `  answer_d = "${master.answer_d}"` : null,
  ].filter(Boolean).join('\n');

  const translationSchema = target_languages
    .map(l => {
      if (isTF) {
        return `    "${l}": { "question_text": "...", "answer_a": "...", "answer_b": "...", "answer_c": null, "answer_d": null, "correct_answer_key": "${correctKey}", "explanation": "...", "saviez_vous": "..." }`;
      }
      return `    "${l}": { "question_text": "...", "answer_a": "...", "answer_b": "...", "answer_c": "...", "answer_d": "...", "correct_answer_key": "${correctKey}", "explanation": "...", "saviez_vous": "..." }`;
    })
    .join(',\n');

  const systemPrompt =
    'Tu es un traducteur structuré strict pour une base de questions de quiz. ' +
    'Tu réponds UNIQUEMENT en JSON valide (pas de markdown, pas de prose).';

  const userPrompt =
`Tu dois traduire une question de quiz depuis le ${source_language.toUpperCase()} vers les langues cibles ci-dessous.

RÈGLES ABSOLUES — AUCUNE EXCEPTION :
1. Tu es un TRADUCTEUR STRICT, pas un créateur de contenu.
2. Le sens, les faits et la structure de la question NE CHANGENT PAS.
3. Les réponses (answer_a, answer_b, answer_c, answer_d) sont traduites EXACTEMENT — même sens, même ordre imposé (A reste A, B reste B, C reste C, D reste D).
4. correct_answer_key = "${correctKey}" dans TOUTES les langues, sans exception, même si une autre réponse te semble plus logique.
5. INTERDIT : reformuler, simplifier, enrichir, changer les réponses, permuter l'ordre des réponses, changer la bonne réponse, modifier le concept, inventer de nouvelles informations.
6. Les distracteurs doivent rester des distracteurs équivalents exacts. Ne pas remplacer une réponse par une autre référence culturelle.
7. explanation et saviez_vous : traduis fidèlement, sans ajouter ni supprimer d'information.
8. Si une réponse est un nom propre, un nombre, une date, un acronyme ou un terme technique : conserve-le TEL QUEL sans traduction.

QUESTION SOURCE (${source_language.toUpperCase()}) :
  question_text = "${master.question_text}"
${answersBlock}
  correct_answer_key = "${correctKey}"
  explanation = "${master.explanation}"
  saviez_vous = "${master.saviez_vous}"

LANGUES CIBLES :
${langLines}

Format JSON exact attendu (correct_answer_key = "${correctKey}" partout, sans aucune modification) :
{
${translationSchema}
}`;

  // ── Validate translation response ─────────────────────────────────────────
  const validate = (text) => {
    let raw = (text || '').trim();
    if (raw.startsWith('```json')) raw = raw.slice(7);
    if (raw.startsWith('```')) raw = raw.slice(3);
    if (raw.endsWith('```')) raw = raw.slice(0, -3);
    raw = raw.trim();

    let parsed;
    try {
      parsed = JSON.parse(raw);
    } catch (e) {
      return { ok: false, reason: `invalid JSON: ${e.message}` };
    }
    if (!parsed || typeof parsed !== 'object') {
      return { ok: false, reason: 'response is not an object' };
    }

    for (const lang of target_languages) {
      const tr = parsed[lang];
      if (!tr || typeof tr !== 'object') {
        return { ok: false, reason: `translations[${lang}] missing` };
      }

      // Required fields present
      const required = [
        'question_text', 'answer_a', 'answer_b', 'answer_c',
        'correct_answer_key', 'explanation', 'saviez_vous',
      ];
      for (const f of required) {
        if (!(f in tr)) {
          return { ok: false, reason: `translations[${lang}].${f} missing` };
        }
      }

      if (!tr.question_text || !tr.question_text.trim()) {
        return { ok: false, reason: `translations[${lang}].question_text empty` };
      }
      if (!tr.saviez_vous || !tr.saviez_vous.trim()) {
        return { ok: false, reason: `translations[${lang}].saviez_vous empty` };
      }
      if (!tr.explanation || !tr.explanation.trim()) {
        return { ok: false, reason: `translations[${lang}].explanation empty` };
      }

      // correct_answer_key must match master exactly
      const trKey = String(tr.correct_answer_key || '').toUpperCase();
      if (trKey !== correctKey) {
        return { ok: false, reason: `translations[${lang}].correct_answer_key=${trKey} ≠ master ${correctKey}` };
      }

      // Answer count must match master exactly
      const trAnswerCount = ['answer_a', 'answer_b', 'answer_c', 'answer_d']
        .filter(f => typeof tr[f] === 'string' && tr[f].trim().length > 0)
        .length;
      if (trAnswerCount !== masterAnswerCount) {
        return { ok: false, reason: `translations[${lang}] has ${trAnswerCount} answers, master has ${masterAnswerCount}` };
      }

      // QCM: all 4 non-empty
      if (!isTF) {
        if (!tr.answer_a || !tr.answer_a.trim()) return { ok: false, reason: `translations[${lang}].answer_a empty` };
        if (!tr.answer_b || !tr.answer_b.trim()) return { ok: false, reason: `translations[${lang}].answer_b empty` };
        if (!tr.answer_c || !tr.answer_c.trim()) return { ok: false, reason: `translations[${lang}].answer_c empty` };
        if (!tr.answer_d || !tr.answer_d.trim()) return { ok: false, reason: `translations[${lang}].answer_d empty` };
      } else {
        // true_false: only A and B allowed (C and D must be null/absent)
        if (!tr.answer_a || !tr.answer_a.trim()) return { ok: false, reason: `translations[${lang}].answer_a empty (true_false)` };
        if (!tr.answer_b || !tr.answer_b.trim()) return { ok: false, reason: `translations[${lang}].answer_b empty (true_false)` };
        if (typeof tr.answer_c === 'string' && tr.answer_c.trim().length > 0) {
          return { ok: false, reason: `translations[${lang}].answer_c must be null for true_false` };
        }
        if (typeof tr.answer_d === 'string' && tr.answer_d.trim().length > 0) {
          return { ok: false, reason: `translations[${lang}].answer_d must be null for true_false` };
        }
      }
    }

    return { ok: true, value: parsed };
  };

  // ── Token budget ───────────────────────────────────────────────────────────
  // Translation is cheaper than generation: question_text + explanation +
  // saviez_vous need translation; answers may be names/numbers.
  // ~500 tokens per language, floor at 2000.
  const maxOutputTokens = Math.max(2000, target_languages.length * 600);

  // ── Call router (low temperature = strict, literal translation) ────────────
  let routed;
  try {
    routed = await aiRouter.generate({
      systemPrompt,
      userPrompt,
      temperature: 0.3,
      maxOutputTokens,
      responseMimeType: 'application/json',
      validate,
    });
  } catch (err) {
    if (err.name === 'NoProvidersConfiguredError') {
      return res.status(503).json({ ok: false, error: 'no_providers_configured', detail: err.message });
    }
    if (err.name === 'AllProvidersExhaustedError') {
      return res.status(503).json({ ok: false, error: 'all_providers_exhausted', detail: err.message });
    }
    return res.status(502).json({ ok: false, error: 'router_error', detail: err.message || String(err) });
  }

  return res.json({
    ok: true,
    translations: routed.validated,
    source: routed.provider,
    latency_ms: routed.latencyMs,
  });
});

const PORT = 3000;
if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`Question API server running on port ${PORT}`);
  });
}
