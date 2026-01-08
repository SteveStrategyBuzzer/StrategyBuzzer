@extends('layouts.app')

@section('content')
@php
$mode = 'duo';
$choices = $question['choices'] ?? [];
$questionText = $question['text'] ?? '';
$isBuzzWinner = ($buzz_winner ?? 'player') === 'player';
@endphp

<style>
    body {
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
        margin: 0;
        overflow-x: hidden;
    }
    
    .game-container {
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
        position: relative;
        min-height: 100vh;
        padding-bottom: 40px;
    }
    
    .question-header {
        background: rgba(78, 205, 196, 0.1);
        padding: 20px;
        border-radius: 20px;
        text-align: center;
        border: 2px solid rgba(78, 205, 196, 0.3);
        margin-bottom: 10px;
    }
    
    .question-number {
        font-size: 0.9rem;
        color: #4ECDC4;
        margin-bottom: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .question-text {
        font-size: 1.4rem;
        font-weight: 600;
        line-height: 1.5;
    }
    
    .buzz-winner-banner {
        background: linear-gradient(135deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 15px 25px;
        border-radius: 15px;
        text-align: center;
        border: 2px solid;
        margin-bottom: 10px;
        animation: bannerPulse 2s ease-in-out infinite;
    }
    
    .buzz-winner-banner.player-won {
        border-color: #4ECDC4;
        box-shadow: 0 0 30px rgba(78, 205, 196, 0.4);
    }
    
    .buzz-winner-banner.opponent-won {
        border-color: #FF6B6B;
        box-shadow: 0 0 30px rgba(255, 107, 107, 0.4);
    }
    
    @keyframes bannerPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
    
    .buzz-winner-text {
        font-size: 1.1rem;
        font-weight: 700;
    }
    
    .buzz-winner-banner.player-won .buzz-winner-text {
        color: #4ECDC4;
    }
    
    .buzz-winner-banner.opponent-won .buzz-winner-text {
        color: #FF6B6B;
    }
    
    .game-layout {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 30px;
        align-items: start;
        justify-items: center;
        margin: 20px 0;
    }
    
    .left-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 30px;
        width: 100%;
    }
    
    .player-circle {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .player-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid #4ECDC4;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.5);
        object-fit: cover;
    }
    
    .player-name {
        font-size: 1rem;
        font-weight: 600;
        color: #4ECDC4;
    }
    
    .player-score {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
    }
    
    .opponent-circle {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .opponent-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        object-fit: cover;
    }
    
    .opponent-avatar-empty {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 107, 107, 0.1);
        font-size: 2.5rem;
        font-weight: 900;
        color: #FF6B6B;
    }
    
    .opponent-name {
        font-size: 1rem;
        font-weight: 600;
        color: #FF6B6B;
    }
    
    .opponent-score {
        font-size: 2rem;
        font-weight: 900;
        color: #FF6B6B;
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.8);
    }
    
    .center-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .chrono-circle {
        width: 180px;
        height: 180px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        box-shadow: 0 15px 50px rgba(102, 126, 234, 0.6);
        animation: pulse-glow 2s ease-in-out infinite;
    }
    
    @keyframes pulse-glow {
        0%, 100% {
            box-shadow: 0 15px 50px rgba(102, 126, 234, 0.6);
        }
        50% {
            box-shadow: 0 15px 70px rgba(102, 126, 234, 0.9);
        }
    }
    
    .chrono-circle::before {
        content: '';
        position: absolute;
        inset: -5px;
        border-radius: 50%;
        background: linear-gradient(45deg, #4ECDC4, #667eea, #FF6B6B);
        opacity: 0.5;
        filter: blur(15px);
        animation: rotate-glow 3s linear infinite;
    }
    
    @keyframes rotate-glow {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .chrono-time {
        font-size: 4rem;
        font-weight: 900;
        position: relative;
        z-index: 1;
        background: linear-gradient(180deg, #fff 0%, #4ECDC4 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .chrono-time.warning {
        background: linear-gradient(180deg, #fff 0%, #FF6B6B 100%);
        -webkit-background-clip: text;
        background-clip: text;
    }
    
    .right-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        width: 100%;
    }
    
    .answers-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .answer-button {
        background: rgba(255, 255, 255, 0.1);
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 25px 20px;
        color: #fff;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        min-height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .answer-button:hover:not(.disabled):not(.selected) {
        background: rgba(78, 205, 196, 0.2);
        border-color: #4ECDC4;
        transform: scale(1.02);
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.4);
    }
    
    .answer-button.selected {
        background: rgba(78, 205, 196, 0.3);
        border-color: #4ECDC4;
        box-shadow: 0 0 30px rgba(78, 205, 196, 0.6);
        animation: selectedPulse 1.5s ease-in-out infinite;
    }
    
    @keyframes selectedPulse {
        0%, 100% { box-shadow: 0 0 30px rgba(78, 205, 196, 0.6); }
        50% { box-shadow: 0 0 50px rgba(78, 205, 196, 0.9); }
    }
    
    .answer-button.correct {
        background: rgba(78, 205, 196, 0.4);
        border-color: #4ECDC4;
        box-shadow: 0 0 40px rgba(78, 205, 196, 0.8);
    }
    
    .answer-button.incorrect {
        background: rgba(255, 107, 107, 0.4);
        border-color: #FF6B6B;
        box-shadow: 0 0 40px rgba(255, 107, 107, 0.8);
    }
    
    .answer-button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .answer-button.waiting {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .result-overlay {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.95);
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        z-index: 200;
        border: 3px solid;
        animation: fadeIn 0.3s ease;
        display: none;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }
    
    .result-overlay.correct {
        border-color: #4ECDC4;
        box-shadow: 0 0 50px rgba(78, 205, 196, 0.8);
    }
    
    .result-overlay.incorrect {
        border-color: #FF6B6B;
        box-shadow: 0 0 50px rgba(255, 107, 107, 0.8);
    }
    
    .result-text {
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 15px;
    }
    
    .result-overlay.correct .result-text {
        color: #4ECDC4;
    }
    
    .result-overlay.incorrect .result-text {
        color: #FF6B6B;
    }
    
    .points-text {
        font-size: 1.5rem;
        font-weight: 600;
        opacity: 0.9;
    }
    
    .correct-answer-text {
        font-size: 1.2rem;
        margin-top: 15px;
        color: #FFD700;
    }
    
    .connection-status {
        position: fixed;
        top: 10px;
        right: 10px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        z-index: 1000;
    }
    
    .connection-status.connected {
        background: rgba(78, 205, 196, 0.3);
        color: #4ECDC4;
    }
    
    .connection-status.disconnected {
        background: rgba(255, 107, 107, 0.3);
        color: #FF6B6B;
    }
    
    .connection-status.connecting {
        background: rgba(255, 215, 0, 0.3);
        color: #FFD700;
    }
    
    .waiting-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 150;
    }
    
    .waiting-message {
        background: rgba(0, 0, 0, 0.9);
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        border: 3px solid #FFD700;
        box-shadow: 0 0 50px rgba(255, 215, 0, 0.5);
    }
    
    .waiting-message h2 {
        font-size: 1.8rem;
        color: #FFD700;
        margin-bottom: 10px;
    }
    
    .waiting-message p {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    @media (max-width: 1024px) {
        .game-layout {
            gap: 20px;
        }
        
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 85px;
            height: 85px;
        }
        
        .chrono-circle {
            width: 150px;
            height: 150px;
        }
        
        .chrono-time {
            font-size: 3.5rem;
        }
        
        .answer-button {
            padding: 20px 15px;
            font-size: 1rem;
        }
    }
    
    @media (max-width: 768px) {
        .game-layout {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .left-column {
            flex-direction: row;
            justify-content: space-around;
            gap: 20px;
        }
        
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 70px;
            height: 70px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.6rem;
        }
        
        .chrono-circle {
            width: 120px;
            height: 120px;
        }
        
        .chrono-time {
            font-size: 2.8rem;
        }
        
        .answers-container {
            grid-template-columns: 1fr;
        }
        
        .answer-button {
            padding: 20px;
            font-size: 1rem;
            min-height: 60px;
        }
        
        .question-text {
            font-size: 1.2rem;
        }
    }
    
    @media (max-width: 480px) {
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 60px;
            height: 60px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.4rem;
        }
        
        .player-name, .opponent-name {
            font-size: 0.85rem;
        }
        
        .chrono-circle {
            width: 100px;
            height: 100px;
        }
        
        .chrono-time {
            font-size: 2.2rem;
        }
        
        .question-text {
            font-size: 1rem;
        }
        
        .answer-button {
            padding: 15px;
            font-size: 0.95rem;
        }
        
        .buzz-winner-text {
            font-size: 0.95rem;
        }
    }
    
