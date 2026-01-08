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
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

.game-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 1.5rem;
}

@media (max-width: 900px) {
    .game-layout {
        grid-template-columns: 1fr;
    }
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

.main-content {
    flex: 1;
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

.question-media {
    text-align: center;
    margin-bottom: 1.5rem;
}

.question-media img {
    max-width: 100%;
    max-height: 300px;
    border-radius: 12px;
}

.choices-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.choice-item {
    background: rgba(255, 255, 255, 0.15);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    text-align: center;
    position: relative;
}

.choice-item.correct {
    background: rgba(76, 175, 80, 0.4);
    border-color: #4CAF50;
    animation: correctPulse 0.5s ease-out;
}

.choice-item.incorrect {
    background: rgba(244, 67, 54, 0.3);
    border-color: #F44336;
    opacity: 0.7;
}

@keyframes correctPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.choice-icon {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    font-size: 1.2rem;
}

.answer-count {
    font-size: 0.8rem;
    margin-top: 0.5rem;
    opacity: 0.8;
}

.correct-players-section {
    background: rgba(76, 175, 80, 0.15);
    border: 1px solid rgba(76, 175, 80, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #4CAF50;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.correct-players-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.correct-player-badge {
    background: rgba(76, 175, 80, 0.3);
    border-radius: 20px;
    padding: 0.4rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.correct-player-badge .points {
    color: #FFD700;
    font-size: 0.8rem;
}

.incorrect-players-section {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.2);
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
}

.incorrect-players-section .section-title {
    color: #F44336;
    font-size: 1rem;
}

.incorrect-players-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    font-size: 0.85rem;
    opacity: 0.8;
}

.leaderboard-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1rem;
    max-height: 500px;
    overflow-y: auto;
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
    padding: 0.4rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.9rem;
}

.player-row:last-child {
    border-bottom: none;
}

.player-row.top-3 {
    background: rgba(255, 215, 0, 0.1);
    margin: 0 -0.5rem;
    padding: 0.4rem 0.5rem;
    border-radius: 4px;
}

.player-row.answered-correct {
    background: rgba(76, 175, 80, 0.15);
    margin: 0 -0.5rem;
    padding: 0.4rem 0.5rem;
    border-radius: 4px;
}

.player-rank {
    width: 25px;
    font-weight: 700;
}

.player-rank.gold { color: #FFD700; }
.player-rank.silver { color: #C0C0C0; }
.player-rank.bronze { color: #CD7F32; }

.player-name {
    flex: 1;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0 0.5rem;
}

.player-score {
    color: #FFD700;
    font-weight: 700;
}

.player-result-icon {
    width: 20px;
    text-align: center;
    margin-left: 0.3rem;
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

.control-buttons {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
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
}

.control-btn:hover {
    transform: scale(1.05);
}

.control-btn.secondary {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.stats-summary {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    text-align: center;
}

.stat-item {
    padding: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: #FFD700;
}

.stat-label {
    font-size: 0.8rem;
    opacity: 0.7;
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

    <div class="game-layout">
        <div class="main-content">
            <div class="answer-section">
                @if($question)
                    <div class="question-text">
                        {{ $question->text ?? '' }}
                    </div>

                    @if($question->media_url)
                        <div class="question-media">
                            <img src="{{ $question->media_url }}" alt="Question media">
                        </div>
                    @endif

                    <div class="choices-grid">
                        @foreach($question->choices ?? [] as $index => $choice)
                            @php
                                $isCorrect = in_array($index, $question->correct_indexes ?? []);
                                $answerCount = $answer_stats[$index] ?? 0;
                            @endphp
                            <div class="choice-item {{ $isCorrect ? 'correct' : 'incorrect' }}">
                                <span class="choice-icon">
                                    @if($isCorrect) ✓ @else ✗ @endif
                                </span>
                                {{ $choice }}
                                <div class="answer-count">
                                    {{ $answerCount }} {{ __('réponse(s)') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(isset($correct_players) && count($correct_players) > 0)
                <div class="correct-players-section">
                    <div class="section-title">
                        ✓ {{ __('Bonnes réponses') }} ({{ count($correct_players) }})
                    </div>
                    <div class="correct-players-grid">
                        @foreach($correct_players as $player)
                            <div class="correct-player-badge">
                                {{ $player->user->name ?? $player->guest_name ?? __('Joueur') }}
                                <span class="points">+{{ $player->points_earned ?? 100 }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(isset($incorrect_players) && count($incorrect_players) > 0)
                <div class="incorrect-players-section">
                    <div class="section-title">
                        ✗ {{ __('Mauvaises réponses') }} ({{ count($incorrect_players) }})
                    </div>
                    <div class="incorrect-players-list">
                        @foreach($incorrect_players as $index => $player)
                            {{ $player->user->name ?? $player->guest_name ?? __('Joueur') }}@if($index < count($incorrect_players) - 1), @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stat-value">{{ count($correct_players ?? []) }}</div>
                    <div class="stat-label">{{ __('Bonnes réponses') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ count($incorrect_players ?? []) + count($no_answer_players ?? []) }}</div>
                    <div class="stat-label">{{ __('Erreurs / Sans réponse') }}</div>
                </div>
            </div>

            @if($is_host)
                <div class="host-controls">
                    <div class="host-controls-title">{{ __('Contrôles du Maître') }}</div>
                    <div class="control-buttons">
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
                </div>
            @endif
        </div>

        <div class="leaderboard-section">
            <div class="leaderboard-title">{{ __('Classement') }}</div>
            @foreach($players->sortByDesc('score')->take(40) as $index => $p)
                @php
                    $rankClass = '';
                    if ($index === 0) $rankClass = 'gold';
                    elseif ($index === 1) $rankClass = 'silver';
                    elseif ($index === 2) $rankClass = 'bronze';
                    $answeredCorrect = isset($correct_players) && $correct_players->contains('id', $p->id);
                @endphp
                <div class="player-row {{ $index < 3 ? 'top-3' : '' }} {{ $answeredCorrect ? 'answered-correct' : '' }}">
                    <span class="player-rank {{ $rankClass }}">
                        @if($index === 0) 🥇
                        @elseif($index === 1) 🥈
                        @elseif($index === 2) 🥉
                        @else {{ $index + 1 }}.
                        @endif
                    </span>
                    <span class="player-name">
                        {{ $p->user->name ?? $p->guest_name ?? __('Joueur') }}
                    </span>
                    <span class="player-result-icon">
                        @if($answeredCorrect) ✓ @endif
                    </span>
                    <span class="player-score">
                        {{ $p->score ?? 0 }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
const GAME_SERVER_URL = @json($game_server_url ?? '');
const JWT_TOKEN = @json($jwt_token ?? '');
const IS_HOST = @json($is_host);
</script>
@endsection
