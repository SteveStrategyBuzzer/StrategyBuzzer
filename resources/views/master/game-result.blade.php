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

.game-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.game-title {
    font-size: 1.5rem;
    font-weight: 900;
    color: #FFD700;
}

.question-counter {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 0.5rem 1rem;
    font-weight: 700;
}

.result-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    text-align: center;
}

.result-title {
    font-size: 1.8rem;
    font-weight: 900;
    color: #FFD700;
    margin-bottom: 1rem;
}

.result-subtitle {
    font-size: 1.1rem;
    opacity: 0.8;
    margin-bottom: 2rem;
}

.leaderboard-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
}

.leaderboard-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #FFD700;
    text-align: center;
}

.player-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 1rem;
    margin-bottom: 0.5rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
}

.player-row.top-3 {
    background: rgba(255, 215, 0, 0.15);
    border: 1px solid rgba(255, 215, 0, 0.3);
}

.player-rank {
    font-weight: 900;
    font-size: 1.2rem;
    width: 40px;
}

.player-rank.gold { color: #FFD700; }
.player-rank.silver { color: #C0C0C0; }
.player-rank.bronze { color: #CD7F32; }

.player-name {
    flex: 1;
    font-weight: 600;
    padding-left: 1rem;
}

.player-score {
    color: #FFD700;
    font-weight: 700;
    font-size: 1.1rem;
}

.host-controls {
    background: rgba(255, 215, 0, 0.2);
    border: 2px solid #FFD700;
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1.5rem;
    text-align: center;
}

.host-controls-title {
    font-weight: 700;
    margin-bottom: 1rem;
    color: #FFD700;
}

.control-btn {
    background: #FFD700;
    color: #003DA5;
    border: none;
    border-radius: 8px;
    padding: 0.8rem 2rem;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: transform 0.2s;
    margin: 0 0.5rem;
}

.control-btn:hover {
    transform: scale(1.05);
}

.control-btn.secondary {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
</style>

<div class="game-container">
    <div class="game-header">
        <div class="game-title">{{ __('Scores après la question') }} {{ $current_question }}</div>
        <div class="question-counter">
            {{ $current_question }}/{{ $total_questions }}
        </div>
    </div>

    <div class="result-section">
        <div class="result-title">{{ __('Classement actuel') }}</div>
        <div class="result-subtitle">
            @if($current_question < $total_questions)
                {{ __('Encore :count questions à jouer', ['count' => $total_questions - $current_question]) }}
            @else
                {{ __('Dernière question terminée !') }}
            @endif
        </div>
    </div>

    <div class="leaderboard-section">
        <div class="leaderboard-title">{{ __('Tableau des scores') }}</div>
        @foreach($players as $index => $p)
            @php
                $rankClass = '';
                if ($index === 0) $rankClass = 'gold';
                elseif ($index === 1) $rankClass = 'silver';
                elseif ($index === 2) $rankClass = 'bronze';
            @endphp
            <div class="player-row {{ $index < 3 ? 'top-3' : '' }}">
                <span class="player-rank {{ $rankClass }}">
                    @if($index === 0) 🥇
                    @elseif($index === 1) 🥈
                    @elseif($index === 2) 🥉
                    @else {{ $index + 1 }}.
                    @endif
                </span>
                <span class="player-name">
                    {{ $p->user->name ?? 'Joueur' }}
                </span>
                <span class="player-score">
                    {{ $p->score ?? 0 }} pts
                </span>
            </div>
        @endforeach
    </div>

    @if($is_host)
        <div class="host-controls">
            <div class="host-controls-title">{{ __('Contrôles du Maître') }}</div>
            @if($current_question < $total_questions)
                <button class="control-btn" id="next-question-btn">
                    {{ __('Question suivante') }}
                </button>
            @else
                <button class="control-btn" onclick="window.location.href='{{ route('game.master.match-result') }}'">
                    {{ __('Résultats finaux') }}
                </button>
            @endif
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nextBtn = document.getElementById('next-question-btn');
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            window.location.href = '{{ route("game.master.question") }}';
        });
    }
});
</script>
@endsection
