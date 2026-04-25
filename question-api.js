const express = require('express');
const { GoogleGenAI } = require('@google/genai');

// Initialize Google Gemini AI client
const gemini = new GoogleGenAI({ 
  apiKey: process.env.GEMINI_API_KEY 
});


const app = express();

// Healthcheck (Nginx + monitoring)
app.get("/health", (req, res) => {
  res.status(200).json({ ok: true, ts: Date.now() });
});

app.use(express.json());

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

app.post('/generate-question', async (req, res) => {
  try {
  const MAX_RETRIES = 3;
  
  const { theme, niveau, questionNumber, opponentAge = null, isBoss = false, language = 'fr' } = req.body;
  // SAFETY: Ensure usedAnswers and usedQuestionTexts are always arrays (fix "not iterable" error)
  const usedAnswers = Array.isArray(req.body.usedAnswers) ? req.body.usedAnswers : [];
  const usedQuestionTexts = Array.isArray(req.body.usedQuestionTexts) ? req.body.usedQuestionTexts : [];
  
  // Récupérer les infos de langue
  const languageInfo = LANGUAGES[language] || LANGUAGES['fr'];
  const languageName = languageInfo.name;
  const languageDict = languageInfo.dict;
  const trueLabel = languageInfo.true;
  const falseLabel = languageInfo.false;
  
  const themeLabel = THEMES_FR[theme] || 'culture générale';
  const difficultyDesc = getDifficultyDescription(niveau);
  const lengthConstraint = getQuestionLengthConstraint(niveau);
  
  // NOUVEAU : Déterminer le niveau de difficulté selon l'adversaire
  let difficultyLevel;
  if (isBoss) {
    difficultyLevel = 'niveau universitaire / expert';
  } else if (opponentAge) {
    difficultyLevel = `niveau ${opponentAge} ans`;
  } else {
    // Fallback : utiliser le niveau de jeu
    difficultyLevel = difficultyDesc;
  }
  
  // NOTE: On NE dit PLUS à l'IA d'éviter certaines réponses dans le prompt
  // Au lieu de ça, la validation POST-génération (ligne ~401) rejette les questions 
  // dont la réponse correcte est déjà utilisée, ce qui force une régénération complète
  // avec un NOUVEAU sujet/question, évitant ainsi les réponses factuellement fausses
  
  // Boucle de retry pour régénérer automatiquement si validation échoue
  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    try {
      console.log(`🔄 Tentative ${attempt}/${MAX_RETRIES} de génération de question...`);
      
      // Décider aléatoirement entre question à choix multiple (80%) et vrai/faux (20%)
      const isMultipleChoice = Math.random() > 0.2;
      
      const prompt = isMultipleChoice 
      ? `Tu es un générateur de questions de quiz. Génère TOUT le contenu (question, réponses et explication) en ${languageName} uniquement.

📋 MÉTHODE STRUCTURÉE OBLIGATOIRE :

ÉTAPE 1 - GÉNÉRATION D'UN FAIT VÉRIFIÉ :
- Pense d'abord à un FAIT HISTORIQUE/GÉOGRAPHIQUE/SCIENTIFIQUE réel et vérifié lié au thème "${themeLabel}"
- Ce fait doit être PRÉCIS, VÉRIFIABLE et directement lié au thème
- Niveau de difficulté : ${difficultyLevel}
- Exemples de faits acceptables :
  * Histoire : "Le Bitcoin a été créé en 2009 par Satoshi Nakamoto" (technologie dans l'histoire)
  * Géographie : "Le Mont Everest culmine à 8849 mètres d'altitude"
  * Faune : "Le guépard peut atteindre 120 km/h en course"

ÉTAPE 2 - FORMULATION DE LA QUESTION :
- Transforme ce fait en une question claire et précise
- La question doit tester la connaissance de ce fait spécifique
- Adapte la difficulté au ${difficultyLevel}

ÉTAPE 3 - AUTO-VALIDATION THÉMATIQUE :
- VÉRIFIE que le fait est bien lié au thème "${themeLabel}"
- Un fait historique peut concerner la technologie, l'économie, la société (ex: Bitcoin en histoire = OK)
- Un fait géographique peut concerner le climat, la population, l'urbanisme
- Si le fait ne correspond PAS clairement au thème, RECOMMENCE avec un autre fait

IMPORTANT:
- La question doit être VRAIMENT UNIQUE et ORIGINALE - évite absolument les questions clichées ou répétitives
- Ne pose PAS de questions évidentes ou trop simples (ex: "Quelle est la capitale de la France?", "Quel animal est le meilleur ami de l'homme?")
- Varie les sujets, les angles d'approche et les formulations
- Adapte la complexité au ${difficultyLevel}
- Pour le niveau universitaire/expert, utilise des détails précis, des dates exactes, des noms complets
- Pour les niveaux jeunes (8-12 ans), utilise un vocabulaire simple et des concepts de base accessibles
- Ceci est la question ${questionNumber} de la partie - évite de répéter des concepts déjà couverts
- LONGUEUR: ${lengthConstraint}

🚫 INTERDICTION ABSOLUE DE DUPLICATION:
${usedAnswers.length > 0 ? `- Réponses/sujets déjà utilisés: ${usedAnswers.slice(0, 50).map(a => `"${a}"`).join(', ')}${usedAnswers.length > 50 ? ` (+${usedAnswers.length - 50} autres)` : ''}` : ''}

RÈGLES ANTI-DUPLICATION STRICTES:
1. Change de sujet de question si tu arrives à une réponse déjà générée
2. Ne jamais répéter une autre fois une même question
3. Soit créatif dans tes choix de questions${theme === 'general' ? '. Dans le thème Général utilise le plus possible des questions de tous les thèmes' : ''}

VALIDATION FACTUELLE STRICTE - 10 RÈGLES OBLIGATOIRES:

1. NE JAMAIS inventer, extrapoler ou deviner des informations
   - Utilise UNIQUEMENT des faits vérifiables et documentés
   - Si tu n'es pas sûr à 100%, ne l'utilise PAS

2. Si une information n'est pas vérifiable, ne la mets pas
   - Chaque fait doit pouvoir être vérifié dans des sources fiables
   - Évite les affirmations vagues ou approximatives

3. Baser chaque affirmation sur des sources crédibles, récentes et vérifiables
   - Privilégie les connaissances encyclopédiques établies
   - Évite les informations obsolètes ou controversées

4. Élaborer clairement chaque réponse par une phrase courte
   - Les réponses doivent être précises et non ambiguës
   - Une seule réponse doit être incontestablement correcte

5. NE PAS utiliser de sources vagues, obsolètes ou douteuses
   - Reste sur des faits établis et consensuels
   - Évite les théories non prouvées ou marginales

6. RESTER neutre et objectif
   - Évite les jugements de valeur ou opinions personnelles
   - Présente uniquement des faits vérifiables

7. EXPLIQUER le raisonnement ou le calcul si une donnée peut être discutée
   - Pour les questions mathématiques ou logiques: vérifie tes calculs
   - Pour les dates historiques: assure-toi de leur exactitude

8. PRIORISER l'exactitude sur la rapidité ou le style
   - Mieux vaut une question simple mais vraie qu'une question élaborée mais fausse
   - La véracité est TOUJOURS la priorité absolue

9. VÉRIFIER avant d'inclure la question/réponse : "Tout est-il factuel, sourcé et vérifiable ?"
   - Relis ta question et vérifie chaque élément
   - Pose-toi: "Suis-je certain à 100% que c'est vrai ?"

10. Si non → corrige avant d'envoyer
    - Si le moindre doute subsiste, RECOMMENCE avec un autre sujet
    - Ne propose jamais une question dont tu n'es pas absolument certain

RÈGLES COMPLÉMENTAIRES SPÉCIFIQUES:
- VÉRIFIE que la question et la réponse correcte sont VRAIES et EXACTES à 100%
- Pour les questions sur les animaux: vérifie les comportements, habitats, et caractéristiques réels
- INTERDICTION ABSOLUE DES MOTS INVENTÉS:
  * Utilise UNIQUEMENT des noms d'animaux/plantes qui EXISTENT RÉELLEMENT
  * EXEMPLES DE MOTS INVENTÉS INTERDITS: "endurolâtre", "gaboulon", "hermite", "toupinel"
  * Avant d'utiliser un nom d'animal, VÉRIFIE qu'il existe dans la nature
  * En cas de DOUTE, utilise un animal/plante CONNU et COMMUN

- CONFUSIONS COURANTES À ÉVITER ABSOLUMENT:
  * ❌ "bar tendre" = NOURRITURE (collation), PAS un poisson! Utilise "barracuda" pour le poisson
  * ❌ Le dauphin est un MAMMIFÈRE MARIN, PAS un poisson (ne jamais classer comme poisson)
  * ❌ La baleine est un MAMMIFÈRE MARIN, PAS un poisson
  * ❌ L'orque est un MAMMIFÈRE MARIN (delphinidé), PAS un poisson
  * ❌ Le phoque est un MAMMIFÈRE MARIN, PAS un poisson
  * ✅ Poissons réels: thon, barracuda, requin, saumon, truite, espadon, mérou
  * ✅ Mammifères marins: dauphin, baleine, orque, cachalot, phoque, otarie

- DISTINCTION ANIMAUX VS INSECTES (RÈGLE CRITIQUE):
  * ❌ NE JAMAIS MÉLANGER animaux et insectes - ni dans les options, ni entre la question et les réponses
  * Si la RÉPONSE CORRECTE est un INSECTE, la question DOIT dire "Quel INSECTE..." (pas "Quel animal...")
  * Si la RÉPONSE CORRECTE est un ANIMAL, la question DOIT dire "Quel ANIMAL..." (pas "Quel insecte...")
  * Si la question dit "Quel INSECTE...", TOUTES les 4 options doivent être des insectes
  * Si la question dit "Quel ANIMAL...", TOUTES les 4 options doivent être des animaux (JAMAIS d'insectes)
  
  * ✅ Insectes réels: fourmi, abeille, papillon, scarabée, libellule, moustique, mouche, coccinelle, criquet, sauterelle
  * ✅ Animaux vertébrés (non-insectes): lion, éléphant, cheval, lapin, souris, oiseau, reptile, mammifère, poisson
  
  * EXEMPLES INCORRECTS À ÉVITER ABSOLUMENT:
    - ❌ "Quel ANIMAL soulève 50× son poids? → fourmi, scarabée, abeille, libellule" (ERREUR: "animal" mais réponses = insectes)
    - ❌ "Quel INSECTE court le plus vite? → guépard, lion, autruche, cheval" (ERREUR: "insecte" mais réponses = animaux)
    - ❌ "Quel INSECTE soulève 50× son poids? → fourmi, tourterelle, écureuil, chat" (ERREUR: mélange insectes + oiseaux + mammifères)
  
  * EXEMPLES CORRECTS:
    - ✅ "Quel INSECTE soulève 50× son poids? → fourmi, scarabée, abeille, libellule" (question + réponses = tous insectes)
    - ✅ "Quel ANIMAL court le plus vite? → guépard, lion, autruche, cheval" (question + réponses = tous animaux vertébrés)
    - ✅ "Quel MAMMIFÈRE vit dans l'eau? → dauphin, baleine, phoque, loutre" (question + réponses = tous mammifères)

- QUESTIONS AVEC PLUSIEURS RÉPONSES CORRECTES - ÉVITER:
  * "Quel animal peut vivre jusqu'à 80 ans?" → Perroquet ET tortue de mer sont corrects (évite cette question)
  * "Quel animal peut nager à 70 km/h?" → Thon ET dauphin sont corrects (évite cette question)
  * "Quel oiseau peut voler en arrière?" → Seul le colibri est correct (OK à utiliser)
  * Choisis UNIQUEMENT des questions avec UNE SEULE réponse correcte incontestable

- EXEMPLES DE QUESTIONS INTERDITES (car factuellement fausses ou imprécises):
  * "Quel poisson peut vivre jusqu'à 80 ans? → Le bar tendre" (TRIPLE ERREUR: bar tendre = nourriture, pas poisson, durée de vie fausse)
  * "Quel poisson peut nager à 70 km/h? → Le dauphin" (FAUX: dauphin = mammifère, pas poisson)
  * "Quel oiseau tisse son nid de fils colorés? → Le tisserin" (FAUX: le tisserin tisse mais PAS avec des fils colorés!)
  * "Quel mammifère lézard se trouve en Australie? → L'ornithorynque" (INCORRECT: l'ornithorynque n'est PAS un "mammifère lézard", c'est un monotrème)
  * "Quel animal est connu pour vivre dans les construits de boue?" (FRANÇAIS INCORRECT: dis "constructions" pas "construits")
  * "Quel animal fait son nid dans la boue? → singe" (FAUX: les singes ne font pas de nid dans la boue)
  * "Quel serpent change de couleur?" (FAUX: c'est le caméléon, pas un serpent)
  * "Quel animal est connu pour se camoufler? → L'endurolâtre" (ABSURDE: mot inventé!)
  * "Quel animal construit avec du safran/hermite?" (ABSURDE: non-sens total)
  * "La girafe a une langue plus longue que son corps" (FAUX biologiquement impossible)
  * "Le cacatoès utilise l'urine pour se marquer" (FAUX: comportement inexistant)
  * "Le merle découvre son aliment grâce à son chant" (FAUX: le chant ne sert pas à trouver la nourriture)
  * "Les rats de champ sculptent des tunnels complexes" (IMPRÉCIS: ce sont les taupes ou les lapins)

- RÈGLES LINGUISTIQUES:
  * Utilise la langue ${languageName} PARFAITEMENT: grammaire, orthographe et syntaxe correctes
  * Ne mélange JAMAIS des termes incompatibles dans la langue choisie
  * Utilise "animal" pour les questions générales, pas "insecte" ou "mammifère" si tu n'es pas sûr

- RÈGLE D'OR: Si tu n'es PAS ABSOLUMENT CERTAIN à 100% qu'un fait est vrai, choisis un autre sujet
- Les réponses doivent être des animaux/plantes RÉELS, CONNUS et VÉRIFIABLES
- ÉVITE les questions sur des comportements animaux rares ou peu connus - reste sur des faits bien établis

📝 RÈGLES D'ORTHOGRAPHE STRICTE:

IMPORTANT - VÉRIFICATION ORTHOGRAPHIQUE OBLIGATOIRE:
1. Vérifie l'orthographe de CHAQUE MOT dans un dictionnaire ${languageDict} avant de générer la question
2. Assure-toi que l'orthographe est conforme aux règles de la langue ${languageName}
3. Exemples d'erreurs courantes à ÉVITER (selon la langue):
   * ${languageName === 'Français' ? 'panthère (✓) vs phantère (✗), murène (✓) vs murraine (✗), phoque (✓) vs foque (✗)' : 'Vérifie les noms propres, les noms d\'animaux, les termes techniques'}
   * Vérifie particulièrement: noms d'animaux, lieux géographiques, noms propres, termes techniques
4. Double-vérification finale: Relis TOUS les mots avant d'envoyer la question
5. Si le moindre doute sur l'orthographe d'un mot → utilise un synonyme dont tu es sûr de l'orthographe
6. L'orthographe correcte est AUSSI IMPORTANTE que la véracité factuelle

Format JSON requis:
{
  "text": "La question en ${languageName}",
  "type": "multiple",
  "answers": ["réponse correcte", "réponse incorrecte 1", "réponse incorrecte 2", "réponse incorrecte 3"],
  "correct_index": 0,
  "explanation": "Une explication courte et intéressante (2-3 phrases maximum) qui apprend quelque chose au joueur sur le sujet de la question. Cette explication sera affichée après la réponse sous 'Le saviez-vous ?'. Elle doit être éducative, captivante et en ${languageName}."
}

RÈGLES STRICTES:
1. La réponse correcte DOIT être à l'index 0 du tableau answers
2. Fournis exactement 4 réponses plausibles
3. Les mauvaises réponses doivent être crédibles mais incorrectes
4. Question unique et originale, pas de répétition
5. Réponds UNIQUEMENT avec le JSON, rien d'autre`
      : `Tu es un générateur de questions de quiz. Génère UNE SEULE question Vrai/Faux unique de ${themeLabel} avec un niveau de difficulté ${difficultyLevel}. Génère TOUT le contenu (question et explication) en ${languageName} uniquement.

IMPORTANT:
- La question doit être VRAIMENT UNIQUE et ORIGINALE - évite absolument les affirmations clichées ou répétitives
- Ne pose PAS d'affirmations évidentes (ex: "Paris est la capitale de la France", "Le chien est un animal domestique")
- Varie les sujets et les angles d'approche
- Adapte la complexité au ${difficultyLevel}
- Pour le niveau universitaire/expert, utilise des affirmations plus nuancées et techniques
- Pour les niveaux jeunes (8-12 ans), utilise un vocabulaire simple et des affirmations claires
- Ceci est la question ${questionNumber} de la partie - évite de répéter des concepts déjà couverts
- LONGUEUR: ${lengthConstraint}

🚫 INTERDICTION ABSOLUE DE DUPLICATION:
${usedAnswers.length > 0 ? `- Réponses/sujets déjà utilisés: ${usedAnswers.slice(0, 50).map(a => `"${a}"`).join(', ')}${usedAnswers.length > 50 ? ` (+${usedAnswers.length - 50} autres)` : ''}` : ''}

RÈGLES ANTI-DUPLICATION STRICTES:
1. Change de sujet de question si tu arrives à une réponse déjà générée
2. Ne jamais répéter une autre fois une même question
3. Soit créatif dans tes choix de questions${theme === 'general' ? '. Dans le thème Général utilise le plus possible des questions de tous les thèmes' : ''}

VALIDATION FACTUELLE STRICTE - 10 RÈGLES OBLIGATOIRES:

1. NE JAMAIS inventer, extrapoler ou deviner des informations
   - Utilise UNIQUEMENT des faits vérifiables et documentés
   - Si tu n'es pas sûr à 100%, ne l'utilise PAS

2. Si une information n'est pas vérifiable, ne la mets pas
   - Chaque fait doit pouvoir être vérifié dans des sources fiables
   - Évite les affirmations vagues ou approximatives

3. Baser chaque affirmation sur des sources crédibles, récentes et vérifiables
   - Privilégie les connaissances encyclopédiques établies
   - Évite les informations obsolètes ou controversées

4. Élaborer clairement chaque réponse par une phrase courte
   - Les réponses doivent être précises et non ambiguës
   - Une seule réponse doit être incontestablement correcte

5. NE PAS utiliser de sources vagues, obsolètes ou douteuses
   - Reste sur des faits établis et consensuels
   - Évite les théories non prouvées ou marginales

6. RESTER neutre et objectif
   - Évite les jugements de valeur ou opinions personnelles
   - Présente uniquement des faits vérifiables

7. EXPLIQUER le raisonnement ou le calcul si une donnée peut être discutée
   - Pour les questions mathématiques ou logiques: vérifie tes calculs
   - Pour les dates historiques: assure-toi de leur exactitude

8. PRIORISER l'exactitude sur la rapidité ou le style
   - Mieux vaut une question simple mais vraie qu'une question élaborée mais fausse
   - La véracité est TOUJOURS la priorité absolue

9. VÉRIFIER avant d'inclure la question/réponse : "Tout est-il factuel, sourcé et vérifiable ?"
   - Relis ta question et vérifie chaque élément
   - Pose-toi: "Suis-je certain à 100% que c'est vrai ?"

10. Si non → corrige avant d'envoyer
    - Si le moindre doute subsiste, RECOMMENCE avec un autre sujet
    - Ne propose jamais une question dont tu n'es pas absolument certain

RÈGLES COMPLÉMENTAIRES SPÉCIFIQUES:
- VÉRIFIE que l'affirmation est soit VRAIE soit FAUSSE de manière claire et vérifiable
- Pour les questions sur les animaux/nature: vérifie les faits biologiques réels
- EXEMPLES D'AFFIRMATIONS INTERDITES (car factuellement inexactes):
  * "Le serpent à sonnette change de couleur" (FAUX: confusion avec le caméléon)
  * "Le castor fait son nid avec du safran" (ABSURDE: non-sens total)
- Si tu n'es PAS CERTAIN à 100% d'un fait, choisis un autre sujet

📝 RÈGLES D'ORTHOGRAPHE STRICTE:

IMPORTANT - VÉRIFICATION ORTHOGRAPHIQUE OBLIGATOIRE:
1. Vérifie l'orthographe de CHAQUE MOT dans un dictionnaire ${languageDict} avant de générer la question
2. Assure-toi que l'orthographe est conforme aux règles de la langue ${languageName}
3. Exemples d'erreurs courantes à ÉVITER (selon la langue):
   * ${languageName === 'Français' ? 'panthère (✓) vs phantère (✗), murène (✓) vs murraine (✗), phoque (✓) vs foque (✗)' : 'Vérifie les noms propres, les noms d\'animaux, les termes techniques'}
   * Vérifie particulièrement: noms d'animaux, lieux géographiques, noms propres, termes techniques
4. Double-vérification finale: Relis TOUS les mots avant d'envoyer la question
5. Si le moindre doute sur l'orthographe d'un mot → utilise un synonyme dont tu es sûr de l'orthographe
6. L'orthographe correcte est AUSSI IMPORTANTE que la véracité factuelle

Format JSON requis:
{
  "text": "L'affirmation en ${languageName}",
  "type": "true_false",
  "answers": ["Vrai", null, "Faux", null],
  "correct_index": 0 ou 2,
  "explanation": "Une explication courte et intéressante (2-3 phrases maximum) qui apprend quelque chose au joueur sur le sujet de l'affirmation. Cette explication sera affichée après la réponse sous 'Le saviez-vous ?'. Elle doit être éducative, captivante et en ${languageName}."
}

RÈGLES STRICTES:
1. Pour une affirmation VRAIE: correct_index = 0
2. Pour une affirmation FAUSSE: correct_index = 2
3. Le tableau answers est TOUJOURS ["Vrai", null, "Faux", null] (IMPORTANT: garder en français pour compatibilité frontend)
4. Question unique et originale
5. Réponds UNIQUEMENT avec le JSON, rien d'autre

NOTE TECHNIQUE: Les réponses restent en français ("Vrai"/"Faux") pour compatibilité avec le frontend/backend actuel. Lors de l'activation future d'autres langues, adapter également le frontend pour afficher les traductions.`;

    const systemPrompt = `Tu es un expert en création de questions de quiz éducatives en ${languageName}. Tu génères des questions uniques, pertinentes et adaptées au niveau de difficulté demandé. Tu réponds UNIQUEMENT en JSON valide, sans markdown ni backticks.`;
    
    const fullPrompt = systemPrompt + "\n\n" + prompt;
    
    const response = await gemini.models.generateContent({
      model: 'gemini-2.0-flash',
      contents: [
        { role: 'user', parts: [{ text: fullPrompt }] }
      ],
      config: {
        temperature: 1.0,
        maxOutputTokens: 500,
        responseMimeType: 'application/json'
      }
    });

    console.log('Gemini Response received');
    
    // Extract text from Gemini response
    let content = '';
    if (response.candidates && response.candidates[0]?.content?.parts) {
      content = response.candidates[0].content.parts.map(p => p.text || '').join('');
    } else if (response.text) {
      content = response.text;
    } else if (typeof response === 'string') {
      content = response;
    }
    
    if (!content) {
      console.error('Gemini response structure:', JSON.stringify(response, null, 2));
      throw new Error('No content in Gemini response');
    }
    
    // Clean up the response - remove markdown code blocks if present
    content = content.trim();
    if (content.startsWith('```json')) {
      content = content.slice(7);
    }
    if (content.startsWith('```')) {
      content = content.slice(3);
    }
    if (content.endsWith('```')) {
      content = content.slice(0, -3);
    }
    content = content.trim();
    
    const questionData = JSON.parse(content);
    
    // Validation de la structure
    if (!questionData.text || !questionData.type || !questionData.answers || questionData.correct_index === undefined) {
      throw new Error('Invalid question structure from AI');
    }
    
    // NOUVELLE VALIDATION: Vérifier la qualité des réponses pour questions à choix multiple
    if (questionData.type === 'multiple') {
      const validAnswers = questionData.answers.filter(a => a && a.trim().length > 0);
      
      // Vérifier qu'il y a exactement 4 réponses non vides
      if (validAnswers.length !== 4) {
        console.log(`⚠️ RÉPONSES INVALIDES: ${validAnswers.length} réponses au lieu de 4`);
        throw new Error(`Invalid number of answers: ${validAnswers.length}`);
      }
      
      // Vérifier qu'il n'y a pas de doublons dans les réponses
      const uniqueAnswers = [...new Set(validAnswers.map(a => a.toLowerCase().trim()))];
      if (uniqueAnswers.length !== validAnswers.length) {
        console.log(`⚠️ DOUBLONS DÉTECTÉS dans les réponses: ${JSON.stringify(validAnswers)}`);
        throw new Error('Duplicate answers in question');
      }
      
      // Vérifier que les réponses ne sont pas trop courtes (minimum 2 caractères)
      const tooShort = validAnswers.filter(a => a.trim().length < 2);
      if (tooShort.length > 0) {
        console.log(`⚠️ RÉPONSES TROP COURTES: ${JSON.stringify(tooShort)}`);
        throw new Error('Answers too short');
      }
      
      // Vérifier qu'il n'y a pas de mots absurdes ou inventés (liste noire)
      // Bloque les mots qui contiennent ou sont exactement ces termes absurdes
      const blacklist = [
        'hermite', 'safran', 'xxxxx', 'yyyyy', 'zzzzz', 
        'endurolâtre', 'endurolat', 'gaboulon', 'toupinel', 'zorbifex',
        'résilience arctique', 'resilience arctique', 'éperlan sculpte', 'éperlan sculpté',
        'hermitique', 'hermitisme', 'safranier', 'toupinelle', 'gaboulette',
        'zorbifexien', 'endurolâtrique', 'résilieniste', 'arctiquien',
        'poisson-lune géant', 'dauphin volant', 'baleine terrestre'
      ];
      const hasBlacklisted = validAnswers.some(a => {
        const normalized = a.toLowerCase().trim().replace(/['']/g, '');
        // Vérifie si la réponse contient un mot de la liste noire
        return blacklist.some(bad => normalized.includes(bad));
      });
      if (hasBlacklisted) {
        console.log(`⚠️ MOTS ABSURDES/INVENTÉS détectés dans les réponses: ${JSON.stringify(validAnswers)}`);
        throw new Error('Nonsense or invented words in answers');
      }
    }
    
    // VALIDATION DU THÈME : Vérifier que la question correspond au thème demandé
    const questionText = questionData.text.toLowerCase().trim();
    const correctAnswerText = questionData.answers[questionData.correct_index]?.toLowerCase().trim() || '';
    
    // Mots-clés spécifiques par thème pour validation
    const themeKeywords = {
      'histoire': ['guerre', 'roi', 'empire', 'révolution', 'siècle', 'bataille', 'civilisation', 'conquête', 'dynastie', 'empereur', 'république', 'monarchie', 'traité', 'indépendance', 'colonisation', 'explorateur', 'découverte', 'président', 'première guerre', 'seconde guerre', 'moyen âge', 'antiquité', 'renaissance', 'napoléon', 'louis', 'charles', '14', '15', '16', '17', '18', '19', '20', 'siècle'],
      'geographie': ['pays', 'capitale', 'continent', 'océan', 'montagne', 'fleuve', 'ville', 'région', 'désert', 'forêt', 'lac', 'mer', 'île', 'volcán', 'frontière', 'territoire', 'climat', 'population', 'géographie'],
      'faune': ['animal', 'mammifère', 'oiseau', 'poisson', 'reptile', 'insecte', 'espèce', 'habitat', 'prédateur', 'herbivore', 'carnivore', 'faune', 'zoo', 'savane', 'jungle', 'océan', 'marin'],
      'sciences': ['atome', 'molécule', 'énergie', 'force', 'physique', 'chimie', 'biologie', 'planète', 'système solaire', 'cellule', 'adn', 'théorie', 'découverte scientifique', 'expérience', 'chercheur'],
      'art': ['peinture', 'sculpture', 'musée', 'artiste', 'tableau', 'œuvre', 'exposition', 'galerie', 'style artistique', 'courant', 'renaissance', 'impressionnisme', 'cubisme'],
      'cinema': ['film', 'acteur', 'réalisateur', 'cinéma', 'oscar', 'festival', 'scénario', 'tournage', 'production'],
      'sport': ['match', 'équipe', 'joueur', 'championnat', 'coupe', 'médaille', 'jeux olympiques', 'compétition', 'entraîneur'],
      'cuisine': ['recette', 'plat', 'ingrédient', 'cuisson', 'chef', 'gastronomie', 'restaurant', 'saveur']
    };
    
    // VALIDATION THÉMATIQUE ASSOUPLIE : Détecte les mélanges flagrants mais autorise les sujets connexes
    // Exemple acceptable : "Bitcoin" dans Histoire (technologie historique)
    // Exemple bloqué : "Match de football" dans Géographie
    const strictlyIncompatible = {
      'histoire': {
        blocked: ['match de football', 'championnat', 'coupe du monde', 'jeux olympiques 2024', 'finale de la ligue', 'recette de cuisine', 'plat gastronomique', 'ingrédient culinaire', 'cuisson au four'],
        reason: 'sport compétitif/cuisine pratique (non historique)'
      },
      'geographie': {
        blocked: ['oscar du meilleur film', 'acteur principal', 'réalisateur célèbre', 'match de football', 'championnat', 'finale de la ligue', 'recette de cuisine', 'plat gastronomique'],
        reason: 'cinéma/sport/cuisine (non géographique)'
      },
      'faune': {
        blocked: ['oscar du meilleur film', 'match de football', 'championnat', 'guerre mondiale', 'bataille historique', 'recette de cuisine', 'plat gastronomique'],
        reason: 'cinéma/sport/histoire militaire/cuisine'
      },
      'sciences': {
        blocked: ['oscar du meilleur film', 'match de football', 'championnat', 'recette de cuisine', 'plat gastronomique'],
        reason: 'cinéma/sport/cuisine'
      }
    };
    
    // Vérifier UNIQUEMENT les combinaisons strictement incompatibles
    if (theme !== 'general' && strictlyIncompatible[theme]) {
      const incompatiblePhrases = strictlyIncompatible[theme].blocked;
      const hasIncompatible = incompatiblePhrases.some(phrase => 
        questionText.includes(phrase) || correctAnswerText.includes(phrase)
      );
      
      if (hasIncompatible) {
        console.log(`⚠️ THÈME INCOMPATIBLE: Sujet strictement incompatible (${strictlyIncompatible[theme].reason}) pour "${theme}"`);
        console.log(`   Question: "${questionData.text}"`);
        console.log(`   Réponse: "${correctAnswerText}"`);
        throw new Error(`Incompatible topic: ${strictlyIncompatible[theme].reason} for ${theme} theme`);
      }
    }
    
    // Patterns problématiques à rejeter
    const invalidPatterns = [
      // Combinaisons de termes incompatibles (avec support de tirets/slashes)
      { pattern: /mammif[eè]re[\s\-\/]+l[ée]zard/i, reason: 'Combinaison de termes incompatibles (mammifère lézard)' },
      { pattern: /reptile[\s\-\/]+mammif[eè]re/i, reason: 'Combinaison de termes incompatibles (reptile mammifère)' },
      { pattern: /insecte[\s\-\/]+mammif[eè]re/i, reason: 'Combinaison de termes incompatibles (insecte mammifère)' },
      { pattern: /oiseau[\s\-\/]+reptile/i, reason: 'Combinaison de termes incompatibles (oiseau reptile)' },
      
      // Formulations factuellement fausses connues
      { pattern: /fils\s+color[ée]s/i, reason: 'Formulation imprécise ou fausse (fils colorés)' },
      { pattern: /tiss[ée]\s+.*\s+fils\s+color[ée]s/i, reason: 'Formulation fausse (tisse avec fils colorés)' },
      
      // Erreurs de français
      { pattern: /construits\s+de\s+/i, reason: 'Erreur de français (construits au lieu de constructions)' },
      { pattern: /dans\s+les\s+construits(?!\s+par)/i, reason: 'Erreur de français (construits au lieu de constructions)' },
      { pattern: /interpell[ée]\s+un\s+insecte?/i, reason: 'Erreur de français (interpelle un insect/insecte)' },
    ];
    
    for (const { pattern, reason } of invalidPatterns) {
      if (pattern.test(questionText) || pattern.test(correctAnswerText)) {
        console.log(`⚠️ QUESTION REJETÉE : ${reason}`);
        console.log(`   Question: "${questionData.text}"`);
        console.log(`   Réponse: "${correctAnswerText}"`);
        throw new Error(`Invalid question pattern: ${reason}`);
      }
    }
    
    // VÉRIFICATION CRITIQUE : La réponse correcte ne doit PAS être dans usedAnswers
    const correctAnswer = questionData.answers[questionData.correct_index];
    if (correctAnswer && usedAnswers.length > 0) {
      // Normaliser pour comparaison (ignorer casse et espaces)
      const normalizedCorrect = correctAnswer.toLowerCase().trim();
      const normalizedUsed = usedAnswers.map(a => a.toLowerCase().trim());
      
      if (normalizedUsed.includes(normalizedCorrect)) {
        console.log(`⚠️ RÉPONSE DUPLIQUÉE DÉTECTÉE: "${correctAnswer}" déjà utilisée. Rejet de cette question.`);
        throw new Error(`Duplicate answer detected: ${correctAnswer}`);
      }
    }
    
    // NOUVELLE VÉRIFICATION : Le texte de la question ne doit PAS être dans usedQuestionTexts
    if (questionData.text && usedQuestionTexts.length > 0) {
      // Normaliser pour comparaison (ignorer casse et espaces multiples)
      const normalizedQuestionText = questionData.text.toLowerCase().trim().replace(/\s+/g, ' ');
      const normalizedUsedTexts = usedQuestionTexts.map(q => q.toLowerCase().trim().replace(/\s+/g, ' '));
      
      if (normalizedUsedTexts.includes(normalizedQuestionText)) {
        console.log(`⚠️ QUESTION DUPLIQUÉE DÉTECTÉE: "${questionData.text}" déjà posée. Rejet de cette question.`);
        throw new Error(`Duplicate question text detected: ${questionData.text}`);
      }
    }
    
    // DÉTECTION DE CONCEPTS SIMILAIRES : Rejeter les questions sur le même sujet
    // Extraction des mots-clés significatifs (> 4 caractères, pas de mots communs)
    const extractKeywords = (text) => {
      const stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'est', 'sont', 'qui', 'que', 'quoi', 'quel', 'quelle', 'quels', 'quelles', 'dans', 'sur', 'sous', 'avec', 'pour', 'par', 'plus', 'moins', 'très', 'bien', 'fait', 'être', 'avoir', 'peut', 'monde', 'terre', 'pays', 'grand', 'petit', 'premier', 'première', 'vrai', 'faux', 'appelle', 'connu', 'connue', 'appelé', 'appelée', 'situé', 'située', 'trouve', 'trouve', 'the', 'is', 'are', 'was', 'were', 'what', 'which', 'where', 'when', 'who', 'how', 'most', 'largest', 'biggest', 'smallest', 'called', 'known', 'located', 'found', 'true', 'false', 'animal', 'animaux', 'lequel', 'laquelle'];
      const words = text.toLowerCase()
        .replace(/[''`´]/g, "'")
        .replace(/[^\wàâäéèêëïîôùûüç\s'-]/gi, ' ')
        .split(/\s+/)
        .filter(word => word.length > 4 && !stopWords.includes(word));
      return [...new Set(words)];
    };
    
    // Combiner question + réponse correcte pour extraire tous les concepts
    const currentKeywords = extractKeywords(questionData.text + ' ' + correctAnswerText);
    
    // Vérifier la similarité avec les questions ET réponses déjà posées
    // usedAnswers contient TOUTES les réponses (correctes + distracteurs) déjà utilisées
    const allUsedConcepts = [...usedQuestionTexts, ...usedAnswers].join(' ');
    const usedKeywords = extractKeywords(allUsedConcepts);
    
    if (usedKeywords.length > 0 && currentKeywords.length > 0) {
      // Calculer le nombre de mots-clés communs
      const commonKeywords = currentKeywords.filter(kw => usedKeywords.includes(kw));
      
      // Si >= 2 mots-clés significatifs en commun, rejeter (même sujet probable)
      if (commonKeywords.length >= 2) {
        console.log(`⚠️ CONCEPT SIMILAIRE DÉTECTÉ: ${commonKeywords.join(', ')}`);
        console.log(`   Nouvelle question: "${questionData.text}" (réponse: "${correctAnswerText}")`);
        console.log(`   Mots-clés communs avec questions précédentes`);
        throw new Error(`Similar concept detected: ${commonKeywords.join(', ')}`);
      }
    }
    
      // Si toutes les validations passent, renvoyer la question
      console.log(`✅ Question validée avec succès (tentative ${attempt})`, questionData);
      return res.json(questionData);
      
    } catch (error) {
      // Si une validation échoue, logger et réessayer
      console.log(`❌ Tentative ${attempt}/${MAX_RETRIES} échouée:`, error.message);
      
      // Si c'est la dernière tentative, renvoyer l'erreur
      if (attempt === MAX_RETRIES) {
        console.error('🚫 Échec après', MAX_RETRIES, 'tentatives:', error);
        return res.status(500).json({ 
          error: 'Failed to generate valid question after retries', 
          details: error.message 
        });
      }
      
      // Sinon, continuer la boucle pour réessayer
      console.log(`🔄 Nouvelle tentative...`);
    }
  }
  } catch (error) {
    console.error('❌ Erreur globale generate-question:', error.message);
    return res.status(500).json({
      error: 'Internal server error',
      details: error.message
    });
  }
});

// NOUVEAU ENDPOINT : Génération progressive de questions (queue system)
// Génère les questions une par une et les stocke dans la session Laravel
app.post('/generate-queue', async (req, res) => {
  const { theme, niveau, avatar, roundNumber } = req.body;
  
  // Nombre de questions à générer (11 pour Magicienne, 10 pour les autres)
  const totalQuestions = avatar === 'magicienne' ? 11 : 10;
  
  console.log(`🎯 Début génération progressive: ${totalQuestions} questions (Round ${roundNumber}, Theme: ${theme}, Niveau: ${niveau})`);
  
  // Variables de suivi
  const usedAnswers = [];
  const usedQuestionTexts = [];
  const generatedQuestions = [];
  let successCount = 0;
  let failureCount = 0;
  
  // Fonction pour générer UNE question
  const generateSingleQuestion = async (questionNumber) => {
    try {
      console.log(`  📝 Génération question ${questionNumber}/${totalQuestions}...`);
      
      const response = await fetch((process.env.QUESTION_API_URL || 'http://localhost:3000') + '/generate-question', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          theme,
          niveau,
          questionNumber,
          usedAnswers,
          usedQuestionTexts
        })
      });
      
      if (!response.ok) {
        console.log(`  ❌ Échec question ${questionNumber}: ${response.status}`);
        failureCount++;
        return null;
      }
      
      const question = await response.json();
      
      // Ajouter la réponse correcte et le texte aux listes d'exclusion
      if (question.type === 'multiple' && question.answers && question.answers[question.correct_index]) {
        usedAnswers.push(question.answers[question.correct_index]);
      }
      if (question.text) {
        usedQuestionTexts.push(question.text);
      }
      
      generatedQuestions.push(question);
      successCount++;
      console.log(`  ✅ Question ${questionNumber} générée avec succès`);
      
      return question;
    } catch (error) {
      console.log(`  ❌ Erreur génération question ${questionNumber}:`, error.message);
      failureCount++;
      return null;
    }
  };
  
  // Générer les questions de manière séquentielle
  for (let i = 1; i <= totalQuestions; i++) {
    await generateSingleQuestion(i);
  }
  
  console.log(`\n📊 Génération terminée: ${successCount} succès, ${failureCount} échecs\n`);
  
  // Retourner toutes les questions générées
  res.json({
    success: true,
    questions: generatedQuestions,
    total: totalQuestions,
    generated: successCount,
    failed: failureCount
  });
});

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
app.post('/generate-master-question', async (req, res) => {
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
    const MAX_RETRIES = 3;
    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
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
      if ((req.body.domainType || 'theme') === 'school') {
        contextBlock = `
CONTEXTE SCOLAIRE OBLIGATOIRE:
- Niveau: ${req.body.schoolLevel || 'non précisé'}
- Année: ${req.body.schoolGrade || 'non précisée'}
- Matière: ${req.body.schoolSubject || 'non précisée'}
- Pays: ${req.body.schoolCountry || 'non précisé'}

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

      const fullPrompt = systemPrompt + "\n\n" + contextBlock + "\n\n" + userPrompt;
      
      const geminiResponse = await gemini.models.generateContent({
      model: 'gemini-2.0-flash',
      contents: [
        { role: 'user', parts: [{ text: fullPrompt }] }
      ],
      config: {
        temperature: (req.body.domainType === 'school') ? 0.1 : 0.3,
        maxOutputTokens: 500,
        responseMimeType: 'application/json'
      }
    });
    
    // Extract text from Gemini response
    let content = '';
    if (geminiResponse.candidates && geminiResponse.candidates[0]?.content?.parts) {
      content = geminiResponse.candidates[0].content.parts.map(p => p.text || '').join('');
    } else if (geminiResponse.text) {
      content = geminiResponse.text;
    } else if (typeof geminiResponse === 'string') {
      content = geminiResponse;
    }
    content = content?.trim() || '';
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
      throw new Error('Réponse JSON invalide de Gemini');
    }
    
    // Validation stricte des données
    if (!parsedData.question || typeof parsedData.question !== 'string') {
      throw new Error('Question invalide');
    }
    
    if (!Array.isArray(parsedData.answers)) {
      throw new Error('Réponses invalides');
    }
    
    if (typeof parsedData.correct_index !== 'number') {
      throw new Error('correct_index invalide');
    }

    if (parsedData.question.trim().length < 10) {
      throw new Error('Question trop courte');
    }

    if (parsedData.answers.length < 2) {
      throw new Error('Nombre de réponses insuffisant');
    }

    if (new Set(parsedData.answers).size !== parsedData.answers.length) {
      throw new Error('Réponses dupliquées');
    }

    if (parsedData.correct_index < 0 || parsedData.correct_index >= parsedData.answers.length) {
      throw new Error('correct_index hors plage');
    }

    const normalizedQuestion = parsedData.question.toLowerCase();
    const correctAnswer = String(parsedData.answers[parsedData.correct_index] ?? '').trim().toLowerCase();

    if (!correctAnswer || correctAnswer.length < 3) {
      throw new Error('Réponse correcte invalide');
    }

    if (['a', 'b', 'c', 'd', 'ok', 'oui', 'non'].includes(correctAnswer)) {
      throw new Error('Réponse correcte trop faible');
    }

    if (normalizedQuestion.includes('nouvelle-france') && normalizedQuestion.includes('moyen âge')) {
      throw new Error('Anachronisme détecté');
    }
    
    console.log(`✅ Question générée: "${parsedData.question.substring(0, 50)}..."`);
    
    return res.json({
      success: true,
      question: {
        text: parsedData.question,
        answers: parsedData.answers,
        correct_index: parsedData.correct_index
      }
    });
    
    } catch (error) {
      console.log(`❌ Tentative ${attempt}/${MAX_RETRIES} échouée:`, error.message);
      if (attempt === MAX_RETRIES) {
        throw error;
      }
    }
  }
    
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

app.post('/generate-image-question', async (req, res) => {
  const { questionNumber = 1, language = 'fr' } = req.body;

  console.log(`\n🖼️ Génération question image-mémoire #${questionNumber} (langue: ${language})`);

  try {
    const scenario = generateVisualScenario();
    console.log(`📋 Scénario: ${scenario.presentElements.join(', ')}`);
    console.log(`❌ Éléments absents: ${scenario.absentElements.join(', ')}`);

    console.log('🎨 Génération de l\'image avec Imagen...');

    const imageResponse = await gemini.models.generateImages({
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

app.post('/generate-fun-fact', async (req, res) => {
  const { questionText = '', correctAnswer = '', language = 'fr' } = req.body;

  const languageNames = {
    'fr': 'français', 'en': 'anglais', 'es': 'espagnol', 'it': 'italien',
    'de': 'allemand', 'pt': 'portugais', 'ru': 'russe', 'ar': 'arabe',
    'zh': 'chinois', 'el': 'grec'
  };
  const langName = languageNames[language] || 'français';

  console.log(`\n💡 Génération fun fact (langue: ${language})`);

  try {
    const prompt = `Tu es un expert en culture générale. Basé sur cette question de quiz : "${questionText}" avec la bonne réponse "${correctAnswer}", explique POURQUOI cette réponse est correcte ou donne le contexte qui permet de comprendre la réponse. Maximum 2 phrases courtes. Réponds en ${langName}. Réponds UNIQUEMENT avec du JSON valide: {"factText": "ton explication ici"}`;

    const geminiResponse = await gemini.models.generateContent({
      model: 'gemini-2.0-flash',
      contents: [
        { role: 'user', parts: [{ text: prompt }] }
      ],
      config: {
        temperature: 0.7,
        maxOutputTokens: 150,
        responseMimeType: 'application/json'
      }
    });

    let content = '';
    if (geminiResponse.candidates && geminiResponse.candidates[0]?.content?.parts) {
      content = geminiResponse.candidates[0].content.parts.map(p => p.text || '').join('');
    } else if (geminiResponse.text) {
      content = geminiResponse.text;
    }
    content = content?.trim() || '';

    let cleanContent = content;
    if (cleanContent.startsWith('```json')) cleanContent = cleanContent.slice(7);
    if (cleanContent.startsWith('```')) cleanContent = cleanContent.slice(3);
    if (cleanContent.endsWith('```')) cleanContent = cleanContent.slice(0, -3);
    cleanContent = cleanContent.trim();

    const parsed = JSON.parse(cleanContent);
    const factText = parsed.factText || parsed.fact_text || parsed.text || '';

    console.log(`✅ Fun fact généré: "${factText.substring(0, 60)}..."`);

    res.json({ success: true, factText });

  } catch (error) {
    console.error('❌ Erreur génération fun fact:', error.message);
    res.json({
      success: true,
      factText: language === 'fr'
        ? 'Chaque question est une opportunité d\'apprendre quelque chose de nouveau !'
        : 'Every question is an opportunity to learn something new!'
    });
  }
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Question API server running on port ${PORT}`);
});
