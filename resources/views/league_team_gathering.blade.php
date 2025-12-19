@extends('layouts.app')

@section('content')
<div class="screen-edge-glow" id="screenEdgeGlow"></div>
<div class="gathering-container">
    <div class="gathering-header">
        <button onclick="window.location.href='{{ route('league.team.management', $team->id) }}'" class="back-button">
            ← {{ __('Retour') }}
        </button>
        <h1>{{ __('RASSEMBLEMENT') }}</h1>
        <div class="team-name-badge">
            {{ $team->name }}
        </div>
    </div>

    <div class="gathering-content">
        <div class="connection-status-banner" id="connectionBanner">
            <span class="status-icon">⏳</span>
            <span class="status-text">{{ __('En attente des joueurs...') }}</span>
            <span class="connected-count">(<span id="connectedCount">1</span>/5)</span>
        </div>

        <div class="players-list" id="playersList">
            @foreach($membersWithStats as $index => $member)
            <div class="player-card {{ in_array($member['id'], $gatheringData['connected']) ? 'connected' : 'disconnected' }}" 
                 data-player-id="{{ $member['id'] }}"
                 data-rank="{{ $index + 1 }}">
                <div class="rank-badge">#{{ $index + 1 }}</div>
                <div class="player-avatar-wrapper {{ in_array($member['id'], $gatheringData['connected']) ? 'glow-active' : '' }}">
                    @if($member['avatar_url'])
                        <img src="{{ $member['avatar_url'] }}" alt="Avatar" class="player-avatar">
                    @else
                        <div class="player-avatar default-avatar">{{ strtoupper(substr($member['name'], 0, 1)) }}</div>
                    @endif
                    <div class="connection-indicator {{ in_array($member['id'], $gatheringData['connected']) ? 'online' : 'offline' }}"></div>
                </div>
                <div class="player-info">
                    <div class="player-name">
                        {{ $member['name'] }}
                        @if($team->captain_id === $member['id'])
                            <span class="captain-badge">👑</span>
                        @endif
                    </div>
                    <div class="player-stats">
                        <span class="stat-item" title="{{ __('Efficacité') }}">
                            🎯 {{ $member['efficiency'] }}%
                        </span>
                        <span class="stat-item" title="{{ __('10 derniers matchs') }}">
                            📊 {{ $member['last_10_wins'] }}V/{{ $member['last_10_losses'] }}D
                        </span>
                    </div>
                </div>
                <div class="player-status">
                    <span class="status-badge {{ in_array($member['id'], $gatheringData['connected']) ? 'ready' : 'waiting' }}">
                        {{ in_array($member['id'], $gatheringData['connected']) ? __('Connecté') : __('En attente') }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>

        
        @if($isCaptain)
        <div class="captain-actions">
            <button class="btn-lobby" id="goToLobbyBtn" onclick="goToLobby()" disabled>
                <span class="btn-icon">🎮</span>
                <span class="btn-text">{{ __('SALON D\'ÉQUIPES') }}</span>
            </button>
            <p class="hint-text" id="lobbyHint">{{ __('Attendez que tous les joueurs soient connectés') }}</p>
        </div>
        @else
        <div class="waiting-message">
            <p>{{ __('En attente du capitaine pour démarrer...') }}</p>
        </div>
        @endif
    </div>
</div>

<!-- Chat Section - Duo Style (bottom-left) -->
<div class="chat-section" id="chatSection">
    <div class="chat-header">
        <span>💬</span>
        <span>{{ __('Chat Équipe') }}</span>
    </div>
    <div class="chat-messages" id="chatMessages">
        <!-- Messages will be added here -->
    </div>
    <div class="chat-input-container">
        <input type="text" class="chat-input" id="chatInput" placeholder="{{ __('Écrivez un message...') }}" maxlength="200">
        <button class="chat-send-btn" onclick="sendMessage()">➤</button>
    </div>
</div>

<!-- Mic Section - Duo Style (bottom-right) -->
<div class="mic-section">
    <button class="mic-btn muted" id="micButton" onclick="toggleMicrophone()">
        <span id="micIcon">🔇</span>
        <div class="speaking-indicator" id="speakingIndicator"></div>
    </button>
</div>

<style>
.gathering-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #0a0a15 0%, #1a1a2e 50%, #16213e 100%);
    padding-bottom: 100px;
}

