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

.header-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.question-counter {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 0.5rem 1rem;
    font-weight: 700;
}

.timer-display {
    background: linear-gradient(135deg, #FF6B35, #FF4444);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    box-shadow: 0 4px 15px rgba(255, 68, 68, 0.4);
    animation: pulse 1s ease-in-out infinite;
}

.timer-display.warning {
    background: linear-gradient(135deg, #FFA500, #FF6B35);
}

.timer-display.danger {
    background: linear-gradient(135deg, #FF4444, #CC0000);
    animation: pulse-fast 0.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes pulse-fast {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.main-content {
    flex: 1;
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

.choice-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.buzz-section {
    margin-top: 2rem;
    text-align: center;
}

.buzz-btn {
    background: linear-gradient(135deg, #FF4444, #CC0000);
    border: none;
    border-radius: 50%;
    width: 120px;
    height: 120px;
    color: #fff;
    font-size: 1.2rem;
    font-weight: 900;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(255, 68, 68, 0.5), inset 0 -4px 10px rgba(0, 0, 0, 0.3);
    transition: all 0.15s ease;
    text-transform: uppercase;
}

.buzz-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 12px 35px rgba(255, 68, 68, 0.6), inset 0 -4px 10px rgba(0, 0, 0, 0.3);
}

.buzz-btn:active {
    transform: scale(0.95);
    box-shadow: 0 4px 15px rgba(255, 68, 68, 0.4), inset 0 4px 10px rgba(0, 0, 0, 0.3);
}

.buzz-btn.buzzed {
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    box-shadow: 0 8px 25px rgba(76, 175, 80, 0.5), inset 0 -4px 10px rgba(0, 0, 0, 0.3);
}

.buzz-btn.disabled {
    background: linear-gradient(135deg, #666, #444);
    cursor: not-allowed;
    box-shadow: none;
}

.buzz-status {
    margin-top: 1rem;
    font-size: 0.9rem;
    opacity: 0.8;
}

.leaderboard-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1rem;
    max-height: 600px;
    overflow-y: auto;
}

.leaderboard-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.8rem;
    color: #FFD700;
    position: sticky;
    top: 0;
    background: rgba(0, 61, 165, 0.95);
    padding: 0.5rem 0;
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

.player-buzzed {
    width: 20px;
    text-align: center;
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
    padding: 0.8rem 1.5rem;
    font-weight: 700;
    font-size: 0.95rem;
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

.control-btn.pause {
    background: #FFA500;
    color: #fff;
}

.control-btn.danger {
    background: #FF4444;
    color: #fff;
}

@media (max-width: 600px) {
    .choices-grid {
        grid-template-columns: 1fr;
    }
    
    .buzz-btn {
        width: 100px;
        height: 100px;
        font-size: 1rem;
    }
}
</style>

<div class="game-container">
    <div class="game-header">
        <div class="game-title">
            @if($is_host)
                {{ __('Mode Maître du Jeu') }}
            @else
                {{ $game->name ?? __('Quiz en cours') }}
            @endif
        </div>
        <div class="header-info">
            <div class="question-counter">
                {{ $current_question }}/{{ $total_questions }}
            </div>
            <div class="timer-display" id="timer">
                <span id="timer-value">{{ $time_limit ?? 30 }}</span>
            </div>
        </div>
    </div>

    <div class="game-layout">
        <div class="main-content">
            <div class="question-section">
                @if($question)
                    <div class="question-text">
                        {{ $question->text ?? __('Question en cours de chargement...') }}
                    </div>

                    @if($question->media_url)
                        <div class="question-media">
                            <img src="{{ $question->media_url }}" alt="Question media">
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

            @if(!$is_host)
                <div class="buzz-section">
                    <button class="buzz-btn" id="buzz-btn">
                        BUZZ!
                    </button>
                    <div class="buzz-status" id="buzz-status">
                        {{ __('Appuyez pour buzzer') }}
                    </div>
                </div>
            @endif

            @if($is_host)
                <div class="host-controls">
                    <div class="host-controls-title">{{ __('Contrôles du Maître') }}</div>
                    <div class="control-buttons">
                        <button class="control-btn pause" id="pause-btn">
                            ⏸ {{ __('Pause') }}
                        </button>
                        <button class="control-btn" id="show-answer-btn">
                            {{ __('Révéler la réponse') }}
                        </button>
                        <button class="control-btn secondary" id="skip-btn">
                            {{ __('Passer') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <div class="leaderboard-section">
            <div class="leaderboard-title">{{ __('Classement') }} ({{ $players->count() }} {{ __('joueurs') }})</div>
            @foreach($players->sortByDesc('score')->take(40) as $index => $p)
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
                        {{ $p->user->name ?? $p->guest_name ?? __('Joueur') }}
                    </span>
                    <span class="player-buzzed" id="buzz-indicator-{{ $p->id }}">
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
const ROOM_ID = @json($room_id ?? '');
const JWT_TOKEN = @json($jwt_token ?? '');
const IS_HOST = @json($is_host);
const GAME_ID = @json($game->id ?? null);
const TIME_LIMIT = @json($time_limit ?? 30);

let timeRemaining = TIME_LIMIT;
let timerInterval = null;
let hasBuzzed = false;
let isPaused = false;

document.addEventListener('DOMContentLoaded', function() {
    startTimer();
    
    const choiceBtns = document.querySelectorAll('.choice-btn');
    choiceBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            if (IS_HOST || this.classList.contains('disabled')) return;
            
            choiceBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            const answerIndex = this.dataset.index;
            submitAnswer(answerIndex);
        });
    });

    const buzzBtn = document.getElementById('buzz-btn');
    if (buzzBtn) {
        buzzBtn.addEventListener('click', function() {
            if (hasBuzzed || this.classList.contains('disabled')) return;
            
            hasBuzzed = true;
            this.classList.add('buzzed');
            this.textContent = '✓';
            document.getElementById('buzz-status').textContent = '{{ __("Vous avez buzzé !") }}';
            
            submitBuzz();
        });
    }

    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) {
        pauseBtn.addEventListener('click', function() {
            isPaused = !isPaused;
            if (isPaused) {
                clearInterval(timerInterval);
                this.innerHTML = '▶ {{ __("Reprendre") }}';
                this.classList.remove('pause');
                this.classList.add('secondary');
            } else {
                startTimer();
                this.innerHTML = '⏸ {{ __("Pause") }}';
                this.classList.add('pause');
                this.classList.remove('secondary');
            }
        });
    }

    const showAnswerBtn = document.getElementById('show-answer-btn');
    if (showAnswerBtn) {
        showAnswerBtn.addEventListener('click', function() {
            window.location.href = '{{ route("game.master.answer") }}';
        });
    }

    const skipBtn = document.getElementById('skip-btn');
    if (skipBtn) {
        skipBtn.addEventListener('click', function() {
            if (confirm('{{ __("Êtes-vous sûr de vouloir passer cette question ?") }}')) {
                window.location.href = '{{ route("game.master.result") }}';
            }
        });
    }
});

function startTimer() {
    const timerEl = document.getElementById('timer');
    const timerValue = document.getElementById('timer-value');
    
    timerInterval = setInterval(() => {
        timeRemaining--;
        timerValue.textContent = timeRemaining;
        
        if (timeRemaining <= 10) {
            timerEl.classList.add('danger');
            timerEl.classList.remove('warning');
        } else if (timeRemaining <= 20) {
            timerEl.classList.add('warning');
        }
        
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            if (IS_HOST) {
                window.location.href = '{{ route("game.master.answer") }}';
            }
        }
    }, 1000);
}

function submitAnswer(answerIndex) {
    console.log('Answer submitted:', answerIndex);
}

function submitBuzz() {
    console.log('Buzz submitted');
}
</script>
@endsection
