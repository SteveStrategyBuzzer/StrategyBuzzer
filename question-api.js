const express = require('express');
const OpenAI = require('openai').default;

// This is using Replit's AI Integrations service, which provides OpenAI-compatible API access without requiring your own OpenAI API key.
// the newest OpenAI model is "gpt-5" which was released August 7, 2025. do not change this unless explicitly requested by the user
const openai = new OpenAI({
  baseURL: process.env.AI_INTEGRATIONS_OPENAI_BASE_URL,
  apiKey: process.env.AI_INTEGRATIONS_OPENAI_API_KEY
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

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Question API server running on port ${PORT}`);
});
