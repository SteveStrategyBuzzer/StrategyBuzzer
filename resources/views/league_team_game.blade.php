@extends('layouts.app')

@section('content')
<div class="league-team-game-container">
    <div class="game-header">
        <div class="round-indicator">
            Round <span id="currentRound">1</span>/3
        </div>
        <div class="question-counter">
            Question <span id="currentQuestion">1</span>/10
        </div>
        <div class="division-badge {{ $match->team1->division }}">
            {{ ucfirst($match->team1->division) }}
        </div>
    </div>

    <div class="teams-display">
        <div class="team-panel team1-panel">
            <div class="team-header">
                <h3>{{ $match->team1->name }}</h3>
                <span class="team-tag">[{{ $match->team1->tag }}]</span>
            </div>
            <div class="team-score" id="team1Score">0</div>
            <div class="rounds-won">Manches: <span id="team1RoundsWon">0</span></div>
            <div class="team-members">
                @foreach($match->team1->teamMembers as $member)
                    <div class="member-mini {{ $member->user_id === Auth::id() ? 'current-user' : '' }}" data-user-id="{{ $member->user_id }}">
                        <div class="member-avatar-mini">
                            @if($member->user->avatar_url)
                                <img src="{{ $member->user->avatar_url }}" alt="">
                            @else
                                <div class="avatar-letter">{{ substr($member->user->name, 0, 1) }}</div>
                            @endif
                        </div>
                        <div class="member-name-mini">{{ $member->user->name }}</div>
                        <div class="member-score" id="score-{{ $member->user_id }}">0</div>
                        <div class="buzz-indicator" id="buzz-{{ $member->user_id }}"></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="vs-divider">VS</div>

        <div class="team-panel team2-panel">
            <div class="team-header">
                <h3>{{ $match->team2->name }}</h3>
                <span class="team-tag">[{{ $match->team2->tag }}]</span>
            </div>
            <div class="team-score" id="team2Score">0</div>
            <div class="rounds-won">Manches: <span id="team2RoundsWon">0</span></div>
            <div class="team-members">
                @foreach($match->team2->teamMembers as $member)
                    <div class="member-mini" data-user-id="{{ $member->user_id }}">
                        <div class="member-avatar-mini">
                            @if($member->user->avatar_url)
                                <img src="{{ $member->user->avatar_url }}" alt="">
                            @else
                                <div class="avatar-letter">{{ substr($member->user->name, 0, 1) }}</div>
                            @endif
                        </div>
                        <div class="member-name-mini">{{ $member->user->name }}</div>
                        <div class="member-score" id="score-{{ $member->user_id }}">0</div>
                        <div class="buzz-indicator" id="buzz-{{ $member->user_id }}"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="question-section" id="questionSection">
        <div class="question-text" id="questionText">
            Chargement de la question...
        </div>

        <div class="buzz-button-container buzzer-waiting" id="buzzContainer">
            <button id="buzzButton" class="buzz-button" disabled>
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
            <div class="round-scores-team">
                <div class="team-round-score">
                    <span class="team-name">{{ $match->team1->name }}</span>
                    <span class="round-score" id="team1RoundScore">0</span>
                </div>
                <div class="vs-text">VS</div>
                <div class="team-round-score">
                    <span class="team-name">{{ $match->team2->name }}</span>
                    <span class="round-score" id="team2RoundScore">0</span>
                </div>
            </div>
            <button id="nextRoundBtn" class="btn-continue">CONTINUER</button>
        </div>
    </div>
</div>

<style>
.league-team-game-container {
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
    padding: 8px 16px;
    border-radius: 20px;
    text-transform: uppercase;
}

