@extends('layouts.app')

@section('content')
<div class="league-lobby-container">
    <div class="league-header">
        <button onclick="window.location.href='{{ route('league.team.management') }}'" class="back-button">
            ← Gestion
        </button>
        <h1>LIGUE PAR ÉQUIPE</h1>
        <div class="team-badge">
            <span class="team-name">{{ $team->name }}</span>
            <span class="team-tag">[{{ $team->tag }}]</span>
            <div class="division-badge {{ $team->division }}">
                <span class="division-name">{{ ucfirst($team->division) }}</span>
                <span class="division-points">{{ $team->points }} pts</span>
            </div>
        </div>
    </div>

    <div class="lobby-content">
        <div class="team-section">
            <div class="team-roster-card">
                <h3>👥 VOTRE ÉQUIPE</h3>
                <div class="roster-grid">
                    @foreach($team->members as $member)
                        <div class="roster-member">
                            <div class="member-avatar-small">
                                @if($member->user->avatar_url)
                                    <img src="{{ $member->user->avatar_url }}" alt="Avatar">
                                @else
                                    <div class="default-avatar-small">{{ substr($member->user->name, 0, 1) }}</div>
                                @endif
                            </div>
                            <div class="member-details">
                                <p class="member-name">{{ $member->user->name }}</p>
                                @if($member->role === 'captain')
                                    <span class="captain-badge">👑</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="matchmaking-card">
                <h3>⚔️ MATCHMAKING</h3>
                <p>Affrontez une autre équipe de votre division</p>
                <button id="startMatchmakingBtn" class="btn-primary btn-large">
                    <span class="btn-icon">🎯</span>
                    TROUVER UN ADVERSAIRE
                </button>
                <div id="searchingStatus" class="searching-status" style="display: none;">
                    <div class="spinner"></div>
                    <p>Recherche d'une équipe adverse...</p>
                </div>
            </div>

            <div class="stats-summary">
                <h4>Statistiques de l'Équipe</h4>
                <div class="stats-grid">
                    <div class="stat">
                        <span class="stat-value">{{ $team->matches_played }}</span>
                        <span class="stat-label">Matchs joués</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">{{ $team->matches_won }}</span>
                        <span class="stat-label">Victoires</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">{{ $team->points }}</span>
                        <span class="stat-label">Points</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">
                            @if($team->matches_played > 0)
                                {{ number_format(($team->matches_won / $team->matches_played) * 100, 1) }}%
                            @else
                                0%
                            @endif
                        </span>
                        <span class="stat-label">Taux de victoire</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="ranking-section">
            <div class="ranking-header">
                <h3>🏆 CLASSEMENT {{ strtoupper($team->division) }}</h3>
            </div>
            <div class="ranking-list">
                @forelse($rankings as $ranking)
                <div class="ranking-item {{ $ranking['team']->id == $team->id ? 'current-team' : '' }}">
                    <span class="rank">#{{ $ranking['rank'] }}</span>
                    <div class="team-details">
                        <span class="team-name-rank">{{ $ranking['team']->name }}</span>
                        <span class="team-tag-rank">[{{ $ranking['team']->tag }}]</span>
                        <span class="team-record">
                            {{ $ranking['team']->matches_won }}V - {{ $ranking['team']->matches_lost }}D
                        </span>
                    </div>
                    <div class="team-stats-right">
                        <span class="team-points">{{ $ranking['team']->points }} pts</span>
                        <span class="team-winrate">({{ $ranking['win_rate'] }}%)</span>
                    </div>
                </div>
                @empty
                <p class="no-rankings">Aucune équipe dans votre division pour le moment</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<style>
.team-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.team-badge .team-name {
    font-size: 24px;
    font-weight: bold;
    color: #00d4ff;
}

.team-badge .team-tag {
    color: #ffd700;
    font-size: 18px;
    font-weight: bold;
}

.team-section {
    flex: 1;
    max-width: 500px;
}

.team-roster-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.team-roster-card h3 {
    color: #00d4ff;
    margin-bottom: 15px;
    text-align: center;
}

.roster-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}

.roster-member {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #1a1a2e;
    border: 1px solid #0f3460;
    border-radius: 8px;
}

.member-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.member-avatar-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #0f3460);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: bold;
    color: #fff;
}

.member-details {
    flex: 1;
    min-width: 0;
}

.member-details .member-name {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.captain-badge {
    font-size: 12px;
}

.team-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.team-name-rank {
    font-weight: bold;
    color: #fff;
}

.team-tag-rank {
    color: #ffd700;
    font-size: 14px;
}

.team-record {
    color: #aaa;
    font-size: 13px;
}

.team-stats-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.team-points {
    font-weight: bold;
    color: #00d4ff;
}

.team-winrate {
    color: #aaa;
    font-size: 13px;
}

.current-team {
    background: linear-gradient(135deg, #0f3460, #1a1a2e) !important;
    border: 2px solid #00d4ff !important;
}

@media (max-width: 768px) {
    .lobby-content {
        flex-direction: column;
    }
    
    .team-section, .ranking-section {
        max-width: 100%;
    }
    
    .roster-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.getElementById('startMatchmakingBtn')?.addEventListener('click', async () => {
    const btn = document.getElementById('startMatchmakingBtn');
    const status = document.getElementById('searchingStatus');
    
    btn.style.display = 'none';
    status.style.display = 'block';

    try {
        const response = await fetch('/api/league/team/start-matchmaking', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            }
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = `/league/team/game/${data.match_id}`;
        } else {
            alert(data.error || 'Erreur lors du matchmaking');
            btn.style.display = 'block';
            status.style.display = 'none';
        }
    } catch (error) {
        alert('Erreur de connexion');
        btn.style.display = 'block';
        status.style.display = 'none';
    }
});
</script>
@endsection
