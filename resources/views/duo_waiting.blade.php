@extends('layouts.app')

@section('content')
@php
$matchId = $params['match_id'] ?? null;
$roomCode = $params['room_code'] ?? null;
$currentQuestion = $params['current_question'] ?? 1;
$totalQuestions = $params['total_questions'] ?? 10;

$playerInfo = $params['player_info'] ?? [];
$playerName = $playerInfo['name'] ?? __('Joueur');
$playerScore = $params['score'] ?? ($playerInfo['score'] ?? 0);

$opponentInfo = $params['opponent_info'] ?? [];
$opponentName = $opponentInfo['name'] ?? __('Adversaire');
$opponentScore = $params['opponent_score'] ?? ($opponentInfo['score'] ?? 0);

$lastAnswer = $params['last_answer'] ?? null;
$correctAnswer = $params['correct_answer'] ?? '';
$wasCorrect = $params['was_correct'] ?? false;
$didYouKnow = $params['did_you_know'] ?? '';
$skills = $params['skills'] ?? [];
$avatarName = $params['avatar_name'] ?? 'Magicienne';
$stats = $params['stats'] ?? [
    'score_match' => $playerScore,
    'lives' => 3,
    'question' => $currentQuestion,
    'no_answer' => 0,
    'correct' => 0,
    'wrong' => 0
];
@endphp

