@extends('layouts.app')

@section('content')
<div class="duo-game-container">
    <div class="game-header">
        <div class="round-indicator">
            Round <span id="currentRound">1</span>/3
        </div>
        <div class="question-counter">
            Question <span id="currentQuestion">1</span>/10
        </div>
    </div>

    <div class="players-scores">
        <div class="player-score-card player-card">
            <div class="player-avatar">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="Vous">
                @else
                    <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                @endif
            </div>
            <div class="player-info">
                <h3>{{ Auth::user()->name }}</h3>
                <div class="score-display">
                    <span class="score-value" id="playerScore">0</span>
                    <span class="score-label">points</span>
                </div>
                <div class="rounds-won">
                    Manches: <span id="playerRoundsWon">0</span>
                </div>
            </div>
            <div class="buzz-indicator" id="playerBuzzIndicator"></div>
        </div>

        <div class="player-score-card opponent-card">
            <div class="player-avatar">
                <div class="default-avatar" id="opponentAvatarLetter">?</div>
            </div>
            <div class="player-info">
                <h3 id="opponentName">Adversaire</h3>
                <div class="score-display">
                    <span class="score-value" id="opponentScore">0</span>
                    <span class="score-label">points</span>
                </div>
                <div class="rounds-won">
                    Manches: <span id="opponentRoundsWon">0</span>
                </div>
            </div>
            <div class="buzz-indicator" id="opponentBuzzIndicator"></div>
        </div>
    </div>

    <div class="question-section" id="questionSection">
        <div class="question-text" id="questionText">
            Chargement de la question...
        </div>

        <div class="buzz-button-container">
            <button id="buzzButton" class="buzz-button">
                <span class="buzz-text">BUZZ</span>
            </button>
        </div>

        <div class="answers-grid" id="answersGrid" style="display: none;">
            <button class="answer-btn" data-answer="A" id="answerA"></button>
            <button class="answer-btn" data-answer="B" id="answerB"></button>
            <button class="answer-btn" data-answer="C" id="answerC"></button>
            <button class="answer-btn" data-answer="D" id="answerD"></button>
        </div>
    </div>

    <div class="answer-result" id="answerResult" style="display: none;">
        <div class="result-icon" id="resultIcon"></div>
        <div class="result-text" id="resultText"></div>
        <div class="points-earned" id="pointsEarned"></div>
    </div>
</div>

<style>
.duo-game-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.game-header {
    display: flex;
    justify-content: space-between;
    color: white;
    margin-bottom: 20px;
    font-size: 1.2em;
    font-weight: bold;
}

.players-scores {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.player-score-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
    position: relative;
}

.player-card {
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.opponent-card {
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
}

.player-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.player-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 1.5em;
    font-weight: bold;
}

.player-info {
    flex: 1;
}

.player-info h3 {
    margin: 0 0 5px 0;
    font-size: 1.1em;
    color: #1a1a1a;
}

.score-display {
    display: flex;
    align-items: baseline;
    gap: 5px;
}

.score-value {
    font-size: 2em;
    font-weight: bold;
    color: #667eea;
}

.score-label {
    color: #666;
    font-size: 0.9em;
}

.rounds-won {
    color: #666;
    font-size: 0.9em;
    margin-top: 5px;
}

.buzz-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    transition: all 0.3s;
}

.buzz-indicator.buzzed {
    background: #ffd700;
    animation: buzzPulse 0.5s;
}

@keyframes buzzPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.5); }
}

.question-section {
    background: white;
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    text-align: center;
}

.question-text {
    font-size: 1.8em;
    color: #1a1a1a;
    margin-bottom: 30px;
    line-height: 1.4;
}

.buzz-button-container {
    display: flex;
    justify-content: center;
    margin: 40px 0;
}

.buzz-button {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
    font-size: 2em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
}

.buzz-button:hover:not(:disabled) {
    transform: scale(1.1);
    box-shadow: 0 12px 30px rgba(255, 107, 107, 0.6);
}

.buzz-button:active:not(:disabled) {
    transform: scale(0.95);
}

.buzz-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.answers-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    max-width: 800px;
    margin: 0 auto;
}

.answer-btn {
    padding: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    background: white;
    font-size: 1.1em;
    cursor: pointer;
    transition: all 0.3s;
    text-align: left;
}