</style>

<div class="connection-status connecting" id="connectionStatus">{{ __('Connexion...') }}</div>

<div class="game-container">
    <div class="question-header">
        <div class="question-number">{{ __('Question') }} {{ $currentQuestion ?? 1 }}/{{ $totalQuestions ?? 10 }}</div>
        <div class="question-text" id="questionText">{{ $questionText }}</div>
    </div>
    
    <div class="buzz-winner-banner {{ $isBuzzWinner ? 'player-won' : 'opponent-won' }}" id="buzzWinnerBanner">
        <div class="buzz-winner-text">
            @if($isBuzzWinner)
                🔔 {{ __('Vous avez buzzé en premier ! Choisissez votre réponse.') }}
            @else
                ⏳ {{ __(':name a buzzé en premier. En attente de sa réponse...', ['name' => $opponentName ?? __('Adversaire')]) }}
            @endif
        </div>
    </div>
    
    <div class="game-layout">
        <div class="left-column">
            <div class="player-circle">
                <img src="{{ $playerAvatarPath ?? asset('images/avatars/standard/default.png') }}" alt="{{ __('Votre avatar') }}" class="player-avatar">
                <div class="player-name">{{ __('Vous') }}</div>
                <div class="player-score" id="playerScore">{{ $playerScore ?? 0 }}</div>
            </div>
            
            <div class="opponent-circle">
                @if(!empty($opponentAvatarPath))
                    <img src="{{ $opponentAvatarPath }}" alt="{{ __('Avatar adversaire') }}" class="opponent-avatar">
                @else
                    <div class="opponent-avatar-empty">?</div>
                @endif
                <div class="opponent-name">{{ $opponentName ?? __('Adversaire') }}</div>
                <div class="opponent-score" id="opponentScore">{{ $opponentScore ?? 0 }}</div>
            </div>
        </div>
        
        <div class="center-column">
            <div class="chrono-circle">
                <div class="chrono-time" id="chronoTimer">10</div>
            </div>
        </div>
        
        <div class="right-column"></div>
    </div>
    
    <div class="answers-container" id="answersContainer">
        @foreach($choices as $index => $choice)
            <button class="answer-button {{ !$isBuzzWinner ? 'waiting' : '' }}" 
                    data-index="{{ $index }}"
                    {{ !$isBuzzWinner ? 'disabled' : '' }}>
                {{ $choice }}
            </button>
        @endforeach
    </div>
