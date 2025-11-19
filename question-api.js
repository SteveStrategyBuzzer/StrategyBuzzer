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
  
  const { theme, niveau, questionNumber, usedAnswers = [], usedQuestionTexts = [] } = req.body;
  
  const themeLabel = THEMES_FR[theme] || 'culture générale';
  const difficultyDesc = getDifficultyDescription(niveau);
  const lengthConstraint = getQuestionLengthConstraint(niveau);
  
  // Créer un contexte pour éviter les réponses déjà utilisées
  const usedAnswersContext = usedAnswers.length > 0
    ? `\n\nRÉPONSES INTERDITES - La réponse correcte NE DOIT PAS être parmi ces réponses déjà utilisées:\n${usedAnswers.map(a => `- ${a}`).join('\n')}\nChoisis une réponse complètement différente.`
    : '';
  
  // Boucle de retry pour régénérer automatiquement si validation échoue
  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    try {
      console.log(`🔄 Tentative ${attempt}/${MAX_RETRIES} de génération de question...`);
      
      // Décider aléatoirement entre question à choix multiple (80%) et vrai/faux (20%)
      const isMultipleChoice = Math.random() > 0.2;
      
      const prompt = isMultipleChoice 
      ? `Tu es un générateur de questions de quiz en français. Génère UNE SEULE question unique de ${themeLabel} avec un niveau de difficulté ${difficultyDesc} (niveau ${niveau}/100).

⚠️ RESPECT DU THÈME OBLIGATOIRE ⚠️
- La question DOIT porter sur "${themeLabel}" et UNIQUEMENT sur ce thème
- Exemple pour "histoire": événements historiques, personnages, guerres, dates, civilisations
- Exemple pour "géographie": pays, capitales, lieux, montagnes, fleuves, climat
- Exemple pour "faune": animaux réels et leurs comportements, habitats, caractéristiques
- NE JAMAIS mélanger les thèmes (pas de questions sur la nature si le thème est "histoire")

IMPORTANT:
- La question doit être VRAIMENT UNIQUE et ORIGINALE - évite absolument les questions clichées ou répétitives
- Ne pose PAS de questions évidentes ou trop simples (ex: "Quelle est la capitale de la France?", "Quel animal est le meilleur ami de l'homme?")
- Varie les sujets, les angles d'approche et les formulations
- Adapte la complexité au niveau ${niveau} (plus le niveau est élevé, plus la question doit être difficile)
- Pour les niveaux élevés (>50), utilise des détails précis, des dates exactes, des noms complets
- Ceci est la question ${questionNumber} de la partie - évite de répéter des concepts déjà couverts
- LONGUEUR: ${lengthConstraint}${usedAnswersContext}

VALIDATION FACTUELLE STRICTE:
- VÉRIFIE que la question et la réponse correcte sont VRAIES et EXACTES à 100%
- Pour les questions sur les animaux: vérifie les comportements, habitats, et caractéristiques réels
- INTERDICTION ABSOLUE DES MOTS INVENTÉS:
  * Utilise UNIQUEMENT des noms d'animaux/plantes qui EXISTENT RÉELLEMENT
  * EXEMPLES DE MOTS INVENTÉS INTERDITS: "endurolâtre", "gaboulon", "hermite", "toupinel"
  * Avant d'utiliser un nom d'animal, VÉRIFIE qu'il existe dans la nature
  * En cas de DOUTE, utilise un animal/plante CONNU et COMMUN

- EXEMPLES DE QUESTIONS INTERDITES (car factuellement fausses ou imprécises):
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
  * Utilise un FRANÇAIS PARFAIT: "constructions" (nom), pas "construits" (participe passé)
  * Ne mélange JAMAIS des termes incompatibles: "mammifère lézard" est ABSURDE
  * Utilise "animal" pour les questions générales, pas "insecte" ou "mammifère" si tu n'es pas sûr

- RÈGLE D'OR: Si tu n'es PAS ABSOLUMENT CERTAIN à 100% qu'un fait est vrai, choisis un autre sujet
- Les réponses doivent être des animaux/plantes RÉELS, CONNUS et VÉRIFIABLES
- ÉVITE les questions sur des comportements animaux rares ou peu connus - reste sur des faits bien établis

Format JSON requis:
{
  "text": "La question en français",
  "type": "multiple",
  "answers": ["réponse correcte", "réponse incorrecte 1", "réponse incorrecte 2", "réponse incorrecte 3"],
  "correct_index": 0
}

RÈGLES STRICTES:
1. La réponse correcte DOIT être à l'index 0 du tableau answers
2. Fournis exactement 4 réponses plausibles
3. Les mauvaises réponses doivent être crédibles mais incorrectes
4. Question unique et originale, pas de répétition
5. Réponds UNIQUEMENT avec le JSON, rien d'autre`
      : `Tu es un générateur de questions de quiz en français. Génère UNE SEULE question Vrai/Faux unique de ${themeLabel} avec un niveau de difficulté ${difficultyDesc} (niveau ${niveau}/100).

IMPORTANT:
- La question doit être VRAIMENT UNIQUE et ORIGINALE - évite absolument les affirmations clichées ou répétitives
- Ne pose PAS d'affirmations évidentes (ex: "Paris est la capitale de la France", "Le chien est un animal domestique")
- Varie les sujets et les angles d'approche
- Adapte la complexité au niveau ${niveau}
- Pour les niveaux élevés, utilise des affirmations plus nuancées
- Ceci est la question ${questionNumber} de la partie - évite de répéter des concepts déjà couverts
- LONGUEUR: ${lengthConstraint}${usedAnswersContext}

VALIDATION FACTUELLE STRICTE:
- VÉRIFIE que l'affirmation est soit VRAIE soit FAUSSE de manière claire et vérifiable
- Pour les questions sur les animaux/nature: vérifie les faits biologiques réels
- EXEMPLES D'AFFIRMATIONS INTERDITES (car factuellement inexactes):
  * "Le serpent à sonnette change de couleur" (FAUX: confusion avec le caméléon)
  * "Le castor fait son nid avec du safran" (ABSURDE: non-sens total)
- Si tu n'es PAS CERTAIN à 100% d'un fait, choisis un autre sujet

Format JSON requis:
{
  "text": "L'affirmation en français",
  "type": "true_false",
  "answers": ["Vrai", null, "Faux", null],
  "correct_index": 0 ou 2
}

RÈGLES STRICTES:
1. Pour une affirmation VRAIE: correct_index = 0
2. Pour une affirmation FAUSSE: correct_index = 2
3. Le tableau answers est TOUJOURS ["Vrai", null, "Faux", null]
4. Question unique et originale
5. Réponds UNIQUEMENT avec le JSON, rien d'autre`;

    const completion = await openai.chat.completions.create({
      model: "gpt-4o-mini", // Using gpt-4o-mini for reliable JSON generation
      messages: [
        {
          role: "system",
          content: "Tu es un expert en création de questions de quiz éducatives en français. Tu génères des questions uniques, pertinentes et adaptées au niveau de difficulté demandé. Tu réponds UNIQUEMENT en JSON valide."
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
    
    // MATRICE DE BLOCAGE CROISÉ : Bloquer les mots d'autres thèmes pour chaque thème
    const themeBlocklist = {
      'histoire': {
        blocked: ['film', 'acteur', 'réalisateur', 'oscar', 'cinéma', 'match', 'équipe', 'joueur', 'championnat', 'coupe du monde', 'jeux olympiques', 'recette', 'plat', 'ingrédient', 'cuisson', 'arbre', 'érable', 'chêne', 'pin', 'saule', 'bouleau', 'insecte', 'mammifère', 'reptile', 'amphibien', 'plante', 'fleur', 'botanique', 'zoologie'],
        reason: 'sport/cinéma/nature/cuisine'
      },
      'geographie': {
        blocked: ['film', 'acteur', 'réalisateur', 'oscar', 'match', 'équipe', 'joueur', 'championnat', 'coupe du monde', 'recette', 'plat', 'ingrédient', 'cuisson'],
        reason: 'sport/cinéma/cuisine'
      },
      'faune': {
        blocked: ['film', 'acteur', 'réalisateur', 'oscar', 'match', 'équipe', 'joueur', 'championnat', 'coupe du monde', 'guerre', 'roi', 'empire', 'révolution', 'bataille', 'recette', 'plat', 'ingrédient', 'cuisson'],
        reason: 'cinéma/sport/histoire/cuisine'
      },
      'sciences': {
        blocked: ['film', 'acteur', 'réalisateur', 'oscar', 'match', 'équipe', 'joueur', 'championnat', 'coupe du monde', 'recette', 'plat', 'ingrédient', 'cuisson'],
        reason: 'cinéma/sport/cuisine'
      },
      'art': {
        blocked: ['match', 'équipe', 'joueur', 'championnat', 'coupe du monde', 'jeux olympiques', 'recette', 'plat', 'ingrédient', 'cuisson', 'animal', 'poisson', 'oiseau', 'insecte', 'mammifère', 'reptile'],
        reason: 'sport/cuisine/faune'
      },
      'cinema': {
        blocked: ['guerre', 'roi', 'empire', 'révolution', 'bataille', 'match', 'équipe', 'joueur', 'championnat', 'coupe du monde', 'jeux olympiques', 'recette', 'plat', 'ingrédient', 'cuisson', 'animal', 'poisson', 'oiseau', 'insecte', 'mammifère'],
        reason: 'histoire/sport/cuisine/faune'
      },
      'sport': {
        blocked: ['film', 'acteur', 'réalisateur', 'oscar', 'guerre', 'roi', 'empire', 'révolution', 'bataille', 'recette', 'plat', 'ingrédient', 'cuisson', 'animal', 'poisson', 'oiseau', 'insecte', 'mammifère'],
        reason: 'cinéma/histoire/cuisine/faune'
      },
      'cuisine': {
        blocked: ['film', 'acteur', 'réalisateur', 'oscar', 'match', 'équipe', 'joueur', 'championnat', 'coupe du monde', 'guerre', 'roi', 'empire', 'révolution', 'bataille', 'animal', 'poisson', 'oiseau', 'insecte', 'mammifère'],
        reason: 'cinéma/sport/histoire/faune'
      }
    };
    
    // Pour tous les thèmes (sauf général), bloquer les mots d'autres thèmes
    if (theme !== 'general' && themeBlocklist[theme]) {
      const blockedWords = themeBlocklist[theme].blocked;
      const hasBlockedWord = blockedWords.some(word => 
        questionText.includes(word) || correctAnswerText.includes(word)
      );
      
      if (hasBlockedWord) {
        console.log(`⚠️ THÈME INCORRECT: Mot d'un autre thème (${themeBlocklist[theme].reason}) détecté pour "${theme}"`);
        console.log(`   Question: "${questionData.text}"`);
        console.log(`   Réponse: "${correctAnswerText}"`);
        throw new Error(`Off-topic question: contains words from ${themeBlocklist[theme].reason} for ${theme} theme`);
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

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Question API server running on port ${PORT}`);
});
