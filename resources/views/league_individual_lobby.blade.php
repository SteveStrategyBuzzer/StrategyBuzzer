@extends('layouts.app')

@section('content')
<div class="league-lobby-container">
    <div class="league-header">
        <button onclick="window.location.href='{{ route('menu') }}'" class="back-button">
            ← Retour
        </button>
        <h1>LIGUE INDIVIDUEL</h1>
        <div class="division-badge {{ $division->division ?? 'bronze' }}">
            <span class="division-name">{{ ucfirst($division->division ?? 'Bronze') }}</span>
            <span class="division-level">Niveau {{ $division->level ?? 1 }}</span>
            <span class="division-points">{{ $division->points ?? 0 }} pts</span>
        </div>
    </div>

    <div class="lobby-content">
        <div class="player-section">
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
                        {{ $stats->matches_won ?? 0 }}V - {{ $stats->matches_lost ?? 0 }}D
                        @if($stats->matches_played > 0)
                            ({{ number_format(($stats->matches_won / $stats->matches_played) * 100, 1) }}%)
                        @endif
                    </p>
                    <p class="player-rank">
                        @if($rank)
                            #{{ $rank }} dans votre division
                        @else
                            Pas encore classé
                        @endif
                    </p>
                </div>
            </div>

            <div class="matchmaking-card">
                <h3>⚔️ MATCHMAKING RAPIDE</h3>
                <p>Affrontez un adversaire aléatoire de votre division</p>
                <button id="findMatchBtn" class="btn-primary btn-large">
                    <span class="btn-icon">🎯</span>
                    TROUVER UN ADVERSAIRE
                </button>
                <div id="searchingStatus" class="searching-status" style="display: none;">
                    <div class="spinner"></div>
                    <p>Recherche d'un adversaire...</p>
                </div>
            </div>

            <div class="stats-summary">
                <h4>Statistiques Globales</h4>
                <div class="stats-grid">
                    <div class="stat">
                        <span class="stat-value">{{ $stats->matches_played ?? 0 }}</span>
                        <span class="stat-label">Matchs joués</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">{{ $stats->total_points ?? 0 }}</span>
                        <span class="stat-label">Points totaux</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">{{ $division->level ?? 1 }}</span>
                        <span class="stat-label">Niveau actuel</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="ranking-section">
            <div class="ranking-header">
                <h3>🏆 CLASSEMENT {{ strtoupper($division->division ?? 'BRONZE') }}</h3>
                <button onclick="window.location.href='{{ route('league.individual.rankings') }}'" class="btn-link">
                    Voir tout →
                </button>
            </div>
            <div class="ranking-list">
                @forelse($rankings as $ranking)
                <div class="ranking-item {{ $ranking['user']->id == Auth::id() ? 'current-player' : '' }}">
                    <span class="rank">#{{ $ranking['rank'] }}</span>
                    <div class="player-details">
                        <span class="player-name">{{ $ranking['user']->name }}</span>
                        <span class="player-record">
                            {{ $ranking['stats']->matches_won ?? 0 }}V - {{ $ranking['stats']->matches_lost ?? 0 }}D
                        </span>
                    </div>
                    <div class="player-stats-right">
                        <span class="player-level">Niv. {{ $ranking['level'] }}</span>
                        <span class="player-points">{{ $ranking['points'] }} pts</span>
                    </div>
                </div>
                @empty
                <p class="no-rankings">Aucun joueur dans votre division pour le moment</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<style>
.league-lobby-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    min-height: 100vh;
    background: #f5f5f5;
}

.league-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.back-button {
    padding: 10px 20px;
    background: #e0e0e0;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    cursor: pointer;
    transition: background 0.3s;
}

.back-button:hover {
    background: #d0d0d0;
}

.league-header h1 {
    font-size: 2.5em;
    color: #1a1a1a;
    flex: 1;
    text-align: center;
}

