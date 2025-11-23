const http = require('http');

const THEMES = [
  'general',
  'geographie',
  'histoire',
  'art',
  'cinema',
  'sport',
  'cuisine',
  'faune',
  'sciences'
];

const QUESTIONS_PER_THEME = 50;

function makeRequest(data) {
  return new Promise((resolve, reject) => {
    const postData = JSON.stringify(data);
    
    const options = {
      hostname: 'localhost',
      port: 3000,
      path: '/generate-question',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData)
      }
    };

    const req = http.request(options, (res) => {
      let body = '';
      res.on('data', (chunk) => body += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(body));
        } catch (e) {
          reject(e);
        }
      });
    });

    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

function normalizeAnswer(answer) {
  return answer
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]/g, '');
}

async function testTheme(theme) {
  console.log(`\n${'='.repeat(80)}`);
  console.log(`🎯 TEST THÈME: ${theme.toUpperCase()}`);
  console.log(`${'='.repeat(80)}\n`);

  const usedAnswers = [];
  const questions = [];
  const duplicates = {
    literal: [],
    semantic: []
  };

  for (let i = 1; i <= QUESTIONS_PER_THEME; i++) {
    try {
      const response = await makeRequest({
        theme: theme,
        difficulty: 'medium',
        questionNumber: i,
        usedQuestionIds: [],
        usedAnswers: usedAnswers,
        questionType: 'multiple',
        language: 'fr'
      });

      if (response.success && response.question) {
        const q = response.question;
        const correctAnswer = q.answers[q.correct_index];
        const normalizedAnswer = normalizeAnswer(correctAnswer);

        // Détecter doublons littéraux
        const existingLiteral = usedAnswers.find(a => normalizeAnswer(a) === normalizedAnswer);
        if (existingLiteral) {
          duplicates.literal.push({
            questionNum: i,
            question: q.text,
            answer: correctAnswer,
            duplicate: existingLiteral
          });
          console.log(`⚠️  Q${i}: DOUBLON LITTÉRAL détecté!`);
          console.log(`    Question: ${q.text}`);
          console.log(`    Réponse: "${correctAnswer}" = "${existingLiteral}"`);
        }

        // Détecter doublons sémantiques (réponses très proches)
        const similarAnswers = usedAnswers.filter(a => {
          const normA = normalizeAnswer(a);
          const normB = normalizedAnswer;
          
          // Vérifier si l'une contient l'autre (ex: "athenes" dans "grece athenes")
          if (normA.includes(normB) || normB.includes(normA)) return true;
          
          // Vérifier si elles partagent > 60% de caractères communs
          const common = [...normA].filter(c => normB.includes(c)).length;
          const similarity = common / Math.max(normA.length, normB.length);
          return similarity > 0.6;
        });

        if (similarAnswers.length > 0) {
          duplicates.semantic.push({
            questionNum: i,
            question: q.text,
            answer: correctAnswer,
            similar: similarAnswers
          });
          console.log(`⚡ Q${i}: DOUBLON SÉMANTIQUE possible!`);
          console.log(`    Question: ${q.text}`);
          console.log(`    Réponse: "${correctAnswer}" ≈ ${similarAnswers.map(a => `"${a}"`).join(', ')}`);
        }

        questions.push({
          num: i,
          question: q.text,
          answer: correctAnswer,
          allAnswers: q.answers
        });

        // Ajouter TOUTES les réponses (correcte + distracteurs) pour simulation réaliste
        usedAnswers.push(correctAnswer);
        q.answers.forEach(a => {
          if (a && a !== correctAnswer) {
            usedAnswers.push(a);
          }
        });

        process.stdout.write(`✓ Q${i} `);
        if (i % 10 === 0) console.log('');
      } else {
        console.log(`\n❌ Q${i}: Erreur de génération`);
      }

      // Petit délai pour ne pas surcharger l'API
      await new Promise(resolve => setTimeout(resolve, 100));

    } catch (error) {
      console.log(`\n❌ Q${i}: Erreur - ${error.message}`);
    }
  }

  console.log('\n');
  return { theme, questions, duplicates, usedAnswers };
}

async function runTests() {
  console.log('\n╔════════════════════════════════════════════════════════════════════════════╗');
  console.log('║   TEST ANTI-DUPLICATION - 50 QUESTIONS × 9 THÈMES = 450 QUESTIONS         ║');
  console.log('╚════════════════════════════════════════════════════════════════════════════╝\n');

  const results = [];

  for (const theme of THEMES) {
    const result = await testTheme(theme);
    results.push(result);
    
    // Pause entre les thèmes
    await new Promise(resolve => setTimeout(resolve, 1000));
  }

  // RAPPORT FINAL
  console.log('\n' + '═'.repeat(80));
  console.log('📊 RAPPORT FINAL - ANALYSE DES DOUBLONS');
  console.log('═'.repeat(80) + '\n');

  let totalLiteral = 0;
  let totalSemantic = 0;

  results.forEach(result => {
    const literalCount = result.duplicates.literal.length;
    const semanticCount = result.duplicates.semantic.length;
    totalLiteral += literalCount;
    totalSemantic += semanticCount;

    const status = (literalCount + semanticCount) === 0 ? '✅' : '⚠️';
    
    console.log(`${status} ${result.theme.toUpperCase().padEnd(15)} - ${result.questions.length} questions`);
    console.log(`   Doublons littéraux: ${literalCount}`);
    console.log(`   Doublons sémantiques: ${semanticCount}`);
    
    if (literalCount > 0) {
      console.log(`   ⚠️  EXEMPLES LITTÉRAUX:`);
      result.duplicates.literal.slice(0, 3).forEach(d => {
        console.log(`      Q${d.questionNum}: "${d.answer}" = "${d.duplicate}"`);
      });
    }
    
    if (semanticCount > 0) {
      console.log(`   ⚡ EXEMPLES SÉMANTIQUES:`);
      result.duplicates.semantic.slice(0, 3).forEach(d => {
        console.log(`      Q${d.questionNum}: "${d.answer}" ≈ ${d.similar.map(a => `"${a}"`).join(', ')}`);
      });
    }
    
    console.log('');
  });

  console.log('═'.repeat(80));
  console.log(`TOTAL: ${results.reduce((sum, r) => sum + r.questions.length, 0)} questions générées`);
  console.log(`Doublons littéraux: ${totalLiteral}`);
  console.log(`Doublons sémantiques: ${totalSemantic}`);
  console.log(`Taux de réussite: ${((450 - totalLiteral) / 450 * 100).toFixed(1)}%`);
  console.log('═'.repeat(80) + '\n');

  // Détail des 10 premières questions de chaque thème
  console.log('\n📋 ÉCHANTILLON - 10 PREMIÈRES QUESTIONS PAR THÈME\n');
  results.forEach(result => {
    console.log(`\n🎯 ${result.theme.toUpperCase()}`);
    console.log('─'.repeat(80));
    result.questions.slice(0, 10).forEach(q => {
      console.log(`Q${q.num}: ${q.question}`);
      console.log(`   ✓ ${q.answer}`);
      console.log(`   ✗ ${q.allAnswers.filter(a => a && a !== q.answer).join(', ')}\n`);
    });
  });
}

runTests().catch(console.error);