<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        overflow-y: auto;
        overflow-x: hidden;
        margin: 0;
    }

    .waiting-container {
        max-width: 800px;
        width: 100%;
        text-align: center;
        padding: 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        max-height: 100vh;
    }

    .opponent-header {
        margin-bottom: 15px;
        padding: 12px;
        background: rgba(102, 126, 234, 0.2);
        border-radius: 12px;
        border: 2px solid rgba(102, 126, 234, 0.4);
    }

    .opponent-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: #667eea;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .score-battle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        animation: fadeIn 0.8s ease-out;
        flex-shrink: 0;
    }

    .score-player, .score-opponent {
        width: 150px;
        min-width: 150px;
        flex-shrink: 0;
        padding: 15px;
        border-radius: 15px;
        position: relative;
        backdrop-filter: blur(10px);
    }

    .score-player {
        background: linear-gradient(145deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        border: 3px solid #667eea;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }

    .score-opponent {
        background: linear-gradient(145deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        border: 3px solid #667eea;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }

    .score-label {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .score-number {
        font-size: 2.5rem;
        font-weight: 900;
        line-height: 1;
    }

    .score-player .score-number {
        color: #667eea;
    }

    .score-opponent .score-number {
        color: #667eea;
    }

    .vs-divider {
        font-size: 1.2rem;
        font-weight: bold;
        color: #4ECDC4;
        background: rgba(78, 205, 196, 0.2);
        padding: 10px;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #4ECDC4;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.5);
    }

    .skills-container {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 15px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .skills-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 12px;
        text-align: center;
    }

    .skills-grid {
        display: grid;
        gap: 10px;
    }

    .skill-item {
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(102, 126, 234, 0.3);
        border-radius: 12px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s;
    }

    .skill-item.used {
        background: rgba(255, 215, 0, 0.1);
        border-color: gold;
    }

    .skill-icon {
        font-size: 2rem;
    }

    .skill-info {
        flex: 1;
        text-align: left;
    }

    .skill-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #667eea;
    }

    .skill-desc {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-top: 2px;
    }

    .skill-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .skill-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.5);
    }

    .skill-btn:disabled {
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.5);
        cursor: not-allowed;
    }

    .skill-used-badge {
        background: gold;
        color: #1a1a2e;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .result-answers {
        background: rgba(0,0,0,0.4);
        padding: 15px;
        border-radius: 15px;
        margin-bottom: 15px;
        animation: fadeIn 1s ease-out;
        border: 2px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
    }

    .answer-display {
        padding: 10px 15px;
        border-radius: 12px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        backdrop-filter: blur(5px);
    }

    .answer-display:last-child {
        margin-bottom: 0;
    }

    .answer-correct {
        background: rgba(46, 204, 113, 0.25);
        border: 2px solid #2ECC71;
        box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
    }

    .answer-incorrect {
        background: rgba(231, 76, 60, 0.25);
        border: 2px solid #E74C3C;
        box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
    }

    .answer-label {
        opacity: 0.9;
        font-size: 0.95rem;
        font-weight: 600;
        flex-shrink: 0;
    }

    .answer-text {
        flex: 1;
        text-align: left;
        font-weight: 500;
    }

    .answer-icon {
        font-size: 1.8rem;
    }

    .progress-info {
        background: rgba(0,0,0,0.3);
        border: 2px solid rgba(78, 205, 196, 0.3);
        border-radius: 10px;
        padding: 12px;
        margin-top: 10px;
        margin-bottom: 15px;
        backdrop-filter: blur(10px);
    }

    .stats-columns {
        display: flex;
        gap: 12px;
    }

    .stats-column {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .stats-column.left {
        border-right: 1px solid rgba(78, 205, 196, 0.3);
        padding-right: 12px;
    }

    .stats-column.right {
        padding-left: 0;
    }

    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 8px;
        background: rgba(78, 205, 196, 0.08);
        border-radius: 6px;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #4ECDC4;
        font-weight: 600;
    }

    .stat-value {
        font-size: 0.85rem;
        color: white;
        font-weight: bold;
    }

    .stat-row.no-answer .stat-value {
        color: #F39C12;
    }

    .stat-row.correct .stat-value {
        color: #2ECC71;
    }

    .stat-row.wrong .stat-value {
        color: #E74C3C;
    }

    .did-you-know {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
        backdrop-filter: blur(10px);
    }

    .did-you-know-title {
        font-size: 1rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 10px;
        text-align: center;
    }

    .did-you-know-content {
        font-size: 0.9rem;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.9);
        text-align: center;
        font-style: italic;
    }

    .status-section {
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .status-title {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .status-row {
        display: flex;
        justify-content: space-between;
        gap: 15px;
    }

    .status-item {
        flex: 1;
        padding: 12px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .status-item.player {
        background: rgba(78, 205, 196, 0.1);
        border: 2px solid rgba(78, 205, 196, 0.3);
    }

    .status-item.opponent {
        background: rgba(255, 107, 107, 0.1);
        border: 2px solid rgba(255, 107, 107, 0.3);
    }

    .status-item.ready {
        background: rgba(46, 204, 113, 0.2);
        border-color: #2ECC71;
    }

    .status-item.ready .status-icon {
        color: #2ECC71;
    }

    .status-item.waiting .status-icon {
        color: #F39C12;
        animation: pulse 1.5s infinite;
    }

    .status-icon {
        font-size: 1.3rem;
    }

    .status-text {
        font-size: 0.85rem;
    }

    .waiting-animation {
        margin: 12px 0;
    }

    .dots {
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .dot {
        width: 10px;
        height: 10px;
        background: #4ECDC4;
        border-radius: 50%;
        animation: bounce 1.4s ease-in-out infinite both;
    }

    .dot:nth-child(1) { animation-delay: -0.32s; }
    .dot:nth-child(2) { animation-delay: -0.16s; }
    .dot:nth-child(3) { animation-delay: 0s; }

    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-bottom: 15px;
    }

    .btn-action {
        flex: 1;
        padding: 12px 20px;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn-menu {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-menu:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }

    .btn-go {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.4);
    }

    .btn-go:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(78, 205, 196, 0.6);
    }

    .btn-go:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .btn-go.ready {
        background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
        box-shadow: 0 5px 20px rgba(46, 204, 113, 0.4);
    }

    .next-question-timer {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 15px;
        border-radius: 15px;
        font-size: 1rem;
        border: 2px solid rgba(78, 205, 196, 0.3);
        animation: fadeIn 1.2s ease-out;
    }

    .timer-count {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        display: inline-block;
        margin: 0 5px;
        animation: timerPulse 1s infinite;
    }

    @keyframes timerPulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.15);
        }
    }

    @media (max-width: 768px) {
        .score-battle {
            gap: 15px;
        }

        .score-number {
            font-size: 2.5rem;
        }

        .vs-divider {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }

        .answer-text {
            font-size: 1rem;
        }
    }

    @media (max-width: 480px) {
        .score-battle {
            gap: 12px;
        }

        .score-player, .score-opponent {
            max-width: 140px;
            min-width: 120px;
        }

        .score-label {
            font-size: 0.75rem;
        }

        .score-number {
            font-size: 2rem;
        }

        .action-buttons {
            flex-direction: column;
        }

        .btn-action {
            width: 100%;
        }

        .status-row {
            flex-direction: column;
            gap: 10px;
        }
    }

    @media (max-width: 360px) {
        .stats-columns {
            flex-direction: column;
            gap: 8px;
        }

        .stats-column.left {
            border-right: none;
            border-bottom: 1px solid rgba(78, 205, 196, 0.3);
            padding-right: 0;
            padding-bottom: 8px;
        }

        .stat-label {
            font-size: 0.7rem;
        }
    }

    @media (max-height: 500px) and (orientation: landscape) {
        .waiting-container {
            padding: 10px;
            max-height: 100vh;
            overflow-y: auto;
        }

        .score-battle {
            gap: 12px;
            margin-bottom: 12px;
        }

        .score-number {
            font-size: 2rem;
        }

        .vs-divider {
            width: 45px;
            height: 45px;
            font-size: 1rem;
        }
    }
    
    .voice-mic-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #dc3545;
        border: 2px solid #c82333;
        font-size: 24px;
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
    }
    .voice-mic-btn.active {
        background: #28a745;
        border-color: #1e7e34;
    }
