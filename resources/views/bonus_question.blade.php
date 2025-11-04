@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        margin: 0;
    }
    
    .bonus-container {
        max-width: 800px;
        width: 100%;
        text-align: center;
    }
    
    .bonus-header {
        background: rgba(255, 255, 255, 0.1);
        padding: 20px;
        border-radius: 20px;
        margin-bottom: 30px;
        border: 3px solid gold;
        box-shadow: 0 0 30px rgba(255, 215, 0, 0.5);
    }
    
    .bonus-title {
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 10px;
        color: gold;
        text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
    }
    
    .bonus-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
    }
    
    .timer-container {
        background: rgba(255, 255, 255, 0.1);
        padding: 15px;
        border-radius: 15px;
        margin-bottom: 30px;
    }
    
    .timer-label {
        font-size: 1rem;
        margin-bottom: 10px;
        opacity: 0.8;
    }
    
    .timer-bar-container {
        height: 12px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    
    .timer-bar {
        height: 100%;
        background: linear-gradient(90deg, gold 0%, #ff6b6b 100%);
        transition: width 1s linear;
        border-radius: 10px;
    }
    
    .timer-number {
        font-size: 3rem;
        font-weight: 900;
        margin-top: 10px;
        color: gold;
        text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
    }
    
    .question-box {
        background: rgba(255, 255, 255, 0.1);
        padding: 25px;
        border-radius: 20px;
        margin-bottom: 30px;
        border: 2px solid rgba(255, 215, 0, 0.3);
    }
    
    .question-text {
        font-size: 1.5rem;
        font-weight: 600;
        line-height: 1.5;
    }
    
    .answers-grid {
        display: grid;
        gap: 15px;
        margin-top: 20px;
    }
    
    .answer-btn {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        padding: 20px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: left;
    }
    
    .answer-btn:hover {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.3), rgba(255, 215, 0, 0.2));
        border-color: gold;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
    }
    
    .skip-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        padding: 15px 40px;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 30px;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 20px;
    }
    
    .skip-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
    }
    
    .score-display {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-bottom: 20px;
    }
    
    .score-item {
        background: rgba(255, 255, 255, 0.1);
        padding: 15px 25px;
        border-radius: 15px;
    }
    
    .score-label {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-bottom: 5px;
    }
    
    .score-value {
        font-size: 2rem;
        font-weight: 900;
    }
</style>

<div class="bonus-container">
    <div class="bonus-header">
        <div class="bonus-title">⭐ QUESTION BONUS ⭐</div>
        <div class="bonus-subtitle">Magie de la Magicienne activée !</div>
    </div>
    
    <div class="score-display">
        <div class="score-item">
            <div class="score-label">VOUS</div>
            <div class="score-value" style="color: #4ECDC4;">{{ $params['score'] }}</div>
        </div>
        <div class="score-item">
            <div class="score-label">ADVERSAIRE</div>
            <div class="score-value" style="color: #FF6B6B;">{{ $params['opponent_score'] }}</div>
        </div>
    </div>
    
    <div class="timer-container">
        <div class="timer-label">⏱️ TEMPS RESTANT</div>
        <div class="timer-bar-container">
            <div class="timer-bar" id="timerBar"></div>
        </div>
        <div class="timer-number" id="timerNumber">10</div>
    </div>
    
    <div class="question-box">
        <div class="question-text">{{ $params['question']['text'] }}</div>
    </div>
    
    <form id="bonusForm" action="{{ route('solo.answer-bonus') }}" method="POST">
        @csrf
        <div class="answers-grid">
            @foreach ($params['question']['answers'] as $index => $answer)
                @if ($answer !== null)
                    <button type="button" class="answer-btn" onclick="selectAnswer({{ $index }})">
                        <strong>{{ chr(65 + $index) }}.</strong> {{ $answer }}
                    </button>
                @endif
            @endforeach
        </div>
        
        <input type="hidden" name="answer_index" id="answerIndex" value="-1">
        
        <button type="button" class="skip-btn" onclick="selectAnswer(-1)">
            Passer (0 point)
        </button>
    </form>
</div>

<script>
let timeLeft = 10;
const timerNumber = document.getElementById('timerNumber');
const timerBar = document.getElementById('timerBar');
const bonusForm = document.getElementById('bonusForm');
const answerIndexInput = document.getElementById('answerIndex');

timerBar.style.width = '100%';

const countdown = setInterval(() => {
    timeLeft--;
    timerNumber.textContent = timeLeft;
    timerBar.style.width = (timeLeft / 10 * 100) + '%';
    
    if (timeLeft <= 0) {
        clearInterval(countdown);
        answerIndexInput.value = '-1';
        bonusForm.submit();
    }
}, 1000);

function selectAnswer(index) {
    clearInterval(countdown);
    answerIndexInput.value = index;
    bonusForm.submit();
}
</script>
@endsection
