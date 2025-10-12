@extends('layouts.app')

@section('content')
<div class="duo-lobby-container">
    <div class="duo-header">
        <button onclick="window.location.href='{{ route('menu') }}'" class="back-button">
            ← Retour
        </button>
        <h1>MODE DUO</h1>
        <div class="division-badge">
            <span class="division-name">{{ $division['name'] ?? 'Bronze' }}</span>
            <span class="division-level">Niveau {{ $division['level'] ?? 1 }}</span>
            <span class="division-points">{{ $division['points'] ?? 0 }} pts</span>
        </div>
    </div>

    <div class="lobby-content">
        <div class="player-card">
            <div class="player-avatar">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="Avatar">
                @else
                    <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                @endif
            </div>
            <div class="player-info">
                <h3>{{ Auth::user()->name }}</h3>
                <p class="player-stats">
                    {{ $stats['matches_won'] ?? 0 }}V - {{ $stats['matches_lost'] ?? 0 }}D
                    @if(isset($stats['win_rate']))
                        ({{ number_format($stats['win_rate'], 1) }}%)
                    @endif
                </p>
            </div>
        </div>

        <div class="matchmaking-options">
            <div class="option-card">
                <h3>🎯 MATCHMAKING ALÉATOIRE</h3>
                <p>Affrontez un adversaire de votre division</p>
                <button id="randomMatchBtn" class="btn-primary btn-large">
                    CHERCHER UN ADVERSAIRE
                </button>
            </div>

            <div class="divider">OU</div>

            <div class="option-card">
                <h3>👥 INVITER UN AMI</h3>
                <p>Défiez un joueur spécifique</p>
                <div class="invite-section">
                    <input type="text" id="inviteInput" placeholder="Nom du joueur..." class="invite-input">
                    <button id="inviteBtn" class="btn-secondary btn-large">
                        INVITER
                    </button>
                </div>
            </div>
        </div>

        <div class="pending-invitations" id="pendingInvitations" style="display: none;">
            <h3>📬 Invitations reçues</h3>
            <div id="invitationsList"></div>
        </div>
    </div>

    <div class="ranking-preview">
        <h3>🏆 Classement {{ $division['name'] ?? 'Bronze' }}</h3>
        <div class="ranking-list">
            @foreach($rankings ?? [] as $index => $player)
            <div class="ranking-item {{ $player['user_id'] == Auth::id() ? 'current-player' : '' }}">
                <span class="rank">#{{ $index + 1 }}</span>
                <span class="player-name">{{ $player['user']['name'] }}</span>
                <span class="player-level">Niv. {{ $player['level'] }}</span>
                <span class="player-points">{{ $player['points'] }} pts</span>
            </div>
            @endforeach
        </div>
        <button onclick="window.location.href='{{ route('duo.rankings') }}'" class="btn-link">
            Voir le classement complet →
        </button>
    </div>
</div>

<style>
.duo-lobby-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.duo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.duo-header h1 {
    font-size: 2.5em;
    color: #1a1a1a;
    text-align: center;
    flex: 1;
}

.division-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
}

.division-name {
    font-size: 1.2em;
    font-weight: bold;
}

.division-level {
    font-size: 0.9em;
    opacity: 0.9;
}

.division-points {
    font-size: 0.8em;
    opacity: 0.8;
}

.lobby-content {
    display: grid;
    gap: 30px;
    margin-bottom: 40px;
}

.player-card {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.player-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 20px;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 2em;
    font-weight: bold;
}

.player-info h3 {
    margin: 0;
    font-size: 1.5em;
    color: #1a1a1a;
}

.player-stats {
    margin: 5px 0 0 0;
    color: #666;
}

.matchmaking-options {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 30px;
    align-items: center;
}

.option-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    text-align: center;
}

.option-card h3 {
    margin: 0 0 10px 0;
    color: #1a1a1a;
}

.option-card p {
    margin: 0 0 20px 0;
    color: #666;
}

.divider {
    text-align: center;
    color: #999;
    font-weight: bold;
}

