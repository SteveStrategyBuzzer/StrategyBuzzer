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
  try {
    const { theme, niveau, questionNumber, usedAnswers = [] } = req.body;
    
    const themeLabel = THEMES_FR[theme] || 'culture générale';
    const difficultyDesc = getDifficultyDescription(niveau);
    const lengthConstraint = getQuestionLengthConstraint(niveau);
    
    // Créer un contexte pour éviter les réponses déjà utilisées
    const usedAnswersContext = usedAnswers.length > 0
      ? `\n\nRÉPONSES INTERDITES - La réponse correcte NE DOIT PAS être parmi ces réponses déjà utilisées:\n${usedAnswers.map(a => `- ${a}`).join('\n')}\nChoisis une réponse complètement différente.`
      : '';
    
    // Décider aléatoirement entre question à choix multiple (80%) et vrai/faux (20%)
    const isMultipleChoice = Math.random() > 0.2;
    
    const prompt = isMultipleChoice 
      ? `Tu es un générateur de questions de quiz en français. Génère UNE SEULE question unique de ${themeLabel} avec un niveau de difficulté ${difficultyDesc} (niveau ${niveau}/100).

IMPORTANT:
- La question doit être VRAIMENT UNIQUE et ORIGINALE - évite absolument les questions clichées ou répétitives
- Ne pose PAS de questions évidentes ou trop simples (ex: "Quelle est la capitale de la France?", "Quel animal est le meilleur ami de l'homme?")
- Varie les sujets, les angles d'approche et les formulations
- Adapte la complexité au niveau ${niveau} (plus le niveau est élevé, plus la question doit être difficile)
- Pour les niveaux élevés (>50), utilise des détails précis, des dates exactes, des noms complets
- Ceci est la question ${questionNumber} de la partie - évite de répéter des concepts déjà couverts
- LONGUEUR: ${lengthConstraint}${usedAnswersContext}

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
    
    console.log('Generated question:', questionData);
    res.json(questionData);
    
  } catch (error) {
    console.error('Error generating question:', error);
    res.status(500).json({ error: 'Failed to generate question', details: error.message });
  }
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Question API server running on port ${PORT}`);
});
