@extends('layouts.app')

@section('content')
@php
$mode = 'duo';
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
        padding-bottom: 180px;
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
    
    .question-theme {
        font-size: 0.85rem;
        color: #FFD700;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .question-text {
        font-size: 1.4rem;
        font-weight: 600;
        line-height: 1.5;
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
        width: 220px;
        height: 220px;
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
        font-size: 5rem;
        font-weight: 900;
        position: relative;
        z-index: 1;
        background: linear-gradient(180deg, #fff 0%, #4ECDC4 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .right-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        width: 100%;
    }
    
    .strategic-avatar-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 3px solid #FFD700;
        box-shadow: 0 8px 30px rgba(255, 215, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 215, 0, 0.1);
        object-fit: cover;
    }
    
    .strategic-avatar-circle.empty {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.3);
        box-shadow: none;
    }
    
    .strategic-avatar-image {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .strategic-avatar-name {
        font-size: 0.9rem;
        color: #FFD700;
        font-weight: 600;
        text-align: center;
    }
    
    .skills-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        align-items: center;
    }
    
    .skill-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        background: rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .skill-circle.active {
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.2);
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
        animation: golden-pulse 2s ease-in-out infinite;
    }
    
    @keyframes golden-pulse {
        0%, 100% {
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
        }
        50% {
            box-shadow: 0 0 35px rgba(255, 215, 0, 0.9);
        }
    }
    
    .skill-circle.empty {
        opacity: 0.3;
        cursor: default;
    }
    
    .skill-circle.used {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .buzz-container-bottom {
        position: fixed;
        bottom: calc(30px + env(safe-area-inset-bottom, 0px));
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
    }
    
    .buzz-button {
        background: none;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease;
        padding: 0;
    }
    
    .buzz-button:hover {
        transform: scale(1.05);
    }
    
    .buzz-button:active {
        transform: scale(0.95);
    }
    
    .buzz-button img {
        width: 180px;
        height: 180px;
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.6));
    }
    
    .buzz-button:hover img {
        filter: drop-shadow(0 15px 40px rgba(78, 205, 196, 0.8));
    }
    
    .buzz-container-bottom.buzzer-waiting .buzz-button {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .buzz-container-bottom.buzzer-waiting .buzz-button img {
        filter: drop-shadow(0 5px 15px rgba(128, 128, 128, 0.4)) grayscale(0.5);
    }
    
    .buzz-container-bottom.buzzer-ready .buzz-button {
        opacity: 1;
        cursor: pointer;
        pointer-events: auto;
        animation: buzzer-pulse 1.5s ease-in-out infinite;
    }
    
    @keyframes buzzer-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.03); }
    }
    
    .buzz-container-bottom.buzzer-ready .buzz-button img {
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.8));
    }
    
    .buzz-container-bottom.buzzer-hidden {
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    
    .result-overlay {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.9);
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
    
    .opponent-buzzed-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 107, 107, 0.2);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 200;
        animation: fadeIn 0.3s ease;
    }
    
    .opponent-buzzed-message {
        background: rgba(0, 0, 0, 0.9);
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        border: 3px solid #FF6B6B;
        box-shadow: 0 0 50px rgba(255, 107, 107, 0.8);
    }
    
    .opponent-buzzed-message h2 {
        font-size: 2rem;
        color: #FF6B6B;
        margin-bottom: 10px;
    }
    
    .opponent-buzzed-message p {
        font-size: 1.2rem;
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
        
        .strategic-avatar-circle {
            width: 100px;
            height: 100px;
        }
        
        .skill-circle {
            width: 50px;
            height: 50px;
            font-size: 1.4rem;
        }
        
        .chrono-circle {
            width: 180px;
            height: 180px;
        }
        
        .chrono-time {
            font-size: 4rem;
        }
    }
    
    @media (max-width: 768px) {
        .game-layout {
            gap: 15px;
        }
        
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 70px;
            height: 70px;
        }
        
        .strategic-avatar-circle {
            width: 80px;
            height: 80px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.6rem;
        }
        
        .chrono-circle {
            width: 140px;
            height: 140px;
        }
        
        .chrono-time {
            font-size: 3rem;
        }
        
        .skill-circle {
            width: 45px;
            height: 45px;
            font-size: 1.2rem;
        }
        
        .buzz-button img {
            width: 150px;
            height: 150px;
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
        
        .strategic-avatar-circle {
            width: 70px;
            height: 70px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.4rem;
        }
        
        .player-name, .opponent-name {
            font-size: 0.85rem;
        }
        
        .chrono-circle {
            width: 120px;
            height: 120px;
        }
        
        .chrono-time {
            font-size: 2.5rem;
        }
        
        .skill-circle {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
        
        .buzz-button img {
            width: 130px;
            height: 130px;
        }
        
        .question-text {
            font-size: 1rem;
        }
    }
    
    @media (max-height: 600px) and (orientation: landscape) {
        .game-container {
            padding-bottom: 140px;
        }
        
        .question-header {
            padding: 12px;
            margin-bottom: 8px;
        }
        
        .question-text {
            font-size: 1rem;
        }
        
        .game-layout {
            gap: 15px;
            margin: 10px 0;
        }
        
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 60px;
            height: 60px;
        }
        
        .strategic-avatar-circle {
            width: 70px;
            height: 70px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.3rem;
        }
        
        .chrono-circle {
            width: 100px;
            height: 100px;
        }
        
        .chrono-time {
            font-size: 2.2rem;
        }
        
        .skill-circle {
            width: 35px;
            height: 35px;
            font-size: 0.9rem;
        }
        
        .buzz-button img {
            width: 110px;
            height: 110px;
        }
        
        .buzz-container-bottom {
            bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        }
    }
