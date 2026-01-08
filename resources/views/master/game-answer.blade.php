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

.answer-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 1.5rem;
}

.question-text {
    font-size: 1.4rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 2rem;
}

.choices-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.choice-btn {
    background: rgba(255, 255, 255, 0.15);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    text-align: center;
}

.choice-btn.correct {
    background: rgba(76, 175, 80, 0.4);
    border-color: #4CAF50;
}

.choice-btn.incorrect {
    background: rgba(244, 67, 54, 0.3);
    border-color: #F44336;
}

.answer-stats {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.stats-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #FFD700;
}

.stats-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.leaderboard-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1rem;
}

.leaderboard-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.8rem;
    color: #FFD700;
}

.player-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.player-row:last-child {
    border-bottom: none;
}

.player-name {
    font-weight: 600;
}

.player-score {
    color: #FFD700;
    font-weight: 700;
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

@media (max-width: 600px) {
    .choices-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="game-container">
    <div class="game-header">
        <div class="game-title">{{ __('Résultats de la question') }}</div>
        <div class="question-counter">
            {{ $current_question }}/{{ $total_questions }}
        </div>
    </div>

    <div class="answer-section">
        @if($question)
            <div class="question-text">
                {{ $question->text ?? '' }}
            </div>

            <div class="choices-grid">
                @foreach($question->choices ?? [] as $index => $choice)
                    @php
                        $isCorrect = in_array($index, $question->correct_indexes ?? []);
                    @endphp
                    <div class="choice-btn {{ $isCorrect ? 'correct' : 'incorrect' }}">
                        {{ $choice }}
                        @if($isCorrect)
                            <span style="margin-left: 0.5rem;">✓</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="leaderboard-section">
        <div class="leaderboard-title">{{ __('Classement') }}</div>
        @foreach($players->take(10) as $index => $p)
            <div class="player-row">
                <span class="player-name">
                    {{ $index + 1 }}. {{ $p->user->name ?? 'Joueur' }}
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
                <button class="control-btn" onclick="window.location.href='{{ route('game.master.result') }}'">
                    {{ __('Voir les scores') }}
                </button>
            @else
                <button class="control-btn" onclick="window.location.href='{{ route('game.master.match-result') }}'">
                    {{ __('Résultats finaux') }}
                </button>
            @endif
        </div>
    @endif
</div>
@endsection