.division-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    min-width: 150px;
}

.division-badge.bronze { background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%); }
.division-badge.argent { background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%); }
.division-badge.or { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
.division-badge.platine { background: linear-gradient(135deg, #E5E4E2 0%, #71797E 100%); }
.division-badge.diamant { background: linear-gradient(135deg, #B9F2FF 0%, #00CED1 100%); }
.division-badge.legende { background: linear-gradient(135deg, #FF1493 0%, #8B008B 100%); }

.division-name {
    font-size: 1.3em;
    font-weight: bold;
    text-transform: uppercase;
}

.division-level, .division-points {
    font-size: 0.95em;
    opacity: 0.95;
}

.lobby-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.player-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.player-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
}

.player-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
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
    font-size: 1.5em;
    margin-bottom: 8px;
    color: #1a1a1a;
}

.player-stats {
    color: #666;
    font-size: 1.1em;
    margin-bottom: 5px;
}

.player-rank {
    color: #667eea;
    font-weight: 600;
}

.matchmaking-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.matchmaking-card h3 {
    font-size: 1.6em;
    color: #1a1a1a;
    margin-bottom: 10px;
}

.matchmaking-card p {
    color: #666;
    margin-bottom: 25px;
}

.btn-primary {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.2em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-icon {
    font-size: 1.3em;
}

.searching-status {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.stats-summary {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.stats-summary h4 {
    font-size: 1.3em;
    margin-bottom: 20px;
    color: #1a1a1a;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat {
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stat-value {
    font-size: 2em;
    font-weight: bold;
    color: #667eea;
}

.stat-label {
    font-size: 0.9em;
    color: #666;
}

.ranking-section {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    height: fit-content;
}

.ranking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.ranking-header h3 {
    font-size: 1.5em;
    color: #1a1a1a;
}

.btn-link {
    background: none;
    border: none;
    color: #667eea;
    cursor: pointer;
    font-size: 1em;
    transition: opacity 0.3s;
}

.btn-link:hover {
    opacity: 0.8;
}

.ranking-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f8f8;
    border-radius: 10px;
    transition: background 0.3s;
}

.ranking-item:hover {
    background: #f0f0f0;
}

.ranking-item.current-player {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border: 2px solid #667eea;
}

.rank {
    font-size: 1.2em;
    font-weight: bold;
    color: #667eea;
    min-width: 40px;
}

.player-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.player-name {
    font-weight: 600;
    color: #1a1a1a;
}

.player-record {
    font-size: 0.9em;
    color: #666;
}

.player-stats-right {
    display: flex;
    gap: 15px;
    align-items: center;
}

.player-level {
    font-size: 0.95em;
    color: #666;
}

.player-points {
    font-size: 1em;
    font-weight: 600;
    color: #667eea;
}

.no-rankings {
    text-align: center;
    color: #999;
    padding: 40px 20px;
    font-style: italic;
}

@media (max-width: 1024px) {
    .lobby-content {
        grid-template-columns: 1fr;
    }

    .league-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }

    .league-header h1 {
        font-size: 2em;
    }

    .division-badge {
        align-items: center;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.getElementById('findMatchBtn').addEventListener('click', async function() {
    const btn = this;
    const searchingStatus = document.getElementById('searchingStatus');
    
    btn.disabled = true;
    searchingStatus.style.display = 'flex';
    
    try {
        const response = await fetch('/api/league/individual/create-match', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = `/league/individual/game/${data.match_id}`;
        } else {
            showToast(data.message || '{{ __("Aucun adversaire disponible pour le moment") }}', 'warning');
            btn.disabled = false;
            searchingStatus.style.display = 'none';
        }
    } catch (error) {
        console.error('Error finding match:', error);
        showToast('{{ __("Erreur lors de la recherche d\'adversaire") }}', 'error');
        btn.disabled = false;
        searchingStatus.style.display = 'none';
    }
});
</script>
@endsection
