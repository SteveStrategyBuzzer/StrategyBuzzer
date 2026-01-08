@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.game-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 1rem;
}

.winner-section {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.3), rgba(255, 215, 0, 0.1));
    border: 3px solid #FFD700;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    margin-bottom: 2rem;
}

.winner-crown {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.winner-title {
    font-size: 1.8rem;
    font-weight: 900;
    color: #FFD700;
    margin-bottom: 0.5rem;
}

.winner-name {
    font-size: 2.2rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
}

.winner-score {
    font-size: 1.5rem;
    font-weight: 700;
    color: #FFD700;
}

.final-leaderboard {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.leaderboard-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #FFD700;
    text-align: center;
}

.player-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
}

.player-row.top-3 {
    background: rgba(255, 215, 0, 0.15);
    border: 1px solid rgba(255, 215, 0, 0.3);
}

.player-row.current-user {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.player-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.player-rank {
    font-weight: 900;
    font-size: 1.5rem;
    width: 50px;
    text-align: center;
}

.player-rank.gold { color: #FFD700; }
.player-rank.silver { color: #C0C0C0; }
.player-rank.bronze { color: #CD7F32; }

.player-name {
    font-weight: 600;
    font-size: 1.1rem;
}

.player-score {
    color: #FFD700;
    font-weight: 700;
    font-size: 1.2rem;
}

.game-stats {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.stats-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #FFD700;
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    text-align: center;
}

.stat-item {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 1rem;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 900;
    color: #FFD700;
}

.stat-label {
    font-size: 0.85rem;
    opacity: 0.8;
    margin-top: 0.3rem;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.action-btn {
    background: #FFD700;
    color: #003DA5;
    border: none;
    border-radius: 10px;
    padding: 1rem 2rem;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: transform 0.2s;
    text-decoration: none;
    display: inline-block;
}

.action-btn:hover {
    transform: scale(1.05);
}

.action-btn.secondary {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

@media (max-width: 600px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="game-container">
    <div class="winner-section">
        <div class="winner-crown">👑</div>
        <div class="winner-title">{{ __('Vainqueur') }}</div>
        @if($winner)
            <div class="winner-name">{{ $winner->user->name ?? 'Joueur' }}</div>
            <div class="winner-score">{{ $winner->score ?? 0 }} {{ __('points') }}</div>
        @else
            <div class="winner-name">{{ __('Aucun vainqueur') }}</div>
        @endif
    </div>

    <div class="final-leaderboard">
        <div class="leaderboard-title">{{ __('Classement final') }}</div>
        @foreach($players as $index => $p)
            @php
                $rankClass = '';
                if ($index === 0) $rankClass = 'gold';
                elseif ($index === 1) $rankClass = 'silver';
                elseif ($index === 2) $rankClass = 'bronze';
                $isCurrentUser = $p->user_id == $current_user->id;
            @endphp
            <div class="player-row {{ $index < 3 ? 'top-3' : '' }} {{ $isCurrentUser ? 'current-user' : '' }}">
                <div class="player-info">
                    <span class="player-rank {{ $rankClass }}">
                        @if($index === 0) 🥇
                        @elseif($index === 1) 🥈
                        @elseif($index === 2) 🥉
                        @else {{ $index + 1 }}.
                        @endif
                    </span>
                    <span class="player-name">
                        {{ $p->user->name ?? 'Joueur' }}
                        @if($isCurrentUser) ({{ __('Vous') }}) @endif
                    </span>
                </div>
                <span class="player-score">
                    {{ $p->score ?? 0 }} pts
                </span>
            </div>
        @endforeach
    </div>

    <div class="game-stats">
        <div class="stats-title">{{ __('Statistiques de la partie') }}</div>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value">{{ $game->total_questions ?? 0 }}</div>
                <div class="stat-label">{{ __('Questions') }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ $players->count() }}</div>
                <div class="stat-label">{{ __('Joueurs') }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ ucfirst($game->structure_type ?? 'podium') }}</div>
                <div class="stat-label">{{ __('Mode') }}</div>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="{{ route('master.index') }}" class="action-btn">
            {{ __('Nouvelle partie') }}
        </a>
        <a href="{{ route('profile') }}" class="action-btn secondary">
            {{ __('Retour au profil') }}
        </a>
    </div>
</div>
@endsection
