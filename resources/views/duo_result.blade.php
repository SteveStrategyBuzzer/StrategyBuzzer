@extends('layouts.app')

@section('content')
<div class="duo-result-container">
    <div class="result-header">
        @if($match_result['player_won'])
            <div class="result-title victory">VICTOIRE !</div>
            <div class="result-icon">🏆</div>
        @else
            <div class="result-title defeat">DÉFAITE</div>
            <div class="result-icon">😔</div>
        @endif
    </div>

    <div class="final-score">
        <div class="player-final player-side">
            <div class="player-avatar">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="Vous">
                @else
                    <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                @endif
            </div>
            <h3>{{ Auth::user()->name }}</h3>
            <div class="rounds-won {{ $match_result['player_won'] ? 'winner' : '' }}">
                {{ $match_result['player_rounds_won'] }}
            </div>
        </div>

        <div class="vs-divider">-</div>

        <div class="player-final opponent-side">
            <div class="player-avatar">
                @if($opponent['avatar_url'] ?? null)
                    <img src="{{ $opponent['avatar_url'] }}" alt="{{ $opponent['name'] }}">
                @else
                    <div class="default-avatar">{{ substr($opponent['name'] ?? 'O', 0, 1) }}</div>
                @endif
            </div>
            <h3>{{ $opponent['name'] ?? 'Adversaire' }}</h3>
            <div class="rounds-won {{ !$match_result['player_won'] ? 'winner' : '' }}">
                {{ $match_result['opponent_rounds_won'] }}
            </div>
        </div>
    </div>

    <div class="stats-section">
        <div class="stat-card">
            <div class="stat-label">Points gagnés/perdus</div>
            <div class="stat-value {{ $points_earned >= 0 ? 'positive' : 'negative' }}">
                {{ $points_earned >= 0 ? '+' : '' }}{{ $points_earned }}
            </div>
        </div>

        @if(($coins_earned ?? 0) > 0)
        <div class="stat-card coins-card">
            <div class="stat-label">Pièces de compétence gagnées</div>
            <div class="stat-value coins">
                +{{ $coins_earned }} 🪙
            </div>
            @if($coins_bonus ?? 0)
            <div class="stat-detail {{ $coins_bonus > 0 ? 'bonus-positive' : 'bonus-negative' }}">
                @if($opponent_strength === 'stronger')
                    Bonus adversaire plus fort (+50%)
                @elseif($opponent_strength === 'weaker')
                    Malus adversaire plus faible (-50%)
                @endif
            </div>
            @endif
        </div>
        @endif

        <div class="stat-card">
            <div class="stat-label">Nouvelle division</div>
            <div class="stat-value division">
                {{ $new_division['name'] }}
            </div>
            <div class="stat-detail">
                Niveau {{ $new_division['level'] }} • {{ $new_division['points'] }} pts
            </div>
        </div>

        @if($division_changed ?? false)
        <div class="division-change">
            <div class="change-message">
                Vous êtes passé en {{ $new_division['name'] }} !
            </div>
        </div>
        @endif
    </div>

    <div class="match-stats">
        <h3>STATISTIQUES DU MATCH</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-icon">✓</span>
                <span class="stat-number">{{ $global_stats['correct'] ?? 0 }}</span>
                <span class="stat-text">Bonnes réponses</span>
            </div>
            <div class="stat-item">
                <span class="stat-icon">✗</span>
                <span class="stat-number">{{ $global_stats['incorrect'] ?? 0 }}</span>
                <span class="stat-text">Mauvaises réponses</span>
            </div>
            <div class="stat-item">
                <span class="stat-icon">💯</span>
                <span class="stat-number">{{ $global_stats['total_points'] ?? 0 }}</span>
                <span class="stat-text">Points totaux</span>
            </div>
            <div class="stat-item">
                <span class="stat-icon">⚡</span>
                <span class="stat-number">{{ $accuracy ?? 0 }}%</span>
                <span class="stat-text">Précision</span>
            </div>
        </div>
    </div>

    <div class="round-details">
        <h3>DÉTAIL DES ROUNDS</h3>
        <div class="rounds-list">
            @foreach($round_details ?? [] as $index => $round)
            <div class="round-item">
                <div class="round-header">
                    <span class="round-number">Round {{ $index + 1 }}</span>
                    <span class="round-winner">
                        @if($round['winner'] === 'player')
                            ✓ Gagné
                        @elseif($round['winner'] === 'opponent')
                            ✗ Perdu
                        @else
                            = Égalité
                        @endif
                    </span>
                </div>
                <div class="round-score">
                    {{ $round['player_score'] ?? 0 }} - {{ $round['opponent_score'] ?? 0 }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="actions">
        @if($opponent_id)
        <button onclick="openResultChat({{ $opponent_id }}, '{{ addslashes($opponent_name) }}')" class="btn-chat-result" title="{{ __('Envoyer un message') }}">
            💬 {{ __('Envoyer un message') }}
        </button>
        @endif
        <button onclick="window.location.href='{{ route('duo.lobby') }}'" class="btn-primary">
            {{ __('REJOUER') }}
        </button>
        <button onclick="window.location.href='{{ route('duo.rankings') }}'" class="btn-secondary">
            {{ __('CLASSEMENTS') }}
        </button>
        <button onclick="window.location.href='{{ route('menu') }}'" class="btn-secondary">
            {{ __('MENU PRINCIPAL') }}
        </button>
    </div>
</div>

<div id="chatModal" class="result-chat-modal" style="display: none;">
    <div class="chat-modal-content">
        <div class="chat-header">
            <button class="chat-back-btn" onclick="closeResultChatModal()">←</button>
            <h3 id="chatContactName">{{ __('Chat') }}</h3>
            <button class="modal-close" onclick="closeResultChatModal()">×</button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <p class="chat-loading">{{ __('Chargement...') }}</p>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="{{ __('Écrivez votre message...') }}" maxlength="500">
            <button onclick="sendResultMessage()">{{ __('Envoyer') }}</button>
        </div>
    </div>
</div>

<audio id="messageNotificationSound" preload="auto">
    <source src="{{ asset('sounds/message_notification.mp3') }}" type="audio/mpeg">
</audio>

<style>
.duo-result-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px 20px;
    color: white;
}

