@extends('layouts.app')

@section('content')
<div class="results-container">
    <div class="results-card">
        <div class="results-header">
            @if($match->winner_id == Auth::id())
                <div class="result-icon victory">🏆</div>
                <h1 class="result-title victory-title">VICTOIRE !</h1>
                <p class="result-subtitle">Félicitations pour cette belle performance</p>
            @else
                <div class="result-icon defeat">😞</div>
                <h1 class="result-title defeat-title">DÉFAITE</h1>
                <p class="result-subtitle">Vous ferez mieux la prochaine fois</p>
            @endif
        </div>

        <div class="match-summary">
            <div class="player-result {{ $match->winner_id == Auth::id() ? 'winner' : '' }}">
                <div class="player-avatar">
                    @if(Auth::user()->avatar_url)
                        <img src="{{ Auth::user()->avatar_url }}" alt="Vous">
                    @else
                        <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                    @endif
                </div>
                <div class="player-details">
                    <h3>{{ Auth::user()->name }}</h3>
                    <p class="player-level">Niveau {{ $match->player1_level }}</p>
                </div>
                <div class="player-rounds">
                    <span class="rounds-won">{{ $gameState['player_rounds_won_map']['player'] ?? 0 }}</span>
                    <span class="rounds-label">Manches</span>
                </div>
            </div>

            <div class="vs-divider">VS</div>

            <div class="player-result {{ $match->winner_id == $match->player2_id ? 'winner' : '' }}">
                <div class="player-avatar">
                    <div class="default-avatar">{{ substr($match->player2->name, 0, 1) }}</div>
                </div>
                <div class="player-details">
                    <h3>{{ $match->player2->name }}</h3>
                    <p class="player-level">Niveau {{ $match->player2_level }}</p>
                </div>
                <div class="player-rounds">
                    <span class="rounds-won">{{ $gameState['player_rounds_won_map']['opponent'] ?? 0 }}</span>
                    <span class="rounds-label">Manches</span>
                </div>
            </div>
        </div>

        <div class="points-earned-section">
            <h3>POINTS DE DIVISION</h3>
            <div class="points-display">
                <span class="points-value {{ $pointsEarned >= 0 ? 'positive' : 'negative' }}">
                    {{ $pointsEarned >= 0 ? '+' : '' }}{{ $pointsEarned }}
                </span>
                <span class="points-label">points</span>
            </div>
            <p class="points-explanation">
                @if($pointsEarned > 0)
                    @if($pointsEarned == 5)
                        Victoire contre un adversaire plus fort !
                    @elseif($pointsEarned == 2)
                        Victoire contre un adversaire de niveau égal
                    @else
                        Victoire contre un adversaire plus faible
                    @endif
                @else
                    Défaite : -2 points
                @endif
            </p>
        </div>

        <div class="stats-section">
            <h3>STATISTIQUES DU MATCH</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-icon">🎯</span>
                    <span class="stat-value">{{ $stats['matches_played'] }}</span>
                    <span class="stat-label">Matchs joués</span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">🏆</span>
                    <span class="stat-value">{{ $stats['matches_won'] }}</span>
                    <span class="stat-label">Victoires</span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">📊</span>
                    <span class="stat-value">
                        @if($stats['matches_played'] > 0)
                            {{ number_format(($stats['matches_won'] / $stats['matches_played']) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </span>
                    <span class="stat-label">Taux de victoire</span>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">💎</span>
                    <span class="stat-value">{{ $division->points }}</span>
                    <span class="stat-label">Points division</span>
                </div>
            </div>
        </div>

        <div class="division-progress">
            <h3>PROGRESSION DE DIVISION</h3>
            <div class="division-info">
                <span class="current-division {{ $division->division }}">
                    {{ ucfirst($division->division) }}
                </span>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ min(100, ($division->points % 100)) }}%"></div>
                </div>
                <span class="next-division">
                    @if($division->division == 'bronze')
                        Argent (100 pts)
                    @elseif($division->division == 'argent')
                        Or (200 pts)
                    @elseif($division->division == 'or')
                        Platine (300 pts)
                    @elseif($division->division == 'platine')
                        Diamant (400 pts)
                    @elseif($division->division == 'diamant')
                        Légende (500 pts)
                    @else
                        Légende (max)
                    @endif
                </span>
            </div>
        </div>

        <div class="action-buttons">
            <button onclick="window.location.href='{{ route('league.individual.lobby') }}'" class="btn-primary">
                REJOUER
            </button>
            <button onclick="window.location.href='{{ route('league.individual.rankings') }}'" class="btn-secondary">
                CLASSEMENTS
            </button>
            <button onclick="window.location.href='{{ route('menu') }}'" class="btn-tertiary">
                MENU PRINCIPAL
            </button>
        </div>
    </div>
</div>

<style>
.results-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.results-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 800px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.results-header {
    text-align: center;
    margin-bottom: 40px;
}

.result-icon {
    font-size: 5em;
    margin-bottom: 20px;
}

.result-title {
    font-size: 3em;
    margin-bottom: 10px;
}

.victory-title {
    color: #4CAF50;
}

.defeat-title {
    color: #f44336;
}

.result-subtitle {
    font-size: 1.2em;
    color: #666;
}

.match-summary {
    display: flex;
    align-items: center;
    justify-content: space-around;
    margin-bottom: 40px;
    padding: 30px;
    background: #f8f8f8;
    border-radius: 15px;
}

.player-result {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border-radius: 12px;
    transition: all 0.3s;
}

.player-result.winner {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.1) 0%, rgba(76, 175, 80, 0.2) 100%);
    border: 2px solid #4CAF50;
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

