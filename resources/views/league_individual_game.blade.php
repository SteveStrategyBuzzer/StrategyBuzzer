@extends('layouts.app')

@section('content')
<div class="league-game-container">
    <div class="game-header">
        <div class="round-indicator">
            Round <span id="currentRound">1</span>/3
        </div>
        <div class="question-counter">
            Question <span id="currentQuestion">1</span>/10
        </div>
        <div class="division-badge">{{ ucfirst(Auth::user()->getDivisionForMode('league_individual')->division ?? 'Bronze') }}</div>
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

    <div class="round-result-modal" id="roundResultModal" style="display: none;">
        <div class="modal-content">
            <h2 id="roundResultTitle">Fin de la manche</h2>
            <div class="round-scores">
                <div class="player-round-score">
                    <span class="player-name">Vous</span>
                    <span class="round-score" id="playerRoundScore">0</span>
                </div>
                <div class="vs-divider">VS</div>
                <div class="opponent-round-score">
                    <span class="player-name" id="opponentNameResult">Adversaire</span>
                    <span class="round-score" id="opponentRoundScore">0</span>
                </div>
            </div>
            <button id="nextRoundBtn" class="btn-continue">CONTINUER</button>
        </div>
    </div>
</div>

<style>
.league-game-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.game-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    margin-bottom: 20px;
    font-size: 1.2em;
    font-weight: bold;
}

.division-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 20px;
    text-transform: uppercase;
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
    font-size: 0.95em;
}

.buzz-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ddd;
    transition: all 0.3s;
}

.buzz-indicator.active {
    background: #4CAF50;
    box-shadow: 0 0 15px #4CAF50;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.question-section {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
}

.question-text {
    font-size: 1.8em;
    color: #1a1a1a;
    margin-bottom: 40px;
    line-height: 1.4;
    font-weight: 500;
}

.buzz-button-container {
    display: flex;
    justify-content: center;
    margin: 30px 0;
}

.buzz-button {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    color: white;
    font-size: 2.5em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
}

.buzz-button:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 15px 40px rgba(255, 107, 107, 0.6);
}

.buzz-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.answers-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 30px;
}

.answer-btn {
    padding: 20px;
    background: #f5f5f5;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 1.1em;
    cursor: pointer;
    transition: all 0.3s;
    text-align: left;
}

.answer-btn:hover:not(:disabled) {
    background: #667eea;
    color: white;
    border-color: #667eea;
    transform: translateY(-2px);
}

.answer-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.answer-btn.correct {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
}

.answer-btn.incorrect {
    background: #f44336;
    color: white;
    border-color: #f44336;
}

.answer-result {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 600px;
    margin: 30px auto 0;
    text-align: center;
}

.result-icon {
    font-size: 5em;
    margin-bottom: 20px;
}

.result-text {
    font-size: 1.5em;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.points-earned {
    font-size: 1.2em;
    color: #667eea;
    font-weight: bold;
}

.round-result-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    max-width: 500px;
    width: 90%;
}

.modal-content h2 {
    font-size: 2em;
    color: #1a1a1a;
    margin-bottom: 30px;
}

.round-scores {
    display: flex;
    align-items: center;
    justify-content: space-around;
    margin-bottom: 30px;
}

