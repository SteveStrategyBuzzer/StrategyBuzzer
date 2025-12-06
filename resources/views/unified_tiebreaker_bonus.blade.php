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

    .tiebreaker-container {
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
        background: linear-gradient(135deg, #FFD700, #FFA500);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
    }

    .subtitle {
        font-size: 1.2rem;
        color: #4ECDC4;
    }

    .question-card {
        background: rgba(255,255,255,0.05);
        border: 3px solid rgba(102, 126, 234, 0.5);
        border-radius: 20px;
        padding: 40px;
        margin-bottom: 30px;
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
        border: 2px solid rgba(102, 126, 234, 0.3);
        border-radius: 15px;
        padding: 20px;
        color: #fff;
        font-size: 1.2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .answer-btn:hover:not(.disabled) {
        background: rgba(102, 126, 234, 0.3);
        border-color: #667eea;
        transform: translateY(-3px);
    }

    .answer-btn.selected {
        background: rgba(255, 215, 0, 0.3);
        border-color: #FFD700;
    }

    .answer-btn.correct {
        background: rgba(76, 175, 80, 0.3);
        border-color: #4CAF50;
    }

    .answer-btn.incorrect {
        background: rgba(244, 67, 54, 0.3);
        border-color: #f44336;
    }

    .answer-btn.disabled {
        pointer-events: none;
        opacity: 0.7;
    }

    .timer-container {
        margin-bottom: 20px;
    }

    .timer-display {
        font-size: 3rem;
        font-weight: 900;
        color: #FFD700;
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
        background: linear-gradient(90deg, #4CAF50, #FFD700, #f44336);
        transition: width 0.1s linear;
    }

    .buzz-btn {
        background: linear-gradient(135deg, #f44336, #e91e63);
        color: white;
        border: none;
        border-radius: 50%;
        width: 120px;
        height: 120px;
        font-size: 1.5rem;
        font-weight: 900;
        cursor: pointer;
        margin: 20px auto;
        display: block;
        transition: all 0.2s ease;
        box-shadow: 0 10px 30px rgba(244, 67, 54, 0.5);
    }

    .buzz-btn:hover:not(.disabled) {
        transform: scale(1.1);
        box-shadow: 0 15px 40px rgba(244, 67, 54, 0.7);
    }

    .buzz-btn.disabled {
        background: #666;
        cursor: not-allowed;
        box-shadow: none;
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
    }

    .player-status.buzzed {
        background: rgba(255, 215, 0, 0.2);
        border: 2px solid #FFD700;
    }

    .player-status.answered {
        background: rgba(76, 175, 80, 0.2);
        border: 2px solid #4CAF50;
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

    @media (max-width: 768px) {
        .title { font-size: 1.8rem; }
        .question-text { font-size: 1.3rem; }
        .answers-grid { grid-template-columns: 1fr; }
        .buzz-btn { width: 100px; height: 100px; font-size: 1.2rem; }
    }
</style>

<div class="tiebreaker-container">
    <div class="header-section">
        <h1 class="title">❓ {{ __('QUESTION BONUS DÉCISIVE') }}</h1>
        <p class="subtitle">{{ __('Le plus rapide avec la bonne réponse l\'emporte !') }}</p>
    </div>

    <div class="timer-container">
        <div class="timer-display" id="timerDisplay">15</div>
        <div class="timer-bar">
            <div class="timer-fill" id="timerFill" style="width: 100%;"></div>
        </div>
    </div>

    <button class="buzz-btn" id="buzzBtn" onclick="handleBuzz()">
        BUZZ!
    </button>

    <div class="question-card" id="questionCard" style="display: none;">
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
            <div class="status-text" id="playerStatusText">{{ __('En attente...') }}</div>
        </div>
        <div class="player-status" id="opponentStatus">
            <div class="status-icon">👤</div>
            <div class="status-name">{{ $opponent_name }}</div>
            <div class="status-text" id="opponentStatusText">{{ __('En attente...') }}</div>
        </div>
    </div>
    @endif
</div>

<form id="answerForm" action="{{ route('game.tiebreaker-bonus-answer', ['mode' => $mode]) }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="answer_index" id="answerIndexInput">
    <input type="hidden" name="buzz_time" id="buzzTimeInput">
</form>

<script>
const mode = "{{ $mode }}";
const matchId = "{{ $match_id ?? '' }}";
const isMultiplayer = {{ $is_multiplayer ? 'true' : 'false' }};
const correctIndex = {{ $question['correct_index'] ?? 0 }};

let timeLeft = 15;
let buzzTime = null;
let hasBuzzed = false;
let hasAnswered = false;
let timerInterval = null;

const i18n = {
    buzzed: "{{ __('A buzzé !') }}",
    answered: "{{ __('A répondu') }}",
    waiting: "{{ __('En attente...') }}",
    timeUp: "{{ __('Temps écoulé !') }}"
};

function startTimer() {
    timerInterval = setInterval(() => {
        timeLeft -= 0.1;
        document.getElementById('timerDisplay').textContent = Math.ceil(timeLeft);
        document.getElementById('timerFill').style.width = (timeLeft / 15 * 100) + '%';
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            if (!hasAnswered) {
                submitAnswer(-1);
            }
        }
    }, 100);
}

function handleBuzz() {
    if (hasBuzzed) return;
    
    hasBuzzed = true;
    buzzTime = 15 - timeLeft;
    
    document.getElementById('buzzBtn').classList.add('disabled');
    document.getElementById('buzzBtn').textContent = '✓ BUZZÉ';
    document.getElementById('questionCard').style.display = 'block';
    
    if (isMultiplayer) {
        document.getElementById('playerStatus').classList.add('buzzed');
        document.getElementById('playerStatusText').textContent = i18n.buzzed;
        syncBuzzToFirebase();
    }
}

function selectAnswer(index) {
    if (hasAnswered) return;
    
    hasAnswered = true;
    clearInterval(timerInterval);
    
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.classList.add('disabled');
    });
    
    const selectedBtn = document.querySelector(`[data-index="${index}"]`);
    selectedBtn.classList.add('selected');
    
    if (isMultiplayer) {
        document.getElementById('playerStatus').classList.add('answered');
        document.getElementById('playerStatusText').textContent = i18n.answered;
    }
    
    setTimeout(() => {
        submitAnswer(index);
    }, 500);
}

function submitAnswer(index) {
    document.getElementById('answerIndexInput').value = index;
    document.getElementById('buzzTimeInput').value = buzzTime || 15;
    
    if (isMultiplayer && matchId) {
        syncAnswerToFirebase(index, buzzTime);
    }
    
    document.getElementById('answerForm').submit();
}

function syncBuzzToFirebase() {
    if (typeof firebase !== 'undefined' && firebase.firestore && matchId) {
        const db = firebase.firestore();
        db.collection('matches').doc(matchId).update({
            [`tiebreaker_buzz_{{ auth()->id() }}`]: buzzTime,
            [`tiebreaker_buzzed_{{ auth()->id() }}`]: true
        }).catch(err => console.error('Firebase sync error:', err));
    }
}

function syncAnswerToFirebase(index, time) {
    if (typeof firebase !== 'undefined' && firebase.firestore && matchId) {
        const db = firebase.firestore();
        db.collection('matches').doc(matchId).update({
            [`tiebreaker_answer_{{ auth()->id() }}`]: index,
            [`tiebreaker_time_{{ auth()->id() }}`]: time,
            [`tiebreaker_correct_{{ auth()->id() }}`]: index === correctIndex
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
            if (data[`tiebreaker_buzzed_${opponentId}`]) {
                document.getElementById('opponentStatus').classList.add('buzzed');
                document.getElementById('opponentStatusText').textContent = i18n.buzzed;
            }
            if (data[`tiebreaker_answer_${opponentId}`] !== undefined) {
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