.player-details {
    text-align: center;
}

.player-details h3 {
    font-size: 1.3em;
    margin-bottom: 5px;
    color: #1a1a1a;
}

.player-level {
    color: #666;
    font-size: 0.95em;
}

.player-rounds {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.rounds-won {
    font-size: 2.5em;
    font-weight: bold;
    color: #667eea;
}

.rounds-label {
    font-size: 0.9em;
    color: #666;
}

.vs-divider {
    font-size: 1.8em;
    font-weight: bold;
    color: #999;
}

.points-earned-section {
    text-align: center;
    margin-bottom: 40px;
    padding: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
}

.points-earned-section h3 {
    font-size: 1.3em;
    margin-bottom: 15px;
    opacity: 0.95;
}

.points-display {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 10px;
    margin-bottom: 10px;
}

.points-value {
    font-size: 4em;
    font-weight: bold;
}

.points-value.positive {
    color: #4CAF50;
}

.points-value.negative {
    color: #f44336;
}

.points-label {
    font-size: 1.2em;
}

.points-explanation {
    opacity: 0.9;
    font-size: 1.1em;
}

.stats-section {
    margin-bottom: 40px;
}

.stats-section h3 {
    font-size: 1.5em;
    text-align: center;
    margin-bottom: 25px;
    color: #1a1a1a;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-card {
    text-align: center;
    padding: 20px;
    background: #f8f8f8;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.stat-icon {
    font-size: 2em;
}

.stat-value {
    font-size: 1.8em;
    font-weight: bold;
    color: #667eea;
}

.stat-label {
    font-size: 0.9em;
    color: #666;
}

.division-progress {
    margin-bottom: 40px;
}

.division-progress h3 {
    font-size: 1.5em;
    text-align: center;
    margin-bottom: 25px;
    color: #1a1a1a;
}

.division-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.current-division {
    font-weight: bold;
    text-transform: uppercase;
    padding: 10px 20px;
    border-radius: 8px;
    color: white;
}

.current-division.bronze { background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%); }
.current-division.argent { background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%); }
.current-division.or { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
.current-division.platine { background: linear-gradient(135deg, #E5E4E2 0%, #71797E 100%); }
.current-division.diamant { background: linear-gradient(135deg, #B9F2FF 0%, #00CED1 100%); }
.current-division.legende { background: linear-gradient(135deg, #FF1493 0%, #8B008B 100%); }

.progress-bar {
    flex: 1;
    height: 30px;
    background: #e0e0e0;
    border-radius: 15px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transition: width 0.5s ease;
}

.next-division {
    font-size: 0.95em;
    color: #666;
    white-space: nowrap;
}

.action-buttons {
    display: flex;
    gap: 15px;
}

.btn-primary, .btn-secondary, .btn-tertiary {
    flex: 1;
    padding: 18px;
    border: none;
    border-radius: 12px;
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
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-secondary:hover {
    background: #667eea;
    color: white;
}

.btn-tertiary {
    background: #e0e0e0;
    color: #666;
}

.btn-tertiary:hover {
    background: #d0d0d0;
}

@media (max-width: 768px) {
    .match-summary {
        flex-direction: column;
        gap: 20px;
    }

    .vs-divider {
        transform: rotate(90deg);
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .division-info {
        flex-direction: column;
        gap: 15px;
    }

    .action-buttons {
        flex-direction: column;
    }
}
</style>

<script>
console.log('League Individual Results page loaded');
</script>
@endsection