.division-badge.bronze { background: linear-gradient(135deg, #CD7F32, #8B4513); }
.division-badge.argent { background: linear-gradient(135deg, #C0C0C0, #808080); }
.division-badge.or { background: linear-gradient(135deg, #FFD700, #FFA500); }
.division-badge.platine { background: linear-gradient(135deg, #E5E4E2, #B0B0B0); }
.division-badge.diamant { background: linear-gradient(135deg, #B9F2FF, #00CED1); }
.division-badge.legende { background: linear-gradient(135deg, #FF00FF, #8B008B); }

.teams-display {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 30px;
}

.team-panel {
    flex: 1;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    color: white;
}

.team1-panel {
    border: 3px solid #00d4ff;
}

.team2-panel {
    border: 3px solid #ff4757;
}

.team-header {
    text-align: center;
    margin-bottom: 15px;
}

.team-header h3 {
    margin: 0;
    font-size: 24px;
}

.team-tag {
    color: #ffd700;
    font-size: 16px;
}

.team-score {
    text-align: center;
    font-size: 48px;
    font-weight: bold;
    margin: 10px 0;
}

.rounds-won {
    text-align: center;
    font-size: 18px;
    margin-bottom: 20px;
}

.team-members {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.member-mini {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    position: relative;
}

.member-mini.current-user {
    background: rgba(0, 212, 255, 0.3);
    border: 2px solid #00d4ff;
}

.member-avatar-mini {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.member-avatar-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-letter {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #667eea);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.member-name-mini {
    flex: 1;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.member-score {
    font-weight: bold;
    font-size: 16px;
    min-width: 30px;
    text-align: right;
}

.buzz-indicator {
    position: absolute;
    right: -10px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border-radius: 50%;
}

.buzz-indicator.buzzed {
    background: #ffd700;
    animation: pulse 0.5s ease-in-out;
}

.vs-divider {
    font-size: 36px;
    font-weight: bold;
    color: white;
    display: flex;
    align-items: center;
}

.question-section {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 30px;
    margin: 20px auto;
    max-width: 900px;
}

.question-text {
    font-size: 24px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 30px;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1a1a2e;
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
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    border: 8px solid #fff;
    box-shadow: 0 10px 30px rgba(255, 107, 107, 0.5);
    cursor: pointer;
    transition: all 0.3s;
    font-size: 32px;
    font-weight: bold;
    color: white;
}

.buzz-button:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 15px 40px rgba(255, 107, 107, 0.7);
}

.buzz-button:active:not(:disabled) {
    transform: scale(0.95);
}

.buzz-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Buzzer States */
.buzz-button-container.buzzer-waiting .buzz-button {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
    filter: grayscale(0.5);
}

.buzz-button-container.buzzer-ready .buzz-button {
    opacity: 1;
    cursor: pointer;
    pointer-events: auto;
    animation: buzzer-pulse 1.5s ease-in-out infinite;
}

@keyframes buzzer-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.03); }
}

.buzz-button-container.buzzer-hidden {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}

.answers-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.answer-btn {
    padding: 20px;
    font-size: 18px;
    font-weight: bold;
    border: 3px solid #667eea;
    border-radius: 12px;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
    min-height: 80px;
}

.answer-btn:hover:not(:disabled) {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
}

.answer-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.answer-btn.correct {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.answer-btn.incorrect {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.answer-result {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 40px;
    border-radius: 20px;
    text-align: center;
    z-index: 1000;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.result-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.result-text {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 15px;
}

.points-earned {
    font-size: 32px;
    font-weight: bold;
    color: #667eea;
}

.round-result-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.modal-content {
    background: white;
    padding: 40px;
    border-radius: 20px;
    text-align: center;
    max-width: 500px;
    width: 90%;
}

.round-scores-team {
    display: flex;
    justify-content: space-around;
    align-items: center;
    margin: 30px 0;
    gap: 20px;
}

.team-round-score {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.team-round-score .team-name {
    font-weight: bold;
    font-size: 18px;
}

.round-score {
    font-size: 48px;
    font-weight: bold;
    color: #667eea;
}

.vs-text {
    font-size: 24px;
    font-weight: bold;
    color: #999;
}

.btn-continue {
    padding: 15px 40px;
    font-size: 20px;
    font-weight: bold;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
}

@keyframes pulse {
    0%, 100% { transform: translateY(-50%) scale(1); }
    50% { transform: translateY(-50%) scale(1.3); }
}

@media (max-width: 768px) {
    .teams-display {
        flex-direction: column;
    }
    
    .vs-divider {
        align-self: center;
        margin: 10px 0;
    }
    
    .buzz-button {
        width: 150px;
        height: 150px;
        font-size: 24px;
    }
}
</style>

<script>
const matchId = {{ $match->id }};
const userId = {{ Auth::id() }};
let questionStartTime;
let buzzAllowed = false;
let alreadyBuzzed = false;

async function loadQuestion() {
    try {
        const response = await fetch(`/api/league/team/match/${matchId}/question`, {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            }
        });

        const data = await response.json();

        if (data.question) {
            document.getElementById('questionText').textContent = data.question;
            document.getElementById('currentQuestion').textContent = data.question_number;
            document.getElementById('currentRound').textContent = data.round;

            document.getElementById('answerA').textContent = data.answers.A;
            document.getElementById('answerB').textContent = data.answers.B;
            document.getElementById('answerC').textContent = data.answers.C;
            document.getElementById('answerD').textContent = data.answers.D;

            questionStartTime = Date.now();
            buzzAllowed = true;
            alreadyBuzzed = false;
            
            document.getElementById('buzzButton').disabled = false;
        } else {
            showMatchResults();
        }
    } catch (error) {
        console.error('Error loading question:', error);
    }
}

document.getElementById('buzzButton')?.addEventListener('click', async () => {
    if (!buzzAllowed || alreadyBuzzed) return;

    alreadyBuzzed = true;
    buzzAllowed = false;
    const buzzTime = (Date.now() - questionStartTime) / 1000;

    document.getElementById('buzzButton').disabled = true;

    try {
        const response = await fetch(`/api/league/team/match/${matchId}/buzz`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            },
            body: JSON.stringify({ buzz_time: buzzTime })
        });

        const data = await response.json();

        if (data.can_answer) {
            document.getElementById('answersGrid').style.display = 'grid';
            document.getElementById('buzzButton').style.display = 'none';
        }

        updateBuzzIndicator(userId);
    } catch (error) {
        console.error('Error buzzing:', error);
    }
});

function updateBuzzIndicator(playerId) {
    const indicator = document.getElementById(`buzz-${playerId}`);
    if (indicator) {
        indicator.classList.add('buzzed');
    }
}

document.querySelectorAll('.answer-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const answer = btn.dataset.answer;
        
        document.querySelectorAll('.answer-btn').forEach(b => b.disabled = true);

        try {
            const response = await fetch(`/api/league/team/match/${matchId}/submit-answer`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token')
                },
                body: JSON.stringify({ answer })
            });

            const data = await response.json();

            if (data.correct) {
                btn.classList.add('correct');
                showResult('✓', 'Bonne réponse !', `+${data.points_awarded} points`);
            } else {
                btn.classList.add('incorrect');
                showResult('✗', 'Mauvaise réponse', `${data.points_awarded} points`);
            }

            updateScores(data.game_state);

            setTimeout(() => {
                hideResult();
                resetQuestion();
                
                if (data.is_round_over && data.round_result) {
                    showRoundResult(data.round_result);
                } else {
                    loadQuestion();
                }
            }, 2000);

        } catch (error) {
            console.error('Error submitting answer:', error);
        }
    });
});

function showResult(icon, text, points) {
    document.getElementById('resultIcon').textContent = icon;
    document.getElementById('resultText').textContent = text;
    document.getElementById('pointsEarned').textContent = points;
    document.getElementById('answerResult').style.display = 'block';
    
    // Jouer le son approprié
    if (icon === '✓') {
        const correctSound = document.getElementById('correctSound');
        if (correctSound) {
            correctSound.currentTime = 0;
            correctSound.play().catch(e => console.log('Audio play failed:', e));
        }
    } else if (icon === '✗') {
        const incorrectSound = document.getElementById('incorrectSound');
        if (incorrectSound) {
            incorrectSound.currentTime = 0;
            incorrectSound.play().catch(e => console.log('Audio play failed:', e));
        }
    }
}

function hideResult() {
    document.getElementById('answerResult').style.display = 'none';
}

function resetQuestion() {
    document.getElementById('answersGrid').style.display = 'none';
    document.getElementById('buzzButton').style.display = 'block';
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('correct', 'incorrect');
    });
    document.querySelectorAll('.buzz-indicator').forEach(ind => ind.classList.remove('buzzed'));
}

function updateScores(gameState) {
    let team1Total = 0;
    let team2Total = 0;

    gameState.players.forEach(player => {
        const scoreEl = document.getElementById(`score-${player.id}`);
        if (scoreEl) {
            scoreEl.textContent = player.current_score || 0;
        }

        if (player.team_index === 1) {
            team1Total += player.current_score || 0;
        } else {
            team2Total += player.current_score || 0;
        }
    });

    document.getElementById('team1Score').textContent = team1Total;
    document.getElementById('team2Score').textContent = team2Total;
    document.getElementById('team1RoundsWon').textContent = gameState.team1_rounds_won || 0;
    document.getElementById('team2RoundsWon').textContent = gameState.team2_rounds_won || 0;
}

function showRoundResult(roundResult) {
    document.getElementById('team1RoundScore').textContent = roundResult.team1_score;
    document.getElementById('team2RoundScore').textContent = roundResult.team2_score;
    
    if (roundResult.match_over) {
        document.getElementById('roundResultTitle').textContent = roundResult.winner_team === 1 ? 'VICTOIRE !' : 'DÉFAITE';
        document.getElementById('nextRoundBtn').textContent = 'VOIR RÉSULTATS';
    } else {
        document.getElementById('roundResultTitle').textContent = `Fin de la manche ${roundResult.round}`;
    }
    
    document.getElementById('roundResultModal').style.display = 'flex';
}

document.getElementById('nextRoundBtn')?.addEventListener('click', () => {
    const title = document.getElementById('roundResultTitle').textContent;
    if (title.includes('VICTOIRE') || title.includes('DÉFAITE')) {
        window.location.href = `/league/team/results/${matchId}`;
    } else {
        document.getElementById('roundResultModal').style.display = 'none';
        loadQuestion();
    }
});

function showMatchResults() {
    window.location.href = `/league/team/results/${matchId}`;
}

loadQuestion();
</script>

<!-- Audio pour les réponses -->
<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
</audio>
<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
</audio>

@endsection