.result-header {
    text-align: center;
    margin-bottom: 40px;
}

.result-title {
    font-size: 4em;
    font-weight: bold;
    margin-bottom: 20px;
    text-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.result-title.victory {
    color: #ffd700;
}

.result-title.defeat {
    color: #ffcdd2;
}

.result-icon {
    font-size: 5em;
}

.final-score {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 40px;
    align-items: center;
    max-width: 800px;
    margin: 0 auto 40px;
}

.player-final {
    text-align: center;
}

.player-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    margin: 0 auto 15px;
    overflow: hidden;
    border: 4px solid white;
}

.player-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.3);
    font-size: 2.5em;
    font-weight: bold;
}

.player-final h3 {
    margin: 0 0 10px 0;
    font-size: 1.5em;
}

.rounds-won {
    font-size: 3em;
    font-weight: bold;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.rounds-won.winner {
    background: #ffd700;
    color: #1a1a1a;
}

.vs-divider {
    font-size: 2em;
    font-weight: bold;
}

.stats-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    max-width: 1000px;
    margin: 0 auto 40px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    backdrop-filter: blur(10px);
}

.stat-label {
    font-size: 1em;
    opacity: 0.9;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 2.5em;
    font-weight: bold;
}

.stat-value.positive {
    color: #4caf50;
}

.stat-value.negative {
    color: #f44336;
}

.stat-value.division {
    color: #ffd700;
}

.stat-detail {
    font-size: 0.9em;
    opacity: 0.9;
    margin-top: 5px;
}

.division-change {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, #ffd700 0%, #ffeb3b 100%);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
}

.change-message {
    font-size: 1.5em;
    font-weight: bold;
    color: #1a1a1a;
}

.match-stats, .round-details {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    padding: 30px;
    max-width: 1000px;
    margin: 0 auto 30px;
    backdrop-filter: blur(10px);
}

.match-stats h3, .round-details h3 {
    margin: 0 0 25px 0;
    font-size: 1.5em;
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
}

.stat-item {
    text-align: center;
}

