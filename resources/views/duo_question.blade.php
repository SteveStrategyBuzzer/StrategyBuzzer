@extends('layouts.game')

@section('game-data')
<script>
window.MATCH_ID          = @json((string)($match_id ?? ''));
window.ROOM_ID           = @json((string)($room_id ?? ''));
window.LOBBY_CODE        = @json((string)($lobby_code ?? ''));
window.JWT_TOKEN         = @json((string)($jwt_token ?? ''));
window.CURRENT_USER_ID   = @json((string)(auth()->id() ?? ''));
window.TOTAL_QUESTIONS   = {{ (int)($totalQuestions ?? 10) }};
window.ANSWER_URL        = @json(route('game.duo.answer'));
window.RESULT_URL        = @json(route('game.duo.result'));
window.MATCH_RESULT_URL  = @json(route('game.duo.match-result'));
</script>
@endsection

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
        position: relative;
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
    
    .skill-circle.used,
    .skill-circle.depleted {
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
   
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        transition: opacity 0.3s ease;
    }
    
    .loading-overlay.hidden {
        opacity: 0;
        pointer-events: none;
    }
    
    .loading-content {
        text-align: center;
    }
    
    .loading-spinner {
        width: 80px;
        height: 80px;
        border: 4px solid rgba(78, 205, 196, 0.3);
        border-top-color: #4ECDC4;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .loading-text {
        font-size: 1.2rem;
        color: #4ECDC4;
        font-weight: 600;
    }
</style>
{{-- loading-overlay, connection-status, voice-mic-button: provided by layouts.game --}}

<div class="game-container" id="gameContainer" style="display: none;">
    <div class="question-header">
        <div class="question-number">{{ __('Question') }} <span id="questionCounter">{{ $currentQuestion ?? 1 }}</span>/{{ $totalQuestions ?? 10 }}</div>
        <div class="question-theme" id="questionTheme">
            @if(!empty($themeDisplay))
                {{ $themeDisplay }}
            @elseif(!empty($theme))
                {{ $theme }}
            @else
                {{ __('En attente du thème...') }}
            @endif
        </div>
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
                <div class="chrono-time" id="chronoTimer">8</div>
            </div>
        </div>
        
        <div class="right-column">
            @if(!empty($strategicAvatarPath))
                <div class="strategic-avatar-circle">
                      <img src="{{ Str::startsWith($strategicAvatarPath, ['http://', 'https://', '/']) ? $strategicAvatarPath : asset($strategicAvatarPath) }}" alt="{{ __('Avatar stratégique') }}" class="strategic-avatar-image">
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
      <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
</audio>

<audio id="noBuzzSound" preload="auto">
    <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
</audio>

{{-- socket.io + DuoSocketClient loaded by layouts.game --}}

