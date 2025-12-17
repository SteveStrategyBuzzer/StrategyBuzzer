@extends('layouts.app')

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

$isDuo = $mode === 'duo';
$isLeague = str_starts_with($mode, 'league');
$needsChat = $isDuo || $isLeague;
$needsMic = $isDuo || $isLeague;
$needsSyncGo = $isDuo || $isLeague;
@endphp

<style>
body { 
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); 
    color: #fff; 
    min-height: 100vh;
    overflow: hidden;
    margin: 0;
}

.resume-container {
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
}

.title-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 0 4px 15px rgba(0,0,0,0.5);
    margin-bottom: 10px;
    animation: fadeInDown 0.8s ease;
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

.info-badge .icon {
    font-size: 1.3rem;
}

.info-badge .text {
    font-size: 1rem;
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

@media (max-width: 768px) {
    .versus-section {
        flex-direction: column;
        gap: 20px;
    }
    
    .versus-text {
        font-size: 2rem;
    }
    
    .player-card {
        min-width: 160px;
        padding: 20px;
    }
    
    .player-avatar {
        width: 90px;
        height: 90px;
    }
    
    .player-name {
        font-size: 1.1rem;
    }
    
    .countdown-number {
        font-size: 4rem;
    }
    
    .title-section h1 {
        font-size: 1.8rem;
    }
}

/* Chat Section */
.chat-section {
    position: fixed;
    bottom: 20px;
    left: 20px;
    width: 300px;
    max-height: 250px;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    border: 2px solid rgba(78, 205, 196, 0.3);
    overflow: hidden;
    z-index: 100;
    display: flex;
    flex-direction: column;
}

.chat-header {
    padding: 10px 15px;
    background: rgba(78, 205, 196, 0.2);
    border-bottom: 1px solid rgba(78, 205, 196, 0.3);
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    max-height: 150px;
}

.chat-message {
    margin-bottom: 8px;
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    max-width: 85%;
}

.chat-message.mine {
    background: rgba(78, 205, 196, 0.3);
    margin-left: auto;
    text-align: right;
}

.chat-message.theirs {
    background: rgba(255, 107, 107, 0.3);
    margin-right: auto;
}

.chat-input-container {
    padding: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    gap: 8px;
}

.chat-input {
    flex: 1;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 8px 15px;
    color: #fff;
    font-size: 0.85rem;
}

.chat-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.chat-send-btn {
    background: linear-gradient(135deg, #4ECDC4 0%, #44a08d 100%);
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

/* Mic Controls */
.mic-section {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 100;
}

.mic-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.3);
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(10px);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.mic-btn.active {
    background: rgba(46, 204, 113, 0.6);
    border-color: #2ecc71;
    animation: mic-pulse 1.5s infinite;
}

.mic-btn.muted {
    background: rgba(231, 76, 60, 0.6);
    border-color: #e74c3c;
}

@keyframes mic-pulse {
    0%, 100% { box-shadow: 0 0 20px rgba(46, 204, 113, 0.4); }
    50% { box-shadow: 0 0 35px rgba(46, 204, 113, 0.7); }
}

.speaking-indicator {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 20px;
    height: 20px;
    background: #2ecc71;
    border-radius: 50%;
    animation: speaking-pulse 0.5s infinite;
    display: none;
}

.speaking-indicator.active {
    display: block;
}

@keyframes speaking-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.7; }
}

/* Synchronized GO Button */
.go-section {
    margin-top: 30px;
    animation: fadeIn 1s ease 0.8s both;
}

.go-button {
    padding: 18px 60px;
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    border: none;
    border-radius: 40px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 3px;
    position: relative;
}

.go-button:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(46, 204, 113, 0.5);
}

.go-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.go-button.clicked {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    animation: waiting-pulse 1.5s infinite;
}

@keyframes waiting-pulse {
    0%, 100% { box-shadow: 0 0 20px rgba(52, 152, 219, 0.4); }
    50% { box-shadow: 0 0 35px rgba(52, 152, 219, 0.7); }
}

.go-status {
    margin-top: 15px;
    display: flex;
    justify-content: center;
    gap: 30px;
}

.go-status-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.go-status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
}

.go-status-dot.ready {
    background: #2ecc71;
    animation: dot-pulse 1s infinite;
}

@keyframes dot-pulse {
    0%, 100% { box-shadow: 0 0 5px rgba(46, 204, 113, 0.5); }
    50% { box-shadow: 0 0 15px rgba(46, 204, 113, 0.8); }
}

