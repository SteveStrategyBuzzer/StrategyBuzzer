@extends('layouts.app')

@section('content')
@php
$matchId = $params['match_id'] ?? null;
$roomCode = $params['room_code'] ?? null;
$currentQuestion = $params['current_question'] ?? 1;
$totalQuestions = $params['total_questions'] ?? 10;

$question = $params['question'] ?? [];
$questionText = $question['text'] ?? '';
$answers = $question['answers'] ?? [];
$correctIndex = $params['correct_index'] ?? -1;

$playerBuzzed = $params['player_buzzed'] ?? false;
$buzzOrder = $params['buzz_order'] ?? 0;
$potentialPoints = $params['potential_points'] ?? 0;
$score = $params['score'] ?? 0;
$opponentScore = $params['opponent_score'] ?? 0;
@endphp

<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        color: #fff;
        min-height: 100vh;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        overflow: hidden;
        margin: 0;
    }
    
    .answer-container {
        max-width: 900px;
        width: 100%;
        margin: 0 auto;
        padding: 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        max-height: 100vh;
    }
    
    .answer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .answer-info {
        background: linear-gradient(135deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 8px 15px;
        border-radius: 15px;
        border: 2px solid rgba(78, 205, 196, 0.3);
        backdrop-filter: blur(10px);
        flex: 1;
        min-width: 150px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .answer-title {
        font-size: 0.85rem;
        color: #4ECDC4;
        margin-bottom: 3px;
        font-weight: 600;
    }
    
    .answer-value {
        font-size: 1.2rem;
        font-weight: bold;
    }
    
    .score-box {
        text-align: right;
    }
    
    .answer-timer {
        margin-bottom: 10px;
        position: relative;
        flex-shrink: 0;
    }
    
    .timer-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        font-size: 0.8rem;
    }
    
    .timer-bar-container {
        height: 8px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        border: 2px solid rgba(255,255,255,0.2);
    }
    
    .timer-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4 0%, #667eea 100%);
        transition: width 1s linear;
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.6);
    }
    
    .timer-bar.warning {
        background: linear-gradient(90deg, #FF6B6B 0%, #EE5A6F 100%);
        animation: timer-pulse 0.5s infinite;
    }
    
    @keyframes timer-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .answers-grid {
        display: grid;
        gap: 8px;
        margin-bottom: 10px;
        flex: 1;
        overflow-y: auto;
    }
    
    .answer-bubble {
        background: linear-gradient(145deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 15px;
        padding: 12px 18px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .answer-bubble::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s;
    }
    
    .answer-bubble:hover::before {
        left: 100%;
    }
    
    .answer-bubble:hover:not(.disabled) {
        transform: translateX(8px) scale(1.02);
        border-color: #4ECDC4;
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.25) 0%, rgba(102, 126, 234, 0.25) 100%);
        box-shadow: 0 10px 30px rgba(78, 205, 196, 0.4);
    }
    
    .answer-bubble:active:not(.disabled) {
        transform: translateX(4px) scale(0.98);
    }
    
    .answer-bubble.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .answer-number {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: bold;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
    }
    
    .answer-text {
        font-size: 0.95rem;
        font-weight: 500;
        flex: 1;
    }
    
    .answer-icon {
        font-size: 1.2rem;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .answer-bubble:hover .answer-icon {
        opacity: 1;
    }
    
    .answer-bubble.highlighted {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.6) 0%, rgba(102, 234, 126, 0.6) 100%) !important;
        border-color: #4ECDC4 !important;
        box-shadow: 0 0 30px rgba(78, 205, 196, 0.9), inset 0 0 20px rgba(78, 205, 196, 0.4) !important;
        animation: glow-pulse 1.5s infinite;
    }
    
    @keyframes glow-pulse {
        0%, 100% { box-shadow: 0 0 30px rgba(78, 205, 196, 0.9), inset 0 0 20px rgba(78, 205, 196, 0.4); }
        50% { box-shadow: 0 0 50px rgba(78, 205, 196, 1), inset 0 0 30px rgba(78, 205, 196, 0.6); }
    }
    
    .buzz-info {
        text-align: center;
        padding: 12px;
        border-radius: 12px;
        margin-bottom: 5px;
        flex-shrink: 0;
    }
    
    .buzz-info.first-buzzer {
        background: rgba(255, 215, 0, 0.15);
        border: 2px solid rgba(255, 215, 0, 0.4);
    }
    
    .buzz-info.second-buzzer {
        background: rgba(192, 192, 192, 0.15);
        border: 2px solid rgba(192, 192, 192, 0.4);
    }
    
    .buzz-info.no-buzz {
        background: rgba(255, 107, 107, 0.15);
        border: 2px solid rgba(255, 107, 107, 0.3);
    }
    
    .buzz-info-text {
        font-size: 1rem;
        font-weight: 600;
    }
    
    .buzz-info.first-buzzer .buzz-info-text {
        color: #FFD700;
    }
    
    .buzz-info.second-buzzer .buzz-info-text {
        color: #C0C0C0;
    }
    
    .buzz-info.no-buzz .buzz-info-text {
        color: #FF6B6B;
    }
    
    @media (max-width: 768px) {
        .answer-header {
            flex-direction: column;
            gap: 10px;
        }
        
        .answer-info {
            width: 100%;
        }
        
        .answer-value {
            font-size: 1.3rem;
        }
        
        .answer-text {
            font-size: 1rem;
        }
        
        .answer-number {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
        }
    }
    
    @media (max-width: 480px) {
        .answer-bubble {
            padding: 15px 18px;
        }
    }
    
    @media (max-width: 480px) and (orientation: portrait) {
        .answer-container {
            padding: 12px;
        }
        
        .answer-bubble {
            padding: 12px 16px;
            margin-bottom: 8px;
        }
        
        .answer-text {
            font-size: 0.95rem;
        }
    }
    
    @media (max-height: 500px) and (orientation: landscape) {
        .answer-container {
            padding: 8px;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .answer-header {
            margin-bottom: 8px;
        }
        
        .answer-timer {
            margin-bottom: 8px;
        }
        
        .answer-bubble {
            padding: 10px 14px;
            margin-bottom: 6px;
        }
        
        .answer-text {
            font-size: 0.9rem;
        }
        
        .answer-number {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }
    }
    
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .answer-bubble {
            padding: 16px 20px;
        }
    }
    
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .answer-container {
            padding: 16px;
        }
    }