.stat-icon {
    font-size: 2em;
    display: block;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

.stat-text {
    font-size: 0.9em;
    opacity: 0.9;
}

.rounds-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.round-item {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.round-header {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.round-number {
    font-weight: bold;
    font-size: 1.1em;
}

.round-winner {
    opacity: 0.9;
}

.round-score {
    font-size: 1.5em;
    font-weight: bold;
}

.actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    max-width: 800px;
    margin: 0 auto;
}

.btn-primary, .btn-secondary {
    padding: 15px 40px;
    border: none;
    border-radius: 12px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: white;
    color: #667eea;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(255, 255, 255, 0.3);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid white;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.3);
}

@media (max-width: 768px) {
    .result-title {
        font-size: 2.5em;
    }
    
    .final-score {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .vs-divider {
        transform: rotate(90deg);
    }
    
    .actions {
        flex-direction: column;
    }
    
    .btn-primary, .btn-secondary, .btn-chat-result {
        width: 100%;
    }
}

.btn-chat-result {
    padding: 15px 40px;
    border: none;
    border-radius: 12px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
    color: white;
}

.btn-chat-result:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 184, 148, 0.3);
}

.result-chat-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.chat-modal-content {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 500px;
    height: 70vh;
    max-height: 600px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chat-header {
    display: flex;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    gap: 15px;
}

.chat-header h3 {
    flex: 1;
    margin: 0;
    font-size: 1.3em;
    color: #1a1a1a;
}

.chat-back-btn {
    background: none;
    border: none;
    font-size: 1.5em;
    color: #666;
    cursor: pointer;
    padding: 0 10px;
}

.chat-back-btn:hover {
    color: #1a1a1a;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5em;
    color: #666;
    cursor: pointer;
}

.modal-close:hover {
    color: #1a1a1a;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.chat-loading {
    text-align: center;
    color: #666;
    padding: 20px;
}

.chat-empty {
    text-align: center;
    color: #999;
    padding: 40px 20px;
    font-style: italic;
}

.chat-message {
    max-width: 80%;
    padding: 10px 15px;
    border-radius: 16px;
    word-wrap: break-word;
}

.chat-message.mine {
    align-self: flex-end;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom-right-radius: 4px;
}

.chat-message.theirs {
    align-self: flex-start;
    background: white;
    color: #1a1a1a;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chat-message .message-text {
    margin-bottom: 5px;
}

.chat-message .message-time {
    font-size: 0.75em;
    opacity: 0.7;
}

.chat-input-area {
    display: flex;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid #e0e0e0;
    background: white;
}

.chat-input-area input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 1em;
    outline: none;
}

.chat-input-area input:focus {
    border-color: #667eea;
}

.chat-input-area button {
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 25px;
    font-weight: bold;
    cursor: pointer;
}

.chat-input-area button:hover {
    transform: scale(1.05);
}
</style>

<script>
let currentChatContactId = null;
let currentChatContactName = '';

function openResultChat(contactId, contactName) {
    currentChatContactId = contactId;
    currentChatContactName = contactName;
    document.getElementById('chatContactName').textContent = contactName;
    document.getElementById('chatModal').style.display = 'flex';
    document.getElementById('chatInput').value = '';
    loadResultConversation();
}

function closeResultChatModal() {
    document.getElementById('chatModal').style.display = 'none';
    currentChatContactId = null;
    currentChatContactName = '';
}

function loadResultConversation() {
    const messagesDiv = document.getElementById('chatMessages');
    messagesDiv.innerHTML = '<p class="chat-loading">{{ __("Chargement...") }}</p>';

    fetch(`/chat/conversation/${currentChatContactId}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayResultMessages(data.messages);
        } else {
            messagesDiv.innerHTML = '<p class="chat-empty">{{ __("Erreur de chargement") }}</p>';
        }
    })
    .catch(error => {
        console.error('Error loading conversation:', error);
        messagesDiv.innerHTML = '<p class="chat-empty">{{ __("Erreur de connexion") }}</p>';
    });
}

function displayResultMessages(messages) {
    const messagesDiv = document.getElementById('chatMessages');
    
    if (messages.length === 0) {
        messagesDiv.innerHTML = '<p class="chat-empty">{{ __("Aucun message. Commencez la conversation !") }}</p>';
        return;
    }

    messagesDiv.innerHTML = messages.map(msg => `
        <div class="chat-message ${msg.is_mine ? 'mine' : 'theirs'}">
            <div class="message-text">${escapeHtml(msg.message)}</div>
            <div class="message-time">${msg.time_ago}</div>
        </div>
    `).join('');

    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function sendResultMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message || !currentChatContactId) return;
    
    input.disabled = true;

    fetch('/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            receiver_id: currentChatContactId,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        input.disabled = false;
        if (data.success) {
            input.value = '';
            const messagesDiv = document.getElementById('chatMessages');
            const emptyMsg = messagesDiv.querySelector('.chat-empty');
            if (emptyMsg) emptyMsg.remove();
            
            messagesDiv.innerHTML += `
                <div class="chat-message mine">
                    <div class="message-text">${escapeHtml(data.message.message)}</div>
                    <div class="message-time">${data.message.time_ago}</div>
                </div>
            `;
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
            input.focus();
        } else {
            alert(data.message || "{{ __('Erreur lors de l\\'envoi du message') }}");
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        input.disabled = false;
        alert("{{ __('Erreur de connexion') }}");
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('chatModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeResultChatModal();
        }
    });

    document.getElementById('chatInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendResultMessage();
        }
    });
});
</script>
@endsection