.waiting-message {
    margin-top: 10px;
    font-size: 0.9rem;
    opacity: 0.8;
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 0.4; }
}

@media (max-width: 768px) {
    .chat-section {
        width: calc(100% - 100px);
        left: 10px;
        bottom: 10px;
        max-height: 200px;
    }
    
    .mic-section {
        right: 10px;
        bottom: 10px;
    }
    
    .mic-btn {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
}
</style>

<div class="resume-container">
    <div class="title-section">
        <h1>🎮 {{ __('Ladies and Gentlemen') }} 🎮</h1>
        <div class="theme-badge">
            <span>{{ $themeIcon }}</span> {{ $theme }}
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
    
    @if($needsSyncGo)
    <!-- Synchronized GO Section for Duo/League -->
    <div class="go-section">
        <button class="go-button" id="goButton" onclick="clickGo()">
            🎮 {{ __('GO!') }}
        </button>
        
        <div class="go-status">
            <div class="go-status-item">
                <div class="go-status-dot" id="playerDot"></div>
                <span>{{ $playerName }}</span>
            </div>
            <div class="go-status-item">
                <div class="go-status-dot" id="opponentDot"></div>
                <span>{{ $opponentName }}</span>
            </div>
        </div>
        
        <div class="waiting-message" id="waitingMessage" style="display: none;">
            {{ __('En attente de l\'autre joueur...') }}
        </div>
        
        <div class="countdown-section" id="countdownSection" style="display: none;">
            <div class="countdown-text">{{ __('La partie commence dans') }}...</div>
            <div class="countdown-number" id="countdown">3</div>
        </div>
    </div>
    @else
    <!-- Simple countdown for Solo/other modes -->
    <div class="countdown-section">
        <div class="countdown-text">{{ __('La partie commence dans') }}...</div>
        <div class="countdown-number" id="countdown">5</div>
    </div>
    @endif
</div>

@if($needsChat)
<!-- Chat Section -->
<div class="chat-section" id="chatSection">
    <div class="chat-header">
        <span>💬</span>
        <span>{{ __('Chat') }}</span>
    </div>
    <div class="chat-messages" id="chatMessages">
        <!-- Messages will be added here -->
    </div>
    <div class="chat-input-container">
        <input type="text" class="chat-input" id="chatInput" placeholder="{{ __('Écrivez un message...') }}" maxlength="200">
        <button class="chat-send-btn" onclick="sendChatMessage()">➤</button>
    </div>
</div>
@endif

@if($needsMic)
<!-- Mic Section -->
<div class="mic-section">
    <button class="mic-btn muted" id="micButton" onclick="toggleMic()">
        <span id="micIcon">🔇</span>
        <div class="speaking-indicator" id="speakingIndicator"></div>
    </button>
</div>
@endif

<script>
(function() {
    const redirectUrl = @json($redirectUrl);
    const needsSyncGo = @json($needsSyncGo);
    const needsChat = @json($needsChat);
    const needsMic = @json($needsMic);
    const sessionId = @json($sessionId);
    const playerId = @json($playerId);
    const opponentId = @json($opponentId);
    
    let playerReady = false;
    let opponentReady = false;
    let redirected = false;
    let micEnabled = false;
    
    // Simple countdown for non-sync modes
    if (!needsSyncGo) {
        let count = 5;
        const countdownEl = document.getElementById('countdown');
        
        if (countdownEl && redirectUrl) {
            const interval = setInterval(() => {
                count--;
                if (count > 0) {
                    countdownEl.textContent = count;
                } else {
                    countdownEl.textContent = '🚀';
                    clearInterval(interval);
                    
                    if (!redirected) {
                        redirected = true;
                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 500);
                    }
                }
            }, 1000);
            
            window.addEventListener('beforeunload', () => {
                clearInterval(interval);
            });
        }
    }
    
    // Synchronized GO for Duo/League
    window.clickGo = function() {
        if (playerReady) return;
        
        playerReady = true;
        const goButton = document.getElementById('goButton');
        const playerDot = document.getElementById('playerDot');
        const waitingMessage = document.getElementById('waitingMessage');
        
        goButton.classList.add('clicked');
        goButton.disabled = true;
        goButton.innerHTML = '✓ {{ __("Prêt!") }}';
        playerDot.classList.add('ready');
        waitingMessage.style.display = 'block';
        
        // Sync with Firebase
        if (typeof firebase !== 'undefined' && sessionId) {
            const db = firebase.firestore();
            db.collection('gameSessions').doc(sessionId).update({
                [`readyStatus.${playerId}`]: true
            }).catch(err => console.log('Firebase sync error:', err));
        }
        
        checkBothReady();
    };
    
    function checkBothReady() {
        if (playerReady && opponentReady) {
            startCountdown();
        }
    }
    
    function startCountdown() {
        const goSection = document.querySelector('.go-section');
        const countdownSection = document.getElementById('countdownSection');
        const waitingMessage = document.getElementById('waitingMessage');
        const goButton = document.getElementById('goButton');
        
        waitingMessage.style.display = 'none';
        goButton.style.display = 'none';
        countdownSection.style.display = 'block';
        
        let count = 3;
        const countdownEl = document.getElementById('countdown');
        
        const interval = setInterval(() => {
            count--;
            if (count > 0) {
                countdownEl.textContent = count;
            } else {
                countdownEl.textContent = '🚀';
                clearInterval(interval);
                
                if (!redirected) {
                    redirected = true;
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 500);
                }
            }
        }, 1000);
    }
    
    // Listen for opponent ready status via Firebase
    if (needsSyncGo && typeof firebase !== 'undefined' && sessionId) {
        firebase.auth().signInAnonymously().then(() => {
            const db = firebase.firestore();
            db.collection('gameSessions').doc(sessionId).onSnapshot((doc) => {
                if (doc.exists) {
                    const data = doc.data();
                    const readyStatus = data.readyStatus || {};
                    
                    if (opponentId && readyStatus[opponentId]) {
                        opponentReady = true;
                        const opponentDot = document.getElementById('opponentDot');
                        if (opponentDot) opponentDot.classList.add('ready');
                        checkBothReady();
                    }
                }
            });
        }).catch(err => console.log('Firebase auth error:', err));
    }
    
    // Chat functionality
    window.sendChatMessage = function() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Add message to local chat
        addChatMessage(message, true);
        input.value = '';
        
        // Send via Firebase
        if (typeof firebase !== 'undefined' && sessionId) {
            const db = firebase.firestore();
            db.collection('gameSessions').doc(sessionId).collection('chat').add({
                senderId: playerId,
                message: message,
                timestamp: firebase.firestore.FieldValue.serverTimestamp()
            }).catch(err => console.log('Chat send error:', err));
        }
    };
    
    function addChatMessage(message, isMine) {
        const messagesEl = document.getElementById('chatMessages');
        if (!messagesEl) return;
        
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message ' + (isMine ? 'mine' : 'theirs');
        msgDiv.textContent = message;
        messagesEl.appendChild(msgDiv);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }
    
    // Listen for chat messages
    if (needsChat && typeof firebase !== 'undefined' && sessionId) {
        firebase.auth().signInAnonymously().then(() => {
            const db = firebase.firestore();
            db.collection('gameSessions').doc(sessionId).collection('chat')
                .orderBy('timestamp', 'asc')
                .onSnapshot((snapshot) => {
                    snapshot.docChanges().forEach((change) => {
                        if (change.type === 'added') {
                            const data = change.doc.data();
                            if (data.senderId !== playerId) {
                                addChatMessage(data.message, false);
                            }
                        }
                    });
                });
        }).catch(err => console.log('Chat listen error:', err));
    }
    
    // Enter key for chat
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
    }
    
    // Mic toggle
    window.toggleMic = function() {
        micEnabled = !micEnabled;
        const micButton = document.getElementById('micButton');
        const micIcon = document.getElementById('micIcon');
        
        if (micEnabled) {
            micButton.classList.remove('muted');
            micButton.classList.add('active');
            micIcon.textContent = '🎤';
            // WebRTC mic activation would go here
        } else {
            micButton.classList.remove('active');
            micButton.classList.add('muted');
            micIcon.textContent = '🔇';
        }
    };
})();
</script>

<!-- Firebase SDK -->
@if($needsSyncGo || $needsChat || $needsMic)
<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-firestore-compat.js"></script>
<script>
    const firebaseConfig = {
        projectId: @json(config('services.firebase.project_id')),
        apiKey: "{{ config('services.firebase.api_key', 'AIzaSyDummyKey') }}"
    };
    if (!firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
    }
</script>
@endif
@endsection