<script>
(function() {
    'use strict';
    
    const MATCH_ID        = window.MATCH_ID        || @json((string)($match_id ?? ''));
    const ROOM_ID         = window.ROOM_ID         || @json((string)($room_id ?? ''));
    const LOBBY_CODE      = window.LOBBY_CODE      || @json((string)($lobby_code ?? ''));
    const JWT_TOKEN       = window.JWT_TOKEN       || @json((string)($jwt_token ?? ''));
    const GAME_SERVER_URL = window.GAME_SERVER_URL || window.location.origin;
    
    const ANSWER_URL        = window.ANSWER_URL       || @json(route('game.duo.answer'));
    const RESULT_URL        = window.RESULT_URL       || @json(route('game.duo.result'));
    const MATCH_RESULT_URL  = window.MATCH_RESULT_URL || @json(route('game.duo.match-result'));
    
    const DEFAULT_THEME = @json($themeDisplay ?? $theme ?? 'Culture générale');
    const DEFAULT_TOTAL_QUESTIONS = {{ (int) ($totalQuestions ?? 10) }};
    const CURRENT_USER_ID = @json((string) (auth()->id() ?? ''));
    
    const PLACEHOLDER_QUESTION_TEXT = @json(__('En attente de la question...'));
    
    const FALLBACK_QUESTION = {
        text: PLACEHOLDER_QUESTION_TEXT,
        theme: DEFAULT_THEME
    };
    
    const TOTAL_TIME = 8;
    let timeLeft = TOTAL_TIME;
    let timerInterval = null;
    let buzzed = false;
    let phaseEndsAtMs = null;
    let currentPhase = 'LOBBY';
    let currentQuestion = null;
    let isRedirecting = false;
    let gameLayoutReady = false;
    let socketConnected = false;
    let questionReceived = false;
    
    @php
        $hasFasterBuzz = false;
        if (isset($skills) && is_array($skills)) {
            foreach ($skills as $skill) {
                if (($skill['id'] ?? '') === 'faster_buzz') {
                    $hasFasterBuzz = true;
                    break;
                }
            }
        }
    @endphp
    const HAS_FASTER_BUZZ = {{ $hasFasterBuzz ? 'true' : 'false' }};
    const BUZZ_REDIRECT_DELAY = HAS_FASTER_BUZZ ? 100 : 300;
    
    const chronoTimer = document.getElementById('chronoTimer');
    const buzzButton = document.getElementById('buzzButton');
    const buzzContainer = document.getElementById('buzzContainer');
    const connectionStatus = document.getElementById('connectionStatus');
    const questionText = document.getElementById('questionText');
    const questionTheme = document.getElementById('questionTheme');
    const questionCounter = document.getElementById('questionCounter');
    const playerScoreEl = document.getElementById('playerScore');
    const opponentScoreEl = document.getElementById('opponentScore');
    const resultOverlay = document.getElementById('resultOverlay');
    const resultText = document.getElementById('resultText');
    const pointsText = document.getElementById('pointsText');
    const buzzerSound = document.getElementById('buzzerSound');
    const noBuzzSound = document.getElementById('noBuzzSound');
    const opponentBuzzedOverlay = document.getElementById('opponentBuzzedOverlay');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingText = document.getElementById('loadingText');
    const gameContainer = document.getElementById('gameContainer');
    
    function isQuestionUsable(question) {
        return !!(question && typeof question.text === 'string' && question.text.trim() !== '' && question.text.trim() !== PLACEHOLDER_QUESTION_TEXT.trim());
    }
    
    function updateQuestionUI(question, questionIndex = null, totalQuestions = null) {
        if (!question) return;
        
        if (questionText && question.text) {
            questionText.textContent = question.text;
        }
        
        const themeValue =
            question.theme ||
            question.category ||
            question.subCategory ||
            DEFAULT_THEME;
        
        if (questionTheme) {
            questionTheme.textContent = themeValue;
        }
        
        if (questionCounter && questionIndex !== null && questionIndex !== undefined) {
            const displayIndex = Number(questionIndex) + 1;
            questionCounter.textContent = Number.isFinite(displayIndex) ? displayIndex : 1;
        }
        
        currentQuestion = question;
        questionReceived = isQuestionUsable(question);
    }
    
    function showGameLayout() {
        if (gameLayoutReady) return;
        gameLayoutReady = true;
        
        loadingOverlay.classList.add('hidden');
        gameContainer.style.display = 'flex';
        chronoTimer.textContent = TOTAL_TIME;
       
        console.log('[DuoQuestion] Interface de jeu prête');
    }
    
    function updateLoadingText(text) {
        if (loadingText) {
            loadingText.textContent = text;
        }
    }
    
    function updateConnectionStatus(status) {
        connectionStatus.className = 'connection-status ' + status;
        switch(status) {
            case 'connected':
                connectionStatus.textContent = '{{ __("Connecté") }}';
                break;
            case 'disconnected':
                connectionStatus.textContent = '{{ __("Déconnecté") }}';
                break;
            default:
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
        chronoTimer.textContent = Math.max(0, timeLeft);
    }
    
    function resetTimerColor() {
        chronoTimer.style.color = '';
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
        
        resetTimerColor();
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
                if (!buzzed && !isRedirecting) {
                    handleNoBuzz();
                }
            }
        }, 250);
    }
    
    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }
    
    function ensureQuestionPhaseReady() {
        if (!isQuestionUsable(currentQuestion)) {
            return false;
        }
        
        if (!gameLayoutReady) {
            showGameLayout();
        }
        
        if (!buzzed && !isRedirecting) {
            startTimer();
        }
        
        return true;
    }
    
    function redirectOnce(url, delay = 0) {
        if (isRedirecting) return;
        isRedirecting = true;
        
        setTimeout(() => {
            window.location.href = url;
        }, delay);
    }
    
    function handleBuzz() {
        if (buzzed || isRedirecting || currentPhase !== 'QUESTION_ACTIVE') return;
        
        buzzed = true;
        stopTimer();
        
        buzzerSound.currentTime = 0;
        buzzerSound.play().catch(e => console.log('Erreur audio:', e));
        
        buzzButton.disabled = true;
        setBuzzerState('hidden');
        
        if (window.DuoSocketClient && window.DuoSocketClient.isConnected()) {
            window.DuoSocketClient.buzz(Date.now());
        }
        
        redirectOnce(ANSWER_URL + '?buzzed=true&match_id=' + encodeURIComponent(MATCH_ID), BUZZ_REDIRECT_DELAY);
    }
    
    function handleNoBuzz() {
        if (isRedirecting) return;
        
        noBuzzSound.currentTime = 0;
        noBuzzSound.play().catch(e => console.log('Erreur audio:', e));
        
        buzzButton.disabled = true;
        setBuzzerState('waiting');
        
        redirectOnce(ANSWER_URL + '?timeout=true&match_id=' + encodeURIComponent(MATCH_ID), 500);
    }
    
    function handleOpponentBuzz() {
        if (buzzed || isRedirecting) return;
        
        stopTimer();
        setBuzzerState('hidden');
        opponentBuzzedOverlay.style.display = 'flex';
        
        redirectOnce(ANSWER_URL + '?opponent_buzzed=true&match_id=' + encodeURIComponent(MATCH_ID), 1200);
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
        if (playerScoreEl && playerScore !== undefined && playerScore !== null) {
            playerScoreEl.textContent = playerScore;
        }
        if (opponentScoreEl && opponentScore !== undefined && opponentScore !== null) {
            opponentScoreEl.textContent = opponentScore;
        }
    }
    
    function applyPhaseVisualState() {
        switch (currentPhase) {
            case 'QUESTION_ACTIVE':
                opponentBuzzedOverlay.style.display = 'none';
                ensureQuestionPhaseReady();
                break;
                
            case 'ANSWER_SELECTION':
                stopTimer();
                setBuzzerState('hidden');
                if (!isRedirecting && gameLayoutReady) {
                    updateLoadingText('{{ __("Passage à la réponse...") }}');
                }
                break;
                
            case 'REVEAL':
                stopTimer();
                setBuzzerState('hidden');
                if (!isRedirecting && gameLayoutReady) {
                    updateLoadingText('{{ __("Révélation de la réponse...") }}');
                }
                break;
                
            case 'ROUND_SCOREBOARD':
            case 'MATCH_END':
            case 'FINISHED':
                stopTimer();
                setBuzzerState('hidden');
                break;
                
            case 'INTRO':
                stopTimer();
                setBuzzerState('waiting');
                updateLoadingText('🎮 ' + Math.max(1, Math.ceil(Math.max(0, phaseEndsAtMs - Date.now()) / 1000)) + ' 🎮');
                setInterval(() => {
                    if (!phaseEndsAtMs) return;
                    const seconds = Math.max(1, Math.ceil(Math.max(0, phaseEndsAtMs - Date.now()) / 1000));
                    updateLoadingText('🎮 ' + seconds + ' 🎮');
                }, 250);
                break;
                
            case 'WAITING':
                stopTimer();
                setBuzzerState('waiting');
                updateLoadingText('{{ __("En attente des joueurs...") }}');
                break;
                
            case 'LOBBY':
            default:
                stopTimer();
                setBuzzerState('waiting');
                updateLoadingText('{{ __("En attente du démarrage de la question...") }}');
                break;
        }
    }
    
    function handleGameState(data) {
        console.log('[DuoQuestion] État du jeu reçu:', data);
        
        if (data.phase) {
            currentPhase = data.phase;
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        if (data.currentQuestion) {
            updateQuestionUI(data.currentQuestion, data.questionIndex, data.totalQuestions);
        }
        
        if (data.players) {
            let myScore = undefined;
            let enemyScore = undefined;
            
            Object.entries(data.players).forEach(([playerId, player]) => {
                const pid = String(playerId);
                const objectId = player && player.id !== undefined ? String(player.id) : null;
                
                if (pid === CURRENT_USER_ID || objectId === CURRENT_USER_ID) {
                    myScore = player.score;
                } else {
                    enemyScore = player.score;
                }
            });
            
            updateScores(myScore, enemyScore);
        }
        
        if (currentPhase === 'ANSWER_SELECTION') {
            // game_state uses lockedAnswerPlayerId (flat), phase_changed uses lockedPlayerId
            const lockedId = data.lockedAnswerPlayerId || data.lockedPlayerId || '';
            if (lockedId && String(lockedId) === CURRENT_USER_ID) {
                redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 150);
                return;
            }
        }
        
        if (currentPhase === 'REVEAL') {
            redirectOnce(RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 150);
            return;
        }
        
        if (currentPhase === 'MATCH_END' || currentPhase === 'FINISHED') {
            redirectOnce(MATCH_RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 150);
            return;
        }
        
        applyPhaseVisualState();
    }
    
    function handlePhaseChanged(data) {
        console.log('[DuoQuestion] Phase changée:', data);
        
        if (data.phase) {
            currentPhase = data.phase;
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        if (data.question) {
            updateQuestionUI(data.question, data.questionIndex, data.totalQuestions);
        }
       
        if (currentPhase === 'QUESTION_ACTIVE') {
            buzzed = false;
            isRedirecting = false;
            opponentBuzzedOverlay.style.display = 'none';
            applyPhaseVisualState();
            return;
        }
        
        if (currentPhase === 'ANSWER_SELECTION') {
            // Server sends lockedPlayerId in phase_changed (lockedAnswerPlayerId in game_state)
            const lockedId = data.lockedPlayerId || data.lockedAnswerPlayerId || '';
            if (String(lockedId) === CURRENT_USER_ID) {
                redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 150);
            } else {
                handleOpponentBuzz();
            }
            return;
        }
        
        if (currentPhase === 'REVEAL') {
            redirectOnce(RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 150);
            return;
        }
        
        if (currentPhase === 'MATCH_END' || currentPhase === 'FINISHED') {
            redirectOnce(MATCH_RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 150);
            return;
        }
        
        applyPhaseVisualState();
    }
    
    function handleQuestionPublished(data) {
        console.log('[DuoQuestion] Question publiée:', data);
        
        if (data.question) {
            updateQuestionUI(data.question, data.questionIndex, data.totalQuestions);
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        if (data.reduceTimeActive) {
            showSkillMessage('{{ __("Temps réduit par un skill adverse!") }}', 'warning', 3000);
        }

        if (data.activeEffects && data.activeEffects.length > 0) {
            renderActiveEffects(data.activeEffects);
        }
        
        currentPhase = 'QUESTION_ACTIVE';
        buzzed = false;
        isRedirecting = false;
        opponentBuzzedOverlay.style.display = 'none';
        
        applyPhaseVisualState();
    }
    
    function handleBuzzWinner(data) {
        console.log('[DuoQuestion] Gagnant du buzz:', data);
        
        stopTimer();
        buzzButton.disabled = true;
        setBuzzerState('hidden');
        
        if (String(data.playerId || '') === CURRENT_USER_ID) {
            // We won the buzz — navigate to answer page with buzzed=true flag
            redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 150);
        } else {
            handleOpponentBuzz();
        }
    }
    
    function handleAnswerRevealed(data) {
        console.log('[DuoQuestion] Réponse révélée:', data);
        
        if (data.isCorrect !== undefined && data.pointsEarned !== undefined) {
            showResult(data.isCorrect, data.pointsEarned);
        }
        
        if (data.playerScore !== undefined) {
            updateScores(data.playerScore, undefined);
        }
        if (data.opponentScore !== undefined) {
            updateScores(undefined, data.opponentScore);
        }

        if (data.skillsTriggered && data.skillsTriggered.length > 0) {
            data.skillsTriggered.forEach(function(triggered) {
                const isMe = String(triggered.playerId) === CURRENT_USER_ID;
                if (triggered.effect === 'score_shield') {
                    showSkillMessage(isMe ? '{{ __("Bouclier: pénalité bloquée!") }}' : '{{ __("Bouclier adverse: pénalité bloquée!") }}', 'info', 4000);
                } else if (triggered.effect === 'double_points') {
                    showSkillMessage(isMe ? '{{ __("Points doublés!") }}' : '{{ __("Double points adverse!") }}', 'success', 4000);
                }
            });
        }

        if (data.didYouKnow) {
            showDidYouKnow(data.didYouKnow);
        }
    }
    
    function handleScoreUpdate(data) {
        console.log('[DuoQuestion] Mise à jour des scores:', data);
        
        if (data.playerId && data.score !== undefined) {
            if (String(data.playerId) === CURRENT_USER_ID) {
                updateScores(data.score, undefined);
            } else {
                updateScores(undefined, data.score);
            }
        } else if (data.scores) {
            Object.entries(data.scores).forEach(([playerId, score]) => {
                if (String(playerId) === CURRENT_USER_ID) {
                    updateScores(score, undefined);
                } else {
                    updateScores(undefined, score);
                }
            });
        }
    }
    
    function handleMatchEnded(data) {
        console.log('[DuoQuestion] Match terminé:', data);
        
        stopTimer();
        redirectOnce(MATCH_RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 600);
    }
    
    function handleSkillActivated(data) {
        console.log('[DuoQuestion] Skill activé:', data);
        const isMe = String(data.activatedBy) === CURRENT_USER_ID;
        const isTarget = String(data.targetPlayerId) === CURRENT_USER_ID;

        switch (data.effect) {
            case 'reduce_time':
                showSkillMessage(isTarget ? '{{ __("Temps réduit par l\'adversaire!") }}' : '{{ __("Temps de l\'adversaire réduit!") }}', isTarget ? 'warning' : 'success', 3000);
                break;
            case 'score_shield':
                if (isMe) showSkillMessage('{{ __("Bouclier de score activé!") }}', 'info', 3000);
                break;
            case 'double_points':
                if (isMe) showSkillMessage('{{ __("Points doublés pour ce round!") }}', 'success', 3000);
                break;
            case 'reveal_correct':
                if (isMe) showSkillMessage('{{ __("Réponse correcte révélée!") }}', 'info', 3000);
                break;
            case 'shuffle_answers':
                showSkillMessage(isTarget ? '{{ __("Réponses mélangées!") }}' : '{{ __("Réponses adverses mélangées!") }}', isTarget ? 'warning' : 'success', 3000);
                break;
        }

        if (isMe && data.skillId !== undefined && data.charges !== undefined) {
            updateSkillChargeUI(data.skillId, data.charges);
        }
    }

    function handleSkillFailed(data) {
        console.log('[DuoQuestion] Skill échoué:', data);
        const msgs = {
            no_charges: '{{ __("Plus de charges!") }}',
            not_applicable: '{{ __("Skill non applicable ici.") }}',
            cooldown: '{{ __("Skill en recharge.") }}',
            invalid_target: '{{ __("Cible invalide.") }}'
        };
        showSkillMessage(msgs[data.reason] || '{{ __("Impossible d\'activer ce skill.") }}', 'error', 3000);
    }

    function handleRateLimited(data) {
        console.log('[DuoQuestion] Limité:', data);
        showSkillMessage('{{ __("Action trop rapide – réessayez.") }}', 'warning', 2000);
    }

    function updateSkillChargeUI(skillId, charges) {
        const skillEl = document.querySelector('[data-skill-id="' + skillId + '"]');
        if (!skillEl) return;
        if (charges <= 0) {
            skillEl.classList.remove('active');
            skillEl.classList.add('depleted');
            skillEl.style.opacity = '0.4';
        }
    }

    function renderActiveEffects(activeEffects) {
        let panel = document.getElementById('active-effects-panel');
        if (!panel) {
            panel = document.createElement('div');
            panel.id = 'active-effects-panel';
            panel.style.cssText = 'position:fixed;top:10px;right:10px;z-index:8000;display:flex;flex-direction:column;gap:6px;';
            document.body.appendChild(panel);
        }
        panel.innerHTML = '';
        activeEffects.forEach(function(eff) {
            const isMe = String(eff.activatedBy) === CURRENT_USER_ID;
            const labels = {
                score_shield: '{{ __("Bouclier") }}',
                double_points: '{{ __("x2 Points") }}',
                reduce_time: '{{ __("Temps réduit") }}',
                reveal_correct: '{{ __("Oracle") }}',
                shuffle_answers: '{{ __("Chaos") }}'
            };
            const colors = { score_shield:'#3498DB', double_points:'#2ECC71', reduce_time:'#E74C3C', reveal_correct:'#9B59B6', shuffle_answers:'#E67E22' };
            const badge = document.createElement('div');
            badge.style.cssText = 'padding:4px 10px;border-radius:20px;font-size:12px;font-weight:bold;color:#fff;background:' + (colors[eff.effect] || '#555') + ';opacity:' + (isMe ? '1' : '0.7') + ';';
            badge.textContent = (isMe ? '' : '⚔ ') + (labels[eff.effect] || eff.effect);
            panel.appendChild(badge);
        });
        panel.style.display = activeEffects.length === 0 ? 'none' : 'flex';
    }

    function showDidYouKnow(text) {
        const div = document.createElement('div');
        div.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);max-width:600px;width:90%;padding:14px 20px;border-radius:12px;background:linear-gradient(135deg,#8E44AD,#6C3483);color:#fff;font-size:14px;z-index:9998;box-shadow:0 4px 16px rgba(0,0,0,0.4);';
        div.innerHTML = '<strong>{{ __("Le saviez-vous?") }}</strong> ' + text;
        document.body.appendChild(div);
        setTimeout(function() { div.style.transition = 'opacity 0.5s'; div.style.opacity = '0'; }, 5500);
        setTimeout(function() { div.remove(); }, 6100);
    }

    function showSkillMessage(message, type, duration = 3000) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'skill-message skill-message-' + type;
        msgDiv.innerHTML = message;
        msgDiv.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 10px; font-weight: bold; z-index: 9999; animation: fadeInOut ' + (duration / 1000) + 's ease-in-out;';
        
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
    
    async function loadQuestionFromServer() {
        try {
            const response = await fetch('/game/duo/fetch-question', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            const data = await response.json();
            
            if (data.success && data.question) {
                updateQuestionUI(data.question, data.current_question ? (Number(data.current_question) - 1) : null, data.total_questions);
                return true;
            }
        } catch (fetchError) {
            console.error('[DuoQuestion] Erreur chargement question:', fetchError);
        }
        return false;
    }
    
    // Socket handler registration deferred to the scripts section (after DuoSocketClient.js loads)

    buzzButton.addEventListener('click', handleBuzz);
    
    document.addEventListener('keydown', function(e) {
        if (e.code === 'Space' || e.key === ' ') {
            e.preventDefault();
            if (!buzzButton.disabled && !buzzed && !isRedirecting && currentPhase === 'QUESTION_ACTIVE') {
                handleBuzz();
            }
        }
    });
    
    document.querySelectorAll('.skill-circle.active').forEach(skill => {
        skill.addEventListener('click', function() {
            const skillId = this.getAttribute('data-skill-id');
            if (!skillId) return;
            
            if (this.classList.contains('used') || this.classList.contains('depleted')) {
                showSkillMessage('⚪ {{ __("Skill déjà utilisé") }}', 'error');
                return;
            }
            
            if (window.DuoSocketClient && window.DuoSocketClient.isConnected()) {
                window.DuoSocketClient.useSkill(skillId);
            } else {
                showSkillMessage('❌ {{ __("Non connecté au serveur") }}', 'error');
            }
        });
    });
    
    applyPhaseVisualState();
    
    window.addEventListener('beforeunload', () => {
        if (duoSocket && duoSocket.isConnected()) {
            // REMOVED disconnect (shared socket lifecycle)
        }
    });
})();
</script>