</style>

<div class="connection-status connecting" id="connectionStatus">{{ __('Connexion...') }}</div>

<div class="game-container">
    <div class="question-header">
        <div class="question-number">{{ __('Question') }} {{ $currentQuestion ?? 1 }}/{{ $totalQuestions ?? 10 }}</div>
        @if(!empty($themeDisplay))
            <div class="question-theme">{{ $themeDisplay }}</div>
        @elseif(!empty($theme))
            <div class="question-theme">{{ $theme }}</div>
        @endif
        <div class="question-text" id="questionText">{{ __('En attente de la question...') }}</div>
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
                <div class="chrono-time" id="chronoTimer">60</div>
            </div>
        </div>
        
        <div class="right-column">
            @if(!empty($strategic_avatar))
                <div class="strategic-avatar-circle">
                    <img src="{{ $strategic_avatar }}" alt="{{ __('Avatar stratégique') }}" class="strategic-avatar-image">
                </div>
                @if(!empty($avatarName))
                    <div class="strategic-avatar-name">{{ $avatarName }}</div>
                @endif
            @else
                <div class="strategic-avatar-circle empty"></div>
            @endif
            
            <div class="skills-container">
                @if(!empty($skills) && is_array($skills))
                    @foreach($skills as $skill)
                        <div class="skill-circle {{ ($skill['used'] ?? false) ? 'used' : 'active' }}" 
                             data-skill-id="{{ $skill['id'] ?? '' }}"
                             data-skill-trigger="{{ $skill['trigger'] ?? 'question' }}"
                             data-uses-left="{{ $skill['uses_left'] ?? 1 }}"
                             title="{{ $skill['name'] ?? '' }}: {{ $skill['description'] ?? '' }}">
                            {{ $skill['icon'] ?? '⭐' }}
                        </div>
                    @endforeach
                    @for($i = count($skills); $i < 3; $i++)
                        <div class="skill-circle empty"></div>
                    @endfor
                @else
                    <div class="skill-circle empty"></div>
                    <div class="skill-circle empty"></div>
                    <div class="skill-circle empty"></div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="buzz-container-bottom buzzer-waiting" id="buzzContainer">
    <button class="buzz-button" id="buzzButton" disabled>
        <img src="{{ asset('images/buzzer.png') }}" alt="{{ __('Buzzer') }}">
    </button>
</div>

<div class="result-overlay" id="resultOverlay">
    <div class="result-text" id="resultText"></div>
    <div class="points-text" id="pointsText"></div>
</div>

<div id="opponentBuzzedOverlay" class="opponent-buzzed-overlay">
    <div class="opponent-buzzed-message">
        <h2>🔔 {{ __('Adversaire a buzzé !') }}</h2>
        <p>{{ __('En attente de sa réponse...') }}</p>
    </div>
</div>

