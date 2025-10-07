@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .result-container {
        max-width: 600px;
        width: 100%;
        text-align: center;
    }
    
    .result-icon {
        font-size: 120px;
        margin-bottom: 30px;
        animation: scaleIn 0.5s ease-out;
    }
    
    .result-title {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 20px;
        animation: slideDown 0.6s ease-out;
    }
    
    .result-correct .result-title {
        color: #2ECC71;
    }
    
    .result-incorrect .result-title {
        color: #E74C3C;
    }
    
    .result-answers {
        background: rgba(0,0,0,0.3);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        animation: fadeIn 0.8s ease-out;
    }
    
    .answer-display {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 1.1rem;
    }
    
    .answer-display:last-child {
        margin-bottom: 0;
    }
    
    .answer-correct {
        background: rgba(46, 204, 113, 0.2);
        border: 2px solid #2ECC71;
    }
    
    .answer-incorrect {
        background: rgba(231, 76, 60, 0.2);
        border: 2px solid #E74C3C;
    }
    
    .answer-label {
        opacity: 0.8;
        font-size: 0.9rem;
        margin-right: auto;
    }
    
    .answer-icon {
        font-size: 1.5rem;
    }
    
    .result-score {
        font-size: 1.3rem;
        margin-bottom: 30px;
        opacity: 0.9;
    }
    
    .next-question-timer {
        background: rgba(255,255,255,0.1);
        padding: 20px;
        border-radius: 12px;
        font-size: 1.1rem;
    }
    
    .timer-count {
        font-size: 2rem;
        font-weight: bold;
        color: #4ECDC4;
        display: inline-block;
        margin: 0 5px;
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    @keyframes slideDown {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }
    
    .timer-count {
        animation: pulse 1s infinite;
    }
</style>

<div class="result-container">
    @if($params['is_correct'])
        <div class="result-correct">
            <div class="result-icon">✅</div>
            <h1 class="result-title">Bonne réponse !</h1>
        </div>
    @else
        <div class="result-incorrect">
            <div class="result-icon">❌</div>
            <h1 class="result-title">Mauvaise réponse</h1>
        </div>
    @endif
    
    <div class="result-answers">
        @php
            $question = $params['question'];
            $userAnswerIndex = $params['answer_index'];
            $correctIndex = $question['correct_index'];
            $isTimeout = $params['is_timeout'] ?? false;
        @endphp
        
        @if(!$params['is_correct'])
            <!-- Afficher la réponse incorrecte du joueur ou le timeout -->
            <div class="answer-display answer-incorrect">
                <span class="answer-label">Votre réponse:</span>
                @if($isTimeout)
                    <span>⏰ Temps écoulé - Pas de buzz</span>
                @else
                    <span>{{ $question['answers'][$userAnswerIndex] }}</span>
                @endif
                <span class="answer-icon">❌</span>
            </div>
        @endif
        
        <!-- Afficher la bonne réponse -->
        <div class="answer-display answer-correct">
            <span class="answer-label">Bonne réponse:</span>
            <span>{{ $question['answers'][$correctIndex] }}</span>
            <span class="answer-icon">✅</span>
        </div>
    </div>
    
    <div class="result-score">
        Score: {{ $params['score'] }} / {{ $params['current_question'] }}
    </div>
    
    <div class="next-question-timer">
        Prochaine question dans <span class="timer-count" id="countdown">3</span> secondes...
    </div>
</div>

<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
    <source src="{{ asset('sounds/correct.wav') }}" type="audio/wav">
</audio>

<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
    <source src="{{ asset('sounds/incorrect.wav') }}" type="audio/wav">
</audio>

<script>
// Jouer le son approprié
const isCorrect = {{ $params['is_correct'] ? 'true' : 'false' }};
if (isCorrect) {
    const correctSound = document.getElementById('correctSound');
    correctSound.play().catch(e => console.log('Audio play failed:', e));
} else {
    const incorrectSound = document.getElementById('incorrectSound');
    incorrectSound.play().catch(e => console.log('Audio play failed:', e));
}

// Compte à rebours de 3 secondes
let countdown = 3;
const countdownElement = document.getElementById('countdown');

const interval = setInterval(() => {
    countdown--;
    if (countdown > 0) {
        countdownElement.textContent = countdown;
    } else {
        clearInterval(interval);
        // Rediriger vers la prochaine question
        window.location.href = "{{ route('solo.next') }}";
    }
}, 1000);
</script>
@endsection
