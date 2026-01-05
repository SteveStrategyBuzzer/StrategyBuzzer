@extends('layouts.app')

@section('content')
@php
$matchId = $params['match_id'] ?? null;
$roomCode = $params['room_code'] ?? null;
$currentQuestion = $params['current_question'] ?? 1;
$totalQuestions = $params['total_questions'] ?? 10;

$playerInfo = $params['player_info'] ?? [];
$playerName = $playerInfo['name'] ?? __('Joueur');
$playerScore = $playerInfo['score'] ?? 0;
$playerLevel = $playerInfo['level'] ?? 1;
$playerAvatar = $playerInfo['avatar'] ?? 'default';

if (strpos($playerAvatar, 'http://') === 0 || strpos($playerAvatar, 'https://') === 0 || strpos($playerAvatar, '//') === 0) {
    $playerAvatarPath = $playerAvatar;
} elseif (strpos($playerAvatar, 'images/') === 0) {
    $playerAvatarPath = asset($playerAvatar);
} elseif (strpos($playerAvatar, '/') !== false && strpos($playerAvatar, '.png') === false) {
    $playerAvatarPath = asset("images/avatars/{$playerAvatar}.png");
} elseif (strpos($playerAvatar, '/') !== false) {
    $playerAvatarPath = asset($playerAvatar);
} else {
    $playerAvatarPath = asset("images/avatars/standard/{$playerAvatar}.png");
}

$opponentInfo = $params['opponent_info'] ?? [];
$opponentName = $opponentInfo['name'] ?? __('Adversaire');
$opponentScore = $opponentInfo['score'] ?? 0;
$opponentLevel = $opponentInfo['level'] ?? 1;
$opponentAvatar = $opponentInfo['avatar'] ?? 'default';

if (strpos($opponentAvatar, 'http://') === 0 || strpos($opponentAvatar, 'https://') === 0 || strpos($opponentAvatar, '//') === 0) {
    $opponentAvatarPath = $opponentAvatar;
} elseif (strpos($opponentAvatar, 'images/') === 0) {
    $opponentAvatarPath = asset($opponentAvatar);
} elseif (strpos($opponentAvatar, '/') !== false && strpos($opponentAvatar, '.png') === false) {
    $opponentAvatarPath = asset("images/avatars/{$opponentAvatar}.png");
} elseif (strpos($opponentAvatar, '/') !== false) {
    $opponentAvatarPath = asset($opponentAvatar);
} else {
    $opponentAvatarPath = asset("images/avatars/standard/{$opponentAvatar}.png");
}

