@extends('layouts.app')

@section('content')
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

        <div class="communication-panel">
            <div class="voice-chat-section">
                <h3>🎤 {{ __('Communication Vocale') }}</h3>
                <div class="voice-controls">
                    <button class="voice-btn mic-btn" id="micToggle" onclick="toggleMicrophone()">
                        <span class="btn-icon">🎤</span>
                        <span class="btn-text">{{ __('Activer Micro') }}</span>
                    </button>
                    <div class="voice-status" id="voiceStatus">
                        <span class="status-dot offline"></span>
                        <span>{{ __('Micro désactivé') }}</span>
                    </div>
                </div>
                <div class="speaking-indicators" id="speakingIndicators"></div>
            </div>

            <div class="text-chat-section">
                <h3>💬 {{ __('Chat Équipe') }}</h3>
                <div class="chat-messages" id="chatMessages">
                    <p class="chat-placeholder">{{ __('Aucun message pour le moment...') }}</p>
                </div>
                <div class="chat-input-wrapper">
                    <input type="text" id="chatInput" placeholder="{{ __('Écrire un message...') }}" maxlength="200">
                    <button class="send-btn" onclick="sendMessage()">
                        ➤
                    </button>
                </div>
            </div>
        </div>

        @if($isCaptain)
        <div class="captain-actions">
            <button class="btn-lobby" id="goToLobbyBtn" onclick="goToLobby()" disabled>
                <span class="btn-icon">🎮</span>
                <span class="btn-text">{{ __('ALLER AU LOBBY') }}</span>
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
</style>

<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-firestore-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-auth-compat.js"></script>

<script>
const sessionId = '{{ $sessionId }}';
const teamId = {{ $team->id }};
const currentUserId = {{ Auth::id() }};
const isCaptain = {{ $isCaptain ? 'true' : 'false' }};
let db = null;
let unsubscribe = null;
let micEnabled = false;
let localStream = null;

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

function updateChat(messages) {
    const chatDiv = document.getElementById('chatMessages');
    
    if (!messages || messages.length === 0) {
        chatDiv.innerHTML = '<p class="chat-placeholder">{{ __("Aucun message pour le moment...") }}</p>';
        return;
    }
    
    chatDiv.innerHTML = messages.slice(-50).map(msg => {
        const div = document.createElement('div');
        div.className = 'chat-message';
        
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

async function toggleMicrophone() {
    const btn = document.getElementById('micToggle');
    const statusDiv = document.getElementById('voiceStatus');
    
    if (!micEnabled) {
        try {
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            micEnabled = true;
            btn.classList.add('active');
            btn.querySelector('.btn-text').textContent = '{{ __("Micro Activé") }}';
            statusDiv.innerHTML = '<span class="status-dot online"></span><span>{{ __("Micro activé") }}</span>';
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
        btn.querySelector('.btn-text').textContent = '{{ __("Activer Micro") }}';
        statusDiv.innerHTML = '<span class="status-dot offline"></span><span>{{ __("Micro désactivé") }}</span>';
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
    window.location.href = '{{ route("league.team.lobby", $team->id) }}';
}

window.addEventListener('beforeunload', () => {
    if (unsubscribe) {
        unsubscribe();
    }
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }
});
</script>
@endsection
