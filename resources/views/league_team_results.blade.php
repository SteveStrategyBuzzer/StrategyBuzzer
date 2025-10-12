@extends('layouts.app')

@section('content')
<div class="league-results-container">
    <div class="results-header">
        @if($match->winner_team_id === $match->team1_id)
            @if($match->team1->members->contains('user_id', Auth::id()))
                <h1 class="victory-title">🏆 VICTOIRE 🏆</h1>
            @else
                <h1 class="defeat-title">DÉFAITE</h1>
            @endif
        @elseif($match->winner_team_id === $match->team2_id)
            @if($match->team2->members->contains('user_id', Auth::id()))
                <h1 class="victory-title">🏆 VICTOIRE 🏆</h1>
            @else
                <h1 class="defeat-title">DÉFAITE</h1>
            @endif
        @else
            <h1 class="draw-title">MATCH NUL</h1>
        @endif
    </div>

    <div class="match-summary">
        <div class="team-result {{ $match->winner_team_id === $match->team1_id ? 'winner' : 'loser' }}">
            <h2>{{ $match->team1->name }}</h2>
            <span class="team-tag">[{{ $match->team1->tag }}]</span>
            <div class="final-score">{{ array_sum(array_column(array_filter($match->game_state['players'], fn($p) => $p['team_index'] === 1), 'total_score')) }}</div>
            <div class="points-change">
                {{ $match->team1_points_earned >= 0 ? '+' : '' }}{{ $match->team1_points_earned }} pts
            </div>
        </div>

        <div class="vs-separator">VS</div>

        <div class="team-result {{ $match->winner_team_id === $match->team2_id ? 'winner' : 'loser' }}">
            <h2>{{ $match->team2->name }}</h2>
            <span class="team-tag">[{{ $match->team2->tag }}]</span>
            <div class="final-score">{{ array_sum(array_column(array_filter($match->game_state['players'], fn($p) => $p['team_index'] === 2), 'total_score')) }}</div>
            <div class="points-change">
                {{ $match->team2_points_earned >= 0 ? '+' : '' }}{{ $match->team2_points_earned }} pts
            </div>
        </div>
    </div>

    <div class="rounds-recap">
        <h3>📊 RÉCAPITULATIF DES MANCHES</h3>
        <div class="rounds-grid">
            @foreach($match->game_state['round_results'] ?? [] as $index => $result)
                <div class="round-card">
                    <h4>Manche {{ $index + 1 }}</h4>
                    <div class="round-scores">
                        <div class="round-team-score">
                            <span>{{ $match->team1->tag }}</span>
                            <span class="score">{{ $result['team1_score'] ?? 0 }}</span>
                        </div>
                        <div class="round-team-score">
                            <span>{{ $match->team2->tag }}</span>
                            <span class="score">{{ $result['team2_score'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="players-stats">
        <div class="team-stats-section">
            <h3>🔵 {{ $match->team1->name }}</h3>
            <div class="players-list">
                @foreach(array_filter($match->game_state['players'], fn($p) => $p['team_index'] === 1) as $player)
                    <div class="player-stat-card {{ $player['id'] === Auth::id() ? 'current-user' : '' }}">
                        <div class="player-info-stat">
                            @php
                                $user = $match->team1->teamMembers->firstWhere('user_id', $player['id'])->user ?? null;
                            @endphp
                            <div class="player-avatar-stat">
                                @if($user && $user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" alt="">
                                @else
                                    <div class="avatar-letter-stat">{{ substr($player['name'], 0, 1) }}</div>
                                @endif
                            </div>
                            <span class="player-name-stat">{{ $player['name'] }}</span>
                        </div>
                        <div class="player-stats-numbers">
                            <div class="stat-item">
                                <span class="stat-label">Score</span>
                                <span class="stat-value">{{ $player['total_score'] ?? 0 }}</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Buzz</span>
                                <span class="stat-value">{{ $player['stats']['total_buzzes'] ?? 0 }}</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Correct</span>
                                <span class="stat-value">{{ $player['stats']['correct_answers'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="team-stats-section">
            <h3>🔴 {{ $match->team2->name }}</h3>
            <div class="players-list">
                @foreach(array_filter($match->game_state['players'], fn($p) => $p['team_index'] === 2) as $player)
                    <div class="player-stat-card {{ $player['id'] === Auth::id() ? 'current-user' : '' }}">
                        <div class="player-info-stat">
                            @php
                                $user = $match->team2->teamMembers->firstWhere('user_id', $player['id'])->user ?? null;
                            @endphp
                            <div class="player-avatar-stat">
                                @if($user && $user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" alt="">
                                @else
                                    <div class="avatar-letter-stat">{{ substr($player['name'], 0, 1) }}</div>
                                @endif
                            </div>
                            <span class="player-name-stat">{{ $player['name'] }}</span>
                        </div>
                        <div class="player-stats-numbers">
                            <div class="stat-item">
                                <span class="stat-label">Score</span>
                                <span class="stat-value">{{ $player['total_score'] ?? 0 }}</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Buzz</span>
                                <span class="stat-value">{{ $player['stats']['total_buzzes'] ?? 0 }}</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Correct</span>
                                <span class="stat-value">{{ $player['stats']['correct_answers'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="actions-section">
        <button onclick="window.location.href='{{ route('league.team.lobby') }}'" class="btn-primary btn-large">
            RETOUR AU LOBBY
        </button>
    </div>
</div>

<style>
.league-results-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    padding: 40px 20px;
}

.results-header {
    text-align: center;
    margin-bottom: 40px;
}

.victory-title {
    font-size: 48px;
    color: #ffd700;
    text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    animation: victoryPulse 2s infinite;
}

.defeat-title {
    font-size: 48px;
    color: #dc3545;
}

.draw-title {
    font-size: 48px;
    color: #aaa;
}

@keyframes victoryPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.match-summary {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.team-result {
    background: rgba(255, 255, 255, 0.1);
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    min-width: 250px;
    border: 3px solid transparent;
}

.team-result.winner {
    border-color: #ffd700;
    background: rgba(255, 215, 0, 0.1);
}

.team-result.loser {
    border-color: #666;
}

.team-result h2 {
    color: #fff;
    margin: 0 0 5px 0;
}

.team-tag {
    color: #ffd700;
    font-size: 16px;
}

.final-score {
    font-size: 64px;
    font-weight: bold;
    color: #00d4ff;
    margin: 20px 0;
}

.points-change {
    font-size: 24px;
    font-weight: bold;
    color: #aaa;
}

.vs-separator {
    font-size: 36px;
    font-weight: bold;
    color: #fff;
}

.rounds-recap {
    max-width: 800px;
    margin: 40px auto;
    background: rgba(255, 255, 255, 0.05);
    padding: 30px;
    border-radius: 15px;
}

.rounds-recap h3 {
    color: #00d4ff;
    text-align: center;
    margin-bottom: 20px;
}

.rounds-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.round-card {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.round-card h4 {
    color: #fff;
    margin: 0 0 15px 0;
}

.round-scores {
    display: flex;
    justify-content: space-around;
}

.round-team-score {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.round-team-score span {
    color: #aaa;
}

.round-team-score .score {
    font-size: 28px;
    font-weight: bold;
    color: #00d4ff;
}

.players-stats {
    max-width: 1200px;
    margin: 40px auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.team-stats-section {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 15px;
}

.team-stats-section h3 {
    color: #fff;
    text-align: center;
    margin-bottom: 20px;
}

.players-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.player-stat-card {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.player-stat-card.current-user {
    background: rgba(0, 212, 255, 0.2);
    border: 2px solid #00d4ff;
}

.player-info-stat {
    display: flex;
    align-items: center;
    gap: 10px;
}

.player-avatar-stat {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
}

.player-avatar-stat img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-letter-stat {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #667eea);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #fff;
}

.player-name-stat {
    color: #fff;
    font-weight: 600;
}

.player-stats-numbers {
    display: flex;
    gap: 20px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-label {
    font-size: 12px;
    color: #aaa;
}

.stat-value {
    font-size: 20px;
    font-weight: bold;
    color: #00d4ff;
}

.actions-section {
    text-align: center;
    margin-top: 40px;
}

@media (max-width: 768px) {
    .match-summary {
        flex-direction: column;
        gap: 20px;
    }
    
    .players-stats {
        grid-template-columns: 1fr;
    }
    
    .player-stats-numbers {
        flex-direction: column;
        gap: 5px;
    }
}
</style>
@endsection
