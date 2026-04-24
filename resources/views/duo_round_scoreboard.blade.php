@extends('layouts.game')

@section('game-data')
@include('partials.game-context', [
    'roomId'         => $room_id ?? '',
    'lobbyCode'      => $lobby_code ?? null,
    'jwtToken'       => $jwt_token ?? '',
    'matchId'        => $match_id ?? '',
    'mode'           => 'duo',
    'page'           => 'round-scoreboard',
    'totalQuestions' => $totalQuestions ?? 10,
    'playerName'     => $playerName ?? (auth()->user()->name ?? 'Joueur'),
    'playerInfo'     => ['avatarId' => $playerAvatar ?? null],
    'noBrainOverlay' => true,
])
<script>
window.MATCH_ID             = @json((string)($match_id ?? ''));
window.ROOM_ID              = @json((string)($room_id ?? ''));
window.LOBBY_CODE           = @json((string)($lobby_code ?? ''));
window.JWT_TOKEN            = @json((string)($jwt_token ?? ''));
window.CURRENT_USER_ID      = @json((string)(auth()->id() ?? ''));
window.TOTAL_QUESTIONS      = {{ (int)($totalQuestions ?? 10) }};
window.QUESTION_URL         = @json(route('game.duo.question'));
window.ROUND_SCOREBOARD_URL = @json(route('game.duo.round-scoreboard'));
window.MATCH_RESULT_URL     = @json(route('game.duo.match-result'));
window.CURRENT_PAGE         = 'round-scoreboard';
window.NO_BRAIN_OVERLAY     = true;
// Bridge UI: page-specific visual state saved on every navigation
window.GR_SAVE_STATE_EXTRA  = {
    phase:         'ROUND_SCOREBOARD',
    current_page:  'round-scoreboard',
    player_score:  {{ (int)($playerScore ?? 0) }},
    opponent_score: {{ (int)($opponentScore ?? 0) }},
};
</script>
@endsection

@section('content')
@php
$mode = 'duo';
$playerScore   = $playerScore   ?? 0;
$opponentScore = $opponentScore ?? 0;
$currentRound  = $currentRound  ?? 1;
$totalQuestions = $totalQuestions ?? 10;
@endphp

