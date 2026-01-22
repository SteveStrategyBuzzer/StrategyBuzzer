@extends('layouts.app')

@push('head')
{{-- Préchargement des ressources critiques de la page question --}}
<link rel="prefetch" href="{{ asset('images/buzzer.png') }}" as="image">
<link rel="prefetch" href="{{ asset('sounds/buzzer_default_1.mp3') }}" as="audio">
<link rel="prefetch" href="{{ asset('sounds/no_buzz.mp3') }}" as="audio">
<link rel="preload" href="{{ asset('images/buzzer.png') }}" as="image">
@endpush

@section('content')
@php
$mode = $params['mode'] ?? 'duo';
$theme = $params['theme'] ?? 'Culture générale';
$nbQuestions = $params['nb_questions'] ?? 10;
$playerName = $params['player_name'] ?? 'Joueur 1';
$playerAvatar = $params['player_avatar'] ?? 'default';
$opponentName = $params['opponent_name'] ?? 'Joueur 2';
$opponentAvatar = $params['opponent_avatar'] ?? 'default';
$playerDivision = $params['player_division'] ?? 'Bronze';
$opponentDivision = $params['opponent_division'] ?? 'Bronze';
$redirectUrl = $params['redirect_url'] ?? route('game.question', ['mode' => $mode]);

$playerId = auth()->id();
$opponentId = $params['opponent_id'] ?? null;
$matchId = $params['match_id'] ?? null;
$sessionId = $params['session_id'] ?? $matchId;
$isHost = $params['is_host'] ?? false;

$themeIcons = [
    'Culture générale' => '🧠',
    'Géographie' => '🌐',
    'Histoire' => '📜',
    'Art' => '🎨',
    'Cinéma' => '🎬',
    'Sport' => '🏅',
    'Cuisine' => '🍳',
    'Animaux' => '🦁',
    'Sciences' => '🔬',
];
$themeIcon = $themeIcons[$theme] ?? '❓';
$themeDisplay = $theme === 'Culture générale' ? __('Général') : __($theme);

$modeLabels = [
    'duo' => 'Duo',
    'league_individual' => 'Ligue',
    'league_team' => 'Ligue Équipe',
    'master' => 'Maître du Jeu',
];
$modeLabel = $modeLabels[$mode] ?? ucfirst($mode);
@endphp

<style>
body { 
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); 
    color: #fff; 
    min-height: 100vh;
    overflow: hidden;
    margin: 0;
}

.intro-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
    text-align: center;
}

.title-section {
    margin-bottom: 30px;
    animation: fadeInDown 0.8s ease;
}

.title-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 0 4px 15px rgba(0,0,0,0.5);
    margin-bottom: 10px;
}

.mode-badge {
    display: inline-block;
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.theme-badge {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 10px 25px;
    border-radius: 30px;
    font-size: 1.2rem;
    animation: fadeIn 1s ease 0.3s both;
}

.versus-section {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 40px;
    margin: 30px 0;
    animation: fadeIn 1s ease 0.5s both;
}

.player-card {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 24px;
    padding: 30px;
    min-width: 200px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.player-card.left {
    border-color: rgba(40, 167, 69, 0.5);
    box-shadow: 0 0 30px rgba(40, 167, 69, 0.2);
}

.player-card.right {
    border-color: rgba(255, 107, 107, 0.5);
    box-shadow: 0 0 30px rgba(255, 107, 107, 0.2);
}

.player-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255,255,255,0.3);
    margin-bottom: 15px;
}

.player-card.left .player-avatar {
    border-color: #28a745;
}

.player-card.right .player-avatar {
    border-color: #ff6b6b;
}