.gathering-header {
    background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, transparent 100%);
    padding: 20px;
    text-align: center;
    position: relative;
}

.gathering-header h1 {
    color: #00d4ff;
    font-size: 1.8rem;
    margin: 10px 0;
    text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
}

.team-name-badge {
    display: inline-block;
    background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
    color: #1a1a2e;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 1.1rem;
}

.back-button {
    position: absolute;
    left: 20px;
    top: 20px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.back-button:hover {
    background: rgba(255,255,255,0.2);
}

.gathering-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.connection-status-banner {
    background: linear-gradient(135deg, #2a2a4e 0%, #1a1a3e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 20px;
}

.connection-status-banner .status-icon {
    font-size: 1.5rem;
    animation: pulse 1.5s ease-in-out infinite;
}

.connection-status-banner.all-connected {
    border-color: #00ff88;
    background: linear-gradient(135deg, #1a3a2e 0%, #0a2a1e 100%);
}

.connection-status-banner.all-connected .status-icon {
    animation: none;
}

.players-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 25px;
}

.player-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    position: relative;
}

.player-card.connected {
    border-color: #00d4ff;
    box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
}

.player-card.disconnected {
    opacity: 0.6;
    border-color: #333;
}

.rank-badge {
    position: absolute;
    top: -10px;
    left: -10px;
    background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
    color: #1a1a2e;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 4px 8px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(255, 215, 0, 0.4);
}

.player-avatar-wrapper {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    padding: 3px;
    background: linear-gradient(135deg, #333 0%, #222 100%);
    transition: all 0.5s ease;
}

.player-avatar-wrapper.glow-active {
    background: linear-gradient(135deg, #00d4ff 0%, #00ff88 50%, #00d4ff 100%);
    box-shadow: 0 0 20px rgba(0, 212, 255, 0.6), 0 0 40px rgba(0, 255, 136, 0.3);
    animation: avatarGlow 2s ease-in-out infinite;
}

@keyframes avatarGlow {
    0%, 100% { box-shadow: 0 0 20px rgba(0, 212, 255, 0.6), 0 0 40px rgba(0, 255, 136, 0.3); }
    50% { box-shadow: 0 0 30px rgba(0, 212, 255, 0.8), 0 0 60px rgba(0, 255, 136, 0.5); }
}

.player-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: #1a1a2e;
}

.default-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: #00d4ff;
}

.connection-indicator {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #1a1a2e;
}

.connection-indicator.online {
    background: #00ff88;
    box-shadow: 0 0 10px #00ff88;
}

.connection-indicator.offline {
    background: #666;
}

.player-info {
    flex: 1;
}

.player-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 8px;
}

.captain-badge {
    font-size: 1rem;
}

