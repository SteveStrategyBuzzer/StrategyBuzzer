@extends('layouts.app')

@section('content')
@include('partials.game-context', [
    'roomId'         => $room_id ?? '',
    'jwtToken'       => $jwt_token ?? '',
    'matchId'        => (string)($game->id ?? ''),
    'mode'           => 'master',
    'page'           => 'question',
    'totalQuestions' => $totalQuestions ?? 10,
    'playerName'     => auth()->user()->name ?? 'Joueur',
    'gameServerUrl'  => $game_server_url ?? null,
])
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

.player-eff {
    color: #4ECDC4;
    font-weight: 600;
    font-size: 0.85rem;
    margin-left: 0.5rem;
    opacity: 0.85;
    min-width: 38px;
    text-align: right;
}

.player-streak {
    color: #FF8E53;
    font-weight: 700;
    font-size: 0.8rem;
    margin-left: 0.4rem;
    min-width: 24px;
    text-align: right;
}

.player-telemetry {
    color: #C0C0C0;
    font-size: 0.75rem;
    margin-left: 0.4rem;
    opacity: 0.85;
    font-weight: 600;
    white-space: nowrap;
}
.player-telemetry .pt-correct { color: #4ECDC4; }
.player-telemetry .pt-wrong   { color: #FF6B6B; }
.player-telemetry .pt-buzz    { color: #FFD700; }

.player-avg-buzz {
    color: #4ECDC4;
    font-size: 0.75rem;
    margin-left: 0.4rem;
    opacity: 0.85;
    min-width: 50px;
    text-align: right;
    font-weight: 600;
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
                @php
                    $masterPid = (string)($p->user_id ?? $p->id);
                    $isSelfRow = (string)($p->user_id ?? '') === (string)(auth()->id() ?? '___');
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
                    <span class="player-score" data-stat="score" data-player="{{ $isSelfRow ? 'self' : $masterPid }}">
                        {{ $p->score ?? 0 }}
                    </span>
                    <span class="player-eff" title="{{ __('Efficacité') }}" data-stat="efficiencyPercent" data-player="{{ $isSelfRow ? 'self' : $masterPid }}">0%</span>
                    <span class="player-streak" title="{{ __('Série en cours') }}" data-stat="currentStreak" data-player="{{ $isSelfRow ? 'self' : $masterPid }}">0</span>
                    <span class="player-telemetry" title="{{ __('Bons/Faux/Buzz') }}">
                        <span class="pt-correct" data-stat="correctAnswers" data-player="{{ $isSelfRow ? 'self' : $masterPid }}">0</span>/<span class="pt-wrong" data-stat="wrongAnswers" data-player="{{ $isSelfRow ? 'self' : $masterPid }}">0</span>/<span class="pt-buzz" data-stat="buzzCount" data-player="{{ $isSelfRow ? 'self' : $masterPid }}">0</span>
                    </span>
                    <span class="player-avg-buzz" title="{{ __('Buzz moyen') }}" data-stat="averageResponseMs" data-player="{{ $isSelfRow ? 'self' : $masterPid }}">0 ms</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
// Task #42 — consume window.SB_GAME_CONTEXT (published by partials.game-context).
// Mode-specific values (IS_HOST, GAME_ID, TIME_LIMIT) stay inline since they are
// not part of the shared gameplay context contract.
const __SB = window.SB_GAME_CONTEXT || {};
const GAME_SERVER_URL = __SB.gameServerUrl || '';
const ROOM_ID = __SB.roomId || '';
const JWT_TOKEN = __SB.jwtToken || '';
const IS_HOST = @json($is_host);
const GAME_ID = @json($game->id ?? null);
const TIME_LIMIT = @json($time_limit ?? 30);

let timeRemaining = TIME_LIMIT;
let timerInterval = null;
let hasBuzzed = false;
let isPaused = false;
// ─── P57.4 — TEMPORAIRE : fallback LOCAL qui CÈDE à Node ───────────────────
// La vue Maître charge bien partials.game-context (room_id + jwt_token) et
// DuoSocketClient ; un GameOrchestrator Node existe pour le mode MASTER.
// MAIS, à date, la progression de phase Maître est pilotée par le HOST via
// HTTP (boutons "Révéler la réponse" / "Passer") plutôt que par la boucle
// d'orchestration Node. Conséquence concrète :
//   • un événement Node `state` / `phase_changed` avec `phaseEndsAtMs` PEUT
//     arriver (et alors `_onMasterPhaseSnapshot` ci-dessous prend la main —
//     c'est la seule source autorisée à hydrater `phaseEndsAtMs`)
//   • mais s'il n'arrive pas, la vue retombe sur un anchor LOCAL
//     (`Date.now() + TIME_LIMIT * 1000`) — c'est un FALLBACK, pas une
//     source de vérité serveur, et il est nommé TEMPORAIRE.
// Migration vers vrai Node-authoritative = tâche séparée : router le mode
// MASTER à travers la boucle orchestrator (transitionToQuestionActive,
// emitPhaseChanged) au lieu de laisser le host piloter par REST.
let phaseEndsAtMs = null;          // null tant que ni Node ni fallback n'a ancré
let serverPhaseEndsAtMs = null;    // dernier deadline Node observé (audit)
let phaseAnchorIsLocalFallback = false;  // true si l'anchor courant est le fallback local

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

    // ── Anchor : Node si déjà reçu, sinon FALLBACK LOCAL ──────────────────
    // Si `_onMasterPhaseSnapshot` a déjà reçu un deadline serveur, ne pas
    // l'écraser. Sinon, on pose un anchor LOCAL clairement marqué TEMPORAIRE
    // (cf. bloc en tête de fichier : la progression de phase Maître n'est
    // pas encore pilotée par la boucle orchestrator Node).
    // La transition parasite legacy (`window.location.href` → page réponse
    // sur timeout client) a été supprimée : l'avance de phase doit venir
    // d'une action host explicite, pas d'un compteur client.
    if (!phaseEndsAtMs || phaseAnchorIsLocalFallback) {
        phaseEndsAtMs = Date.now() + Math.max(0, timeRemaining) * 1000;
        phaseAnchorIsLocalFallback = true;
    }

    timerInterval = setInterval(() => {
        let computed;
        if (phaseEndsAtMs) {
            const remainingMs = Math.max(0, phaseEndsAtMs - Date.now());
            computed = Math.ceil(remainingMs / 1000);
        } else {
            // Defensive fallback: deadline cleared mid-flight.
            computed = timeRemaining - 1;
        }
        // Monotone-decreasing guard: a late state event resyncing
        // phaseEndsAtMs upward must never push the chrono back up.
        if (computed > timeRemaining) computed = timeRemaining;
        timeRemaining = computed;

        timerValue.textContent = timeRemaining;

        if (timeRemaining <= 10) {
            timerEl.classList.add('danger');
            timerEl.classList.remove('warning');
        } else if (timeRemaining <= 20) {
            timerEl.classList.add('warning');
        }

        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
            // Phase transition is owned by Node now (was: parasitic
            // window.location.href on the host). The host control buttons
            // ("Révéler la réponse", "Passer") still drive explicit
            // navigation; the timeout itself just stops the visible counter.
        }
    }, 250);
}

// P57.4 — Hydrate phaseEndsAtMs from Node when available.
// Si Node publie un deadline pour ce room, on remplace IMMÉDIATEMENT
// l'anchor local fallback et on bascule en mode authoritative pour ce
// cycle. Tant que Node n'a rien envoyé, le fallback local reste actif
// (et marqué comme tel via `phaseAnchorIsLocalFallback`).
function _onMasterPhaseSnapshot(data) {
    if (!data || !data.phaseEndsAtMs) return;
    var remaining = Math.max(0, data.phaseEndsAtMs - Date.now());
    if (remaining < 2000 && timeRemaining >= 5) {
        console.warn('[Master] state ignored stale snapshot: server remaining=' + remaining + 'ms vs local timeRemaining=' + timeRemaining + 's');
        return;
    }
    serverPhaseEndsAtMs = data.phaseEndsAtMs;
    // Si l'anchor courant est le fallback local, Node l'écrase : on devient
    // authoritative pour cette phase.
    if (phaseAnchorIsLocalFallback) {
        phaseEndsAtMs = data.phaseEndsAtMs;
        phaseAnchorIsLocalFallback = false;
        return;
    }
    // Anchor déjà Node : monotone-decreasing guard, on ne remonte jamais.
    if (phaseEndsAtMs && data.phaseEndsAtMs > phaseEndsAtMs) {
        return;
    }
    phaseEndsAtMs = data.phaseEndsAtMs;
}

function submitAnswer(answerIndex) {
    console.log('Answer submitted:', answerIndex);
}

function submitBuzz() {
    console.log('Buzz submitted');
}
</script>

{{--
    Live stats wiring (Task #42).

    This view extends layouts.app (not layouts.game), so the shared
    GameplayRuntime stack is not auto-loaded. Master mode IS already
    backed by the Node WS game server — the controller publishes
    room_id + jwt_token via partials.game-context above — so we just
    need to load Socket.IO + DuoSocketClient + GameplayRuntime here.

    Once loaded, GameplayRuntime subscribes to player_stats_updated /
    round_stats / match_stats from the server and paints every
    [data-stat][data-player] slot rendered above. No stat math runs in
    Blade and no REST polling is involved.
--}}
<script>
(function () {
    var ctx = window.SB_GAME_CONTEXT || {};
    if (ctx.gameServerUrl) {
        window.GAME_SERVER_URL = ctx.gameServerUrl;
    } else if (!window.GAME_SERVER_URL) {
        window.GAME_SERVER_URL = window.location.protocol + '//' + window.location.hostname + ':3001';
    }
})();
</script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>
<script src="{{ asset('js/GameplayRuntime.js') }}"></script>

{{-- P57.4 — Wire Node `state` / `game_state` / `phase_changed` events into the
     local timer so phaseEndsAtMs is hydrated from the orchestrator when it
     emits one. If Node never publishes a deadline for this room, the timer
     stays on the locally-anchored fallback set by startTimer() (clearly
     marked TEMPORAIRE — see top-of-file P57.4 comment). --}}
<script>
(function () {
    if (!window.DuoSocketClient || typeof _onMasterPhaseSnapshot !== 'function') return;
    var prevState = DuoSocketClient.onState;
    var prevGameState = DuoSocketClient.onGameState;
    var prevPhaseChanged = DuoSocketClient.onPhaseChanged;
    DuoSocketClient.onState = function (data) {
        _onMasterPhaseSnapshot(data && (data.state || data));
        if (typeof prevState === 'function') prevState(data);
    };
    DuoSocketClient.onGameState = function (data) {
        _onMasterPhaseSnapshot(data);
        if (typeof prevGameState === 'function') prevGameState(data);
    };
    DuoSocketClient.onPhaseChanged = function (data) {
        _onMasterPhaseSnapshot(data);
        if (typeof prevPhaseChanged === 'function') prevPhaseChanged(data);
    };
})();
</script>
@endsection
