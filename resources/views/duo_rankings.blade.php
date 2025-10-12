@extends('layouts.app')

@section('content')
<div class="rankings-container">
    <div class="rankings-header">
        <button onclick="window.location.href='{{ route('duo.lobby') }}'" class="back-button">
            ← Retour
        </button>
        <h1>CLASSEMENTS DUO</h1>
        <div class="division-selector">
            <select id="divisionSelect" onchange="changeDivision(this.value)" class="division-dropdown">
                <option value="bronze" {{ $division == 'bronze' ? 'selected' : '' }}>Bronze</option>
                <option value="argent" {{ $division == 'argent' ? 'selected' : '' }}>Argent</option>
                <option value="or" {{ $division == 'or' ? 'selected' : '' }}>Or</option>
                <option value="platine" {{ $division == 'platine' ? 'selected' : '' }}>Platine</option>
                <option value="diamant" {{ $division == 'diamant' ? 'selected' : '' }}>Diamant</option>
                <option value="legende" {{ $division == 'legende' ? 'selected' : '' }}>Légende</option>
            </select>
        </div>
    </div>

    <div class="my-position">
        <div class="position-card">
            <span class="position-label">Votre position</span>
            <span class="position-rank">#{{ $my_rank ?? '-' }}</span>
        </div>
    </div>

    <div class="rankings-list">
        @forelse($rankings as $index => $player)
        <div class="ranking-row {{ $player['user_id'] == Auth::id() ? 'current-player' : '' }} rank-{{ $index + 1 }}">
            <div class="rank-badge">
                @if($index == 0)
                    🥇
                @elseif($index == 1)
                    🥈
                @elseif($index == 2)
                    🥉
                @else
                    #{{ $index + 1 }}
                @endif
            </div>
            
            <div class="player-avatar-small">
                @if($player['user']['avatar_url'] ?? null)
                    <img src="{{ $player['user']['avatar_url'] }}" alt="{{ $player['user']['name'] }}">
                @else
                    <div class="default-avatar-small">{{ substr($player['user']['name'], 0, 1) }}</div>
                @endif
            </div>

            <div class="player-details">
                <div class="player-name">
                    {{ $player['user']['name'] }}
                    @if($player['user_id'] == Auth::id())
                        <span class="you-badge">VOUS</span>
                    @endif
                </div>
                <div class="player-meta">
                    Niveau {{ $player['level'] }} • {{ $player['points'] }} pts
                </div>
            </div>

            <div class="player-stats">
                <div class="win-rate">
                    @if(isset($player['matches_played']) && $player['matches_played'] > 0)
                        {{ round(($player['matches_won'] / $player['matches_played']) * 100) }}% victoires
                    @else
                        - %
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="no-rankings">
            <p>Aucun joueur dans cette division</p>
        </div>
        @endforelse
    </div>

    <div class="division-info">
        <h3>À propos de la division {{ ucfirst($division) }}</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Points requis:</span>
                <span class="info-value">
                    @if($division == 'bronze') 0-99
                    @elseif($division == 'argent') 100-199
                    @elseif($division == 'or') 200-299
                    @elseif($division == 'platine') 300-399
                    @elseif($division == 'diamant') 400-499
                    @else 500+
                    @endif
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Joueurs:</span>
                <span class="info-value">{{ count($rankings) }}</span>
            </div>
        </div>
    </div>
</div>

<style>
.rankings-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f5f5;
    min-height: 100vh;
}

.rankings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: white;
    padding: 20px;
    border-radius: 16px;
}

.rankings-header h1 {
    flex: 1;
    text-align: center;
    margin: 0;
    font-size: 2em;
    color: #1a1a1a;
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

.division-dropdown {
    padding: 10px 15px;
    border: 2px solid #667eea;
    border-radius: 8px;
    font-size: 1em;
    font-weight: bold;
    color: #667eea;
    background: white;
    cursor: pointer;
}

.my-position {
    margin-bottom: 20px;
}

.position-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.position-label {
    font-size: 1.1em;
}

.position-rank {
    font-size: 2em;
    font-weight: bold;
}

.rankings-list {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.ranking-row {
    display: grid;
    grid-template-columns: 60px 50px 1fr auto;
    gap: 15px;
    align-items: center;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 10px;
    background: #f9f9f9;
    transition: all 0.3s;
}

.ranking-row:hover {
    background: #f0f0f0;
    transform: translateX(5px);
}

.ranking-row.current-player {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
    border: 2px solid #667eea;
}

.ranking-row.rank-1 {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.2) 0%, rgba(255, 223, 0, 0.2) 100%);
}

.ranking-row.rank-2 {
    background: linear-gradient(135deg, rgba(192, 192, 192, 0.2) 0%, rgba(211, 211, 211, 0.2) 100%);
}

.ranking-row.rank-3 {
    background: linear-gradient(135deg, rgba(205, 127, 50, 0.2) 0%, rgba(184, 115, 51, 0.2) 100%);
}

.rank-badge {
    font-size: 1.5em;
    font-weight: bold;
    text-align: center;
    color: #667eea;
}

.player-avatar-small {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
}

.player-avatar-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar-small {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: bold;
    font-size: 1.2em;
}

.player-details {
    flex: 1;
}

.player-name {
    font-weight: bold;
    font-size: 1.1em;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 10px;
}

.you-badge {
    background: #667eea;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7em;
}

.player-meta {
    color: #666;
    font-size: 0.9em;
    margin-top: 3px;
}

.player-stats {
    text-align: right;
}

.win-rate {
    color: #4caf50;
    font-weight: bold;
}

.no-rankings {
    text-align: center;
    padding: 40px;
    color: #999;
}

.division-info {
    background: white;
    border-radius: 16px;
    padding: 25px;
}

.division-info h3 {
    margin: 0 0 15px 0;
    color: #1a1a1a;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 8px;
}

.info-label {
    color: #666;
}

.info-value {
    font-weight: bold;
    color: #1a1a1a;
}

@media (max-width: 768px) {
    .rankings-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .ranking-row {
        grid-template-columns: 50px 40px 1fr;
    }
    
    .player-stats {
        grid-column: 2 / -1;
        text-align: left;
        margin-top: 5px;
    }
}
</style>

<script>
function changeDivision(division) {
    window.location.href = '{{ route("duo.rankings") }}?division=' + division;
}
</script>
@endsection