</style>

<button id="voiceMicBtn" class="voice-mic-btn" onclick="toggleVoiceMic()" title="{{ __('Activer/Désactiver le micro') }}">
    🔇
</button>

<div class="waiting-container">
    <div class="opponent-header">
        <h1 class="opponent-name">{{ __('VS') }} {{ $opponentName }}</h1>
    </div>

    <div class="score-battle">
        <div class="score-player">
            <div class="score-label">🎮 {{ __('Vous') }}</div>
            <div class="score-number">{{ $playerScore }}</div>
        </div>
        
        <div class="vs-divider">{{ __('VS') }}</div>
        
        <div class="score-opponent">
            <div class="score-label">🎯 {{ $opponentName }}</div>
            <div class="score-number">{{ $opponentScore }}</div>
        </div>
    </div>

    @if(count($skills) > 0)
    <div class="skills-container">
        <div class="skills-title">✨ {{ __('Compétences') }} {{ $avatarName }} ✨</div>
        <div class="skills-grid">
            @foreach($skills as $skill)
            <div class="skill-item {{ ($skill['used'] ?? false) ? 'used' : '' }}">
                <span class="skill-icon">{{ $skill['icon'] ?? '🔮' }}</span>
                <div class="skill-info">
                    <div class="skill-name">{{ $skill['name'] ?? __('Compétence') }}</div>
                </div>
                @if($skill['used'] ?? false)
                    <span class="skill-used-badge">{{ __('Utilisé') }}</span>
                @else
                    <button class="skill-btn" onclick="activateSkill('{{ $skill['id'] ?? '' }}')">{{ __('Activer') }}</button>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($lastAnswer !== null)
    <div class="result-answers">
        <div class="answer-display {{ $wasCorrect ? 'answer-correct' : 'answer-incorrect' }}">
            <span class="answer-label">{{ __('Votre réponse:') }}</span>
            <span class="answer-text">{{ $lastAnswer }}</span>
            <span class="answer-icon">{{ $wasCorrect ? '✓' : '✗' }}</span>
        </div>
        <div class="answer-display answer-correct">
            <span class="answer-label">{{ __('Bonne réponse:') }}</span>
            <span class="answer-text">{{ $correctAnswer }}</span>
            <span class="answer-icon">✓</span>
        </div>
    </div>
    @endif

    <div class="progress-info">
        <div class="stats-columns">
            <div class="stats-column left">
                <div class="stat-row">
                    <span class="stat-label">{{ __('Score Match') }}</span>
                    <span class="stat-value">{{ $stats['score_match'] ?? $playerScore }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Vie') }}</span>
                    <span class="stat-value">{{ $stats['lives'] ?? 3 }} ❤️</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Question') }}</span>
                    <span class="stat-value">{{ $currentQuestion }} / {{ $totalQuestions }}</span>
                </div>
            </div>
            <div class="stats-column right">
                <div class="stat-row no-answer">
                    <span class="stat-label">{{ __('Sans Réponse') }}</span>
                    <span class="stat-value">{{ $stats['no_answer'] ?? 0 }}</span>
                </div>
                <div class="stat-row correct">
                    <span class="stat-label">{{ __('Bonne') }}</span>
                    <span class="stat-value">{{ $stats['correct'] ?? 0 }}</span>
                </div>
                <div class="stat-row wrong">
                    <span class="stat-label">{{ __('Échec') }}</span>
                    <span class="stat-value">{{ $stats['wrong'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($didYouKnow))
    <div class="did-you-know">
        <div class="did-you-know-title">💡 {{ __('Le saviez-vous ?') }}</div>
        <div class="did-you-know-content">{{ $didYouKnow }}</div>
    </div>
    @endif

    <div class="status-section">
        <div class="status-title">{{ __('Statut des joueurs') }}</div>
        
        <div class="status-row">
            <div class="status-item player waiting" id="playerStatus">
                <span class="status-icon">⏳</span>
                <span class="status-text">{{ __('Vous') }} - {{ __('En attente') }}</span>
            </div>
            
            <div class="status-item opponent waiting" id="opponentStatus">
                <span class="status-icon">⏳</span>
                <span class="status-text">{{ $opponentName }} - {{ __('En attente') }}</span>
            </div>
        </div>

        <div class="waiting-animation" id="waitingAnimation">
            <div class="dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <button class="btn-action btn-menu" onclick="exitMatch()">
            ← {{ __('DUO') }}
        </button>
        <button class="btn-action btn-go" id="continueBtn" onclick="markAsReady()">
            🏃 {{ __('GO') }}
        </button>
    </div>

    <div class="next-question-timer" id="timerInfo" style="display: none;">
        {{ __('Prochaine question dans') }} <span class="timer-count" id="timerCount">15</span> {{ __('secondes...') }}
    </div>
</div>

<script src="{{ asset('js/firebase-game-sync.js') }}"></script>
<script>
(function() {
    const MATCH_ID = @json($matchId);
    const ROOM_CODE = @json($roomCode);
    const CURRENT_QUESTION = @json($currentQuestion);
    const PLAYER_ID = @json(auth()->id());
    const OPPONENT_ID = @json($opponentInfo['id'] ?? '');
    const OPPONENT_NAME = @json($opponentName);
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let playerReady = false;
    let opponentReady = false;
    let autoRedirectTimer = null;
    let countdown = 15;

    function updatePlayerStatusUI() {
        const playerStatusEl = document.getElementById('playerStatus');
        playerStatusEl.classList.remove('waiting');
        playerStatusEl.classList.add('ready');
        playerStatusEl.innerHTML = `
            <span class="status-icon">✓</span>
            <span class="status-text">{{ __('Vous') }} - {{ __('Prêt') }}</span>
        `;
        
        const continueBtn = document.getElementById('continueBtn');
        continueBtn.classList.add('ready');
        continueBtn.disabled = true;
        continueBtn.textContent = "{{ __('Prêt !') }}";
    }

    function updateOpponentStatusUI() {
        const opponentStatusEl = document.getElementById('opponentStatus');
        opponentStatusEl.classList.remove('waiting');
        opponentStatusEl.classList.add('ready');
        opponentStatusEl.innerHTML = `
            <span class="status-icon">✓</span>
            <span class="status-text">${OPPONENT_NAME} - {{ __('Prêt') }}</span>
        `;
    }

    window.markAsReady = function() {
        if (playerReady) return;
        
        playerReady = true;
        updatePlayerStatusUI();
        
        if (typeof DuoSocketClient !== 'undefined' && DuoSocketClient.isConnected() && MATCH_ID) {
            DuoSocketClient.setReady(true);
            console.log('[DuoWaiting] Ready status sent via Socket.IO');
            checkBothReady();
        } else {
            checkBothReady();
        }
    };

    function checkBothReady() {
        if (playerReady && opponentReady) {
            document.getElementById('waitingAnimation').style.display = 'none';
            document.getElementById('timerInfo').style.display = 'block';
            startAutoRedirect();
        }
    }

    function startAutoRedirect() {
        if (autoRedirectTimer) return;
        
        const timerCountEl = document.getElementById('timerCount');
        
        autoRedirectTimer = setInterval(function() {
            countdown--;
            timerCountEl.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(autoRedirectTimer);
                redirectToNextQuestion();
            }
        }, 1000);
    }

    function redirectToNextQuestion() {
        window.location.href = "{{ route('game.question', ['mode' => 'duo']) }}";
    }

    window.exitMatch = function() {
        if (window.customDialog) {
            window.customDialog.confirm("{{ __('Êtes-vous sûr de vouloir quitter le match ?') }}")
                .then(function(confirmed) {
                    if (confirmed) {
                        window.location.href = "{{ route('duo.lobby') }}";
                    }
                });
        } else {
            if (confirm("{{ __('Êtes-vous sûr de vouloir quitter le match ?') }}")) {
                window.location.href = "{{ route('duo.lobby') }}";
            }
        }
    };

    window.activateSkill = function(skillId) {
        console.log('[DuoWaiting] Activating skill:', skillId);
        if (typeof DuoSocketClient !== 'undefined' && DuoSocketClient.isConnected() && MATCH_ID) {
            DuoSocketClient.emit('activate_skill', { skillId: skillId, matchId: MATCH_ID });
        }
    };

    function handleOpponentReady(data) {
        if (opponentReady) return;
        
        const nextQuestion = CURRENT_QUESTION + 1;
        const opponentReadyField = `ready_for_q${nextQuestion}_${OPPONENT_ID}`;
        
        if (data && (data[opponentReadyField] || data.opponentReady === true)) {
            opponentReady = true;
            updateOpponentStatusUI();
            checkBothReady();
        }
    }

    function initSocketIO() {
        if (typeof DuoSocketClient !== 'undefined' && MATCH_ID) {
            const socketUrl = (window.location.protocol === 'https:' ? 'wss://' : 'ws://') + window.location.hostname + ':3001';
            
            DuoSocketClient.connect(socketUrl).then(function() {
                console.log('[DuoWaiting] Socket.IO connected');
                
                DuoSocketClient.joinRoom(MATCH_ID, ROOM_CODE, {
                    playerId: PLAYER_ID,
                    playerName: PLAYER_NAME
                });
            }).catch(function(error) {
                console.error('[DuoWaiting] Socket.IO connect failed:', error);
            });
            
            DuoSocketClient.onPlayerReady = function(data) {
                console.log('[DuoWaiting] Player ready:', data);
                if (data.playerId && data.playerId !== PLAYER_ID && data.isReady) {
                    opponentReady = true;
                    updateOpponentStatusUI();
                    checkBothReady();
                }
            };
            
            DuoSocketClient.onPhaseChanged = function(data) {
                console.log('[DuoWaiting] Phase changed:', data);
                if (data.phase === 'QUESTION_ACTIVE') {
                    redirectToNextQuestion();
                }
            };
            
            DuoSocketClient.onQuestionPublished = function(data) {
                console.log('[DuoWaiting] Question published:', data);
                redirectToNextQuestion();
            };
            
            DuoSocketClient.onPlayerLeft = function(data) {
                console.log('[DuoWaiting] Player left:', data);
            };
        }
    }

    initSocketIO();

    setTimeout(function() {
        if (!playerReady) {
            console.log('[DuoWaiting] Auto-timeout: marking as ready');
            window.markAsReady();
        }
    }, 60000);

    window.addEventListener('beforeunload', function() {
        if (typeof DuoSocketClient !== 'undefined') {
            DuoSocketClient.disconnect();
        }
    });
})();
</script>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<script type="module">
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

<script src="{{ asset('js/VoiceChat.js') }}"></script>

<script>
(function() {
    'use strict';
    
    let voiceChat = null;
    const VOICE_LOBBY_CODE = '{{ $roomCode ?? "" }}';
    const CURRENT_PLAYER_ID = {{ auth()->id() ?? 0 }};
    
    async function initVoiceChat() {
        if (!VOICE_LOBBY_CODE || !window.voiceChatDb) {
            console.log('[VoiceChat] Missing lobby code or Firebase - skipping');
            return;
        }
        
        try {
            voiceChat = new VoiceChat({
                sessionId: VOICE_LOBBY_CODE,
                localUserId: CURRENT_PLAYER_ID,
                mode: 'duo',
                db: window.voiceChatDb,
                onConnectionChange: (state) => updateMicUI(state),
                onError: (error) => console.error('[VoiceChat] Error:', error)
            });
            
            await voiceChat.initialize();
            console.log('[VoiceChat] Initialized successfully');
        } catch (error) {
            console.error('[VoiceChat] Init error:', error);
        }
    }
    
    function updateMicUI(state) {
        const micBtn = document.getElementById('voiceMicBtn');
        if (micBtn) {
            micBtn.classList.toggle('active', !state.muted);
            micBtn.textContent = state.muted ? '🔇' : '🔊';
        }
    }
    
    window.toggleVoiceMic = async function() {
        if (!voiceChat) {
            await initVoiceChat();
        }
        if (voiceChat) {
            await voiceChat.toggleMicrophone();
        }
    };
    
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initVoiceChat, 1000);
    });
    
    window.addEventListener('beforeunload', () => {
        if (voiceChat) voiceChat.cleanup();
    });
})();
</script>
@endsection
