@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        min-height: 100vh;
        padding: 20px;
        margin: 0;
    }

    .sudden-death-container {
        max-width: 900px;
        margin: 0 auto;
        text-align: center;
    }

    .header-section {
        margin-bottom: 30px;
    }

    .title {
        font-size: 2.5rem;
        font-weight: 900;
        background: linear-gradient(135deg, #f44336, #e91e63);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
        animation: pulse 1.5s ease-in-out infinite;
    }

    .question-counter {
        font-size: 1.2rem;
        color: #FFD700;
        font-weight: 700;
    }

    .warning-text {
        color: #f44336;
        font-weight: 600;
        margin-top: 10px;
        animation: blink 1s ease-in-out infinite;
    }

    .question-card {
        background: rgba(255,255,255,0.05);
        border: 3px solid rgba(244, 67, 54, 0.5);
        border-radius: 20px;
        padding: 40px;
        margin: 30px 0;
    }

    .question-text {
        font-size: 1.8rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.5;
        margin-bottom: 30px;
    }

    .answers-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .answer-btn {
        background: rgba(255,255,255,0.1);
        border: 2px solid rgba(244, 67, 54, 0.3);
        border-radius: 15px;
        padding: 20px;
        color: #fff;
        font-size: 1.2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .answer-btn:hover:not(.disabled) {
        background: rgba(244, 67, 54, 0.3);
        border-color: #f44336;
        transform: translateY(-3px);
    }

    .answer-btn.selected {
        background: rgba(255, 215, 0, 0.3);
        border-color: #FFD700;
    }

    .answer-btn.disabled {
        pointer-events: none;
        opacity: 0.7;
    }

    .timer-container {
        margin-bottom: 20px;
    }

    .timer-display {
        font-size: 4rem;
        font-weight: 900;
        color: #f44336;
    }

    .timer-display.warning {
        animation: pulse 0.5s ease-in-out infinite;
    }

    .timer-bar {
        width: 100%;
        height: 8px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        overflow: hidden;
        margin-top: 10px;
    }

    .timer-fill {
        height: 100%;
        background: linear-gradient(90deg, #f44336, #e91e63);
        transition: width 0.1s linear;
    }

    .players-status {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin-top: 30px;
    }

    .player-status {
        background: rgba(255,255,255,0.05);
        border-radius: 15px;
        padding: 15px 25px;
        text-align: center;
        min-width: 150px;
    }

    .player-status.answered {
        background: rgba(76, 175, 80, 0.2);
        border: 2px solid #4CAF50;
    }

    .player-status.eliminated {
        background: rgba(244, 67, 54, 0.2);
        border: 2px solid #f44336;
    }

    .status-icon {
        font-size: 2rem;
        margin-bottom: 5px;
    }

    .status-name {
        font-weight: 700;
        color: #fff;
    }

    .status-text {
        font-size: 0.9rem;
        color: #B0B0B0;
    }

    .lives-display {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 10px;
    }

    .life-heart {
        font-size: 1.5rem;
    }

    .life-heart.lost {
        opacity: 0.3;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
    }

    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    @media (max-width: 768px) {
        .title { font-size: 1.8rem; }
        .question-text { font-size: 1.3rem; }
        .answers-grid { grid-template-columns: 1fr; }
        .timer-display { font-size: 3rem; }
    }
</style>

<div class="sudden-death-container">
    <div class="header-section">
        <h1 class="title">💀 {{ __('SUDDEN DEATH') }} 💀</h1>
        <div class="question-counter">{{ __('Question') }} #{{ $question_number }}</div>
        <div class="warning-text">⚠️ {{ __('Première erreur = Élimination !') }}</div>
    </div>

    <div class="timer-container">
        <div class="timer-display" id="timerDisplay">10</div>
        <div class="timer-bar">
            <div class="timer-fill" id="timerFill" style="width: 100%;"></div>
        </div>
    </div>

    <div class="question-card">
        <div class="question-text">{{ $question['text'] ?? '' }}</div>
        
        <div class="answers-grid">
            @foreach($question['answers'] ?? [] as $index => $answer)
            <button class="answer-btn" data-index="{{ $index }}" onclick="selectAnswer({{ $index }})">
                {{ is_array($answer) ? ($answer['text'] ?? $answer) : $answer }}
            </button>
            @endforeach
        </div>
    </div>

    @if($is_multiplayer)
    <div class="players-status">
        <div class="player-status" id="playerStatus">
            <div class="status-icon">👤</div>
            <div class="status-name">{{ $player_name }}</div>
            <div class="status-text" id="playerStatusText">{{ __('En jeu') }}</div>
            <div class="lives-display">
                <span class="life-heart">❤️</span>
            </div>
        </div>
        <div class="player-status" id="opponentStatus">
            <div class="status-icon">👤</div>
            <div class="status-name">{{ $opponent_name }}</div>
            <div class="status-text" id="opponentStatusText">{{ __('En jeu') }}</div>
            <div class="lives-display">
                <span class="life-heart">❤️</span>
            </div>
        </div>
    </div>
    @endif
</div>

<form id="answerForm" action="{{ route('game.tiebreaker-sudden-death-answer', ['mode' => $mode]) }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="answer_index" id="answerIndexInput">
    <input type="hidden" name="buzz_time" id="buzzTimeInput">
</form>

<script>
const mode = "{{ $mode }}";
const matchId = "{{ $match_id ?? '' }}";
const isMultiplayer = {{ $is_multiplayer ? 'true' : 'false' }};
const correctIndex = {{ $question['correct_index'] ?? 0 }};
const questionNumber = {{ $question_number }};

let timeLeft = 10;
let hasAnswered = false;
let timerInterval = null;

const i18n = {
    answered: "{{ __('A répondu') }}",
    inGame: "{{ __('En jeu') }}",
    eliminated: "{{ __('ÉLIMINÉ !') }}",
    timeUp: "{{ __('Temps écoulé !') }}"
};

function startTimer() {
    timerInterval = setInterval(() => {
        timeLeft -= 0.1;
        const display = document.getElementById('timerDisplay');
        display.textContent = Math.ceil(timeLeft);
        document.getElementById('timerFill').style.width = (timeLeft / 10 * 100) + '%';
        
        if (timeLeft <= 3) {
            display.classList.add('warning');
        }
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            if (!hasAnswered) {
                submitAnswer(-1);
            }
        }
    }, 100);
}

function selectAnswer(index) {
    if (hasAnswered) return;
    
    hasAnswered = true;
    clearInterval(timerInterval);
    
    const buzzTime = 10 - timeLeft;
    
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.classList.add('disabled');
    });
    
    const selectedBtn = document.querySelector(`[data-index="${index}"]`);
    selectedBtn.classList.add('selected');
    
    if (isMultiplayer) {
        document.getElementById('playerStatus').classList.add('answered');
        document.getElementById('playerStatusText').textContent = i18n.answered;
        syncAnswerToFirebase(index, buzzTime);
    }
    
    setTimeout(() => {
        document.getElementById('answerIndexInput').value = index;
        document.getElementById('buzzTimeInput').value = buzzTime;
        document.getElementById('answerForm').submit();
    }, 500);
}