.invite-section {
    display: flex;
    gap: 10px;
}

.invite-input {
    flex: 1;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1em;
}

.invite-input:focus {
    outline: none;
    border-color: #667eea;
}

.btn-primary, .btn-secondary {
    padding: 15px 30px;
    border: none;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #f0f0f0;
    color: #1a1a1a;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-large {
    width: 100%;
}

.pending-invitations {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.pending-invitations h3 {
    margin: 0 0 15px 0;
    color: #1a1a1a;
}

.ranking-preview {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.ranking-preview h3 {
    margin: 0 0 20px 0;
    color: #1a1a1a;
}

.ranking-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.ranking-item {
    display: grid;
    grid-template-columns: 50px 1fr auto auto;
    gap: 15px;
    align-items: center;
    padding: 12px;
    border-radius: 8px;
    background: #f9f9f9;
}

.ranking-item.current-player {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border: 2px solid #667eea;
}

.rank {
    font-weight: bold;
    color: #667eea;
}

.player-name {
    font-weight: 500;
}

.player-level, .player-points {
    color: #666;
    font-size: 0.9em;
}

.btn-link {
    background: none;
    border: none;
    color: #667eea;
    cursor: pointer;
    font-size: 1em;
    padding: 10px;
}

.btn-link:hover {
    text-decoration: underline;
}

.back-button {
    background: #f0f0f0;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 1em;
}

.back-button:hover {
    background: #e0e0e0;
}

@media (max-width: 768px) {
    .matchmaking-options {
        grid-template-columns: 1fr;
    }
    
    .divider {
        display: none;
    }
    
    .duo-header {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const randomMatchBtn = document.getElementById('randomMatchBtn');
    const inviteBtn = document.getElementById('inviteBtn');
    const inviteInput = document.getElementById('inviteInput');

    randomMatchBtn.addEventListener('click', function() {
        this.disabled = true;
        this.textContent = 'RECHERCHE EN COURS...';
        
        fetch('{{ route("duo.matchmaking.random") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '{{ route("duo.matchmaking") }}?match_id=' + data.match_id;
            } else {
                alert(data.message || 'Erreur lors de la recherche');
                this.disabled = false;
                this.textContent = 'CHERCHER UN ADVERSAIRE';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur de connexion');
            this.disabled = false;
            this.textContent = 'CHERCHER UN ADVERSAIRE';
        });
    });

    inviteBtn.addEventListener('click', function() {
        const playerName = inviteInput.value.trim();
        if (!playerName) {
            alert('Entrez le nom d\'un joueur');
            return;
        }

        fetch('{{ route("duo.invite") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ player_name: playerName })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Invitation envoyée à ' + playerName);
                inviteInput.value = '';
            } else {
                alert(data.message || 'Erreur lors de l\'invitation');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur de connexion');
        });
    });

    // Vérifier les invitations reçues
    function checkInvitations() {
        fetch('{{ route("duo.invitations") }}')
            .then(response => response.json())
            .then(data => {
                if (data.invitations && data.invitations.length > 0) {
                    displayInvitations(data.invitations);
                }
            });
    }

    function displayInvitations(invitations) {
        const container = document.getElementById('pendingInvitations');
        const list = document.getElementById('invitationsList');
        
        list.innerHTML = invitations.map(inv => `
            <div class="invitation-item">
                <span>${inv.from_player.name} vous invite</span>
                <button onclick="acceptInvitation(${inv.match_id})" class="btn-accept">Accepter</button>
                <button onclick="declineInvitation(${inv.match_id})" class="btn-decline">Refuser</button>
            </div>
        `).join('');
        
        container.style.display = 'block';
    }

    checkInvitations();
    setInterval(checkInvitations, 5000);
});

function acceptInvitation(matchId) {
    fetch(`/duo/matches/${matchId}/accept`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/duo/game/' + matchId;
        }
    });
}

function declineInvitation(matchId) {
    // TODO: Implémenter le refus d'invitation
    location.reload();
}
</script>
@endsection