</style>

<div class="answer-container">
    <div class="answer-header">
        <div class="answer-info" style="text-align: center; width: 100%; flex-direction: row; align-items: center; justify-content: space-between; gap: 15px;">
            @php
                $pointColor = $potentialPoints == 0 ? '#FF6B6B' : ($potentialPoints == 1 ? '#C0C0C0' : '#FFD700');
            @endphp
            <div style="font-size: 1.7rem; font-weight: 700; flex: 1; text-align: left;">
                {{ __('Question') }} #{{ $currentQuestion }}/{{ $totalQuestions }}
            </div>
            <div style="font-size: 2.5rem; font-weight: 900; color: {{ $pointColor }}; text-shadow: 0 0 20px {{ $pointColor }}80;">
                +{{ $potentialPoints }}
            </div>
            <div style="font-size: 1.7rem; font-weight: 700; flex: 1; text-align: right;">
                {{ __('Score') }} {{ $score }}/{{ $opponentScore }}
            </div>
        </div>
    </div>
    
    <div class="answer-timer">
        <div class="timer-label">
            <span>⏱️ {{ __('Temps pour répondre') }}</span>
            <span id="timerText">10s</span>
        </div>
        <div class="timer-bar-container">
            <div class="timer-bar" id="timerBar"></div>
        </div>
    </div>
    
    <div class="answers-grid">
        @php
            $isTrueFalse = ($question['type'] ?? '') === 'true_false';
        @endphp
        
        @foreach($answers as $index => $answer)
            @if($isTrueFalse && $answer === null)
                @continue
            @endif
            
            <div class="answer-bubble" onclick="selectAnswer({{ $index }})" data-index="{{ $index }}">
                <div class="answer-number">{{ $index + 1 }}</div>
                <div class="answer-text">{{ $answer }}</div>
                <div class="answer-icon">👉</div>
            </div>
        @endforeach
    </div>
    
    @if($playerBuzzed && $buzzOrder === 1)
        <div class="buzz-info first-buzzer">
            <div class="buzz-info-text">
                🥇 {{ __('Premier buzzer !') }} (+2pts)
            </div>
        </div>
    @elseif($playerBuzzed && $buzzOrder === 2)
        <div class="buzz-info second-buzzer">
            <div class="buzz-info-text">
                🥈 {{ __('Deuxième buzzer') }} (+1pt)
            </div>
        </div>
    @else
        <div class="buzz-info no-buzz">
            <div class="buzz-info-text">
                ⚠️ {{ __('Pas buzzé') }} (0 {{ __('point') }})
            </div>
        </div>
    @endif
</div>

<audio id="tickSound" preload="auto" loop>
    <source src="{{ asset('sounds/tic_tac.mp3') }}" type="audio/mpeg">
</audio>

<audio id="timeoutSound" preload="auto">
    <source src="{{ asset('sounds/timeout.mp3') }}" type="audio/mpeg">
</audio>

<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
</audio>

<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
</audio>

<script src="{{ asset('js/firebase-game-sync.js') }}"></script>