$question = $params['question'] ?? [];
$questionText = $question['text'] ?? '';
$questionTheme = $question['theme'] ?? '';
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
    
    .question-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        font-size: 0.85rem;
        color: #4ECDC4;
        opacity: 0.9;
    }
    
    .question-number {
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .question-theme {
        font-style: italic;
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
    
    .left-column, .right-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
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
    
    .player-level {
        font-size: 0.85rem;
        color: #4ECDC4;
        opacity: 0.8;
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
    
    .opponent-name {
        font-size: 1rem;
        font-weight: 600;
        color: #FF6B6B;
    }
    
    .opponent-level {
        font-size: 0.85rem;
        color: #FF6B6B;
        opacity: 0.8;
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
    
    .opponent-buzzed-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 107, 107, 0.2);
        display: flex;
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
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @media (max-width: 1024px) {
        .game-layout {
            gap: 20px;
        }
        
        .player-avatar, .opponent-avatar {
            width: 85px;
            height: 85px;
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
        
        .player-avatar, .opponent-avatar {
            width: 70px;
            height: 70px;
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
        
        .buzz-button img {
            width: 150px;
            height: 150px;
        }
        
        .question-text {
            font-size: 1.2rem;
        }
    }
    
    @media (max-width: 480px) {
        .player-avatar, .opponent-avatar {
            width: 60px;
            height: 60px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.4rem;
        }
        
        .player-name, .opponent-name {
            font-size: 0.85rem;
        }
        
        .player-level, .opponent-level {
            font-size: 0.75rem;
        }
        
        .chrono-circle {
            width: 120px;
            height: 120px;
        }
        
        .chrono-time {
            font-size: 2.5rem;
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
        
        .player-avatar, .opponent-avatar {
            width: 60px;
            height: 60px;
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
        
        .buzz-button img {
            width: 110px;
            height: 110px;
        }
        
        .buzz-container-bottom {
            bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        }
    }
</style>

<div class="game-container">
    <div class="question-header">
        <div class="question-info">
            <span class="question-number">{{ __('Question') }} {{ $currentQuestion }}/{{ $totalQuestions }}</span>
            @if($questionTheme)
                <span class="question-theme">{{ $questionTheme }}</span>
            @endif
        </div>
        <div class="question-text">{{ $questionText }}</div>
    </div>
    
    <div class="game-layout">
        <div class="left-column">
            <div class="player-circle">
                <img src="{{ $playerAvatarPath }}" alt="{{ __('Votre avatar') }}" class="player-avatar">
                <div class="player-name">{{ $playerName }}</div>
                <div class="player-level">{{ __('Niveau') }} {{ $playerLevel }}</div>
                <div class="player-score" id="playerScore">{{ $playerScore }}</div>
            </div>
        </div>
        
        <div class="center-column">
            <div class="chrono-circle">
                <div class="chrono-time" id="chronoTimer">8</div>
            </div>
        </div>
        
        <div class="right-column">
            <div class="opponent-circle">
                <img src="{{ $opponentAvatarPath }}" alt="{{ __('Avatar adversaire') }}" class="opponent-avatar">
                <div class="opponent-name">{{ $opponentName }}</div>
                <div class="opponent-level">{{ __('Niveau') }} {{ $opponentLevel }}</div>
                <div class="opponent-score" id="opponentScore">{{ $opponentScore }}</div>
            </div>
        </div>
    </div>
    
    <div class="buzz-container-bottom buzzer-waiting" id="buzzContainer">
        <button id="buzzButton" class="buzz-button" disabled>
            <img src="{{ asset('images/buzzer.png') }}" alt="{{ __('Buzzer') }}">
        </button>
    </div>
</div>

<div id="opponentBuzzedOverlay" class="opponent-buzzed-overlay" style="display: none;">
    <div class="opponent-buzzed-message">
        <h2>🔔 {{ __('Adversaire a buzzé !') }}</h2>
        <p>{{ __('En attente de sa réponse...') }}</p>
    </div>
</div>

<audio id="buzzerSound" preload="auto">
    <source id="buzzerSource" src="{{ asset('sounds/buzzer_default_1.mp3') }}" type="audio/mpeg">
</audio>

<audio id="noBuzzSound" preload="auto">
    <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
</audio>

<script>
(function() {
    const MATCH_ID = @json($matchId);
    const ROOM_CODE = @json($roomCode);
    const CURRENT_QUESTION = @json($currentQuestion);
    const PLAYER_ID = @json(auth()->id());
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    let timeLeft = 8;
    let timerInterval = null;
    let hasBuzzed = false;
    let opponentHasBuzzed = false;
    let isRedirecting = false;
    
    const chronoTimer = document.getElementById('chronoTimer');
    const buzzContainer = document.getElementById('buzzContainer');
    const buzzButton = document.getElementById('buzzButton');
    const buzzerSound = document.getElementById('buzzerSound');
    const noBuzzSound = document.getElementById('noBuzzSound');
    const opponentBuzzedOverlay = document.getElementById('opponentBuzzedOverlay');
    
    function setBuzzerState(state) {
        buzzContainer.classList.remove('buzzer-waiting', 'buzzer-ready', 'buzzer-hidden');
        buzzContainer.classList.add('buzzer-' + state);
        buzzButton.disabled = (state !== 'ready');
    }
    
    function startTimer() {
        setBuzzerState('ready');
        
        timerInterval = setInterval(function() {
            timeLeft--;
            chronoTimer.textContent = timeLeft;
            
            if (timeLeft <= 3) {
                chronoTimer.style.color = '#FF6B6B';
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                handleTimeout();
            }
        }, 1000);
    }
    
    function handleTimeout() {
        if (isRedirecting) return;
        isRedirecting = true;
        
        setBuzzerState('hidden');
        
        if (noBuzzSound) {
            noBuzzSound.play().catch(function() {});
        }
        
        setTimeout(function() {
            window.location.href = '/game/duo/answer?timeout=true&match_id=' + MATCH_ID;
        }, 500);
    }
    
    function handlePlayerBuzz() {
        if (hasBuzzed || opponentHasBuzzed || isRedirecting) return;
        
        hasBuzzed = true;
        clearInterval(timerInterval);
        setBuzzerState('hidden');
        
        if (buzzerSound) {
            buzzerSound.play().catch(function() {});
        }
        
        if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
            FirebaseGameSync.sendBuzz(Date.now());
        }
        
        isRedirecting = true;
        setTimeout(function() {
            window.location.href = '/game/duo/answer?buzzed=true&match_id=' + MATCH_ID;
        }, 300);
    }
    
    function handleOpponentBuzz(opponentId, buzzTime) {
        if (opponentHasBuzzed || hasBuzzed || isRedirecting) return;
        
        opponentHasBuzzed = true;
        clearInterval(timerInterval);
        setBuzzerState('hidden');
        
        opponentBuzzedOverlay.style.display = 'flex';
        
        isRedirecting = true;
        setTimeout(function() {
            window.location.href = '/game/duo/answer?opponent_buzzed=true&match_id=' + MATCH_ID;
        }, 1500);
    }
    
    buzzButton.addEventListener('click', handlePlayerBuzz);
    
    document.addEventListener('keydown', function(e) {
        if (e.code === 'Space' || e.key === ' ') {
            e.preventDefault();
            if (!buzzButton.disabled) {
                handlePlayerBuzz();
            }
        }
    });
    
    if (MATCH_ID && typeof FirebaseGameSync !== 'undefined') {
        FirebaseGameSync.init({
            matchId: MATCH_ID,
            mode: 'duo',
            laravelUserId: PLAYER_ID,
            csrfToken: CSRF_TOKEN,
            callbacks: {
                onReady: function() {
                    console.log('[DuoQuestion] Firebase ready');
                    startTimer();
                },
                onBuzz: function(buzzWinnerRole, buzzTime, data, isOpponentBuzz) {
                    if (isOpponentBuzz && !hasBuzzed) {
                        handleOpponentBuzz(data.buzzWinnerLaravelId, buzzTime);
                    }
                },
                onPhaseChange: function(phase, data) {
                    console.log('[DuoQuestion] Phase changed:', phase);
                    if (phase === 'answering' && data.buzzWinnerLaravelId && data.buzzWinnerLaravelId !== PLAYER_ID) {
                        handleOpponentBuzz(data.buzzWinnerLaravelId, data.buzzTime);
                    }
                },
                onOpponentDisconnect: function(opponentId, info) {
                    console.log('[DuoQuestion] Opponent disconnected:', opponentId);
                }
            }
        }).catch(function(error) {
            console.error('[DuoQuestion] Firebase init failed:', error);
            startTimer();
        });
    } else {
        setTimeout(startTimer, 500);
    }
    
    window.addEventListener('beforeunload', function() {
        if (typeof FirebaseGameSync !== 'undefined') {
            FirebaseGameSync.cleanup();
        }
    });
})();
</script>

<script src="{{ asset('js/firebase-game-sync.js') }}"></script>
@endsection
