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
@endphp

<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        margin: 0;
    }

    .waiting-container {
        max-width: 600px;
        width: 100%;
        text-align: center;
    }

    .title {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .subtitle {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 30px;
    }

    .score-battle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        margin-bottom: 40px;
    }

    .score-player, .score-opponent {
        width: 160px;
        padding: 20px;
        border-radius: 15px;
        backdrop-filter: blur(10px);
    }

    .score-player {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.2) 0%, rgba(78, 205, 196, 0.1) 100%);
        border: 3px solid #4ECDC4;
        box-shadow: 0 10px 40px rgba(78, 205, 196, 0.3);
    }

    .score-opponent {
        background: linear-gradient(145deg, rgba(255, 107, 107, 0.2) 0%, rgba(255, 107, 107, 0.1) 100%);
        border: 3px solid #FF6B6B;
        box-shadow: 0 10px 40px rgba(255, 107, 107, 0.3);
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
        color: #4ECDC4;
    }

    .score-opponent .score-number {
        color: #FF6B6B;
    }

    .vs-divider {
        font-size: 1.2rem;
        font-weight: bold;
        color: #fff;
        background: rgba(255, 255, 255, 0.1);
        padding: 12px;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .status-section {
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .status-title {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .status-row {
        display: flex;
        justify-content: space-between;
        gap: 20px;
    }

    .status-item {
        flex: 1;
        padding: 15px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-size: 1rem;
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
        font-size: 1.5rem;
    }

    .status-text {
        font-size: 0.9rem;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .waiting-animation {
        margin: 20px 0;
    }

    .dots {
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .dot {
        width: 12px;
        height: 12px;
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

    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
    }

    .btn-action {
        padding: 16px 40px;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn-exit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-exit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }

    .btn-continue {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.4);
    }

    .btn-continue:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(78, 205, 196, 0.6);
    }

    .btn-continue:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .btn-continue.ready {
        background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
        box-shadow: 0 5px 20px rgba(46, 204, 113, 0.4);
    }

    .timer-info {
        margin-top: 25px;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.6);
    }

    .timer-count {
        color: #4ECDC4;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .question-info {
        margin-top: 20px;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }

    @media (max-width: 480px) {
        .title {
            font-size: 1.6rem;
        }

        .score-battle {
            gap: 10px;
        }

        .score-player, .score-opponent {
            width: 130px;
            padding: 15px;
        }

        .score-number {
            font-size: 2rem;
        }

        .vs-divider {
            width: 40px;
            height: 40px;
            font-size: 1rem;
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
</style>

<div class="waiting-container">
    <h1 class="title" id="pageTitle">{{ __('Prêt pour la suite ?') }}</h1>
    <p class="subtitle">{{ __('Question') }} {{ $currentQuestion }} / {{ $totalQuestions }}</p>

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
        <button class="btn-action btn-exit" onclick="exitMatch()">
            {{ __('Sortir') }}
        </button>
        <button class="btn-action btn-continue" id="continueBtn" onclick="markAsReady()">
            {{ __('Continuer') }}
        </button>
    </div>

    <div class="timer-info" id="timerInfo" style="display: none;">
        {{ __('Redirection automatique dans') }} <span class="timer-count" id="timerCount">15</span>s
    </div>

    <div class="question-info">
        {{ __('Prochaine question') }}: {{ $currentQuestion + 1 }} / {{ $totalQuestions }}
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
        
        document.getElementById('pageTitle').textContent = "{{ __('En attente...') }}";
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
        
        if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady && MATCH_ID) {
            FirebaseGameSync.setPlayerReady(CURRENT_QUESTION + 1).then(function() {
                console.log('[DuoWaiting] Ready status sent');
                checkBothReady();
            }).catch(function(error) {
                console.error('[DuoWaiting] Error sending ready status:', error);
                checkBothReady();
            });
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

    function initFirebase() {
        if (typeof FirebaseGameSync !== 'undefined' && MATCH_ID) {
            FirebaseGameSync.init({
                matchId: MATCH_ID,
                mode: 'duo',
                laravelUserId: PLAYER_ID,
                csrfToken: CSRF_TOKEN,
                callbacks: {
                    onReady: function() {
                        console.log('[DuoWaiting] Firebase ready');
                    },
                    onPhaseChange: function(phase, data) {
                        console.log('[DuoWaiting] Phase changed:', phase, data);
                        handleOpponentReady(data);
                    },
                    onOpponentDisconnect: function(opponentId, info) {
                        console.log('[DuoWaiting] Opponent disconnected:', opponentId);
                    }
                }
            }).catch(function(error) {
                console.error('[DuoWaiting] Firebase init failed:', error);
            });
        }
    }

    initFirebase();

    setTimeout(function() {
        if (!playerReady) {
            console.log('[DuoWaiting] Auto-timeout: marking as ready');
            window.markAsReady();
        }
    }, 60000);

    window.addEventListener('beforeunload', function() {
        if (typeof FirebaseGameSync !== 'undefined') {
            FirebaseGameSync.cleanup();
        }
    });
})();
</script>
@endsection
