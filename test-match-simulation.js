const http = require('http');

// Configuration du test : 50 questions × 3 manches = 150 questions sur UN thème
const QUESTIONS_PER_ROUND = 50;
const ROUNDS = 3;
const TEST_THEME = 'geographie'; // Un seul thème pour simuler un match réel

console.log('╔════════════════════════════════════════════════════════════════════════════╗');
console.log('║   TEST MATCH SIMULATION - 50 QUESTIONS × 3 MANCHES = 150 QUESTIONS        ║');
console.log(`║   THÈME UNIQUE: ${TEST_THEME.toUpperCase().padEnd(60)}║`);
console.log('╚════════════════════════════════════════════════════════════════════════════╝\n');

function makeRequest(data) {
  return new Promise((resolve, reject) => {
    const postData = JSON.stringify(data);

    const options = {
      hostname: 'localhost',
      port: 3000,
      path: '/generate',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(postData)
      }
    };

    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
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
  if (!answer) return '';
  return answer
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]/g, '');
}

async function testMatchSimulation() {
  const allUsedAnswers = []; // Pool global de toutes les réponses sur les 3 manches
  const duplicates = {
    literal: [],
    semantic: []
  };
  
  let totalQuestions = 0;
  let successfulQuestions = 0;

  for (let round = 1; round <= ROUNDS; round++) {
    console.log('\n' + '='.repeat(80));
    console.log(`🏆 MANCHE ${round}/${ROUNDS} - ${TEST_THEME.toUpperCase()}`);
    console.log('='.repeat(80) + '\n');

    const roundAnswers = new Map(); // Réponses de cette manche

    for (let i = 1; i <= QUESTIONS_PER_ROUND; i++) {
      totalQuestions++;
      
      try {
        const q = await makeRequest({
          theme: TEST_THEME,
          difficulty: 'medium',
          questionNumber: i,
          usedQuestionIds: [],
          usedAnswers: allUsedAnswers,
          questionType: 'multiple',
          language: 'fr'
        });

        if (q && q.text && q.answers) {
          successfulQuestions++;
          const correctAnswer = q.answers[q.correct_index];
          const normalizedAnswer = normalizeAnswer(correctAnswer);

          // Vérifier les doublons littéraux
          if (allUsedAnswers.includes(correctAnswer)) {
            duplicates.literal.push({
              round: round,
              questionNum: i,
              question: q.text,
              answer: correctAnswer,
              previousRound: allUsedAnswers.indexOf(correctAnswer) < (round - 1) * QUESTIONS_PER_ROUND ? 
                Math.floor(allUsedAnswers.indexOf(correctAnswer) / QUESTIONS_PER_ROUND) + 1 : round
            });
            console.log(`\n⚠️ DOUBLON LITTÉRAL - Manche ${round}, Q${i}:`);
            console.log(`    Réponse: "${correctAnswer}"`);
          }

          // Vérifier les doublons sémantiques (normalisation)
          const existingSemantic = allUsedAnswers.find(a => 
            normalizeAnswer(a) === normalizedAnswer && a !== correctAnswer
          );
          
          if (existingSemantic) {
            duplicates.semantic.push({
              round: round,
              questionNum: i,
              question: q.text,
              answer: correctAnswer,
              similarTo: existingSemantic
            });
            console.log(`\n⚡ DOUBLON SÉMANTIQUE - Manche ${round}, Q${i}:`);
            console.log(`    Réponse: "${correctAnswer}" ≈ "${existingSemantic}"`);
          }

          // Ajouter toutes les réponses au pool global
          q.answers.forEach(a => {
            if (a) {
              allUsedAnswers.push(a);
            }
          });

          roundAnswers.set(i, correctAnswer);
          
          process.stdout.write(`✓ Q${i} `);
          if (i % 10 === 0) console.log('');
        } else {
          console.log(`\n❌ Q${i}: Réponse invalide`);
        }

        // Délai pour ne pas surcharger l'API
        await new Promise(resolve => setTimeout(resolve, 100));

      } catch (error) {
        console.log(`\n❌ Q${i}: Erreur - ${error.message}`);
      }
    }

    console.log(`\n\n📊 Fin Manche ${round}: ${roundAnswers.size}/${QUESTIONS_PER_ROUND} questions générées`);
    console.log(`   Réponses uniques accumulées: ${allUsedAnswers.length}`);
  }

  // RAPPORT FINAL
  console.log('\n\n');
  console.log('╔════════════════════════════════════════════════════════════════════════════╗');
  console.log('║                        📊 RAPPORT FINAL                                    ║');
  console.log('╚════════════════════════════════════════════════════════════════════════════╝\n');

  console.log(`🎯 STATISTIQUES GLOBALES`);
  console.log('─'.repeat(80));
  console.log(`   Questions totales:        ${totalQuestions}`);
  console.log(`   Questions réussies:       ${successfulQuestions} (${((successfulQuestions/totalQuestions)*100).toFixed(1)}%)`);
  console.log(`   Réponses accumulées:      ${allUsedAnswers.length}`);
  console.log(`   Doublons littéraux:       ${duplicates.literal.length} ❌`);
  console.log(`   Doublons sémantiques:     ${duplicates.semantic.length} ⚡`);

  if (duplicates.literal.length === 0 && duplicates.semantic.length === 0) {
    console.log('\n✅ ✅ ✅ SUCCÈS TOTAL - AUCUN DOUBLON DÉTECTÉ ! ✅ ✅ ✅\n');
  } else {
    console.log('\n⚠️ DOUBLONS DÉTECTÉS - VOIR DÉTAILS CI-DESSOUS\n');
    
    if (duplicates.literal.length > 0) {
      console.log('\n❌ DOUBLONS LITTÉRAUX:');
      console.log('─'.repeat(80));
      duplicates.literal.forEach((d, idx) => {
        console.log(`${idx + 1}. Manche ${d.round}, Q${d.questionNum}:`);
        console.log(`   Question: ${d.question}`);
        console.log(`   Réponse: "${d.answer}" (déjà vue en manche ${d.previousRound})\n`);
      });
    }

    if (duplicates.semantic.length > 0) {
      console.log('\n⚡ DOUBLONS SÉMANTIQUES:');
      console.log('─'.repeat(80));
      duplicates.semantic.forEach((d, idx) => {
        console.log(`${idx + 1}. Manche ${d.round}, Q${d.questionNum}:`);
        console.log(`   Question: ${d.question}`);
        console.log(`   Réponse: "${d.answer}" ≈ "${d.similarTo}"\n`);
      });
    }
  }

  console.log('\n' + '═'.repeat(80));
  console.log(`TEST TERMINÉ - ${successfulQuestions}/${totalQuestions} questions générées avec succès`);
  console.log('═'.repeat(80) + '\n');
}

testMatchSimulation().catch(console.error);