.player-name {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 8px;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.player-division {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    background: rgba(255,255,255,0.1);
    padding: 5px 15px;
    border-radius: 20px;
}

.versus-text {
    font-size: 3rem;
    font-weight: 900;
    color: #ffd700;
    text-shadow: 0 0 20px rgba(255,215,0,0.5);
    animation: pulse 1.5s ease infinite;
}

.info-row {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-top: 30px;
    animation: fadeIn 1s ease 0.7s both;
}

.info-badge {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    padding: 12px 24px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.countdown-section {
    margin-top: 40px;
    animation: fadeIn 1s ease 1s both;
}

.countdown-text {
    font-size: 1.5rem;
    margin-bottom: 15px;
    color: rgba(255,255,255,0.8);
}

.countdown-number {
    font-size: 6rem;
    font-weight: 900;
    color: #ffd700;
    text-shadow: 0 0 30px rgba(255,215,0,0.6);
    animation: countdownPulse 1s ease infinite;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes countdownPulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.15); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

/* Mobile portrait */
@media (max-width: 768px) and (orientation: portrait) {
    .intro-container {
        padding: 15px 10px;
        justify-content: flex-start;
        padding-top: 30px;
    }
    
    .title-section h1 {
        font-size: 1.5rem;
    }
    
    .theme-badge {
        font-size: 0.9rem;
        padding: 6px 16px;
    }
    
    .versus-section {
        flex-direction: row;
        gap: 15px;
        margin: 15px 0;
    }
    
    .versus-text {
        font-size: 1.5rem;
    }
    
    .player-card {
        min-width: 120px;
        max-width: 140px;
        padding: 15px 10px;
    }
    
    .player-avatar {
        width: 70px;
        height: 70px;
    }
    
    .player-name {
        font-size: 0.95rem;
    }
    
    .player-division {
        font-size: 0.75rem;
    }
    
    .countdown-number {
        font-size: 5rem;
    }
}

/* Mobile landscape */
@media (max-width: 768px) and (orientation: landscape) {
    .versus-section {
        gap: 20px;
    }
    
    .versus-text {
        font-size: 1.8rem;
    }
    
    .player-card {
        min-width: 140px;
        padding: 15px;
    }
    
    .player-avatar {
        width: 60px;
        height: 60px;
    }
    
    .countdown-number {
        font-size: 4rem;
    }
    
    .title-section h1 {
        font-size: 1.5rem;
    }
}
</style>

<div class="intro-container">
    <div class="title-section">
        <div class="mode-badge">{{ $modeLabel }}</div>
        <h1>🎮 {{ __('Ladies and Gentlemen') }} 🎮</h1>
        <div class="theme-badge">
            <span>{{ $themeIcon }}</span> {{ $themeDisplay }}
        </div>
    </div>
    
    <div class="versus-section">
        <div class="player-card left">
            @if(str_contains($playerAvatar, '/'))
                <img src="{{ asset($playerAvatar) }}" alt="{{ $playerName }}" class="player-avatar">
            @else
                <img src="{{ asset("images/avatars/standard/{$playerAvatar}.png") }}" alt="{{ $playerName }}" class="player-avatar">
            @endif
            <div class="player-name">{{ $playerName }}</div>
            <div class="player-division">{{ $playerDivision }}</div>
        </div>
        
        <div class="versus-text">VS</div>
        
        <div class="player-card right">
            @if(str_contains($opponentAvatar, '/'))
                <img src="{{ asset($opponentAvatar) }}" alt="{{ $opponentName }}" class="player-avatar">
            @else
                <img src="{{ asset("images/avatars/standard/{$opponentAvatar}.png") }}" alt="{{ $opponentName }}" class="player-avatar">
            @endif
            <div class="player-name">{{ $opponentName }}</div>
            <div class="player-division">{{ $opponentDivision }}</div>
        </div>
    </div>
    
    <div class="info-row">
        <div class="info-badge">
            <span class="icon">📝</span>
            <span class="text">{{ $nbQuestions }} {{ __('questions') }}</span>
        </div>
        <div class="info-badge">
            <span class="icon">🏆</span>
            <span class="text">{{ __('Best of 3') }}</span>
        </div>
    </div>
    
    <div class="countdown-section">
        <div class="countdown-text" id="countdownText">{{ __('Préparation') }}...</div>
        <div class="countdown-number" id="countdown">VS</div>
    </div>
</div>

<audio id="readyAudio" preload="auto">
    <source src="{{ asset('sounds/ready_announcement.mp3') }}" type="audio/mpeg">
</audio>

<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-firestore-compat.js"></script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<script>
(function() {
    const redirectUrl = @json($redirectUrl);
    const sessionId = @json($sessionId);
    const playerId = @json($playerId);
    const isHost = @json($isHost);
    const mode = @json($mode);
    const matchId = @json($params['match_id'] ?? null);
    const roomId = @json($params['room_id'] ?? null);
    const lobbyCode = @json($params['lobby_code'] ?? null);
    const jwtToken = @json($params['jwt_token'] ?? null);
    
    let redirected = false;
    let db = null;
    let unsubscribe = null;
    let questionPrefetched = false;
    let socketConnected = false;
    
    const VS_DISPLAY_TIME = 3000;
    const COUNTDOWN_NUMBERS = [3, 2, 1];
    const SYNC_TIMEOUT = 10000;
    
    async function initFirebase() {
        if (typeof firebase === 'undefined') return false;
        
        try {
            const firebaseConfig = {
                projectId: @json(config('services.firebase.project_id')),
                apiKey: "{{ config('services.firebase.api_key', '') }}"
            };
            
            if (!firebaseConfig.projectId) return false;
            
            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }
            
            if (!firebase.auth().currentUser) {
                await firebase.auth().signInAnonymously();
            }
            
            db = firebase.firestore();
            return true;
        } catch (err) {
            console.warn('Firebase init failed:', err.message);
            return false;
        }
    }
    
    async function prefetchFirstQuestion() {
        if (questionPrefetched || !matchId) return;
        
        try {
            const response = await fetch('/game/' + mode + '/fetch-question', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ match_id: matchId, question_number: 1 })
            });
            
            if (response.ok) {
                questionPrefetched = true;
                console.log('[Intro] First question prefetched');
            }
        } catch (err) {
            console.warn('[Intro] Question prefetch failed:', err.message);
        }
    }
    
    async function warmupSocketConnection() {
        if (socketConnected || !jwtToken || !roomId) {
            console.log('[Intro] Socket warmup skipped - missing credentials');
            return;
        }
        
        try {
            function getGameServerUrl() {
                const hostname = window.location.hostname;
                const protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';
                return `${protocol}//${hostname}:3001`;
            }
            
            const serverUrl = getGameServerUrl();
            console.log('[Intro] Socket warmup starting...', serverUrl);
            
            if (typeof DuoSocketClient !== 'undefined') {
                const warmupSocket = new DuoSocketClient();
                
                warmupSocket.onConnect = () => {
                    socketConnected = true;
                    console.log('[Intro] Socket connected - joining room...');
                    warmupSocket.joinRoom(roomId, lobbyCode, { token: jwtToken });
                    
                    sessionStorage.setItem('duo_socket_warmed', 'true');
                    sessionStorage.setItem('duo_socket_timestamp', Date.now().toString());
                };
                
                warmupSocket.onGameState = (data) => {
                    console.log('[Intro] Game state received from server');
                    sessionStorage.setItem('duo_game_state', JSON.stringify(data));
                };
                
                await warmupSocket.connect(serverUrl, jwtToken);
                console.log('[Intro] Socket warmup complete');
            }
        } catch (err) {
            console.warn('[Intro] Socket warmup failed:', err.message);
        }
    }
    
    async function syncReadyAndStart() {
        const countdownEl = document.getElementById('countdown');
        const countdownText = document.getElementById('countdownText');
        const audio = document.getElementById('readyAudio');
        
        prefetchFirstQuestion();
        warmupSocketConnection();
        
        if (audio) {
            audio.volume = 1.0;
            audio.play().catch(() => {});
        }
        
        const firebaseReady = sessionId && await initFirebase();
        
        if (firebaseReady) {
            try {
                await db.collection('gameSessions').doc(sessionId).set({
                    readyStatus: { [playerId]: true },
                    lastActivity: firebase.firestore.FieldValue.serverTimestamp()
                }, { merge: true });
                
                if (countdownText) countdownText.textContent = "{{ __('Synchronisation des joueurs') }}...";
                
                let syncResolved = false;
                const syncTimeout = setTimeout(() => {
                    if (!syncResolved) {
                        syncResolved = true;
                        console.log('[Intro] Sync timeout - starting anyway');
                        if (unsubscribe) unsubscribe();
                        startCountdownSequence();
                    }
                }, SYNC_TIMEOUT);
                
                unsubscribe = db.collection('gameSessions').doc(sessionId).onSnapshot((doc) => {
                    if (syncResolved) return;
                    
                    const data = doc.data();
                    const readyStatus = data?.readyStatus || {};
                    const readyCount = Object.keys(readyStatus).filter(k => readyStatus[k] === true).length;
                    
                    console.log('[Intro] Ready status:', readyCount, '/ 2');
                    
                    if (readyCount >= 2) {
                        syncResolved = true;
                        clearTimeout(syncTimeout);
                        if (unsubscribe) unsubscribe();
                        
                        if (countdownText) countdownText.textContent = "{{ __('Joueurs synchronisés') }}!";
                        
                        setTimeout(() => {
                            startCountdownSequence();
                        }, 500);
                    }
                });
                
            } catch (err) {
                console.warn('Ready sync failed:', err.message);
                startCountdownSequence();
            }
        } else {
            setTimeout(() => {
                startCountdownSequence();
            }, VS_DISPLAY_TIME);
        }
    }
    
    function startCountdownSequence() {
        const countdownText = document.getElementById('countdownText');
        if (countdownText) countdownText.textContent = "{{ __('La partie commence dans') }}...";
        runCountdown();
    }
    
    function runCountdown() {
        const countdownEl = document.getElementById('countdown');
        let index = 0;
        
        function tick() {
            if (index < COUNTDOWN_NUMBERS.length) {
                if (countdownEl) countdownEl.textContent = COUNTDOWN_NUMBERS[index];
                index++;
                setTimeout(tick, 1000);
            } else {
                if (countdownEl) countdownEl.textContent = '🚀';
                
                setTimeout(() => {
                    if (!redirected) {
                        redirected = true;
                        window.location.href = redirectUrl;
                    }
                }, 500);
            }
        }
        
        tick();
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        syncReadyAndStart();
    });
})();
</script>
@endsection
