document.addEventListener("DOMContentLoaded", () => {
  const buzzer = document.getElementById("buzzer");
  const answers = document.querySelectorAll(".answer");
  const timerDisplay = document.getElementById("countdown");
  const questionText = document.getElementById("question-text");
  const questionNumber = document.getElementById("question-number");
  const scoreDisplay = document.getElementById("score");
  const skillBtn = document.getElementById("skill-btn");

  let isActive = false;
  let score = 0;
  let currentQuestion = 1;
  let timer;
  let countdown = 10;

  const questions = [
    { q: "Quelle est la capitale de la France ?", a: ["Lyon", "Marseille", "Paris", "Nice"], correct: 2 },
    { q: "Combien font 7 x 8 ?", a: ["54", "56", "58", "52"], correct: 1 },
    { q: "Quel est l'animal le plus rapide ?", a: ["Lion", "Guépard", "Aigle", "Panthère"], correct: 1 }
  ];

  const audioBuzz = new Audio("/js/buzz.mp3");
  const audioFail = new Audio("/js/fail.mp3");
  const audioCorrect = new Audio("/js/correct.mp3");
  const audioWrong = new Audio("/js/wrong.mp3");

  function loadQuestion(index) {
    const q = questions[index];
    questionText.textContent = q.q;
    answers.forEach((btn, i) => {
      btn.textContent = q.a[i];
      btn.classList.remove("correct");
      btn.style.backgroundColor = "";
      btn.style.color = "";
      if (i === q.correct) btn.classList.add("correct");
    });
    buzzer.classList.remove("buzzer-active");
    buzzer.classList.add("buzzer-inactive");
    isActive = false;
    countdown = 10;
    timerDisplay.textContent = countdown;
    timer = setInterval(() => {
      countdown--;
      timerDisplay.textContent = countdown;
      if (countdown <= 0) {
        clearInterval(timer);
        buzzer.classList.remove("buzzer-inactive");
        buzzer.classList.add("buzzer-active");
        isActive = true;
      }
    }, 1000);
  }

  buzzer.addEventListener("click", () => {
    if (isActive) {
      audioBuzz.play();
    } else {
      audioFail.play();
    }
  });

  answers.forEach(btn => {
    btn.addEventListener("click", () => {
      if (!isActive) return;
      if (btn.classList.contains("correct")) {
        btn.style.backgroundColor = "green";
        btn.style.color = "white";
        audioCorrect.play();
        score += 10;
        scoreDisplay.textContent = score;
      } else {
        btn.style.backgroundColor = "red";
        btn.style.color = "white";
        audioWrong.play();
      }
      setTimeout(() => {
        currentQuestion++;
        if (currentQuestion > questions.length) {
          alert("Fin du quiz. Score : " + score);
          window.location.href = "/results";
        } else {
          questionNumber.textContent = currentQuestion;
          loadQuestion(currentQuestion - 1);
        }
      }, 1500);
    });
  });

  skillBtn.addEventListener("click", () => {
    countdown += 5;
    timerDisplay.textContent = countdown;
    skillBtn.disabled = true;
    skillBtn.textContent = "Utilisé";
  });

  // Charge première question
  loadQuestion(0);
});
