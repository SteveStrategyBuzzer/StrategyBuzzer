@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .result-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        padding: 30px;
        max-width: 700px;
        width: 100%;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .round-title {
        font-size: 2rem;
        font-weight: 800;
        color: #667eea;
        margin-bottom: 20px;
    }

    .winner-emoji {
        font-size: 4rem;
        margin: 15px 0;
    }

    .score-display {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin: 30px 0;
    }

    .score-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px 35px;
        border-radius: 15px;
        font-size: 2.5rem;
        font-weight: 900;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .score-card.player {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    }

    .score-card.opponent {
        background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
    }

    .score-label {
        font-size: 1rem;
        font-weight: 600;
        margin-top: 10px;
        color: #333;
    }

    .match-status {
        font-size: 1.3rem;
        margin: 25px 0;
        color: #333;
        font-weight: 600;
    }

    .match-status .rounds {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 10px;
    }

    .round-indicator {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: white;
    }

    .round-indicator.won {
        background: #4CAF50;
    }

    .round-indicator.lost {
        background: #f44336;
    }

    .round-indicator.draw {
        background: #FFC107;
    }

    .round-indicator.pending {
        background: #ccc;
    }

    .next-button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 18px 50px;
        font-size: 1.2rem;
        font-weight: 700;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 20px;
        text-transform: uppercase;
        text-decoration: none;
        display: inline-block;
    }

    .next-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
    }

    .tiebreaker-alert {
        background: linear-gradient(135deg, #FFD700, #FFA500);
        color: #333;
        padding: 20px;
        border-radius: 15px;
        margin: 20px 0;
        font-weight: 700;
        font-size: 1.2rem;
    }

    @media (max-width: 600px) {
        .score-display { flex-direction: column; gap: 20px; }
        .score-card { font-size: 2rem; padding: 20px 30px; }
        .round-title { font-size: 1.5rem; }
    }
</style>

<div class="result-container">
    @php
        $playerWonRound = ($params['player_score'] ?? 0) > ($params['opponent_score'] ?? 0);
        $tied = ($params['player_score'] ?? 0) === ($params['opponent_score'] ?? 0);
        $roundNumber = $params['round_result']['current_round'] ?? 1;
        $isTiebreaker = $params['match_complete'] && $params['player_rounds_won'] === $params['opponent_rounds_won'];
    @endphp

    <div class="round-title">
        ⚔️ {{ __('Manche') }} {{ $roundNumber }} {{ __('terminée !') }}
    </div>

    @if($playerWonRound)
        <div class="winner-emoji">🎉</div>
        <div style="color: #4CAF50; font-size: 1.5rem; font-weight: 700;">{{ __('Vous avez gagné cette manche !') }}</div>
    @elseif($tied)
        <div class="winner-emoji">🤝</div>
        <div style="color: #FFC107; font-size: 1.5rem; font-weight: 700;">{{ __('Égalité !') }}</div>
    @else
        <div class="winner-emoji">😤</div>
        <div style="color: #f44336; font-size: 1.5rem; font-weight: 700;">{{ __('Votre adversaire a gagné cette manche') }}</div>
    @endif

    <div class="score-display">
        <div>
            <div class="score-card player">{{ $params['player_score'] ?? 0 }}</div>
            <div class="score-label">{{ __('VOUS') }}</div>
        </div>
        <div style="font-size: 2rem; font-weight: 900; color: #667eea; align-self: center;">VS</div>
        <div>
            <div class="score-card opponent">{{ $params['opponent_score'] ?? 0 }}</div>
            <div class="score-label">{{ $params['opponent_info']['name'] ?? __('Adversaire') }}</div>
        </div>
    </div>

    <div class="match-status">
        {{ __('Score du match') }}: <strong>{{ $params['player_rounds_won'] }} - {{ $params['opponent_rounds_won'] }}</strong>
    </div>

    @if($isTiebreaker)
        <div class="tiebreaker-alert">
            ⚔️ {{ __('ÉGALITÉ PARFAITE ! Jeu décisif requis !') }}
        </div>
        <a href="{{ route('game.tiebreaker-choice', ['mode' => $params['mode']]) }}" class="next-button">
            ⚔️ {{ __('Jeu Décisif') }}
        </a>
    @elseif($params['match_complete'])
        <a href="{{ route('game.match-result', ['mode' => $params['mode']]) }}" class="next-button">
            🏆 {{ __('Voir le résultat final') }}
        </a>
    @else
        <form action="{{ route('game.next-round', ['mode' => $params['mode']]) }}" method="POST">
            @csrf
            <button type="submit" class="next-button">
                🚀 {{ __('Manche') }} {{ $roundNumber + 1 }}
            </button>
        </form>
    @endif
</div>
@endsection
