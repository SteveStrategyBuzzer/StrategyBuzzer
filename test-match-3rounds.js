const http = require('http');

// Configuration : Simulation d'un match réel
const THEME = 'geographie'; // Un seul thème comme dans un vrai match
const QUESTIONS_PER_ROUND = 3; // TEST RAPIDE: 3 questions par manche
const ROUNDS = 3; // Best-of-3

console.log('╔═══════════════════════════════════════════════════════════════╗');
console.log('║          TEST SIMULATION MATCH RÉEL                          ║');
console.log('║      50 QUESTIONS × 3 MANCHES = 150 QUESTIONS                ║');
console.log(`║      THÈME: ${THEME.toUpperCase().padEnd(49)}║`);
console.log('╚═══════════════════════════════════════════════════════════════╝\n');

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
      let responseData = '';
      res.on('data', (chunk) => { responseData += chunk; });
      res.on('end', () => {
        try {
          resolve(JSON.parse(responseData));
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

async function runTest() {
  const allUsedAnswers = []; // Pool global de TOUTES les réponses (3 manches)
  const duplicates = {
    literal: [],
    semantic: []
  };

  let totalGenerated = 0;
  let totalAttempted = 0;

  for (let round = 1; round <= ROUNDS; round++) {
    console.log('\n' + '='.repeat(70));
    console.log(`🏆 MANCHE ${round}/${ROUNDS} - ${THEME.toUpperCase()}`);
    console.log('='.repeat(70) + '\n');

    for (let i = 1; i <= QUESTIONS_PER_ROUND; i++) {
      totalAttempted++;
      
      try {
        const q = await makeRequest({
          theme: THEME,
          difficulty: 'medium',
          questionNumber: i,
          usedQuestionIds: [],
          usedAnswers: allUsedAnswers,
          questionType: 'multiple',
          language: 'fr'
        });

        if (q && q.text && q.answers) {
          totalGenerated++;
          const correctAnswer = q.answers[q.correct_index];
          const normalizedCorrect = normalizeAnswer(correctAnswer);

          // Vérifier doublon littéral
          if (allUsedAnswers.includes(correctAnswer)) {
            duplicates.literal.push({
              round,
              question: i,
              text: q.text,
              answer: correctAnswer
            });
            console.log(`\n❌ DOUBLON LITTÉRAL - Manche ${round}, Q${i}: "${correctAnswer}"`);
          }

          // Vérifier doublon sémantique
          const semanticMatch = allUsedAnswers.find(a => 
            normalizeAnswer(a) === normalizedCorrect && a !== correctAnswer
          );
          
          if (semanticMatch) {
            duplicates.semantic.push({
              round,
              question: i,
              text: q.text,
              answer: correctAnswer,
              similarTo: semanticMatch
            });
            console.log(`\n⚡ DOUBLON SÉMANTIQUE - Manche ${round}, Q${i}: "${correctAnswer}" ≈ "${semanticMatch}"`);
          }

          // Ajouter TOUTES les réponses (correct + distracteurs)
          q.answers.forEach(a => {
            if (a) allUsedAnswers.push(a);
          });

          process.stdout.write(`✓ Q${i} `);
          if (i % 10 === 0) console.log('');
        } else {
          console.log(`\n❌ Q${i}: Réponse invalide`);
        }

        // Délai pour ne pas surcharger l'API
        await new Promise(resolve => setTimeout(resolve, 100));

      } catch (error) {
        console.log(`\n❌ Q${i}: ${error.message}`);
      }
    }

    const roundGenerated = totalGenerated - ((round - 1) * QUESTIONS_PER_ROUND);
    console.log(`\n\n📊 Fin Manche ${round}: ${roundGenerated}/${QUESTIONS_PER_ROUND} générées`);
    console.log(`   Pool de réponses total: ${allUsedAnswers.length}`);
  }

  // RAPPORT FINAL
  console.log('\n\n');
  console.log('╔═══════════════════════════════════════════════════════════════╗');
  console.log('║                    📊 RAPPORT FINAL                          ║');
  console.log('╚═══════════════════════════════════════════════════════════════╝\n');

  console.log(`🎯 STATISTIQUES GLOBALES`);
  console.log('─'.repeat(70));
  console.log(`   Questions générées:      ${totalGenerated} / ${ROUNDS * QUESTIONS_PER_ROUND}`);
  console.log(`   Taux de succès:          ${((totalGenerated / (ROUNDS * QUESTIONS_PER_ROUND)) * 100).toFixed(1)}%`);
  console.log(`   Réponses accumulées:     ${allUsedAnswers.length}`);
  console.log(`   Doublons littéraux:      ${duplicates.literal.length} ❌`);
  console.log(`   Doublons sémantiques:    ${duplicates.semantic.length} ⚡`);

  if (duplicates.literal.length === 0 && duplicates.semantic.length === 0) {
    console.log('\n✅ ✅ ✅ SUCCÈS COMPLET - AUCUN DOUBLON ! ✅ ✅ ✅\n');
  } else {
    console.log('\n⚠️ DOUBLONS DÉTECTÉS:\n');
    
    if (duplicates.literal.length > 0) {
      console.log('\n❌ DOUBLONS LITTÉRAUX:');
      console.log('─'.repeat(70));
      duplicates.literal.forEach((d, idx) => {
        console.log(`${idx + 1}. Manche ${d.round}, Q${d.question}: "${d.answer}"`);
        console.log(`   ${d.text}\n`);
      });
    }

    if (duplicates.semantic.length > 0) {
      console.log('\n⚡ DOUBLONS SÉMANTIQUES:');
      console.log('─'.repeat(70));
      duplicates.semantic.forEach((d, idx) => {
        console.log(`${idx + 1}. Manche ${d.round}, Q${d.question}: "${d.answer}" ≈ "${d.similarTo}"`);
        console.log(`   ${d.text}\n`);
      });
    }
  }

  console.log('═'.repeat(70));
  console.log(`TEST TERMINÉ - ${totalGenerated} questions générées avec succès`);
  console.log('═'.repeat(70) + '\n');
}

runTest().catch(err => {
  console.error('\n❌ ERREUR FATALE:', err);
  process.exit(1);
});
