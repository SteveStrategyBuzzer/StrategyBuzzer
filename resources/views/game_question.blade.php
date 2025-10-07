@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #001F3F 0%, #0a3d62 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .game-container {
        max-width: 500px;
        width: 100%;
        text-align: center;
    }
    
    .question-header {
        background: rgba(0,0,0,0.3);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .question-number {
        font-size: 1.1rem;
        color: #4ECDC4;
        margin-bottom: 10px;
    }
    
    .question-score {
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    .question-box {
        background: rgba(255,255,255,0.1);
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        min-height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .question-text {
        font-size: 1.4rem;
        font-weight: 500;
        line-height: 1.5;
    }
    
    .chrono-container {
        margin-bottom: 30px;
    }
    
    .chrono-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        position: relative;
        box-shadow: 0 8px 32px rgba(102, 126, 234, 0.4);
    }
    
    .chrono-time {
        font-size: 2.5rem;
        font-weight: bold;
    }
    
    .chrono-label {
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    .buzz-button {
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
        border: 8px solid rgba(255,255,255,0.3);
        color: white;
        font-size: 2.5rem;
        font-weight: bold;
        cursor: pointer;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        box-shadow: 0 10px 40px rgba(255, 107, 107, 0.5);
    }
    
    .buzz-button:hover:not(:disabled) {
        transform: scale(1.05);
        box-shadow: 0 15px 50px rgba(255, 107, 107, 0.7);
    }
    
    .buzz-button:active:not(:disabled) {
        transform: scale(0.95);
    }
    
    .buzz-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .buzz-icon {
        display: block;
        font-size: 3rem;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .chrono-warning {
        animation: pulse 0.5s infinite;
    }
</style>

<div class="game-container">
    <!-- En-tête -->
    <div class="question-header">
        <div class="question-number">Question {{ $params['current_question'] }} / {{ $params['total_questions'] }}</div>
        <div class="question-score">Pointage: {{ $params['score'] }} / {{ $params['current_question'] - 1 }}</div>
    </div>
    
    <!-- Question -->
    <div class="question-box">
        <div class="question-text">{{ $params['question']['text'] }}</div>
    </div>
    
    <!-- Chronomètre -->
    <div class="chrono-container">
        <div class="chrono-circle" id="chronoCircle">
            <div class="chrono-time" id="chronoTime">{{ $params['chrono_time'] }}</div>
        </div>
        <div class="chrono-label">Secondes pour buzzer</div>
    </div>
    
    <!-- Bouton BUZZ -->
    <form id="buzzForm" method="POST" action="{{ route('solo.buzz') }}">
        @csrf
        <button type="button" id="buzzButton" class="buzz-button" onclick="handleBuzz()">
            <span class="buzz-icon">🔔</span>
        </button>
    </form>
</div>

<audio id="buzzSound" preload="auto">
    <source src="{{ asset('sounds/buzz.mp3') }}" type="audio/mpeg">
    <source src="{{ asset('sounds/buzz.wav') }}" type="audio/wav">
</audio>

<audio id="failSound" preload="auto">
    <source src="{{ asset('sounds/fail.mp3') }}" type="audio/mpeg">
    <source src="{{ asset('sounds/fail.wav') }}" type="audio/wav">
</audio>

<script>
let timeLeft = {{ $params['chrono_time'] }};
let chronoInterval;
let buzzed = false;

// Démarrer le chronomètre
chronoInterval = setInterval(() => {
    timeLeft--;
    document.getElementById('chronoTime').textContent = timeLeft;
    
    // Avertissement visuel à 3 secondes
    if (timeLeft <= 3 && timeLeft > 0) {
        document.getElementById('chronoCircle').classList.add('chrono-warning');
    }
    
    // Temps écoulé
    if (timeLeft <= 0) {
        clearInterval(chronoInterval);
        handleTimeout();
    }
}, 1000);

function handleBuzz() {
    if (buzzed) return;
    
    buzzed = true;
    clearInterval(chronoInterval);
    
    // Jouer le son de buzz
    const buzzSound = document.getElementById('buzzSound');
    buzzSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver le bouton
    document.getElementById('buzzButton').disabled = true;
    
    // Soumettre le formulaire après un court délai pour entendre le son
    setTimeout(() => {
        document.getElementById('buzzForm').submit();
    }, 300);
}

function handleTimeout() {
    if (buzzed) return;
    
    buzzed = true;
    
    // Jouer le son d'échec
    const failSound = document.getElementById('failSound');
    failSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver le bouton
    const buzzButton = document.getElementById('buzzButton');
    buzzButton.disabled = true;
    buzzButton.style.background = 'linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%)';
    
    // Message d'échec
    document.getElementById('chronoTime').textContent = '0';
    
    // Rediriger vers écran de résultat (timeout) après 2 secondes
    setTimeout(() => {
        window.location.href = "{{ route('solo.timeout') }}";
    }, 2000);
}
</script>
@endsection
