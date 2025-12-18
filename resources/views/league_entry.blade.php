@extends('layouts.app')

@section('title', __('Ligue par Équipe'))

@section('content')
<div class="league-lobby-container">
    <div class="league-header">
        <button onclick="window.location.href='{{ route('menu') }}'" class="back-button">
            ← {{ __('Retour') }}
        </button>
        <h1>🏆 {{ __('LIGUE PAR ÉQUIPE') }}</h1>
    </div>

    <div class="league-entry-content">
        @if($userTeams->isNotEmpty())
            <div class="my-teams-section">
                <h2>👥 {{ __('Mes Équipes') }}</h2>
                <div class="teams-grid">
                    @foreach($userTeams as $team)
                        <div class="team-entry-card" onclick="selectTeam({{ $team->id }})">
                            <div class="team-emblem">
                                @if($team->custom_emblem_path)
                                    <img src="{{ asset('storage/' . $team->custom_emblem_path) }}" alt="Emblem">
                                @else
                                    @php
                                        $emblems = \App\Models\Team::EMBLEM_CATEGORIES[$team->emblem_category ?? 'animals'] ?? [];
                                        $emblemEmoji = $emblems[($team->emblem_index ?? 1) - 1] ?? '🛡️';
                                    @endphp
                                    <span>{{ $emblemEmoji }}</span>
                                @endif
                            </div>
                            <div class="team-info">
                                <h3>{{ $team->name }}</h3>
                                <span class="team-id">#{{ str_pad($team->id, 6, '0', STR_PAD_LEFT) }}</span>
                                <div class="team-stats">
                                    <span class="division {{ strtolower($team->division ?? 'bronze') }}">{{ ucfirst($team->division ?? 'Bronze') }}</span>
                                    <span class="members">{{ $team->members->count() }}/5</span>
                                </div>
                                @if($team->captain_id === Auth::id())
                                    <span class="captain-badge">👑 {{ __('Capitaine') }}</span>
                                @endif
                            </div>
                            @if($team->pending_match_invites > 0)
                                <div class="notification-badge">{{ $team->pending_match_invites }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($pendingInvitations->isNotEmpty())
            <div class="invitations-section">
                <h2>📨 {{ __('Invitations Reçues') }}</h2>
                @foreach($pendingInvitations as $invitation)
                    <div class="invitation-card">
                        <div class="invitation-info">
                            <p class="team-name">{{ $invitation->team->name }} [{{ $invitation->team->tag }}]</p>
                            <p class="captain-name">{{ __('Capitaine') }}: {{ $invitation->team->captain->name }}</p>
                        </div>
                        <div class="invitation-actions">
                            <button onclick="acceptInvitation({{ $invitation->id }})" class="btn-accept">✓ {{ __('Accepter') }}</button>
                            <button onclick="declineInvitation({{ $invitation->id }})" class="btn-decline">✗ {{ __('Refuser') }}</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="menu-cards-grid">
            @if($canCreateTeam ?? false)
            <a href="{{ route('league.team.create') }}" class="menu-action-card">
                <div class="menu-card-icon">➕</div>
                <h3>{{ __('Créer une équipe') }}</h3>
                <p>{{ __('Formez votre propre équipe et invitez des joueurs') }}</p>
            </a>
            @else
            <div class="menu-action-card disabled" title="{{ __('Complétez 25 matchs Duo pour débloquer') }}">
                <div class="menu-card-icon">🔒</div>
                <h3>{{ __('Créer une équipe') }}</h3>
                <p>{{ $duoMatchesPlayed ?? 0 }}/25 {{ __('matchs Duo') }}</p>
            </div>
            @endif
            <a href="{{ route('league.team.search') }}" class="menu-action-card">
                <div class="menu-card-icon">🔍</div>
                <h3>{{ __('Chercher Équipe') }}</h3>
                <p>{{ __('Trouvez une équipe qui recrute') }}</p>
            </a>
        </div>
    </div>
</div>

<style>
.league-entry-content {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.my-teams-section {
    margin-bottom: 30px;
}

.my-teams-section h2 {
    color: #00d4ff;
    text-align: center;
    margin-bottom: 20px;
}

.teams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.team-entry-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.team-entry-card:hover {
    border-color: #00d4ff;
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 212, 255, 0.2);
}

.team-entry-card .team-emblem {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border: 3px solid #00d4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
    overflow: hidden;
}

.team-entry-card .team-emblem img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.team-entry-card .team-info {
    flex: 1;
}

.team-entry-card .team-info h3 {
    color: #fff;
    margin: 0 0 5px 0;
    font-size: 1.3rem;
}

.team-entry-card .team-id {
    color: #888;
    font-size: 0.85rem;
    font-family: monospace;
}

.team-entry-card .team-stats {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.team-entry-card .division {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
    text-transform: uppercase;
}

.team-entry-card .division.bronze { background: #cd7f32; color: #fff; }
.team-entry-card .division.silver { background: #c0c0c0; color: #333; }
.team-entry-card .division.gold { background: #ffd700; color: #333; }
.team-entry-card .division.platinum { background: #e5e4e2; color: #333; }
.team-entry-card .division.diamond { background: linear-gradient(135deg, #b9f2ff 0%, #00d4ff 100%); color: #333; }

.team-entry-card .members {
    color: #aaa;
    font-size: 0.85rem;
}

.captain-badge {
    display: inline-block;
    margin-top: 5px;
    padding: 2px 8px;
    background: rgba(255, 215, 0, 0.2);
    border: 1px solid #ffd700;
    border-radius: 10px;
    color: #ffd700;
    font-size: 0.75rem;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 24px;
    height: 24px;
    background: #ff4444;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.8rem;
    font-weight: bold;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.menu-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.menu-action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 30px 20px;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.menu-action-card:hover {
    border-color: #00d4ff;
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 212, 255, 0.2);
}

.menu-action-card.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    border-color: #555;
}

.menu-action-card.disabled:hover {
    transform: none;
    border-color: #555;
    box-shadow: none;
}

.menu-card-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.menu-action-card h3 {
    color: #00d4ff;
    margin: 0 0 10px 0;
    font-size: 1.2rem;
}

.menu-action-card p {
    color: #888;
    margin: 0;
    font-size: 0.9rem;
}

.invitations-section {
    margin-bottom: 30px;
}

.invitations-section h2 {
    color: #ffa500;
    text-align: center;
    margin-bottom: 15px;
}

.invitation-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #2a1a3e 0%, #1a1a2e 100%);
    border: 2px solid #ffa500;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 10px;
}

.invitation-info .team-name {
    color: #fff;
    font-weight: bold;
    margin: 0;
}

.invitation-info .captain-name {
    color: #aaa;
    font-size: 0.9rem;
    margin: 5px 0 0 0;
}

.invitation-actions {
    display: flex;
    gap: 10px;
}

.btn-accept, .btn-decline {
    padding: 8px 15px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s ease;
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

@media (max-width: 600px) {
    .teams-grid {
        grid-template-columns: 1fr;
    }
    
    .team-entry-card {
        padding: 15px;
    }
    
    .invitation-card {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
}
</style>

<script>
function selectTeam(teamId) {
    window.location.href = '/league/team/management/' + teamId;
}

async function acceptInvitation(invitationId) {
    try {
        const response = await fetch('/league/team/invitation/' + invitationId + '/accept', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (data.success) {
            window.showToast?.('{{ __("Invitation acceptée !") }}', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            window.showToast?.(data.error || '{{ __("Erreur") }}', 'error');
        }
    } catch (error) {
        window.showToast?.('{{ __("Erreur de connexion") }}', 'error');
    }
}

async function declineInvitation(invitationId) {
    try {
        const response = await fetch('/league/team/invitation/' + invitationId + '/decline', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (data.success) {
            window.showToast?.('{{ __("Invitation refusée") }}', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            window.showToast?.(data.error || '{{ __("Erreur") }}', 'error');
        }
    } catch (error) {
        window.showToast?.('{{ __("Erreur de connexion") }}', 'error');
    }
}
</script>
@endsection
