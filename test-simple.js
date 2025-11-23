const http = require('http');

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
          const result = JSON.parse(body);
          resolve(result);
        } catch (e) {
          console.error('Parse error:', e.message);
          console.error('Body:', body.substring(0, 500));
          reject(e);
        }
      });
    });

    req.on('error', (e) => {
      console.error('Request error:', e.message);
      reject(e);
    });
    
    req.write(postData);
    req.end();
  });
}

async function test() {
  console.log('Testing single question generation...\n');
  
  try {
    const response = await makeRequest({
      theme: 'general',
      difficulty: 'medium',
      questionNumber: 1,
      usedQuestionIds: [],
      usedAnswers: [],
      questionType: 'multiple',
      language: 'fr'
    });
    
    console.log('Response:', JSON.stringify(response, null, 2));
    console.log('\nSuccess:', response.success);
    
    if (response.question) {
      console.log('\nQuestion:', response.question.text);
      console.log('Answer:', response.question.answers[response.question.correct_index]);
    }
  } catch (error) {
    console.error('Error:', error.message);
  }
}

test();
