<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>StrategyBuzzer — Solo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body{font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin:40px;}
    .card{max-width:680px;margin:auto;padding:24px;border:1px solid #ddd;border-radius:12px}
    .choices{display:grid;gap:12px;margin-top:16px}
    button.choice{padding:12px 16px;border-radius:10px;border:1px solid #ccc;cursor:pointer;text-align:left}
    .ok{background:#e6ffed;border-color:#34c759}
    .ko{background:#ffecec;border-color:#ff3b30}
  </style>
</head>
<body>
  <div class="card">
    <h1>Solo — Question</h1>
    <div id="q">Chargement…</div>
    <div class="choices" id="choices"></div>
    <div id="result" style="margin-top:16px;font-weight:600"></div>
    <div style="margin-top:20px">
      <button id="next">Question suivante</button>
      <a href="/menu" style="margin-left:12px">Retour menu</a>
    </div>
  </div>

<script>
const API = {
  next: '/api/solo/next',
  answer: '/api/solo/answer',
};

let current = null;

async function loadQuestion(){
  document.getElementById('result').textContent = '';
  document.getElementById('choices').innerHTML = '';
  document.getElementById('q').textContent = 'Chargement…';

  const r = await fetch(API.next);
  const data = await r.json();
  if(!data.ok){ document.getElementById('q').textContent = 'Erreur chargement'; return; }

  current = data.question;
  document.getElementById('q').textContent = current.text;

  current.choices.forEach((label, idx) => {
    const btn = document.createElement('button');
    btn.className = 'choice';
    btn.textContent = label;
    btn.onclick = () => submitAnswer(idx, btn);
    document.getElementById('choices').appendChild(btn);
  });
}

async function submitAnswer(idx, btn){
  if(!current) return;
  const r = await fetch(API.answer, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id: current.id, choice: idx})
  });
  const data = await r.json();
  const res = document.getElementById('result');
  if(data.ok){
    res.textContent = data.correct ? '✅ Bonne réponse!' : '❌ Mauvaise réponse.';
    const buttons = document.querySelectorAll('button.choice');
    buttons.forEach((b, i) => {
      b.classList.remove('ok','ko');
      if(i === data.correct_index) b.classList.add('ok');
      if(i === idx && !data.correct) b.classList.add('ko');
      b.disabled = true;
    });
  } else {
    res.textContent = 'Erreur validation.';
  }
}

document.getElementById('next').onclick = loadQuestion;
loadQuestion();
</script>
</body>
</html>