<audio id="buzzerSound" preload="auto">
    <source src="{{ asset('audio/buzzers/correct/correct1.mp3') }}" type="audio/mpeg">
</audio>

<audio id="noBuzzSound" preload="auto">
    <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
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
    
    const TOTAL_TIME = 60;
    let timeLeft = TOTAL_TIME;
    let timerInterval = null;
    let buzzed = false;
    let phaseEndsAtMs = null;
    let currentPhase = 'LOBBY';
    let currentQuestion = null;
    let isRedirecting = false;
    
    const chronoTimer = document.getElementById('chronoTimer');
    const buzzButton = document.getElementById('buzzButton');
    const buzzContainer = document.getElementById('buzzContainer');
    const connectionStatus = document.getElementById('connectionStatus');
    const questionText = document.getElementById('questionText');
    const playerScoreEl = document.getElementById('playerScore');
    const opponentScoreEl = document.getElementById('opponentScore');
    const resultOverlay = document.getElementById('resultOverlay');
    const resultText = document.getElementById('resultText');
    const pointsText = document.getElementById('pointsText');
    const buzzerSound = document.getElementById('buzzerSound');
    const noBuzzSound = document.getElementById('noBuzzSound');
    const opponentBuzzedOverlay = document.getElementById('opponentBuzzedOverlay');
    
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
    
    function syncTimerWithServer(serverPhaseEndsAtMs) {
        if (!serverPhaseEndsAtMs) return;
        
        phaseEndsAtMs = serverPhaseEndsAtMs;
        const now = Date.now();
        const remainingMs = Math.max(0, phaseEndsAtMs - now);
        timeLeft = Math.ceil(remainingMs / 1000);
        chronoTimer.textContent = timeLeft;
    }
    
    function setBuzzerState(state) {
        buzzContainer.classList.remove('buzzer-waiting', 'buzzer-ready', 'buzzer-hidden');
        buzzContainer.classList.add('buzzer-' + state);
        buzzButton.disabled = (state !== 'ready');
    }
    
    function startTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
        }
        
        setBuzzerState('ready');
        
        timerInterval = setInterval(() => {
            if (phaseEndsAtMs) {
                const now = Date.now();
                const remainingMs = Math.max(0, phaseEndsAtMs - now);
                timeLeft = Math.ceil(remainingMs / 1000);
            } else {
                timeLeft--;
            }
            
            chronoTimer.textContent = Math.max(0, timeLeft);
            
            if (timeLeft <= 10) {
                chronoTimer.style.color = '#FF6B6B';
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                if (!buzzed) {
                    handleNoBuzz();
                }
            }
        }, 1000);
    }
    
    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }
    
    function handleBuzz() {
        if (buzzed || isRedirecting) return;
        
        buzzed = true;
        stopTimer();
        
        buzzerSound.currentTime = 0;
        buzzerSound.play().catch(e => console.log('{{ __("Erreur audio") }}:', e));
        
        buzzButton.disabled = true;
        setBuzzerState('hidden');
        
        if (duoSocket && duoSocket.isConnected()) {
            duoSocket.buzz(Date.now());
        }
        
        isRedirecting = true;
        setTimeout(() => {
            window.location.href = '/game/duo/answer?buzzed=true&match_id=' + MATCH_ID;
        }, 300);
    }
    
    function handleNoBuzz() {
        if (isRedirecting) return;
        
        noBuzzSound.currentTime = 0;
        noBuzzSound.play().catch(e => console.log('{{ __("Erreur audio") }}:', e));
        
        buzzButton.disabled = true;
        setBuzzerState('waiting');
        
        isRedirecting = true;
        setTimeout(() => {
            window.location.href = '/game/duo/answer?timeout=true&match_id=' + MATCH_ID;
        }, 500);
    }
    
    function handleOpponentBuzz(data) {
        if (buzzed || isRedirecting) return;
        
        stopTimer();
        setBuzzerState('hidden');
        opponentBuzzedOverlay.style.display = 'flex';
        
        isRedirecting = true;
        setTimeout(() => {
            window.location.href = '/game/duo/answer?opponent_buzzed=true&match_id=' + MATCH_ID;
        }, 1500);
    }
    
    function showResult(isCorrect, points) {
        resultOverlay.style.display = 'block';
        resultOverlay.className = 'result-overlay ' + (isCorrect ? 'correct' : 'incorrect');
        resultText.textContent = isCorrect ? '{{ __("Correct!") }}' : '{{ __("Incorrect!") }}';
        pointsText.textContent = (points >= 0 ? '+' : '') + points + ' {{ __("points") }}';
        
        setTimeout(() => {
            resultOverlay.style.display = 'none';
        }, 2000);
    }
    
    function updateScores(playerScore, opponentScore) {
        if (playerScoreEl && playerScore !== undefined) {
            playerScoreEl.textContent = playerScore;
        }
        if (opponentScoreEl && opponentScore !== undefined) {
            opponentScoreEl.textContent = opponentScore;
        }
    }
    
    function handleGameState(data) {
        console.log('[DuoQuestion] {{ __("État du jeu reçu") }}:', data);
        
        if (data.phase) {
            currentPhase = data.phase;
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        if (data.currentQuestion) {
            currentQuestion = data.currentQuestion;
            if (questionText && currentQuestion.text) {
                questionText.textContent = currentQuestion.text;
            }
        }
        
        if (data.players) {
            const players = Object.values(data.players);
            players.forEach(player => {
                if (player.isHost !== undefined) {
                    if (player.isHost) {
                        updateScores(player.score, undefined);
                    } else {
                        updateScores(undefined, player.score);
                    }
                }
            });
        }
        
        if (currentPhase === 'QUESTION_ACTIVE' && !buzzed && !isRedirecting) {
            if (!timerInterval) {
                startTimer();
            }
        } else if (currentPhase !== 'QUESTION_ACTIVE') {
            stopTimer();
            setBuzzerState('waiting');
        }
    }
    
    function handlePhaseChanged(data) {
        console.log('[DuoQuestion] {{ __("Phase changée") }}:', data);
        
        currentPhase = data.phase;
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        if (currentPhase === 'QUESTION_ACTIVE') {
            buzzed = false;
            isRedirecting = false;
            startTimer();
        } else if (currentPhase === 'ANSWER_SELECTION') {
            stopTimer();
        } else if (currentPhase === 'REVEAL') {
            stopTimer();
        }
    }
    
    function handleQuestionPublished(data) {
        console.log('[DuoQuestion] {{ __("Question publiée") }}:', data);
        
        if (data.question && questionText) {
            questionText.textContent = data.question.text;
            currentQuestion = data.question;
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        buzzed = false;
        isRedirecting = false;
        startTimer();
    }
    
    function handleBuzzWinner(data) {
        console.log('[DuoQuestion] {{ __("Gagnant du buzz") }}:', data);
        
        stopTimer();
        buzzButton.disabled = true;
        setBuzzerState('hidden');
        
        if (data.playerId && data.playerId !== '{{ auth()->id() ?? "" }}') {
            handleOpponentBuzz(data);
        }
    }
    
    function handleAnswerRevealed(data) {
        console.log('[DuoQuestion] {{ __("Réponse révélée") }}:', data);
        
        if (data.isCorrect !== undefined && data.pointsEarned !== undefined) {
            showResult(data.isCorrect, data.pointsEarned);
        }
        
        if (data.playerScore !== undefined) {
            updateScores(data.playerScore, undefined);
        }
        if (data.opponentScore !== undefined) {
            updateScores(undefined, data.opponentScore);
        }
    }
    
    function handleScoreUpdate(data) {
        console.log('[DuoQuestion] {{ __("Mise à jour des scores") }}:', data);
        
        if (data.scores) {
            Object.entries(data.scores).forEach(([playerId, score]) => {
                if (playerId === '{{ auth()->id() ?? "" }}') {
                    updateScores(score, undefined);
                } else {
                    updateScores(undefined, score);
                }
            });
        }
    }
    
    function handleMatchEnded(data) {
        console.log('[DuoQuestion] {{ __("Match terminé") }}:', data);
        
        stopTimer();
        
        isRedirecting = true;
        setTimeout(() => {
            window.location.href = '/duo/results?match_id=' + MATCH_ID;
        }, 2000);
    }
    
    function handleSkillUsed(data) {
        console.log('[DuoQuestion] {{ __("Skill utilisé") }}:', data);
        
        if (data.effect === 'time_bonus' && data.extraSeconds) {
            timeLeft += data.extraSeconds;
            if (phaseEndsAtMs) {
                phaseEndsAtMs += data.extraSeconds * 1000;
            }
            chronoTimer.textContent = timeLeft;
            showSkillMessage('⏰ +' + data.extraSeconds + ' {{ __("secondes") }}!', 'success');
        }
    }
    
    function showSkillMessage(message, type, duration = 3000) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'skill-message skill-message-' + type;
        msgDiv.innerHTML = message;
        msgDiv.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 10px; font-weight: bold; z-index: 9999; animation: fadeInOut ' + (duration/1000) + 's ease-in-out;';
        
        if (type === 'success') {
            msgDiv.style.background = 'linear-gradient(135deg, #2ECC71, #27AE60)';
        } else if (type === 'error') {
            msgDiv.style.background = 'linear-gradient(135deg, #E74C3C, #C0392B)';
        } else if (type === 'warning') {
            msgDiv.style.background = 'linear-gradient(135deg, #F39C12, #E67E22)';
        } else {
            msgDiv.style.background = 'linear-gradient(135deg, #3498DB, #2980B9)';
        }
        msgDiv.style.color = 'white';
        
        document.body.appendChild(msgDiv);
        setTimeout(() => msgDiv.remove(), duration);
    }
    
    async function initializeSocket() {
        if (!GAME_SERVER_URL) {
            console.warn('[DuoQuestion] {{ __("URL du serveur de jeu non configurée") }}');
            updateConnectionStatus('disconnected');
            startTimer();
            return;
        }
        
        updateConnectionStatus('connecting');
        
        duoSocket.onConnect = () => {
            updateConnectionStatus('connected');
            
            duoSocket.joinRoom(ROOM_ID, LOBBY_CODE, {
                token: JWT_TOKEN
            });
        };
        
        duoSocket.onDisconnect = (reason) => {
            updateConnectionStatus('disconnected');
            console.log('[DuoQuestion] {{ __("Déconnecté") }}:', reason);
        };
        
        duoSocket.onError = (error) => {
            console.error('[DuoQuestion] {{ __("Erreur Socket") }}:', error);
            updateConnectionStatus('disconnected');
        };
        
        duoSocket.onGameState = handleGameState;
        duoSocket.onPhaseChanged = handlePhaseChanged;
        duoSocket.onQuestionPublished = handleQuestionPublished;
        duoSocket.onBuzzWinner = handleBuzzWinner;
        duoSocket.onBuzzResult = handleBuzzWinner;
        duoSocket.onAnswerRevealed = handleAnswerRevealed;
        duoSocket.onScoreUpdate = handleScoreUpdate;
        duoSocket.onMatchEnded = handleMatchEnded;
        duoSocket.onSkillUsed = handleSkillUsed;
        
        try {
            await duoSocket.connect(GAME_SERVER_URL, JWT_TOKEN);
        } catch (error) {
            console.error('[DuoQuestion] {{ __("Échec de la connexion") }}:', error);
            updateConnectionStatus('disconnected');
            startTimer();
        }
    }
    
    buzzButton.addEventListener('click', handleBuzz);
    
    document.addEventListener('keydown', function(e) {
        if (e.code === 'Space' || e.key === ' ') {
            e.preventDefault();
            if (!buzzButton.disabled && !buzzed && !isRedirecting) {
                handleBuzz();
            }
        }
    });
    
    document.querySelectorAll('.skill-circle.active').forEach(skill => {
        skill.addEventListener('click', function() {
            const skillId = this.getAttribute('data-skill-id');
            if (!skillId) return;
            
            if (this.classList.contains('used')) {
                showSkillMessage('⚪ {{ __("Skill déjà utilisé") }}', 'error');
                return;
            }
            
            if (duoSocket && duoSocket.isConnected()) {
                duoSocket.useSkill(skillId);
                
                this.classList.remove('active');
                this.classList.add('used');
                this.textContent = '⚪';
            } else {
                showSkillMessage('❌ {{ __("Non connecté au serveur") }}', 'error');
            }
        });
    });
    
    initializeSocket();
    
    window.addEventListener('beforeunload', () => {
        if (duoSocket && duoSocket.isConnected()) {
            duoSocket.disconnect();
        }
    });
})();
</script>
@endsection