.player-stats {
    display: flex;
    gap: 15px;
    margin-top: 5px;
    font-size: 0.85rem;
    color: #aaa;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

.player-status {
    text-align: right;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge.ready {
    background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
    color: #0a2a1e;
}

.status-badge.waiting {
    background: #444;
    color: #aaa;
}

.communication-panel {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

@media (max-width: 700px) {
    .communication-panel {
        grid-template-columns: 1fr;
    }
}

.voice-chat-section, .text-chat-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 15px;
}

.voice-chat-section h3, .text-chat-section h3 {
    color: #00d4ff;
    font-size: 1rem;
    margin-bottom: 15px;
}

.voice-controls {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.voice-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.mic-btn {
    background: linear-gradient(135deg, #444 0%, #333 100%);
    color: #fff;
}

.mic-btn.active {
    background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
    color: #fff;
}

.mic-btn:hover {
    transform: translateY(-2px);
}

.voice-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #888;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.status-dot.online {
    background: #00ff88;
    box-shadow: 0 0 8px #00ff88;
}

.status-dot.offline {
    background: #666;
}

.speaking-indicators {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.speaking-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: rgba(0, 212, 255, 0.2);
    border-radius: 15px;
    font-size: 0.8rem;
}

.speaking-indicator.speaking {
    background: rgba(0, 255, 136, 0.3);
    animation: speakingPulse 0.5s ease-in-out infinite;
}

@keyframes speakingPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.chat-messages {
    height: 150px;
    overflow-y: auto;
    background: #0a0a15;
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 10px;
}

.chat-placeholder {
    color: #666;
    text-align: center;
    font-style: italic;
    margin-top: 50px;
}

.chat-message {
    margin-bottom: 8px;
    padding: 6px 10px;
    background: rgba(0, 212, 255, 0.1);
    border-radius: 8px;
}

.chat-message .sender {
    font-weight: 600;
    color: #00d4ff;
    font-size: 0.85rem;
}

.chat-message .text {
    color: #ddd;
    font-size: 0.9rem;
    margin-top: 2px;
}

.chat-input-wrapper {
    display: flex;
    gap: 10px;
}

.chat-input-wrapper input {
    flex: 1;
    background: #0a0a15;
    border: 1px solid #0f3460;
    border-radius: 8px;
    padding: 10px 15px;
    color: #fff;
    font-size: 0.95rem;
}

.chat-input-wrapper input:focus {
    outline: none;
    border-color: #00d4ff;
}

.send-btn {
    background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 15px;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.send-btn:hover {
    transform: scale(1.05);
}

.captain-actions {
    text-align: center;
    padding: 20px;
}

.btn-lobby {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 18px 40px;
    background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
    border: 3px solid #00ff88;
    border-radius: 15px;
    color: #0a2a1e;
    font-size: 1.3rem;
    font-weight: 800;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0, 255, 136, 0.4);
}

.btn-lobby:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(0, 255, 136, 0.6);
}

.btn-lobby:disabled {
    background: linear-gradient(135deg, #444 0%, #333 100%);
    border-color: #555;
    color: #888;
    cursor: not-allowed;
    box-shadow: none;
}

.hint-text {
    color: #888;
    font-size: 0.9rem;
    margin-top: 10px;
}

.waiting-message {
    text-align: center;
    padding: 30px;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    color: #aaa;
    font-size: 1.1rem;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Chat Section - Duo Style */
.chat-section {
    position: fixed;
    bottom: 20px;
    left: 20px;
    width: 320px;
    max-height: 280px;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    border: 2px solid rgba(0, 212, 255, 0.3);
    overflow: hidden;
    z-index: 100;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.chat-header {
    padding: 12px 15px;
    background: linear-gradient(135deg, rgba(0, 212, 255, 0.2) 0%, rgba(0, 150, 200, 0.2) 100%);
    border-bottom: 1px solid rgba(0, 212, 255, 0.3);
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #00d4ff;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    max-height: 180px;
    min-height: 100px;
}

.chat-message {
    margin-bottom: 10px;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 0.9rem;
    max-width: 90%;
    animation: fadeIn 0.3s ease;
}

.chat-message.mine {
    background: linear-gradient(135deg, rgba(0, 212, 255, 0.3) 0%, rgba(0, 150, 200, 0.3) 100%);
    margin-left: auto;
    text-align: right;
    border-bottom-right-radius: 4px;
}

.chat-message.theirs {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.2) 0%, rgba(255, 140, 0, 0.2) 100%);
    margin-right: auto;
    border-bottom-left-radius: 4px;
}

.chat-message .sender {
    font-weight: 600;
    font-size: 0.8rem;
    margin-bottom: 4px;
    color: #00d4ff;
}

.chat-message.theirs .sender {
    color: #ffd700;
}

.chat-message .text {
    color: #fff;
    word-wrap: break-word;
}

.chat-placeholder {
    color: #666;
    text-align: center;
    font-style: italic;
    padding: 30px 10px;
}

.chat-input-container {
    padding: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    gap: 10px;
    background: rgba(0, 0, 0, 0.3);
}

.chat-input {
    flex: 1;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(0, 212, 255, 0.3);
    border-radius: 20px;
    padding: 10px 16px;
    color: #fff;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
}

.chat-input:focus {
    outline: none;
    border-color: #00d4ff;
}

.chat-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.chat-send-btn {
    background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #fff;
    transition: all 0.3s ease;
}

.chat-send-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(0, 212, 255, 0.4);
}

/* Mic Section - Duo Style */
.mic-section {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 100;
}

.mic-btn {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.3);
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(10px);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    transition: all 0.3s ease;
    position: relative;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.mic-btn:hover {
    transform: scale(1.05);
}

.mic-btn.active {
    background: rgba(46, 204, 113, 0.6);
    border-color: #2ecc71;
    animation: mic-pulse 1.5s infinite;
}

.mic-btn.muted {
    background: rgba(231, 76, 60, 0.5);
    border-color: #e74c3c;
}

@keyframes mic-pulse {
    0%, 100% { box-shadow: 0 0 20px rgba(46, 204, 113, 0.4); }
    50% { box-shadow: 0 0 40px rgba(46, 204, 113, 0.7); }
}

.speaking-indicator {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 22px;
    height: 22px;
    background: #2ecc71;
    border-radius: 50%;
    border: 2px solid #fff;
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

@media (max-width: 768px) {
    .chat-section {
        width: calc(100% - 100px);
        left: 10px;
        bottom: 10px;
        max-height: 220px;
    }
    
    .mic-section {
        right: 10px;
        bottom: 10px;
    }
    
    .mic-btn {
        width: 55px;
        height: 55px;
        font-size: 1.4rem;
    }
}

.screen-edge-glow {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.screen-edge-glow.active {
    opacity: 1;
    animation: edgeGlowPulse 1.5s ease-out;
}

@keyframes edgeGlowPulse {
    0% {
        box-shadow: inset 0 0 100px rgba(0, 255, 136, 0.8),
                    inset 0 0 200px rgba(0, 212, 255, 0.5);
        opacity: 1;
    }
    50% {
        box-shadow: inset 0 0 150px rgba(0, 255, 136, 0.6),
                    inset 0 0 250px rgba(0, 212, 255, 0.4);
        opacity: 0.8;
    }
    100% {
        box-shadow: inset 0 0 50px rgba(0, 255, 136, 0),
                    inset 0 0 100px rgba(0, 212, 255, 0);
        opacity: 0;
    }
}
</style>

<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-firestore-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-auth-compat.js"></script>

<!-- WebRTC Voice Chat Module -->
<script src="{{ asset('js/VoiceChat.js') }}"></script>

<script>
const sessionId = '{{ $sessionId }}';
const teamId = {{ $team->id }};
const currentUserId = {{ Auth::id() }};
const isCaptain = {{ $isCaptain ? 'true' : 'false' }};
const teamMemberIds = @json($membersWithStats->pluck('id')->filter(fn($id) => $id !== Auth::id())->values());
let db = null;
let unsubscribe = null;
let micEnabled = false;
let localStream = null;
let voiceChat = null;
let previouslyConnectedIds = [];

const firebaseConfig = {
    apiKey: "{{ config('services.firebase.api_key') }}",
    authDomain: "{{ config('services.firebase.auth_domain') }}",
    projectId: "{{ config('services.firebase.project_id') }}",
    storageBucket: "{{ config('services.firebase.storage_bucket') }}",
    messagingSenderId: "{{ config('services.firebase.messaging_sender_id') }}",
    appId: "{{ config('services.firebase.app_id') }}"
};

document.addEventListener('DOMContentLoaded', async function() {
    try {
        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }
        db = firebase.firestore();
        
        await markAsConnected();
        subscribeToGathering();
        startPolling();
    } catch (error) {
        console.error('Firebase init error:', error);
        startPolling();
    }
});

async function markAsConnected() {
    if (!db) return;
    
    try {
        const gatheringRef = db.collection('teamGatherings').doc(sessionId);
        const doc = await gatheringRef.get();
        
        if (!doc.exists) {
            await gatheringRef.set({
                teamId: teamId,
                connectedPlayers: [currentUserId],
                createdAt: firebase.firestore.FieldValue.serverTimestamp(),
                messages: []
            });
        } else {
            await gatheringRef.update({
                connectedPlayers: firebase.firestore.FieldValue.arrayUnion(currentUserId)
            });
        }
    } catch (error) {
        console.error('Mark connected error:', error);
    }
}

function subscribeToGathering() {
    if (!db) return;
    
    const gatheringRef = db.collection('teamGatherings').doc(sessionId);
    
    unsubscribe = gatheringRef.onSnapshot((doc) => {
        if (doc.exists) {
            const data = doc.data();
            updatePlayersList(data.connectedPlayers || []);
            updateChat(data.messages || []);
        }
    }, (error) => {
        console.error('Snapshot error:', error);
    });
}

function updatePlayersList(connectedIds) {
    const playerCards = document.querySelectorAll('.player-card');
    let connectedCount = 0;
    
    const newlyConnectedIds = connectedIds.filter(id => !previouslyConnectedIds.includes(id) && id !== currentUserId);
    if (newlyConnectedIds.length > 0 && previouslyConnectedIds.length > 0) {
        triggerScreenEdgeGlow();
    }
    previouslyConnectedIds = [...connectedIds];
    
    playerCards.forEach(card => {
        const playerId = parseInt(card.dataset.playerId);
        const isConnected = connectedIds.includes(playerId);
        const wrapper = card.querySelector('.player-avatar-wrapper');
        const indicator = card.querySelector('.connection-indicator');
        const badge = card.querySelector('.status-badge');
        
        if (isConnected) {
            connectedCount++;
            card.classList.add('connected');
            card.classList.remove('disconnected');
            wrapper.classList.add('glow-active');
            indicator.classList.add('online');
            indicator.classList.remove('offline');
            badge.classList.add('ready');
            badge.classList.remove('waiting');
            badge.textContent = '{{ __("Connecté") }}';
        } else {
            card.classList.remove('connected');
            card.classList.add('disconnected');
            wrapper.classList.remove('glow-active');
            indicator.classList.remove('online');
            indicator.classList.add('offline');
            badge.classList.remove('ready');
            badge.classList.add('waiting');
            badge.textContent = '{{ __("En attente") }}';
        }
    });
    
    document.getElementById('connectedCount').textContent = connectedCount;
    
    const banner = document.getElementById('connectionBanner');
    const statusIcon = banner.querySelector('.status-icon');
    const statusText = banner.querySelector('.status-text');
    
    if (connectedCount >= 5) {
        banner.classList.add('all-connected');
        statusIcon.textContent = '✅';
        statusText.textContent = '{{ __("Tous les joueurs sont connectés !") }}';
        
        if (isCaptain) {
            document.getElementById('goToLobbyBtn').disabled = false;
            document.getElementById('lobbyHint').textContent = '{{ __("L\'équipe est prête !") }}';
        }
    } else {
        banner.classList.remove('all-connected');
        statusIcon.textContent = '⏳';
        statusText.textContent = '{{ __("En attente des joueurs...") }}';
        
        if (isCaptain) {
            document.getElementById('goToLobbyBtn').disabled = true;
            document.getElementById('lobbyHint').textContent = '{{ __("Attendez que tous les joueurs soient connectés") }}';
        }
    }
}

function triggerScreenEdgeGlow() {
    const glowElement = document.getElementById('screenEdgeGlow');
    if (glowElement) {
        glowElement.classList.remove('active');
        void glowElement.offsetWidth;
        glowElement.classList.add('active');
        setTimeout(() => {
            glowElement.classList.remove('active');
        }, 1500);
    }
}

function updateChat(messages) {
    const chatDiv = document.getElementById('chatMessages');
    
    if (!messages || messages.length === 0) {
        chatDiv.innerHTML = '<p class="chat-placeholder">{{ __("Aucun message pour le moment...") }}</p>';
        return;
    }
    
    chatDiv.innerHTML = messages.slice(-50).map(msg => {
        const isMine = msg.senderId === currentUserId;
        const div = document.createElement('div');
        div.className = 'chat-message ' + (isMine ? 'mine' : 'theirs');
        
        const sender = document.createElement('div');
        sender.className = 'sender';
        sender.textContent = msg.senderName || 'Joueur';
        
        const text = document.createElement('div');
        text.className = 'text';
        text.textContent = msg.text || '';
        
        div.appendChild(sender);
        div.appendChild(text);
        return div.outerHTML;
    }).join('');
    
    chatDiv.scrollTop = chatDiv.scrollHeight;
}

async function sendMessage() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    
    if (!text || !db) return;
    
    input.value = '';
    
    try {
        const gatheringRef = db.collection('teamGatherings').doc(sessionId);
        await gatheringRef.update({
            messages: firebase.firestore.FieldValue.arrayUnion({
                senderId: currentUserId,
                senderName: '{{ Auth::user()->name }}',
                text: text,
                timestamp: Date.now()
            })
        });
    } catch (error) {
        console.error('Send message error:', error);
    }
}

document.getElementById('chatInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
});

async function initVoiceChat() {
    if (voiceChat || !db) return;
    
    try {
        voiceChat = new VoiceChat({
            sessionId: 'team_gathering_' + sessionId,
            localUserId: currentUserId,
            remoteUserIds: teamMemberIds,
            isHost: isCaptain,
            mode: 'league_team',
            db: db,
            onSpeakingChange: (speaking) => {
                const indicator = document.getElementById('speakingIndicator');
                if (indicator) {
                    indicator.classList.toggle('active', speaking);
                }
            },
            onConnectionChange: (state) => {
                console.log('Voice connection state:', state);
            },
            onError: (error) => {
                console.warn('Voice chat error:', error);
            }
        });
        
        await voiceChat.initialize();
        console.log('VoiceChat initialized for team gathering:', sessionId);
    } catch (error) {
        console.warn('VoiceChat init failed:', error);
    }
}

async function toggleMicrophone() {
    const btn = document.getElementById('micButton');
    const icon = document.getElementById('micIcon');
    const speakingIndicator = document.getElementById('speakingIndicator');
    
    if (!btn || !icon) return;
    
    if (!voiceChat) {
        await initVoiceChat();
    }
    
    if (voiceChat) {
        const enabled = await voiceChat.toggleMicrophone();
        micEnabled = enabled;
        
        if (enabled) {
            btn.classList.remove('muted');
            btn.classList.add('active');
            icon.textContent = '🎤';
        } else {
            btn.classList.remove('active');
            btn.classList.add('muted');
            icon.textContent = '🔇';
            speakingIndicator.classList.remove('active');
        }
    } else {
        if (!micEnabled) {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                micEnabled = true;
                btn.classList.remove('muted');
                btn.classList.add('active');
                icon.textContent = '🎤';
            } catch (error) {
                console.error('Microphone error:', error);
                alert('{{ __("Impossible d\'accéder au microphone") }}');
            }
        } else {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            micEnabled = false;
            btn.classList.remove('active');
            btn.classList.add('muted');
            icon.textContent = '🔇';
            speakingIndicator.classList.remove('active');
        }
    }
}

function startPolling() {
    setInterval(async () => {
        try {
            const response = await fetch(`/league/team/gathering/${sessionId}/members`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const connectedIds = data.members
                    .filter(m => m.is_connected)
                    .map(m => m.id);
                updatePlayersList(connectedIds);
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 3000);
}

function goToLobby() {
    localStorage.setItem('team_gathering_complete_{{ $team->id }}', 'true');
    localStorage.setItem('team_gathering_time_{{ $team->id }}', Date.now().toString());
    window.location.href = '{{ route("league.team.lobby", $team->id) }}';
}

window.addEventListener('beforeunload', () => {
    if (unsubscribe) {
        unsubscribe();
    }
    if (voiceChat) {
        voiceChat.destroy();
    }
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }
});
</script>
@endsection