<script type="text/plain" id="voice-firebase-module-disabled">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getFirestore, doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, getDoc, setDoc, serverTimestamp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "{{ config('services.firebase.api_key', 'AIzaSyC2D2lVq3D_lRFM3kvbLmLUFJpv8Dh35qU') }}",
    authDomain: "{{ config('services.firebase.project_id', 'strategybuzzer') }}.firebaseapp.com",
    projectId: "{{ config('services.firebase.project_id', 'strategybuzzer') }}",
    storageBucket: "{{ config('services.firebase.project_id', 'strategybuzzer') }}.appspot.com",
    messagingSenderId: "{{ config('services.firebase.messaging_sender_id', '681234567890') }}",
    appId: "{{ config('services.firebase.app_id', '1:681234567890:web:abc123') }}"
};

const app = initializeApp(firebaseConfig, 'voice-chat-app');
const db = getFirestore(app);
window.voiceChatDb = db;
window.voiceChatFirebase = { doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, getDoc, setDoc, serverTimestamp };
</script>

<script type="text/plain" id="voicechat-script-disabled" src="{{ asset('js/VoiceChat.js') }}"></script>

<script type="text/plain" id="voicechat-inline-disabled">
(function() {
    'use strict';
    
    let voiceChat = null;
    let isMicActive = false;
    const VOICE_LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const CURRENT_PLAYER_ID = {{ auth()->id() ?? 0 }};
    
    const micButton = document.getElementById('voiceMicButton');
    const micIcon = document.getElementById('micIcon');
    
    function updateMicButtonState(active) {
        isMicActive = active;
        if (active) {
            micButton.classList.add('active');
            micButton.classList.remove('muted');
            micIcon.textContent = '🎤';
        } else {
            micButton.classList.remove('active');
            micButton.classList.add('muted');
            micIcon.textContent = '🔇';
        }
    }
    
    async function toggleMicrophone() {
        if (!voiceChat) {
            console.log('[VoiceChat] Voice chat not initialized');
            return;
        }
       

@endsection

@section('scripts')
<script>
(function() {
    var ds = window.DuoSocketClient;
    if (ds) {
        ds.on('game_state', handleGameState);
        ds.on('phase_changed', handlePhaseChanged);
        ds.on('question_published', handleQuestionPublished);
        ds.on('buzz_winner', handleBuzzWinner);
        ds.on('buzz_result', handleBuzzWinner);
        ds.on('answer_revealed', handleAnswerRevealed);
        ds.on('score_update', handleScoreUpdate);
        ds.on('match_ended', handleMatchEnded);
        ds.on('skill_activated', handleSkillActivated);
        ds.on('skill_failed', handleSkillFailed);
        ds.on('rate_limited', handleRateLimited);
    } else {
        console.warn('[DuoQuestion] DuoSocketClient unavailable — falling back to HTTP question load');
        if (typeof loadQuestionFromServer === 'function') loadQuestionFromServer();
    }
})();
</script>
@endsection