.answer-btn:hover:not(:disabled) {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.answer-btn.correct {
    background: #4caf50;
    color: white;
    border-color: #4caf50;
}

.answer-btn.incorrect {
    background: #f44336;
    color: white;
    border-color: #f44336;
}

.answer-btn:disabled {
    cursor: not-allowed;
}

.answer-result {
    text-align: center;
    color: white;
    padding: 30px;
}

.result-icon {
    font-size: 5em;
    margin-bottom: 20px;
}

.result-text {
    font-size: 2em;
    font-weight: bold;
    margin-bottom: 15px;
}

.points-earned {
    font-size: 1.5em;
}

@media (max-width: 768px) {
    .players-scores {
        grid-template-columns: 1fr;
    }
    
    .question-text {
        font-size: 1.3em;
    }
    
    .buzz-button {
        width: 150px;
        height: 150px;
        font-size: 1.5em;
    }
}
</style>

<script>
const matchId = {{ $match_id }};
const userId = {{ Auth::id() }};
let gameState = null;
let canBuzz = true;
let hasBuzzed = false;

function loadGameState() {
    fetch(`/duo/matches/${matchId}/game-state`)
        .then(response => response.json())
        .then(data => {
            gameState = data.game_state;
            updateUI();
        });
}

function updateUI() {
    if (!gameState) return;

    document.getElementById('currentRound').textContent = gameState.current_round;
    document.getElementById('currentQuestion').textContent = gameState.current_question_number;
    
    document.getElementById('playerScore').textContent = gameState.score || 0;
    document.getElementById('opponentScore').textContent = gameState.opponent_score || 0;
    
    document.getElementById('playerRoundsWon').textContent = gameState.player_rounds_won || 0;
    document.getElementById('opponentRoundsWon').textContent = gameState.opponent_rounds_won || 0;

    if (gameState.current_question) {
        document.getElementById('questionText').textContent = gameState.current_question.text;
        
        if (gameState.current_question.answers) {
            const answers = gameState.current_question.answers;
            document.getElementById('answerA').textContent = 'A. ' + answers[0];
            document.getElementById('answerB').textContent = 'B. ' + answers[1];
            document.getElementById('answerC').textContent = 'C. ' + answers[2];
            document.getElementById('answerD').textContent = 'D. ' + answers[3];
        }
    }
}

document.getElementById('buzzButton').addEventListener('click', function() {
    if (!canBuzz || hasBuzzed) return;
    
    hasBuzzed = true;
    this.disabled = true;
    
    fetch(`/duo/matches/${matchId}/buzz`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            client_time: Date.now() / 1000
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('playerBuzzIndicator').classList.add('buzzed');
            document.getElementById('answersGrid').style.display = 'grid';
            document.getElementById('buzzButton').style.display = 'none';
        }
    });
});

document.querySelectorAll('.answer-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const answer = this.dataset.answer;
        
        document.querySelectorAll('.answer-btn').forEach(b => b.disabled = true);
        
        fetch(`/duo/matches/${matchId}/submit-answer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ answer })
        })
        .then(response => response.json())
        .then(data => {
            showResult(data);
            
            setTimeout(() => {
                if (data.hasMoreQuestions) {
                    nextQuestion();
                } else if (data.roundFinished) {
                    if (data.gameState && (data.gameState.player_rounds_won >= 2 || data.gameState.opponent_rounds_won >= 2)) {
                        window.location.href = `/duo/result/${matchId}`;
                    } else {
                        nextRound();
                    }
                }
            }, 2000);
        });
    });
});

function showResult(data) {
    const resultSection = document.getElementById('answerResult');
    const icon = document.getElementById('resultIcon');
    const text = document.getElementById('resultText');
    const points = document.getElementById('pointsEarned');
    
    if (data.isCorrect) {
        icon.textContent = '✓';
        text.textContent = 'CORRECT !';
        points.textContent = '+' + data.points + ' points';
        // Jouer le son de bonne réponse
        const correctSound = document.getElementById('correctSound');
        if (correctSound) {
            correctSound.currentTime = 0;
            correctSound.play().catch(e => console.log('Audio play failed:', e));
        }
    } else {
        icon.textContent = '✗';
        text.textContent = 'INCORRECT';
        points.textContent = data.points + ' points';
        // Jouer le son de mauvaise réponse
        const incorrectSound = document.getElementById('incorrectSound');
        if (incorrectSound) {
            incorrectSound.currentTime = 0;
            incorrectSound.play().catch(e => console.log('Audio play failed:', e));
        }
    }
    
    document.getElementById('questionSection').style.display = 'none';
    resultSection.style.display = 'block';
}

function nextQuestion() {
    hasBuzzed = false;
    canBuzz = true;
    
    document.getElementById('answerResult').style.display = 'none';
    document.getElementById('questionSection').style.display = 'block';
    document.getElementById('answersGrid').style.display = 'none';
    document.getElementById('buzzButton').style.display = 'block';
    document.getElementById('buzzButton').disabled = false;
    
    document.getElementById('playerBuzzIndicator').classList.remove('buzzed');
    document.getElementById('opponentBuzzIndicator').classList.remove('buzzed');
    
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('correct', 'incorrect');
    });
    
    loadGameState();
}

function nextRound() {
    showToast('{{ __("Fin du round ! Round suivant...") }}', 'info');
    nextQuestion();
}

document.addEventListener('DOMContentLoaded', function() {
    loadGameState();
    setInterval(loadGameState, 3000);
});
</script>

<!-- Audio pour les réponses -->
<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
</audio>
<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
</audio>

@endsection