function submitAnswer(index) {
    document.getElementById('answerIndexInput').value = index;
    document.getElementById('buzzTimeInput').value = 10;
    document.getElementById('answerForm').submit();
}

function syncAnswerToFirebase(index, time) {
    if (typeof firebase !== 'undefined' && firebase.firestore && matchId) {
        const db = firebase.firestore();
        db.collection('matches').doc(matchId).update({
            [`sudden_death_answer_{{ auth()->id() }}_q${questionNumber}`]: index,
            [`sudden_death_correct_{{ auth()->id() }}_q${questionNumber}`]: index === correctIndex,
            [`sudden_death_time_{{ auth()->id() }}_q${questionNumber}`]: time
        }).catch(err => console.error('Firebase sync error:', err));
    }
}

@if($is_multiplayer)
if (typeof firebase !== 'undefined' && firebase.firestore && matchId) {
    const db = firebase.firestore();
    const opponentId = "{{ $gameState['opponent_id'] ?? '' }}";
    
    db.collection('matches').doc(matchId).onSnapshot(doc => {
        const data = doc.data();
        if (data) {
            const opponentAnswerKey = `sudden_death_answer_${opponentId}_q${questionNumber}`;
            if (data[opponentAnswerKey] !== undefined) {
                document.getElementById('opponentStatus').classList.add('answered');
                document.getElementById('opponentStatusText').textContent = i18n.answered;
            }
        }
    });
}
@endif

startTimer();
</script>
@endsection
