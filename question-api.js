const express = require('express');
const OpenAI = require('openai').default;

// This is using Replit's AI Integrations service for text generation
const openai = new OpenAI({
  baseURL: process.env.AI_INTEGRATIONS_OPENAI_BASE_URL,
  apiKey: process.env.AI_INTEGRATIONS_OPENAI_API_KEY
});

// Separate OpenAI client for DALL-E image generation (uses direct API key)
// DALL-E requires the standard OpenAI API, not the Replit integration
const openaiDallE = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY
});

const app = express();
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
  const MAX_RETRIES = 3;
  
  const { theme, niveau, questionNumber, usedAnswers = [], usedQuestionTexts = [], opponentAge = null, isBoss = false, language = 'fr' } = req.body;
  
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
${usedAnswers.length > 0 ? `- Réponses déjà utilisées dans ce match: ${usedAnswers.slice(0, 20).map(a => `"${a}"`).join(', ')}${usedAnswers.length > 20 ? ` ... et ${usedAnswers.length - 20} autres` : ''}` : ''}

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
${usedAnswers.length > 0 ? `- Réponses/sujets déjà utilisés dans ce match: ${usedAnswers.slice(0, 20).map(a => `"${a}"`).join(', ')}${usedAnswers.length > 20 ? ` ... et ${usedAnswers.length - 20} autres` : ''}` : ''}

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

    const completion = await openai.chat.completions.create({
      model: "gpt-4o-mini", // Using gpt-4o-mini for reliable JSON generation
      messages: [
        {
          role: "system",
          content: `Tu es un expert en création de questions de quiz éducatives en ${languageName}. Tu génères des questions uniques, pertinentes et adaptées au niveau de difficulté demandé. Tu réponds UNIQUEMENT en JSON valide.`
        },
        {
          role: "user",
          content: prompt
        }
      ],
      response_format: { type: "json_object" },
      temperature: 1.2,
      max_completion_tokens: 500
    });

    console.log('OpenAI Response:', JSON.stringify(completion, null, 2));
    
    const content = completion.choices[0]?.message?.content;
    if (!content) {
      throw new Error('No content in OpenAI response');
    }
    
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
      
      const response = await fetch('http://localhost:3000/generate-question', {
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
  
  // Construire la description pour DALL-E
  scenario.description = `A peaceful countryside scene with ${scenario.presentElements.join(', ')}. The style should be realistic and detailed, with good visibility of all elements. Natural lighting, clear day.`;
  
  return scenario;
}

// Endpoint pour générer une question image-mémoire
app.post('/generate-image-question', async (req, res) => {
  const { questionNumber = 1, language = 'fr' } = req.body;
  
  console.log(`\n🖼️ Génération question image-mémoire #${questionNumber} (langue: ${language})`);
  
  try {
    // 1. Générer le scénario visuel
    const scenario = generateVisualScenario();
    console.log(`📋 Scénario: ${scenario.presentElements.join(', ')}`);
    console.log(`❌ Éléments absents: ${scenario.absentElements.join(', ')}`);
    
    // 2. Générer l'image avec DALL-E (utilise le client OpenAI direct, pas l'intégration Replit)
    console.log('🎨 Génération de l\'image avec DALL-E...');
    
    const imageResponse = await openaiDallE.images.generate({
      model: "dall-e-3",
      prompt: scenario.description,
      n: 1,
      size: "1024x1024",
      quality: "standard",
      style: "natural"
    });
    
    const imageUrl = imageResponse.data[0].url;
    console.log('✅ Image générée avec succès');
    
    // 3. Créer la question et les réponses
    // Choisir un élément présent comme bonne réponse
    const correctElement = scenario.presentElements[Math.floor(Math.random() * scenario.presentElements.length)];
    
    // Choisir 3 éléments absents comme mauvaises réponses
    const shuffledAbsent = scenario.absentElements.sort(() => Math.random() - 0.5);
    const wrongElements = shuffledAbsent.slice(0, 3);
    
    // Si pas assez d'éléments absents, en prendre d'autres catégories
    while (wrongElements.length < 3) {
      const allAbsent = Object.values(VISUAL_ELEMENTS).flatMap(cat => cat.absent);
      const randomWrong = allAbsent[Math.floor(Math.random() * allAbsent.length)];
      if (!wrongElements.includes(randomWrong) && randomWrong !== correctElement) {
        wrongElements.push(randomWrong);
      }
    }
    
    // Traduire les éléments selon la langue
    const translatedCorrect = translateElement(correctElement, language);
    const translatedWrong = wrongElements.map(el => translateElement(el, language));
    
    // Mélanger les réponses (la bonne réponse à l'index 0 pour compatibilité)
    const answers = [translatedCorrect, ...translatedWrong];
    
    // Texte de la question selon la langue
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
    
    // Retourner la question complète
    res.json({
      success: true,
      type: 'image_memory',
      image_url: imageUrl,
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
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Endpoint pour télécharger et sauvegarder une image générée
app.post('/download-image', async (req, res) => {
  const { imageUrl, filename } = req.body;
  
  if (!imageUrl || !filename) {
    return res.status(400).json({ success: false, error: 'imageUrl and filename required' });
  }
  
  try {
    const fetch = (await import('node-fetch')).default;
    const fs = await import('fs');
    const path = await import('path');
    
    // Télécharger l'image
    const response = await fetch(imageUrl);
    const buffer = await response.buffer();
    
    // Créer le dossier si nécessaire
    const uploadDir = path.join(process.cwd(), 'storage', 'app', 'public', 'master_images');
    if (!fs.existsSync(uploadDir)) {
      fs.mkdirSync(uploadDir, { recursive: true });
    }
    
    // Sauvegarder l'image
    const filepath = path.join(uploadDir, filename);
    fs.writeFileSync(filepath, buffer);
    
    res.json({
      success: true,
      path: `master_images/${filename}`
    });
    
  } catch (error) {
    console.error('❌ Erreur téléchargement image:', error.message);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Question API server running on port ${PORT}`);
});
