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

.question-section {
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
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.choice-btn:hover {
    background: rgba(255, 215, 0, 0.3);
    border-color: #FFD700;
}

.choice-btn.selected {
    background: rgba(255, 215, 0, 0.4);
    border-color: #FFD700;
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
        <div class="game-title">
            @if($is_host)
                {{ __('Mode Maître du Jeu') }}
            @else
                {{ __('Quiz en cours') }}
            @endif
        </div>
        <div class="question-counter">
            {{ $current_question }}/{{ $total_questions }}
        </div>
    </div>

    <div class="question-section">
        @if($question)
            <div class="question-text">
                {{ $question->text ?? __('Question en cours de chargement...') }}
            </div>

            @if($question->media_url)
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <img src="{{ $question->media_url }}" alt="Question media" style="max-width: 100%; max-height: 300px; border-radius: 12px;">
                </div>
            @endif

            <div class="choices-grid">
                @foreach($question->choices ?? [] as $index => $choice)
                    <button class="choice-btn" data-index="{{ $index }}">
                        {{ $choice }}
                    </button>
                @endforeach
            </div>
        @else
            <div class="question-text">
                {{ __('En attente de la question...') }}
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
            <button class="control-btn" id="show-answer-btn">
                {{ __('Révéler la réponse') }}
            </button>
        </div>
    @endif
</div>

<script>
const GAME_SERVER_URL = @json($game_server_url);
const ROOM_ID = @json($room_id);
const JWT_TOKEN = @json($jwt_token);
const IS_HOST = @json($is_host);
const GAME_ID = @json($game_id);

document.addEventListener('DOMContentLoaded', function() {
    const choiceBtns = document.querySelectorAll('.choice-btn');
    
    choiceBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            if (IS_HOST) return;
            
            choiceBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            const answerIndex = this.dataset.index;
            submitAnswer(answerIndex);
        });
    });

    const showAnswerBtn = document.getElementById('show-answer-btn');
    if (showAnswerBtn) {
        showAnswerBtn.addEventListener('click', function() {
            window.location.href = '{{ route("game.master.answer") }}';
        });
    }
});

function submitAnswer(answerIndex) {
    console.log('Answer submitted:', answerIndex);
}
</script>
@endsection