<script>
(function() {
    const MATCH_ID = @json($matchId);
    const ROOM_CODE = @json($roomCode);
    const CURRENT_QUESTION = @json($currentQuestion);
    const PLAYER_ID = @json(auth()->id());
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const CORRECT_INDEX = @json($correctIndex);
    const POTENTIAL_POINTS = @json($potentialPoints);
    
    let timeLeft = 10;
    let totalTime = 10;
    let answered = false;
    let correctSoundDuration = 2000;
    let incorrectSoundDuration = 500;
    let timerInterval = null;
    
    const timerBar = document.getElementById('timerBar');
    const tickSound = document.getElementById('tickSound');
    const timeoutSound = document.getElementById('timeoutSound');
    const correctSound = document.getElementById('correctSound');
    const incorrectSound = document.getElementById('incorrectSound');
    
    timerBar.style.width = '100%';
    
    tickSound.currentTime = 0;
    tickSound.play().catch(function(e) { console.log('Audio play failed:', e); });
    
    correctSound.addEventListener('loadedmetadata', function() {
        correctSoundDuration = Math.floor(correctSound.duration * 1000) + 100;
    });
    
    incorrectSound.addEventListener('loadedmetadata', function() {
        incorrectSoundDuration = Math.floor(incorrectSound.duration * 1000) + 100;
    });
    
    function initFirebase() {
        if (typeof FirebaseGameSync !== 'undefined' && MATCH_ID) {
            FirebaseGameSync.init({
                matchId: MATCH_ID,
                mode: 'duo',
                laravelUserId: PLAYER_ID,
                csrfToken: CSRF_TOKEN,
                callbacks: {
                    onReady: function() {
                        console.log('[DuoAnswer] Firebase ready');
                    }
                }
            }).catch(function(error) {
                console.error('[DuoAnswer] Firebase init failed:', error);
            });
        }
    }
    
    function startTimer() {
        timerInterval = setInterval(function() {
            timeLeft--;
            const percentage = (timeLeft / totalTime) * 100;
            timerBar.style.width = percentage + '%';
            document.getElementById('timerText').textContent = timeLeft + 's';
            
            if (timeLeft <= 3) {
                timerBar.classList.add('warning');
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                tickSound.pause();
                if (!answered) {
                    handleTimeout();
                }
            }
        }, 1000);
    }
    
    function sendAnswerToFirebase(answerIndex, isCorrect) {
        if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
            FirebaseGameSync.sendAnswerAfterServerConfirm({
                answerIndex: answerIndex,
                isCorrect: isCorrect
            }, {
                points: isCorrect ? POTENTIAL_POINTS : 0,
                newScore: @json($score) + (isCorrect ? POTENTIAL_POINTS : 0)
            }).catch(function(error) {
                console.error('[DuoAnswer] Firebase sendAnswer error:', error);
            });
        }
    }
    
    window.selectAnswer = function(index) {
        if (answered) return;
        answered = true;
        
        clearInterval(timerInterval);
        tickSound.pause();
        
        document.querySelectorAll('.answer-bubble').forEach(function(bubble) {
            bubble.classList.add('disabled');
        });
        
        const isCorrect = (index === CORRECT_INDEX);
        let soundDelay = 500;
        
        if (isCorrect) {
            correctSound.currentTime = 0;
            correctSound.play().catch(function(e) { console.log('Audio play failed:', e); });
            soundDelay = correctSoundDuration;
        } else {
            incorrectSound.currentTime = 0;
            incorrectSound.play().catch(function(e) { console.log('Audio play failed:', e); });
            soundDelay = incorrectSoundDuration;
        }
        
        sendAnswerToFirebase(index, isCorrect);
        
        setTimeout(function() {
            const params = new URLSearchParams({
                match_id: MATCH_ID,
                room_code: ROOM_CODE || '',
                answer_index: index,
                question_number: CURRENT_QUESTION
            });
            window.location.href = '/game/duo/result?' + params.toString();
        }, soundDelay);
    };
    
    function handleTimeout() {
        if (answered) return;
        answered = true;
        
        timeoutSound.play().catch(function(e) { console.log('Audio play failed:', e); });
        
        document.querySelectorAll('.answer-bubble').forEach(function(bubble) {
            bubble.classList.add('disabled');
        });
        
        sendAnswerToFirebase(-1, false);
        
        setTimeout(function() {
            const params = new URLSearchParams({
                match_id: MATCH_ID,
                room_code: ROOM_CODE || '',
                answer_index: -1,
                question_number: CURRENT_QUESTION,
                timeout: 'true'
            });
            window.location.href = '/game/duo/result?' + params.toString();
        }, 2000);
    }
    
    initFirebase();
    startTimer();
    
    window.addEventListener('beforeunload', function() {
        if (typeof FirebaseGameSync !== 'undefined') {
            FirebaseGameSync.cleanup();
        }
    });
})();
</script>
@endsection