</div>

<div class="result-overlay" id="resultOverlay">
    <div class="result-text" id="resultText"></div>
    <div class="points-text" id="pointsText"></div>
    <div class="correct-answer-text" id="correctAnswerText"></div>
</div>

<div class="waiting-overlay" id="waitingOverlay">
    <div class="waiting-message">
        <h2>⏳ {{ __('En attente...') }}</h2>
        <p id="waitingText">{{ __(':name répond à la question...', ['name' => $opponentName ?? __('Adversaire')]) }}</p>
    </div>
</div>

<audio id="correctSound" preload="auto">
    <source src="{{ asset('audio/buzzers/correct/correct1.mp3') }}" type="audio/mpeg">
</audio>

<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('audio/buzzers/incorrect/incorrect1.mp3') }}" type="audio/mpeg">
</audio>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<script>
(function() {
    'use strict';
    
    const MATCH_ID = '{{ $match_id ?? "" }}';
    const ROOM_ID = '{{ $room_id ?? "" }}';
    const LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const JWT_TOKEN = '{{ $jwt_token ?? "" }}';
    const GAME_SERVER_URL = '{{ config("app.game_server_url", "") }}';
    const IS_BUZZ_WINNER = {{ $isBuzzWinner ? 'true' : 'false' }};
    
    const ANSWER_TIME = 10;
    let timeLeft = ANSWER_TIME;
    let timerInterval = null;
    let answered = false;
    let selectedIndex = null;
    let isRedirecting = false;
    
    const chronoTimer = document.getElementById('chronoTimer');
    const connectionStatus = document.getElementById('connectionStatus');
    const playerScoreEl = document.getElementById('playerScore');
    const opponentScoreEl = document.getElementById('opponentScore');
    const resultOverlay = document.getElementById('resultOverlay');
    const resultText = document.getElementById('resultText');
    const pointsText = document.getElementById('pointsText');
    const correctAnswerText = document.getElementById('correctAnswerText');
    const waitingOverlay = document.getElementById('waitingOverlay');
    const answersContainer = document.getElementById('answersContainer');
    const correctSound = document.getElementById('correctSound');
    const incorrectSound = document.getElementById('incorrectSound');
    const answerButtons = document.querySelectorAll('.answer-button');
    
    function updateConnectionStatus(status) {
        connectionStatus.className = 'connection-status ' + status;
        switch(status) {
            case 'connected':
                connectionStatus.textContent = '{{ __("Connecté") }}';
                break;
            case 'disconnected':
                connectionStatus.textContent = '{{ __("Déconnecté") }}';
                break;
            case 'connecting':
                connectionStatus.textContent = '{{ __("Connexion...") }}';
                break;
        }
    }
    
    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        
        timerInterval = setInterval(function() {
            timeLeft--;
            chronoTimer.textContent = Math.max(0, timeLeft);
            
            if (timeLeft <= 5) {
                chronoTimer.classList.add('warning');
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                if (!answered && IS_BUZZ_WINNER) {
                    handleTimeout();
                }
            }
        }, 1000);
    }
    
    function handleTimeout() {
        if (answered) return;
        answered = true;
        
        answerButtons.forEach(function(btn) {
            btn.classList.add('disabled');
        });
        
        DuoSocketClient.answer(-1);
    }
    
    function selectAnswer(index) {
        if (answered || !IS_BUZZ_WINNER) return;
        
        answered = true;
        selectedIndex = index;
        
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        answerButtons.forEach(function(btn) {
            btn.classList.remove('selected');
            btn.classList.add('disabled');
        });
        
        answerButtons[index].classList.add('selected');
        answerButtons[index].classList.remove('disabled');
        
        DuoSocketClient.answer(index);
    }
    
    function showResult(isCorrect, correctIndex, pointsEarned) {
        resultOverlay.className = 'result-overlay ' + (isCorrect ? 'correct' : 'incorrect');
        resultText.textContent = isCorrect ? '{{ __("Bonne réponse !") }}' : '{{ __("Mauvaise réponse !") }}';
        pointsText.textContent = isCorrect ? '+' + pointsEarned + ' {{ __("points") }}' : '{{ __("0 point") }}';
        
        if (!isCorrect && correctIndex !== undefined && correctIndex >= 0) {
            const choices = @json($choices);
            if (choices[correctIndex]) {
                correctAnswerText.textContent = '{{ __("La bonne réponse était :") }} ' + choices[correctIndex];
            }
        } else {
            correctAnswerText.textContent = '';
        }
        
        resultOverlay.style.display = 'block';
        
        if (isCorrect && correctSound) {
            correctSound.play().catch(function() {});
        } else if (!isCorrect && incorrectSound) {
            incorrectSound.play().catch(function() {});
        }
        
        answerButtons.forEach(function(btn, idx) {
            btn.classList.remove('selected');
            if (idx === correctIndex) {
                btn.classList.add('correct');
            } else if (idx === selectedIndex && !isCorrect) {
                btn.classList.add('incorrect');
            }
        });
    }
    
    function updateScores(playerScore, opponentScore) {
        if (playerScoreEl) playerScoreEl.textContent = playerScore;
        if (opponentScoreEl) opponentScoreEl.textContent = opponentScore;
    }
    
    answerButtons.forEach(function(btn, index) {
        btn.addEventListener('click', function() {
            selectAnswer(index);
        });
    });
    
    DuoSocketClient.onConnect = function() {
        updateConnectionStatus('connected');
        
        DuoSocketClient.joinRoom(ROOM_ID, LOBBY_CODE, {
            token: JWT_TOKEN
        });
    };
    
    DuoSocketClient.onDisconnect = function(reason) {
        updateConnectionStatus('disconnected');
    };
    
    DuoSocketClient.onError = function(error) {
        console.error('[DuoAnswer] Socket error:', error);
    };
    
    DuoSocketClient.onAnswerRevealed = function(data) {
        if (isRedirecting) return;
        
        waitingOverlay.style.display = 'none';
        
        const isCorrect = data.isCorrect || false;
        const correctIndex = data.correctIndex !== undefined ? data.correctIndex : data.correctAnswer;
        const pointsEarned = data.points || data.pointsEarned || 0;
        
        if (data.scores) {
            updateScores(data.scores.player || 0, data.scores.opponent || 0);
        }
        
        showResult(isCorrect, correctIndex, pointsEarned);
        
        setTimeout(function() {
            if (isRedirecting) return;
            
            if (data.nextUrl) {
                isRedirecting = true;
                window.location.href = data.nextUrl;
            } else if (data.matchEnded) {
                isRedirecting = true;
                window.location.href = '/duo/result/' + MATCH_ID;
            }
        }, 3000);
    };
    
    DuoSocketClient.onRoundEnded = function(data) {
        if (isRedirecting) return;
        
        if (data.scores) {
            updateScores(data.scores.player || 0, data.scores.opponent || 0);
        }
        
        setTimeout(function() {
            if (isRedirecting) return;
            isRedirecting = true;
            
            if (data.nextQuestionUrl) {
                window.location.href = data.nextQuestionUrl;
            } else {
                window.location.href = '/duo/question/' + MATCH_ID;
            }
        }, 2000);
    };
    
    DuoSocketClient.onMatchEnded = function(data) {
        if (isRedirecting) return;
        isRedirecting = true;
        
        setTimeout(function() {
            window.location.href = '/duo/result/' + MATCH_ID;
        }, 2000);
    };
    
    DuoSocketClient.onScoreUpdate = function(data) {
        if (data.playerScore !== undefined) {
            playerScoreEl.textContent = data.playerScore;
        }
        if (data.opponentScore !== undefined) {
            opponentScoreEl.textContent = data.opponentScore;
        }
    };
    
    if (GAME_SERVER_URL) {
        updateConnectionStatus('connecting');
        DuoSocketClient.connect(GAME_SERVER_URL, JWT_TOKEN)
            .then(function() {
                console.log('[DuoAnswer] Connected to game server');
            })
            .catch(function(error) {
                console.error('[DuoAnswer] Failed to connect:', error);
                updateConnectionStatus('disconnected');
            });
    }
    
    if (IS_BUZZ_WINNER) {
        startTimer();
    } else {
        waitingOverlay.style.display = 'flex';
    }
})();
</script>
@endsection