<style>
    body {
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
        margin: 0;
        overflow: hidden;
    }

    .scoreboard-container {
        max-width: 600px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 18px;
        padding: 20px;
    }

    .scoreboard-header {
        text-align: center;
        padding: 20px;
        border-radius: 20px;
        background: linear-gradient(135deg, rgba(78, 205, 196, 0.15) 0%, rgba(44, 83, 100, 0.4) 100%);
        border: 2px solid rgba(78, 205, 196, 0.4);
    }

    .round-badge {
        display: inline-block;
        background: rgba(78, 205, 196, 0.2);
        border: 1px solid rgba(78, 205, 196, 0.5);
        color: #4ECDC4;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 2px;
        padding: 4px 14px;
        border-radius: 20px;
        margin-bottom: 10px;
    }

    .scoreboard-title {
        font-size: 1.8rem;
        font-weight: 900;
        color: #fff;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.5);
        margin: 0;
    }

    .scores-battle {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
    }

    .score-card {
        flex: 1;
        padding: 20px 15px;
        border-radius: 16px;
        text-align: center;
        background: rgba(78, 205, 196, 0.08);
        border: 2px solid rgba(78, 205, 196, 0.25);
        transition: all 0.3s ease;
    }

    .score-card.opponent {
        background: rgba(255, 107, 107, 0.08);
        border-color: rgba(255, 107, 107, 0.25);
    }

    .score-card.winner {
        background: rgba(78, 205, 196, 0.18);
        border-color: rgba(78, 205, 196, 0.6);
        box-shadow: 0 0 30px rgba(78, 205, 196, 0.25);
    }

    .score-card.winner.opponent {
        background: rgba(255, 107, 107, 0.18);
        border-color: rgba(255, 107, 107, 0.6);
        box-shadow: 0 0 30px rgba(255, 107, 107, 0.25);
    }

    .score-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(78, 205, 196, 0.5);
        margin-bottom: 8px;
    }

    .score-avatar.opponent {
        border-color: rgba(255, 107, 107, 0.5);
    }

    .score-name {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.75);
        font-weight: 600;
        margin-bottom: 6px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .score-value {
        font-size: 2.8rem;
        font-weight: 900;
        color: #4ECDC4;
        line-height: 1;
        text-shadow: 0 0 15px rgba(78, 205, 196, 0.4);
    }

    .score-value.opponent {
        color: #FF6B6B;
        text-shadow: 0 0 15px rgba(255, 107, 107, 0.4);
    }

    .score-pts {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 4px;
    }

    .vs-separator {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.15);
        font-size: 0.95rem;
        font-weight: 900;
        color: rgba(255, 255, 255, 0.6);
        flex-shrink: 0;
    }

    .result-badge {
        text-align: center;
        padding: 16px;
        border-radius: 14px;
        font-size: 1.3rem;
        font-weight: 800;
    }

    .result-badge.win {
        background: rgba(46, 204, 113, 0.15);
        border: 2px solid rgba(46, 204, 113, 0.4);
        color: #2ECC71;
    }

    .result-badge.loss {
        background: rgba(255, 107, 107, 0.12);
        border: 2px solid rgba(255, 107, 107, 0.35);
        color: #FF6B6B;
    }

    .result-badge.tie {
        background: rgba(255, 215, 0, 0.12);
        border: 2px solid rgba(255, 215, 0, 0.35);
        color: #FFD700;
    }

    .next-info {
        text-align: center;
        padding: 14px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        line-height: 1.5;
    }

    .countdown-wrap {
        text-align: center;
        padding: 8px 0 4px;
    }

    .countdown-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .countdown-bar-track {
        width: 100%;
        height: 7px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        overflow: hidden;
    }

    .countdown-bar-fill {
        height: 100%;
        width: 100%;
        background: linear-gradient(90deg, #4ECDC4, #2ECC71);
        border-radius: 4px;
        transition: width 1s linear;
    }

    .countdown-bar-fill.urgent {
        background: linear-gradient(90deg, #FF6B6B, #ff4444);
    }

    .countdown-secs {
        font-size: 0.9rem;
        font-weight: 700;
        color: #4ECDC4;
        margin-top: 4px;
    }

    .countdown-secs.urgent { color: #FF6B6B; }

    .btn-exit {
        width: 100%;
        padding: 11px 20px;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        border: 2px solid rgba(255, 107, 107, 0.35);
        background: rgba(255, 107, 107, 0.07);
        color: rgba(255, 107, 107, 0.8);
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn-exit:hover {
        background: rgba(255, 107, 107, 0.18);
        border-color: rgba(255, 107, 107, 0.6);
        color: #FF6B6B;
    }

    .btn-exit.confirming {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border-color: #e74c3c;
        color: white;
    }
</style>

{{-- connection-status provided by layouts.game --}}

@php
$playerLeading  = $playerScore > $opponentScore;
$opponentLeading = $opponentScore > $playerScore;
$tied = $playerScore === $opponentScore;
@endphp

<div class="scoreboard-container">
    <div class="scoreboard-header">
        <div class="round-badge">{{ __('Fin de manche') }} {{ $currentRound }}</div>
        <h1 class="scoreboard-title">🏆 {{ __('Tableau des scores') }}</h1>
    </div>

    @if($playerLeading)
        <div class="result-badge win">🎉 {{ __('Vous êtes en tête !') }}</div>
    @elseif($opponentLeading)
        <div class="result-badge loss">💪 {{ __('L\'adversaire est en tête') }}</div>
    @else
        <div class="result-badge tie">🤝 {{ __('Égalité !') }}</div>
    @endif

    <div class="scores-battle">
        <div class="score-card {{ $playerLeading ? 'winner' : '' }}">
            <img src="{{ $playerAvatarPath ?? asset('images/avatars/standard/default.png') }}"
                 alt="{{ __('Votre avatar') }}" class="score-avatar">
            <div class="score-name">{{ __('Vous') }}</div>
            <div class="score-value" id="sbPlayerScore">{{ $playerScore }}</div>
            <div class="score-pts">{{ __('points') }}</div>
        </div>

        <div class="vs-separator">VS</div>

        <div class="score-card opponent {{ $opponentLeading ? 'winner opponent' : '' }}">
            @if(!empty($opponentAvatarPath))
                <img src="{{ $opponentAvatarPath }}" alt="{{ __('Avatar adversaire') }}" class="score-avatar opponent">
            @else
                <div class="score-avatar opponent" style="display:flex;align-items:center;justify-content:center;font-size:1.5rem;">?</div>
            @endif
            <div class="score-name">{{ $opponentName ?? __('Adversaire') }}</div>
            <div class="score-value opponent" id="sbOpponentScore">{{ $opponentScore }}</div>
            <div class="score-pts">{{ __('points') }}</div>
        </div>
    </div>

    <div class="next-info" id="nextInfo">
        {{ __('Prochaine manche en cours de préparation...') }}
    </div>

    <div class="countdown-wrap">
        <div class="countdown-label">{{ __('Prochaine manche dans') }}</div>
        <div class="countdown-bar-track">
            <div class="countdown-bar-fill" id="sbCountdownFill"></div>
        </div>
        <div class="countdown-secs" id="sbCountdownSecs">30s</div>
    </div>

    <button class="btn-exit" id="sbBtnExit">🔙 {{ __('Retour Duo') }}</button>
</div>

{{-- socket.io + DuoSocketClient: loaded by layouts.game --}}

<script>
(function() {
    'use strict';

    const MATCH_ID   = window.MATCH_ID   || '';
    const ROOM_ID    = window.ROOM_ID    || '';
    const LOBBY_CODE = window.LOBBY_CODE || '';

    var isRedirecting = false;
    var countdownSecs = 30;
    var countdownInterval = null;
    var exitConfirming = false;

    var sbBtnExit = document.getElementById('sbBtnExit');
    var nextInfo  = document.getElementById('nextInfo');

    function cancelCountdown() {
        if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
    }

    function startCountdown(seconds) {
        cancelCountdown();
        countdownSecs = seconds || 30;
        var fill = document.getElementById('sbCountdownFill');
        var secsEl = document.getElementById('sbCountdownSecs');
        var total = countdownSecs;
        if (fill) { fill.style.width = '100%'; fill.classList.remove('urgent'); }
        if (secsEl) { secsEl.textContent = countdownSecs + 's'; secsEl.classList.remove('urgent'); }
        countdownInterval = setInterval(function() {
            countdownSecs--;
            var pct = Math.max(0, (countdownSecs / total) * 100);
            if (fill) { fill.style.width = pct + '%'; }
            if (secsEl) { secsEl.textContent = Math.max(0, countdownSecs) + 's'; }
            if (countdownSecs <= 8) {
                if (fill) fill.classList.add('urgent');
                if (secsEl) secsEl.classList.add('urgent');
            }
            if (countdownSecs <= 0) {
                cancelCountdown();
                navigateToQuestion();
            }
        }, 1000);
    }

    var _nav = function(u) { (window.duoNavigate || function(x) { window.location.href = x; })(u); };

    function navigateToQuestion() {
        cancelCountdown();
        if (isRedirecting) return;
        isRedirecting = true;
        _nav((window.QUESTION_URL || '/game/duo/question') + '?match_id=' + encodeURIComponent(MATCH_ID));
    }

    function navigateToMatchResult() {
        cancelCountdown();
        if (isRedirecting) return;
        isRedirecting = true;
        _nav((window.MATCH_RESULT_URL || '/game/duo/match-result') + '?match_id=' + encodeURIComponent(MATCH_ID));
    }

    function _onSbPhaseChanged(data) {
        if (isRedirecting || !data || !data.phase) return;
        var phase = data.phase;

        if (phase === 'INTRO' || phase === 'WAITING') {
            // Next round starting
            cancelCountdown();
            if (nextInfo) nextInfo.textContent = '{{ __("Prochaine manche !") }}';
            setTimeout(navigateToQuestion, 400);
            return;
        }

        if (phase === 'QUESTION_ACTIVE' || phase === 'SYNC') {
            cancelCountdown();
            navigateToQuestion();
            return;
        }

        if (phase === 'MATCH_END') {
            cancelCountdown();
            navigateToMatchResult();
            return;
        }
    }

    function _onSbState(payload) {
        if (!payload) return;
        var data = payload.state || payload;
        var phase = data.phase;

        if (!phase || phase === 'ROUND_SCOREBOARD') return;

        if (phase === 'INTRO' || phase === 'WAITING' || phase === 'QUESTION_ACTIVE' || phase === 'SYNC') {
            navigateToQuestion();
            return;
        }

        if (phase === 'MATCH_END' || phase === 'FINISHED') {
            navigateToMatchResult();
            return;
        }
    }

    function _onSbScoreUpdate(data) {
        var playerEl   = document.getElementById('sbPlayerScore');
        var opponentEl = document.getElementById('sbOpponentScore');
        if (!playerEl || !opponentEl) return;
        var userId = window.CURRENT_USER_ID ? String(window.CURRENT_USER_ID) : '';

        if (data.scores) {
            Object.keys(data.scores).forEach(function(pid) {
                if (String(pid) === userId) {
                    playerEl.textContent = data.scores[pid];
                } else {
                    opponentEl.textContent = data.scores[pid];
                }
            });
        }
    }

    function _onSbMatchEnded(data) {
        cancelCountdown();
        navigateToMatchResult();
    }

    // Exit button
    if (sbBtnExit) {
        sbBtnExit.addEventListener('click', function(e) {
            e.stopPropagation();
            if (exitConfirming) {
                window.location.href = '{{ route("duo.lobby") }}';
            } else {
                exitConfirming = true;
                sbBtnExit.textContent = '⚠️ {{ __("Confirmer la sortie ?") }}';
                sbBtnExit.classList.add('confirming');
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (exitConfirming && sbBtnExit && !sbBtnExit.contains(e.target)) {
            exitConfirming = false;
            sbBtnExit.textContent = '🔙 {{ __("Retour Duo") }}';
            sbBtnExit.classList.remove('confirming');
        }
    });

    // Register DuoSocketClient handlers
    document.addEventListener('DOMContentLoaded', function() {
        var ds = window.DuoSocketClient;
        if (ds) {
            ds.on('phase_changed', _onSbPhaseChanged);
            ds.on('state',         _onSbState);
            ds.on('score_update',  _onSbScoreUpdate);
            ds.on('match_ended',   _onSbMatchEnded);
        }

        // Start 30s countdown
        startCountdown(30);
    });

    window.addEventListener('beforeunload', function() {
        cancelCountdown();
    });
})();
</script>
@endsection

@section('scripts')
{{-- Handlers registered in @section('content') IIFE above --}}
@endsection