.player-round-score, .opponent-round-score {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.player-round-score .player-name,
.opponent-round-score .player-name {
    font-size: 1.1em;
    color: #666;
}

.round-score {
    font-size: 3em;
    font-weight: bold;
    color: #667eea;
}

.vs-divider {
    font-size: 1.5em;
    font-weight: bold;
    color: #999;
}

.btn-continue {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.2em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
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
        font-size: 2em;
    }

    .answers-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
const matchId = {{ $match->id }};
let gameState = null;
let opponent = null;
let buzzed = false;
let questionStartTime = null;

async function loadGameState() {
    try {
        const response = await fetch(`/api/league/individual/match/${matchId}/game-state`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            gameState = data.game_state;
            opponent = data.opponent;
            updateUI();
        }
    } catch (error) {
        console.error('Error loading game state:', error);
    }
}

function updateUI() {
    if (!gameState) return;

    document.getElementById('currentRound').textContent = gameState.current_round;
    document.getElementById('currentQuestion').textContent = gameState.current_question_index + 1;

    document.getElementById('opponentName').textContent = opponent.name;
    document.getElementById('opponentAvatarLetter').textContent = opponent.name.charAt(0).toUpperCase();
    document.getElementById('opponentNameResult').textContent = opponent.name;

    const playerScore = gameState.player_scores_map?.player ?? 0;
    const opponentScore = gameState.player_scores_map?.opponent ?? 0;
    document.getElementById('playerScore').textContent = playerScore;
    document.getElementById('opponentScore').textContent = opponentScore;

    const playerRoundsWon = gameState.player_rounds_won_map?.player ?? 0;
    const opponentRoundsWon = gameState.player_rounds_won_map?.opponent ?? 0;
    document.getElementById('playerRoundsWon').textContent = playerRoundsWon;
    document.getElementById('opponentRoundsWon').textContent = opponentRoundsWon;

    if (gameState.current_question) {
        displayQuestion(gameState.current_question);
    }
}

function displayQuestion(question) {
    document.getElementById('questionText').textContent = question.text;
    buzzed = false;
    questionStartTime = Date.now();
    
    document.getElementById('buzzButton').disabled = false;
    document.getElementById('buzzButton').style.display = 'block';
    document.getElementById('answersGrid').style.display = 'none';
    document.getElementById('answerResult').style.display = 'none';
    
    document.getElementById('playerBuzzIndicator').classList.remove('active');
    document.getElementById('opponentBuzzIndicator').classList.remove('active');
    
    if (question.type === 'mcq') {
        const answerOptions = question.options || [];
        ['A', 'B', 'C', 'D'].forEach((letter, index) => {
            const btn = document.getElementById(`answer${letter}`);
            if (btn && answerOptions[index]) {
                btn.textContent = `${letter}. ${answerOptions[index]}`;
                btn.onclick = () => submitAnswer(answerOptions[index]);
                btn.disabled = false;
                btn.className = 'answer-btn';
            }
        });
    }
}

document.getElementById('buzzButton').addEventListener('click', async function() {
    if (buzzed) return;
    
    buzzed = true;
    this.disabled = true;
    
    document.getElementById('playerBuzzIndicator').classList.add('active');
    
    try {
        const response = await fetch(`/api/league/individual/match/${matchId}/buzz`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                client_time: questionStartTime
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            setTimeout(() => {
                this.style.display = 'none';
                document.getElementById('answersGrid').style.display = 'grid';
            }, 500);
        }
    } catch (error) {
        console.error('Error buzzing:', error);
        this.disabled = false;
        buzzed = false;
    }
});

async function submitAnswer(answer) {
    const answerButtons = document.querySelectorAll('.answer-btn');
    answerButtons.forEach(btn => btn.disabled = true);
    
    try {
        const response = await fetch(`/api/league/individual/match/${matchId}/submit-answer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ answer })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAnswerResult(data.isCorrect, data.points);
            
            setTimeout(() => {
                if (data.roundFinished) {
                    showRoundResult(data.roundResult);
                } else {
                    gameState = data.gameState;
                    updateUI();
                }
            }, 2000);
        }
    } catch (error) {
        console.error('Error submitting answer:', error);
    }
}

function showAnswerResult(isCorrect, points) {
    const resultDiv = document.getElementById('answerResult');
    const resultIcon = document.getElementById('resultIcon');
    const resultText = document.getElementById('resultText');
    const pointsEarned = document.getElementById('pointsEarned');
    
    if (isCorrect) {
        resultIcon.textContent = '✅';
        resultText.textContent = 'Bonne réponse !';
        resultText.style.color = '#4CAF50';
    } else {
        resultIcon.textContent = '❌';
        resultText.textContent = 'Mauvaise réponse';
        resultText.style.color = '#f44336';
    }
    
    pointsEarned.textContent = points > 0 ? `+${points} points` : `${points} points`;
    resultDiv.style.display = 'block';
}

function showRoundResult(roundResult) {
    const modal = document.getElementById('roundResultModal');
    const title = document.getElementById('roundResultTitle');
    const playerRoundScore = document.getElementById('playerRoundScore');
    const opponentRoundScore = document.getElementById('opponentRoundScore');
    
    if (roundResult.winner === 'player') {
        title.textContent = '🎉 Vous avez gagné cette manche !';
    } else if (roundResult.winner === 'opponent') {
        title.textContent = '😞 Vous avez perdu cette manche';
    } else {
        title.textContent = '🤝 Égalité - Nouvelle manche !';
    }
    
    playerRoundScore.textContent = roundResult.player_score || 0;
    opponentRoundScore.textContent = roundResult.opponent_score || 0;
    
    modal.style.display = 'flex';
}

document.getElementById('nextRoundBtn').addEventListener('click', async function() {
    const modal = document.getElementById('roundResultModal');
    modal.style.display = 'none';
    
    if (gameState && gameState.is_match_finished) {
        try {
            const response = await fetch(`/api/league/individual/match/${matchId}/finish`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = `/league/individual/results/${matchId}`;
            }
        } catch (error) {
            console.error('Error finishing match:', error);
        }
    } else {
        await loadGameState();
    }
});

loadGameState();
setInterval(loadGameState, 2000);
</script>
@endsection
