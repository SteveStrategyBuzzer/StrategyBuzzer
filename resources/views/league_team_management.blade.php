@extends('layouts.app')

@section('content')
<div class="league-lobby-container">
    <div class="league-header">
        <button onclick="window.location.href='{{ route('menu') }}'" class="back-button">
            ← Retour
        </button>
        <h1>GESTION D'ÉQUIPE</h1>
    </div>

    <div class="team-management-content">
        @if(!$team)
            <div class="create-team-section">
                <h2>🛡️ Créer une Équipe</h2>
                <p>Formez une équipe de 5 joueurs pour participer à la Ligue par Équipe</p>
                
                <div class="create-team-form">
                    <div class="form-group">
                        <label>Nom de l'équipe</label>
                        <input type="text" id="teamName" placeholder="ex: Les Champions" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label>Tag (3-10 caractères majuscules/chiffres)</label>
                        <input type="text" id="teamTag" placeholder="ex: CHAMP" maxlength="10" style="text-transform: uppercase;">
                    </div>
                    <button id="createTeamBtn" class="btn-primary btn-large">
                        <span class="btn-icon">⚔️</span>
                        CRÉER L'ÉQUIPE
                    </button>
                </div>
                <div id="createError" class="error-message" style="display: none;"></div>
            </div>

            @if($pendingInvitations->isNotEmpty())
                <div class="invitations-section">
                    <h3>📨 Invitations Reçues</h3>
                    @foreach($pendingInvitations as $invitation)
                        <div class="invitation-card">
                            <div class="invitation-info">
                                <p class="team-name">{{ $invitation->team->name }} [{{ $invitation->team->tag }}]</p>
                                <p class="captain-name">Capitaine: {{ $invitation->team->captain->name }}</p>
                            </div>
                            <div class="invitation-actions">
                                <button onclick="acceptInvitation({{ $invitation->id }})" class="btn-accept">✓ Accepter</button>
                                <button onclick="declineInvitation({{ $invitation->id }})" class="btn-decline">✗ Refuser</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="team-info-section">
                <div class="team-header-card">
                    <h2>{{ $team->name }} <span class="team-tag">[{{ $team->tag }}]</span></h2>
                    <div class="team-division {{ $team->division }}">
                        {{ ucfirst($team->division) }} - {{ $team->points }} pts
                    </div>
                    <div class="team-stats">
                        <span>{{ $team->matches_won }}V - {{ $team->matches_lost }}D</span>
                        @if($team->matches_played > 0)
                            <span>({{ number_format(($team->matches_won / $team->matches_played) * 100, 1) }}%)</span>
                        @endif
                    </div>
                </div>

                <div class="team-members-section">
                    <h3>👥 Membres ({{ $team->teamMembers->count() }}/5)</h3>
                    <div class="members-list">
                        @foreach($team->teamMembers as $member)
                            <div class="member-card">
                                <div class="member-info">
                                    <div class="member-avatar">
                                        @if($member->user->avatar_url)
                                            <img src="{{ $member->user->avatar_url }}" alt="Avatar">
                                        @else
                                            <div class="default-avatar">{{ substr($member->user->name, 0, 1) }}</div>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="member-name">{{ $member->user->name }}</p>
                                        <p class="member-role">
                                            @if($member->role === 'captain')
                                                👑 Capitaine
                                            @else
                                                Membre
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @if($team->captain_id === Auth::id() && $member->user_id !== Auth::id())
                                    <button onclick="kickMember({{ $member->user_id }})" class="btn-kick">
                                        Expulser
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($team->captain_id === Auth::id() && $team->teamMembers->count() < 5)
                    <div class="invite-section">
                        <h3>📩 Inviter un Joueur</h3>
                        <div class="invite-form">
                            <input type="text" id="playerName" placeholder="Nom du joueur">
                            <button id="inviteBtn" class="btn-primary">Inviter</button>
                        </div>
                        <div id="inviteError" class="error-message" style="display: none;"></div>
                        <div id="inviteSuccess" class="success-message" style="display: none;"></div>
                    </div>
                @endif

                <div class="team-actions">
                    @if($team->teamMembers->count() >= 5)
                        <button onclick="window.location.href='{{ route('league.team.lobby') }}'" class="btn-primary btn-large">
                            <span class="btn-icon">🎮</span>
                            ALLER AU LOBBY
                        </button>
                    @else
                        <p class="info-message">⚠️ Votre équipe doit avoir 5 joueurs pour participer aux matchs</p>
                    @endif
                    
                    <button onclick="leaveTeam()" class="btn-danger">
                        {{ $team->captain_id === Auth::id() && $team->teamMembers->count() > 1 ? 'Quitter & Transférer Capitanat' : 'Quitter l\'Équipe' }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
.team-management-content {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.create-team-section, .team-info-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 20px;
}

.create-team-section h2 {
    color: #00d4ff;
    margin-bottom: 10px;
}

.create-team-form {
    margin-top: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #fff;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #0f3460;
    border-radius: 8px;
    background: #16213e;
    color: #fff;
    font-size: 16px;
}

.form-group input:focus {
    outline: none;
    border-color: #00d4ff;
}

.team-header-card {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border-radius: 10px;
}

.team-header-card h2 {
    color: #00d4ff;
    margin-bottom: 10px;
}

.team-tag {
    color: #ffd700;
    font-weight: bold;
}

.team-division {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 20px;
    margin: 10px 0;
    font-weight: bold;
}

.team-division.bronze { background: linear-gradient(135deg, #CD7F32, #8B4513); }
.team-division.argent { background: linear-gradient(135deg, #C0C0C0, #808080); }
.team-division.or { background: linear-gradient(135deg, #FFD700, #FFA500); }
.team-division.platine { background: linear-gradient(135deg, #E5E4E2, #B0B0B0); }
.team-division.diamant { background: linear-gradient(135deg, #B9F2FF, #00CED1); }
.team-division.legende { background: linear-gradient(135deg, #FF00FF, #8B008B); }

.team-stats {
    color: #aaa;
    margin-top: 10px;
}

.team-members-section {
    margin: 30px 0;
}

.team-members-section h3 {
    color: #00d4ff;
    margin-bottom: 15px;
}

.members-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.member-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #1a1a2e;
    border: 1px solid #0f3460;
    border-radius: 10px;
}

.member-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.member-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
}

.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #0f3460);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    color: #fff;
}

.member-name {
    font-weight: bold;
    color: #fff;
    margin: 0;
}

.member-role {
    color: #aaa;
    font-size: 14px;
    margin: 5px 0 0 0;
}

.btn-kick {
    padding: 8px 16px;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-kick:hover {
    background: #c82333;
}

.invite-section {
    margin: 30px 0;
}

.invite-section h3 {
    color: #00d4ff;
    margin-bottom: 15px;
}

.invite-form {
    display: flex;
    gap: 10px;
}

.invite-form input {
    flex: 1;
    padding: 12px;
    border: 2px solid #0f3460;
    border-radius: 8px;
    background: #16213e;
    color: #fff;
}

.team-actions {
    margin-top: 30px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: center;
}

.btn-danger {
    padding: 12px 24px;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-danger:hover {
    background: #c82333;
}

.info-message {
    color: #ffd700;
    text-align: center;
    padding: 15px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 8px;
}

.invitations-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 20px;
    margin-top: 20px;
}

.invitations-section h3 {
    color: #00d4ff;
    margin-bottom: 15px;
}

.invitation-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #1a1a2e;
    border: 1px solid #0f3460;
    border-radius: 10px;
    margin-bottom: 10px;
}

.invitation-info .team-name {
    font-weight: bold;
    color: #00d4ff;
    margin: 0 0 5px 0;
}

.invitation-info .captain-name {
    color: #aaa;
    font-size: 14px;
    margin: 0;
}

.invitation-actions {
    display: flex;
    gap: 10px;
}

.btn-accept, .btn-decline {
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s;
}

.btn-accept {
    background: #28a745;
    color: #fff;
}

.btn-accept:hover {
    background: #218838;
}

.btn-decline {
    background: #dc3545;
    color: #fff;
}

.btn-decline:hover {
    background: #c82333;
}

.error-message {
    color: #ff6b6b;
    background: rgba(255, 107, 107, 0.1);
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
}

.success-message {
    color: #28a745;
    background: rgba(40, 167, 69, 0.1);
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
}
</style>

<script>
document.getElementById('createTeamBtn')?.addEventListener('click', async () => {
    const name = document.getElementById('teamName').value.trim();
    const tag = document.getElementById('teamTag').value.trim().toUpperCase();
    const errorDiv = document.getElementById('createError');

    if (!name || !tag) {
        errorDiv.textContent = 'Veuillez remplir tous les champs';
        errorDiv.style.display = 'block';
        return;
    }

    if (!/^[A-Z0-9]+$/.test(tag)) {
        errorDiv.textContent = 'Le tag doit contenir uniquement des majuscules et des chiffres';
        errorDiv.style.display = 'block';
        return;
    }

    try {
        const response = await fetch('/api/league/team/create-team', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            },
            body: JSON.stringify({ name, tag })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            errorDiv.textContent = data.error || 'Erreur lors de la création de l\'équipe';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Erreur de connexion';
        errorDiv.style.display = 'block';
    }
});

document.getElementById('inviteBtn')?.addEventListener('click', async () => {
    const playerName = document.getElementById('playerName').value.trim();
    const errorDiv = document.getElementById('inviteError');
    const successDiv = document.getElementById('inviteSuccess');

    if (!playerName) {
        errorDiv.textContent = 'Veuillez entrer un nom de joueur';
        errorDiv.style.display = 'block';
        successDiv.style.display = 'none';
        return;
    }

    try {
        const response = await fetch('/api/league/team/invite-player', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            },
            body: JSON.stringify({ player_name: playerName })
        });

        const data = await response.json();

        if (data.success) {
            successDiv.textContent = 'Invitation envoyée avec succès !';
            successDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            document.getElementById('playerName').value = '';
        } else {
            errorDiv.textContent = data.error || 'Erreur lors de l\'invitation';
            errorDiv.style.display = 'block';
            successDiv.style.display = 'none';
        }
    } catch (error) {
        errorDiv.textContent = 'Erreur de connexion';
        errorDiv.style.display = 'block';
        successDiv.style.display = 'none';
    }
});

async function acceptInvitation(invitationId) {
    try {
        const response = await fetch(`/api/league/team/invitation/${invitationId}/accept`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            }
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erreur lors de l\'acceptation');
        }
    } catch (error) {
        alert('Erreur de connexion');
    }
}

async function declineInvitation(invitationId) {
    try {
        const response = await fetch(`/api/league/team/invitation/${invitationId}/decline`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            }
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        }
    } catch (error) {
        alert('Erreur de connexion');
    }
}

async function kickMember(memberId) {
    if (!confirm('Êtes-vous sûr de vouloir expulser ce membre ?')) return;

    try {
        const response = await fetch('/api/league/team/kick-member', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            },
            body: JSON.stringify({ member_id: memberId })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erreur lors de l\'expulsion');
        }
    } catch (error) {
        alert('Erreur de connexion');
    }
}

async function leaveTeam() {
    if (!confirm('Êtes-vous sûr de vouloir quitter l\'équipe ?')) return;

    try {
        const response = await fetch('/api/league/team/leave-team', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            }
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erreur lors de la sortie');
        }
    } catch (error) {
        alert('Erreur de connexion');
    }
}
</script>
@endsection
