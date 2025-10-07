@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #0a3d62 0%, #001F3F 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .answer-container {
        max-width: 600px;
        width: 100%;
    }
    
    .answer-header {
        background: rgba(0,0,0,0.3);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .answer-number {
        font-size: 1.2rem;
        font-weight: bold;
        color: #4ECDC4;
    }
    
    .answer-score {
        font-size: 1rem;
        opacity: 0.9;
    }
    
    .answers-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .answer-btn {
        padding: 20px;
        background: linear-gradient(135deg, #0074D9 0%, #0056a3 100%);
        border: 3px solid rgba(255,255,255,0.2);
        border-radius: 12px;
        color: white;
        font-size: 1.2rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .answer-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 116, 217, 0.4);
        border-color: rgba(255,255,255,0.4);
    }
    
    .answer-btn:disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }
    
    .answer-number-badge {
        width: 35px;
        height: 35px;
        background: rgba(255,255,255,0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    
    .chrono-bar-container {
        background: rgba(0,0,0,0.3);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .chrono-bar-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .chrono-bar-track {
        height: 12px;
        background: rgba(255,255,255,0.1);
        border-radius: 6px;
        overflow: hidden;
    }
    
    .chrono-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4 0%, #44A08D 100%);
        transition: width 1s linear;
        border-radius: 6px;
    }
    
    .buzz-info {
        text-align: center;
        font-size: 0.9rem;
        opacity: 0.7;
        margin-top: 10px;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    .chrono-warning .chrono-bar-fill {
        background: linear-gradient(90deg, #FF6B6B 0%, #EE5A6F 100%);
        animation: shake 0.5s infinite;
    }
</style>

<div class="answer-container">
    <!-- En-tête -->
    <div class="answer-header">
        <div class="answer-number">Réponses Q: {{ $params['current_question'] }}</div>
        <div class="answer-score">Score: {{ $params['score'] }} / {{ $params['current_question'] - 1 }}</div>
    </div>
    
    <!-- Chronomètre pour répondre -->
    <div class="chrono-bar-container" id="chronoContainer">
        <div class="chrono-bar-label">
            <span>⏱️ Temps pour répondre</span>
            <span id="chronoText">{{ $params['answer_time'] }}s</span>
        </div>
        <div class="chrono-bar-track">
            <div class="chrono-bar-fill" id="chronoBar"></div>
        </div>
    </div>
    
    <!-- Grille de réponses -->
    <form id="answerForm" method="POST" action="{{ route('solo.answer') }}">
        @csrf
        <div class="answers-grid">
            @php
                $question = $params['question'];
                $isTrueFalse = $question['type'] === 'true_false';
            @endphp
            
            @foreach($question['answers'] as $index => $answer)
                @if($isTrueFalse && $answer === null)
                    @continue
                @endif
                
                <button 
                    type="button" 
                    class="answer-btn" 
                    onclick="selectAnswer({{ $index }})"
                    data-index="{{ $index }}"
                >
                    <div class="answer-number-badge">{{ $index + 1 }}</div>
                    <div>{{ $answer }}</div>
                </button>
            @endforeach
        </div>
        
        <input type="hidden" name="answer_index" id="answerIndex">
    </form>
    
    <div class="buzz-info">
        @if(isset($params['buzz_time']))
            Vous avez buzzé en {{ $params['buzz_time'] }}s 🎯
        @endif
    </div>
</div>

<audio id="tickSound" preload="auto">
    <source src="{{ asset('sounds/tick.mp3') }}" type="audio/mpeg">
</audio>

<audio id="timeoutSound" preload="auto">
    <source src="{{ asset('sounds/timeout.mp3') }}" type="audio/mpeg">
</audio>

<script>
let timeLeft = {{ $params['answer_time'] }};
const totalTime = {{ $params['answer_time'] }};
let chronoInterval;
let answered = false;

// Démarrer le chronomètre
const chronoBar = document.getElementById('chronoBar');
chronoBar.style.width = '100%';

chronoInterval = setInterval(() => {
    timeLeft--;
    const percentage = (timeLeft / totalTime) * 100;
    chronoBar.style.width = percentage + '%';
    document.getElementById('chronoText').textContent = timeLeft + 's';
    
    // Avertissement visuel à 3 secondes
    if (timeLeft <= 3 && timeLeft > 0) {
        document.getElementById('chronoContainer').classList.add('chrono-warning');
        const tickSound = document.getElementById('tickSound');
        tickSound.play().catch(e => console.log('Audio play failed:', e));
    }
    
    // Temps écoulé
    if (timeLeft <= 0) {
        clearInterval(chronoInterval);
        handleTimeout();
    }
}, 1000);

function selectAnswer(index) {
    if (answered) return;
    
    answered = true;
    clearInterval(chronoInterval);
    
    // Marquer la réponse sélectionnée
    document.getElementById('answerIndex').value = index;
    
    // Désactiver tous les boutons
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.disabled = true;
    });
    
    // Soumettre le formulaire
    document.getElementById('answerForm').submit();
}

function handleTimeout() {
    if (answered) return;
    
    answered = true;
    
    // Jouer son de timeout
    const timeoutSound = document.getElementById('timeoutSound');
    timeoutSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver tous les boutons
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
    });
    
    // Rediriger vers les stats (timeout = échec)
    setTimeout(() => {
        window.location.href = "{{ route('solo.stat') }}";
    }, 2000);
}
</script>
@endsection
