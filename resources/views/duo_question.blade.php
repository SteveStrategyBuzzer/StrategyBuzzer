@extends('layouts.game')

@section('game-data')
@include('partials.game-context', [
    'roomId'         => $room_id ?? '',
    'lobbyCode'      => $lobby_code ?? null,
    'jwtToken'       => $jwt_token ?? '',
    'matchId'        => $match_id ?? '',
    'mode'           => 'duo',
    'page'           => 'question',
    'totalQuestions' => $totalQuestions ?? 10,
    'playerName'     => $playerName ?? (auth()->user()->name ?? 'Joueur'),
    'playerInfo'     => ['avatarId' => $playerAvatar ?? null],
    'noBrainOverlay' => true,
])
<script>
window.MATCH_ID              = @json((string)($match_id ?? ''));
window.ROOM_ID               = @json((string)($room_id ?? ''));
window.LOBBY_CODE            = @json((string)($lobby_code ?? ''));
window.JWT_TOKEN             = @json((string)($jwt_token ?? ''));
window.CURRENT_USER_ID       = @json((string)(auth()->id() ?? ''));
window.TOTAL_QUESTIONS       = {{ (int)($totalQuestions ?? 10) }};
window.ANSWER_URL            = @json(route('game.duo.answer'));
window.RESULT_URL            = @json(route('game.duo.result'));
window.ROUND_SCOREBOARD_URL  = @json(route('game.duo.round-scoreboard'));
window.MATCH_RESULT_URL      = @json(route('game.duo.match-result'));
window.QUESTION_URL          = @json(route('game.duo.question'));
window.CURRENT_PAGE          = 'question';
// Prevent brain overlay from covering question content on the question page.
// If game_state briefly shows phase=INTRO during the intro→question transition,
// the brain overlay must not obscure the question UI.
window.NO_BRAIN_OVERLAY      = true;
// Bridge UI: page-specific visual state saved on every navigation
// phase is intentionally null on initial render — Node is the sole phase
// authority (cf. docs/decisions/…). The first `state` / `phase_changed`
// socket event will publish the canonical phase. Any pre-injected phase
// here would be a duplicate source of truth (Tâche #77, P77.1).
window.GR_SAVE_STATE_EXTRA   = {
    phase:        null,
    current_page: 'question',
};
</script>
@endsection

@section('content')
@php
$mode = 'duo';
@endphp

<style>
    :root {
        --player-color: {{ $playerColor ?? '#4ECDC4' }};
        --opponent-color: {{ $opponentColor ?? '#FF6B6B' }};
    }
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
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    
    .game-container {
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
        position: relative;
        min-height: 100vh;
        padding-bottom: 180px;
    }
    
    .question-header {
        background: rgba(78, 205, 196, 0.1);
        padding: 20px;
        border-radius: 20px;
        text-align: center;
        border: 2px solid rgba(78, 205, 196, 0.3);
        margin-bottom: 10px;
    }
    
    .question-number {
        font-size: 0.9rem;
        color: #4ECDC4;
        margin-bottom: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .question-theme {
        font-size: 0.85rem;
        color: #FFD700;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .question-text {
        font-size: 1.4rem;
        font-weight: 600;
        line-height: 1.5;
    }
    
    .game-layout {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 30px;
        align-items: start;
        justify-items: center;
        margin: 20px 0;
    }
    
    .left-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 30px;
        width: 100%;
    }
    
    .player-circle {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .player-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid var(--player-color, #4ECDC4);
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.5);
        object-fit: cover;
    }
    
    .player-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--player-color, #4ECDC4);
    }

    .player-level {
        font-size: 0.85rem;
        color: var(--player-color, #4ECDC4);
        opacity: 0.8;
    }
    
    .player-score {
        font-size: 2rem;
        font-weight: 900;
        color: var(--player-color, #4ECDC4);
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
    }

    .live-stats {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.2;
        opacity: 0.9;
    }

    .live-stats .stat-row {
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .live-stats .stat-label {
        opacity: 0.65;
    }

    .live-stats.player .stat-value { color: var(--player-color, #4ECDC4); }
    .live-stats.opponent .stat-value { color: var(--opponent-color, #FF6B6B); }
    .live-stats .stat-value { font-weight: 800; }
    
    .opponent-circle {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .opponent-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid var(--opponent-color, #FF6B6B);
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        object-fit: cover;
    }
    
    .opponent-avatar-empty {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid var(--opponent-color, #FF6B6B);
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 107, 107, 0.1);
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--opponent-color, #FF6B6B);
    }
    
    .opponent-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--opponent-color, #FF6B6B);
    }

    .opponent-level {
        font-size: 0.85rem;
        color: var(--opponent-color, #FF6B6B);
        opacity: 0.8;
    }
    
    .opponent-score {
        font-size: 2rem;
        font-weight: 900;
        color: var(--opponent-color, #FF6B6B);
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.8);
    }
    
    .center-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .chrono-circle {
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        box-shadow: 0 15px 50px rgba(102, 126, 234, 0.6);
        animation: pulse-glow 2s ease-in-out infinite;
    }
    
    @keyframes pulse-glow {
        0%, 100% {
            box-shadow: 0 15px 50px rgba(102, 126, 234, 0.6);
        }
        50% {
            box-shadow: 0 15px 70px rgba(102, 126, 234, 0.9);
        }
    }
    
    .chrono-circle::before {
        content: '';
        position: absolute;
        inset: -5px;
        border-radius: 50%;
        background: linear-gradient(45deg, #4ECDC4, #667eea, #FF6B6B);
        opacity: 0.5;
        filter: blur(15px);
        animation: rotate-glow 3s linear infinite;
    }
    
    @keyframes rotate-glow {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .chrono-time {
        font-size: 5rem;
        font-weight: 900;
        position: relative;
        z-index: 1;
        background: linear-gradient(180deg, #fff 0%, #4ECDC4 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .right-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        width: 100%;
    }
    
    .strategic-avatar-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 3px solid #FFD700;
        box-shadow: 0 8px 30px rgba(255, 215, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 215, 0, 0.1);
        object-fit: cover;
    }
    
    .strategic-avatar-circle.empty {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.3);
        box-shadow: none;
    }
    
    .strategic-avatar-image {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .strategic-avatar-name {
        font-size: 0.9rem;
        color: #FFD700;
        font-weight: 600;
        text-align: center;
    }
    
    .skills-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        align-items: center;
    }
    
    .skill-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        background: rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        cursor: default;
        position: relative;
    }

    /* Active skills glow gold — available after the question on the answer/result page */
    .skill-circle.active {
        border-color: rgba(255, 215, 0, 0.7);
        background: rgba(255, 215, 0, 0.15);
        box-shadow: 0 0 12px rgba(255, 215, 0, 0.35);
        cursor: default;
    }

    /* Passive skill (faster_buzz): shown as a soft green glow — no lock, auto-applied */
    .skill-circle.passive {
        border-color: rgba(72, 199, 116, 0.7);
        background: rgba(72, 199, 116, 0.15);
        opacity: 0.9;
        cursor: default;
    }

    /* Tooltip hint label under the skills area */
    .skills-phase-hint {
        font-size: 0.65rem;
        color: rgba(255,215,0,0.6);
        text-align: center;
        margin-top: 4px;
        letter-spacing: 0.3px;
    }

    .skill-circle.empty {
        opacity: 0.3;
        cursor: default;
    }
    
    .skill-circle.used,
    .skill-circle.depleted {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Task #55 — Reactive glow: pulses gold ~3.5s when the opponent buzzes
       first, only on offensive skills targeting the opponent that are still
       available. Replaces the legacy "L'adversaire a buzzé en premier" banner. */
    @keyframes sb-reactive-glow {
        0%   { box-shadow: 0 0 12px rgba(255, 215, 0, 0.35); transform: scale(1); border-color: rgba(255,215,0,0.7); }
        50%  { box-shadow: 0 0 28px rgba(255, 215, 0, 1), 0 0 48px rgba(255, 195, 0, 0.65); transform: scale(1.15); border-color: rgba(255,215,0,1); }
        100% { box-shadow: 0 0 12px rgba(255, 215, 0, 0.35); transform: scale(1); border-color: rgba(255,215,0,0.7); }
    }
    .skill-circle.reactive-glow {
        animation: sb-reactive-glow 1.2s ease-in-out 3;
        background: rgba(255, 215, 0, 0.28);
    }
    
    .buzz-container-bottom {
        position: fixed;
        bottom: calc(30px + env(safe-area-inset-bottom, 0px));
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
    }
    
    .buzz-button {
        background: none;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease;
        padding: 0;
    }
    
    .buzz-button:hover {
        transform: scale(1.05);
    }
    
    .buzz-button:active {
        transform: scale(0.95);
    }
    
    .buzz-button img {
        width: 180px;
        height: 180px;
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.6));
    }
    
    .buzz-button:hover img {
        filter: drop-shadow(0 15px 40px rgba(78, 205, 196, 0.8));
    }
    
    .buzz-container-bottom.buzzer-waiting .buzz-button {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .buzz-container-bottom.buzzer-waiting .buzz-button img {
        filter: drop-shadow(0 5px 15px rgba(128, 128, 128, 0.4)) grayscale(0.5);
    }
    
    .buzz-container-bottom.buzzer-ready .buzz-button {
        opacity: 1;
        cursor: pointer;
        pointer-events: auto;
        animation: buzzer-pulse 1.5s ease-in-out infinite;
    }
    
    @keyframes buzzer-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.03); }
    }
    
    .buzz-container-bottom.buzzer-ready .buzz-button img {
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.8));
    }
    
    .buzz-container-bottom.buzzer-hidden {
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    
    .result-overlay {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.9);
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        z-index: 200;
        border: 3px solid;
        animation: fadeIn 0.3s ease;
        display: none;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }
    
    .result-overlay.correct {
        border-color: #4ECDC4;
        box-shadow: 0 0 50px rgba(78, 205, 196, 0.8);
    }
    
    .result-overlay.incorrect {
        border-color: #FF6B6B;
        box-shadow: 0 0 50px rgba(255, 107, 107, 0.8);
    }
    
    .result-text {
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 15px;
    }
    
    .result-overlay.correct .result-text {
        color: #4ECDC4;
    }
    
    .result-overlay.incorrect .result-text {
        color: #FF6B6B;
    }
    
    .points-text {
        font-size: 1.5rem;
        font-weight: 600;
        opacity: 0.9;
    }
    

    /* Chrono turns red during last 3 seconds */
    .chrono-circle.urgent {
        background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
        animation: pulse-urgent 0.5s ease-in-out infinite;
    }
    @keyframes pulse-urgent {
        0%, 100% { box-shadow: 0 15px 50px rgba(231, 76, 60, 0.7); }
        50%       { box-shadow: 0 15px 70px rgba(231, 76, 60, 1); }
    }
    .chrono-circle.urgent .chrono-time {
        background: linear-gradient(180deg, #fff 0%, #FF6B6B 100%);
        -webkit-background-clip: text;
        background-clip: text;
    }

    /* Buzz order indicator */
    .buzz-order-badge {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255,215,0,0.95);
        color: #0f2027;
        font-size: 1.2rem;
        font-weight: 900;
        padding: 14px 28px;
        border-radius: 50px;
        z-index: 500;
        animation: fadeIn 0.2s ease;
        display: none;
    }

    /* Voice mic: hide on question page */
    #voiceMicButton { display: none !important; }
    
    @media (max-width: 1024px) {
        .game-layout {
            gap: 20px;
        }
        
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 85px;
            height: 85px;
        }
        
        .strategic-avatar-circle {
            width: 100px;
            height: 100px;
        }
        
        .skill-circle {
            width: 50px;
            height: 50px;
            font-size: 1.4rem;
        }
        
        .chrono-circle {
            width: 180px;
            height: 180px;
        }
        
        .chrono-time {
            font-size: 4rem;
        }
    }
    
    @media (max-width: 768px) {
        .game-layout {
            gap: 15px;
        }
        
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 70px;
            height: 70px;
        }
        
        .strategic-avatar-circle {
            width: 80px;
            height: 80px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.6rem;
        }
        
        .chrono-circle {
            width: 140px;
            height: 140px;
        }
        
        .chrono-time {
            font-size: 3rem;
        }
        
        .skill-circle {
            width: 45px;
            height: 45px;
            font-size: 1.2rem;
        }
        
        .buzz-button img {
            width: 150px;
            height: 150px;
        }
        
        .question-text {
            font-size: 1.2rem;
        }
    }
    
    @media (max-width: 480px) {
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 60px;
            height: 60px;
        }
        
        .strategic-avatar-circle {
            width: 70px;
            height: 70px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.4rem;
        }
        
        .player-name, .opponent-name {
            font-size: 0.85rem;
        }
        
        .chrono-circle {
            width: 120px;
            height: 120px;
        }
        
        .chrono-time {
            font-size: 2.5rem;
        }
        
        .skill-circle {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
        
        .buzz-button img {
            width: 130px;
            height: 130px;
        }
        
        .question-text {
            font-size: 1rem;
        }
    }
    
    @media (max-height: 600px) and (orientation: landscape) {
        .game-container {
            padding-bottom: 140px;
        }
        
        .question-header {
            padding: 12px;
            margin-bottom: 8px;
        }
        
        .question-text {
            font-size: 1rem;
        }
        
        .game-layout {
            gap: 15px;
            margin: 10px 0;
        }
        
        .player-avatar, .opponent-avatar, .opponent-avatar-empty {
            width: 60px;
            height: 60px;
        }
        
        .strategic-avatar-circle {
            width: 70px;
            height: 70px;
        }
        
        .player-score, .opponent-score {
            font-size: 1.3rem;
        }
        
        .chrono-circle {
            width: 100px;
            height: 100px;
        }
        
        .chrono-time {
            font-size: 2.2rem;
        }
        
        .skill-circle {
            width: 35px;
            height: 35px;
            font-size: 0.9rem;
        }
        
        .buzz-button img {
            width: 110px;
            height: 110px;
        }
        
        .buzz-container-bottom {
            bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        }
    }

    /* Hide shared layout #gameHeader on Duo question page — this view already
       integrates its own .question-header + .left-column (player + opponent
       circles) so the shared header would duplicate / overlap the panels. */
    #gameHeader { display: none !important; }
</style>
{{-- loading-overlay, voice-mic-button: provided by layouts.game --}}

<div class="game-container" id="gameContainer" style="display: none;">
    <div class="question-header">
        <div class="question-number">{{ __('Question') }} <span id="questionCounter">{{ $currentQuestion ?? 1 }}</span>/{{ $totalQuestions ?? 10 }}</div>
        <div class="question-theme" id="questionTheme">
            @if(!empty($themeDisplay))
                {{ $themeDisplay }}
            @elseif(!empty($theme))
                {{ $theme }}
            @else
                {{ __('En attente du thème...') }}
            @endif
        </div>
        <div class="question-text" id="questionText">{{ __('En attente de la question...') }}</div>
    </div>
    
    <div class="game-layout">
        <div class="left-column">
            <div class="player-circle">
                <img src="{{ $playerAvatarPath ?? asset('images/avatars/standard/default.png') }}" alt="{{ __('Votre avatar') }}" class="player-avatar">
                <div class="player-name">{{ $playerName ?? __('Vous') }}</div>
                <div class="player-level">{{ __('Niveau') }} {{ $playerLevel ?? 0 }} {{ __('Duo') }}</div>
                <div class="player-score" id="playerScore" data-stat="score" data-player="self">{{ $playerScore ?? 0 }}</div>
                {{-- Player live-stats row (efficiency / streak / avg buzz / correct/buzz) intentionally hidden:
                     same UX rationale as the opponent row below — the "0% · 0/0" badge is noise on the Question
                     page. DOM nodes are kept (display:none) so GameplayRuntime data-stat updates never throw
                     and so we can re-enable later without restoring markup. --}}
                <div class="live-stats player" aria-label="{{ __('Stats en direct') }}" style="display:none;" aria-hidden="true">
                    <div class="stat-row">
                        <span class="stat-label">⚡</span>
                        <span class="stat-value" data-stat="efficiencyPercent" data-player="self">0%</span>
                        <span class="stat-label">·</span>
                        <span class="stat-label">{{ __('Série') }}</span>
                        <span class="stat-value" data-stat="currentStreak" data-player="self">0</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">{{ __('Buzz') }}</span>
                        <span class="stat-value" data-stat="averageResponseMs" data-player="self">0 ms</span>
                        <span class="stat-label">·</span>
                        <span class="stat-value" data-stat="correctAnswers" data-player="self">0</span><span class="stat-label">/</span><span class="stat-value" data-stat="buzzCount" data-player="self">0</span>
                    </div>
                </div>
            </div>
            
            <div class="opponent-circle">
                @if(!empty($opponentAvatarPath))
                    <img src="{{ $opponentAvatarPath }}" alt="{{ __('Avatar adversaire') }}" class="opponent-avatar">
                @else
                    <div class="opponent-avatar-empty">?</div>
                @endif
                <div class="opponent-name">{{ $opponentName ?? __('Adversaire') }}</div>
                <div class="opponent-level">{{ __('Niveau') }} {{ $opponentLevel ?? 0 }} {{ __('Duo') }}</div>
                <div class="opponent-score" id="opponentScore" data-stat="score" data-player="opponent">{{ $opponentScore ?? 0 }}</div>
                {{-- Opponent live-stats row (efficiency %, streak, avg buzz ms, correct/buzz) intentionally hidden:
                     UX feedback considered the "17% / 1/7" badge confusing on the Question page. The DOM nodes
                     are kept (display:none) so GameplayRuntime data-stat updates don't throw and so we can
                     re-enable later without restoring markup. --}}
                <div class="live-stats opponent" aria-label="{{ __('Stats adversaire en direct') }}" style="display:none;" aria-hidden="true">
                    <div class="stat-row">
                        <span class="stat-label">⚡</span>
                        <span class="stat-value" data-stat="efficiencyPercent" data-player="opponent">0%</span>
                        <span class="stat-label">·</span>
                        <span class="stat-label">{{ __('Série') }}</span>
                        <span class="stat-value" data-stat="currentStreak" data-player="opponent">0</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">{{ __('Buzz') }}</span>
                        <span class="stat-value" data-stat="averageResponseMs" data-player="opponent">0 ms</span>
                        <span class="stat-label">·</span>
                        <span class="stat-value" data-stat="correctAnswers" data-player="opponent">0</span><span class="stat-label">/</span><span class="stat-value" data-stat="buzzCount" data-player="opponent">0</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="center-column">
            <div class="chrono-circle">
                <div class="chrono-time" id="chronoTimer">8</div>
            </div>
        </div>
        
        <div class="right-column">
            @if(!empty($strategicAvatarPath))
                <div class="strategic-avatar-circle">
                      <img src="{{ Str::startsWith($strategicAvatarPath, ['http://', 'https://', '/']) ? $strategicAvatarPath : asset($strategicAvatarPath) }}" alt="{{ __('Avatar stratégique') }}" class="strategic-avatar-image">
                </div>
                @if(!empty($avatarName))
                    <div class="strategic-avatar-name">{{ $avatarName }}</div>
                @endif
            @else
                <div class="strategic-avatar-circle empty"></div>
            @endif
            
            <div class="skills-container">
                @if(!empty($skills) && is_array($skills))
                    @foreach($skills as $skill)
                        @php
                            $isPassive = in_array($skill['id'] ?? '', ['faster_buzz', 'skill_recharge']);
                            $isUsed    = $skill['used'] ?? false;
                            $circleClass = $isUsed ? 'used' : ($isPassive ? 'passive' : 'active');
                        @endphp
                        <div class="skill-circle {{ $circleClass }}" 
                             data-skill-id="{{ $skill['id'] ?? '' }}"
                             data-skill-trigger="{{ $skill['trigger'] ?? 'question' }}"
                             data-uses-left="{{ $skill['uses_left'] ?? 1 }}"
                             data-passive="{{ $isPassive ? 'true' : 'false' }}"
                             data-affects-others="{{ !empty($skill['affects_others']) ? 'true' : 'false' }}"
                             title="{{ $skill['name'] ?? '' }}: {{ $skill['description'] ?? '' }}">
                            {{ $skill['icon'] ?? '⭐' }}
                        </div>
                    @endforeach
                    @for($i = count($skills); $i < 3; $i++)
                        <div class="skill-circle empty"></div>
                    @endfor
                    {{-- Only show the hint if there are active (non-passive) skills --}}
                    @if(collect($skills)->contains(fn($s) => !in_array($s['id'] ?? '', ['faster_buzz','skill_recharge'])))
                        <div class="skills-phase-hint">{{ __('Après la question') }}</div>
                    @endif
                @else
                    <div class="skill-circle empty"></div>
                    <div class="skill-circle empty"></div>
                    <div class="skill-circle empty"></div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="buzz-container-bottom buzzer-waiting" id="buzzContainer">
    <button class="buzz-button" id="buzzButton" disabled>
        <img src="{{ asset('images/buzzer.png') }}" alt="{{ __('Buzzer') }}">
    </button>
</div>

<div class="result-overlay" id="resultOverlay">
    <div class="result-text" id="resultText"></div>
    <div class="points-text" id="pointsText"></div>
</div>

{{-- Task #55 — Legacy opponent-buzz banner removed. Visual feedback is now
     conveyed by the reactive-glow pulse on offensive skills targeting the
     opponent (handled in JS handleOpponentBuzz). No DOM element needed. --}}

<audio id="buzzerSound" preload="auto">
    <source id="buzzerSource" src="{{ asset('sounds/buzzer_default_1.mp3') }}" type="audio/mpeg">
</audio>

<audio id="noBuzzSound" preload="auto">
    <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
</audio>

<audio id="chronoBackgroundSound" preload="auto">
    <source src="{{ asset('sounds/grenouille.mp3') }}" type="audio/mpeg">
</audio>

<audio id="gameplayAmbient" preload="auto" loop>
    <source src="{{ asset('sounds/gameplay_ambient.mp3') }}" type="audio/mpeg">
</audio>

{{-- socket.io + DuoSocketClient loaded by layouts.game --}}

<script>
(function() {
    'use strict';

    // ── D-A — Page fade-in on load, fade-out before navigation ───────────────
    // window.duoNavigate is checked by every redirectOnce() call on this page.
    // Setting it here (before any phase event fires) ensures all navigations
    // — buzz, answer reveal, round end, match end — use the same smooth fade.
    window.duoNavigate = function(url) {
        document.body.style.opacity = '0';
        setTimeout(function() { window.location.href = url; }, 220);
    };
    document.addEventListener('DOMContentLoaded', function() {
        requestAnimationFrame(function() { document.body.style.opacity = '1'; });
    });
    // ─────────────────────────────────────────────────────────────────────────

    const MATCH_ID        = window.MATCH_ID        || @json((string)($match_id ?? ''));
    const ROOM_ID         = window.ROOM_ID         || @json((string)($room_id ?? ''));
    const LOBBY_CODE      = window.LOBBY_CODE      || @json((string)($lobby_code ?? ''));
    const JWT_TOKEN       = window.JWT_TOKEN       || @json((string)($jwt_token ?? ''));
    const GAME_SERVER_URL = window.GAME_SERVER_URL || window.location.origin;
    
    const ANSWER_URL        = window.ANSWER_URL       || @json(route('game.duo.answer'));
    const RESULT_URL        = window.RESULT_URL       || @json(route('game.duo.result'));
    const MATCH_RESULT_URL  = window.MATCH_RESULT_URL || @json(route('game.duo.match-result'));
    
    const DEFAULT_THEME = @json($themeDisplay ?? $theme ?? 'Culture générale');
    const DEFAULT_TOTAL_QUESTIONS = {{ (int) ($totalQuestions ?? 10) }};
    const CURRENT_USER_ID = @json((string) (auth()->id() ?? ''));
    
    const PLACEHOLDER_QUESTION_TEXT = @json(__('En attente de la question...'));
    
    const FALLBACK_QUESTION = {
        text: PLACEHOLDER_QUESTION_TEXT,
        theme: DEFAULT_THEME
    };
    
    const TOTAL_TIME = 8;
    let timeLeft = TOTAL_TIME;
    let timerInterval = null;
    let introInterval = null;   // FIX: stored so we can clear it
    let buzzed = false;
    let phaseEndsAtMs = null;
    let currentPhase = 'LOBBY';
    let currentQuestion = null;
    let isRedirecting = false;

    // Persist "this player buzzed for question N" across the 302 bounce that the
    // backend issues when a position-2 buzzer hits /duo/answer during ANSWER_SELECTION
    // (the lock is held by position 1). When ANSWER_COLLECTION later opens (2s grace),
    // the duo_question handler reads this flag to navigate the player to /duo/answer
    // so they can use their answering window. Cleared on each new QUESTION_ACTIVE.
    const BUZZED_KEY = 'duoBuzzed_' + (window.MATCH_ID || '');
    function markBuzzedForQuestion(qIdx) {
        try {
            sessionStorage.setItem(BUZZED_KEY, JSON.stringify({
                questionIndex: (qIdx === undefined || qIdx === null) ? null : Number(qIdx),
                ts: Date.now()
            }));
        } catch (e) {}
    }
    function hasBuzzedForQuestion(qIdx) {
        try {
            const raw = sessionStorage.getItem(BUZZED_KEY);
            if (!raw) return false;
            const data = JSON.parse(raw);
            if (!data) return false;
            // If we don't have a questionIndex on either side, fall back to "any recent buzz" (<60s)
            if (qIdx === undefined || qIdx === null || data.questionIndex === null) {
                return (Date.now() - (data.ts || 0)) < 60000;
            }
            return Number(data.questionIndex) === Number(qIdx);
        } catch (e) { return false; }
    }
    function clearBuzzedFlag() {
        try { sessionStorage.removeItem(BUZZED_KEY); } catch (e) {}
    }
    let gameLayoutReady = false;
    let socketConnected = false;
    let questionReceived = false;
    let frogStarted = false;
    const FROG_THRESHOLD = 3; // Last 3 seconds = frog sounds
    
    @php
        $hasFasterBuzz = false;
        if (isset($skills) && is_array($skills)) {
            foreach ($skills as $skill) {
                if (($skill['id'] ?? '') === 'faster_buzz') {
                    $hasFasterBuzz = true;
                    break;
                }
            }
        }
    @endphp
    const HAS_FASTER_BUZZ = {{ $hasFasterBuzz ? 'true' : 'false' }};
    const BUZZ_REDIRECT_DELAY = HAS_FASTER_BUZZ ? 100 : 300;
    
    const chronoTimer = document.getElementById('chronoTimer');
    const buzzButton = document.getElementById('buzzButton');
    const buzzContainer = document.getElementById('buzzContainer');
    const questionText = document.getElementById('questionText');
    const questionTheme = document.getElementById('questionTheme');
    const questionCounter = document.getElementById('questionCounter');
    const playerScoreEl = document.getElementById('playerScore');
    const opponentScoreEl = document.getElementById('opponentScore');
    const resultOverlay = document.getElementById('resultOverlay');
    const resultText = document.getElementById('resultText');
    const pointsText = document.getElementById('pointsText');
    const buzzerSound = document.getElementById('buzzerSound');
    const buzzerSource = document.getElementById('buzzerSource');
    const noBuzzSound = document.getElementById('noBuzzSound');
    const chronoBackgroundSound = document.getElementById('chronoBackgroundSound');
    const gameplayAmbient = document.getElementById('gameplayAmbient');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingText = document.getElementById('loadingText');
    const gameContainer = document.getElementById('gameContainer');

    // ── Bridge UI: warm restore — immediately render cached state before socket arrives ──
    (function() {
        var rqt  = window.GR_RESTORED_QUESTION_TEXT;
        var rps  = window.GR_RESTORED_PLAYER_SCORE;
        var ros  = window.GR_RESTORED_OPPONENT_SCORE;
        if (rqt  && questionText)     { questionText.textContent   = rqt; }
        if (rps !== undefined && playerScoreEl)   { playerScoreEl.textContent   = String(rps); }
        if (ros !== undefined && opponentScoreEl) { opponentScoreEl.textContent = String(ros); }
    })();

    // Load user's selected buzzer from localStorage (same as solo mode)
    const selectedBuzzer = localStorage.getItem('selectedBuzzer') || 'buzzer_default_1';
    if (buzzerSource) {
        buzzerSource.src = `/sounds/${selectedBuzzer}.mp3`;
        buzzerSound.load();
    }
    
    function isQuestionUsable(question) {
        return !!(question && typeof question.text === 'string' && question.text.trim() !== '' && question.text.trim() !== PLACEHOLDER_QUESTION_TEXT.trim());
    }
    
    function updateQuestionUI(question, questionIndex = null, totalQuestions = null) {
        if (!question) return;
        
        if (questionText && question.text) {
            questionText.textContent = question.text;
        }
        
        const themeValue =
            question.theme ||
            question.category ||
            question.subCategory ||
            DEFAULT_THEME;
        
        if (questionTheme) {
            questionTheme.textContent = themeValue;
        }
        
        if (questionCounter && questionIndex !== null && questionIndex !== undefined) {
            const displayIndex = Number(questionIndex) + 1;
            questionCounter.textContent = Number.isFinite(displayIndex) ? displayIndex : 1;
        }
        
        currentQuestion = question;
        questionReceived = isQuestionUsable(question);
    }
    
    function showGameLayout() {
        if (gameLayoutReady) return;
        gameLayoutReady = true;
        
        loadingOverlay.classList.add('hidden');
        gameContainer.style.display = 'flex';
        // FIX: Don't overwrite the display if syncTimerWithServer already set it
        if (phaseEndsAtMs) {
            syncTimerWithServer(phaseEndsAtMs);
        } else {
            chronoTimer.textContent = TOTAL_TIME;
        }

        // Start ambient background music (looping, like solo mode)
        if (gameplayAmbient) {
            gameplayAmbient.volume = 0.3;
            gameplayAmbient.play().catch(() => {});
        }
       
        console.log('[DuoQuestion] Interface de jeu prête');
    }
    
    function updateLoadingText(text) {
        if (loadingText) {
            loadingText.textContent = text;
        }
    }
    
    function syncTimerWithServer(serverPhaseEndsAtMs) {
        if (!serverPhaseEndsAtMs) return;

        // The chrono on the Question page represents the QUESTION countdown only.
        // Other phases the orchestrator drives — ANSWER_COLLECTION (2s grace),
        // RESULT (60s ready-up window), ROUND_SCOREBOARD, etc. — must NOT be
        // displayed here, otherwise the user sees the chrono jump to "2" right
        // after the question ends and then to "60 → 57 → …" before the page
        // navigates to the Result view. Only sync when we're either:
        //   - explicitly in QUESTION_ACTIVE, or
        //   - on initial page load before the first phase event arrives
        //     (currentPhase still seeded with default 'QUESTION_ACTIVE').
        if (currentPhase && currentPhase !== 'QUESTION_ACTIVE') {
            return;
        }

        phaseEndsAtMs = serverPhaseEndsAtMs;
        const now = Date.now();
        const remainingMs = Math.max(0, phaseEndsAtMs - now);
        timeLeft = Math.ceil(remainingMs / 1000);
        chronoTimer.textContent = Math.max(0, timeLeft);
    }
    
    function resetTimerColor() {
        chronoTimer.style.color = '';
    }
    
    function setBuzzerState(state) {
        buzzContainer.classList.remove('buzzer-waiting', 'buzzer-ready', 'buzzer-hidden');
        buzzContainer.classList.add('buzzer-' + state);
        buzzButton.disabled = (state !== 'ready');
    }
    
    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);

        frogStarted = false;
        const chronoCircle = document.querySelector('.chrono-circle');
        if (chronoCircle) chronoCircle.classList.remove('urgent');

        // Ensure gameplay ambient is playing for the first 5 seconds
        if (gameplayAmbient) {
            gameplayAmbient.volume = 0.35;
            gameplayAmbient.play().catch(() => {});
        }
        // Make sure frog is silent
        if (chronoBackgroundSound) {
            chronoBackgroundSound.pause();
            chronoBackgroundSound.currentTime = 0;
        }

        setBuzzerState('ready');

        timerInterval = setInterval(() => {
            if (phaseEndsAtMs) {
                const now = Date.now();
                const remainingMs = Math.max(0, phaseEndsAtMs - now);
                timeLeft = Math.ceil(remainingMs / 1000);
            } else {
                timeLeft--;
            }

            const display = Math.max(0, timeLeft);
            chronoTimer.textContent = display;

            // Last 3 seconds: add frog ambiance on top (ambient keeps playing at lower volume)
            if (timeLeft <= FROG_THRESHOLD && !frogStarted) {
                frogStarted = true;
                // Lower ambient (don't stop) — frog sounds layer on top
                if (gameplayAmbient) {
                    gameplayAmbient.volume = 0.08;
                }
                // Start frog sounds
                if (chronoBackgroundSound) {
                    chronoBackgroundSound.currentTime = 0;
                    chronoBackgroundSound.play().catch(() => {});
                }
                // Visual urgency
                const cc = document.querySelector('.chrono-circle');
                if (cc) cc.classList.add('urgent');
            }

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                if (!buzzed && !isRedirecting) {
                    handleNoBuzz();
                }
            }
        }, 250);
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        // FIX: also clear the INTRO countdown interval
        if (introInterval) {
            clearInterval(introInterval);
            introInterval = null;
        }
        // Stop frog sound, restore ambient to full volume
        if (chronoBackgroundSound) {
            chronoBackgroundSound.pause();
            chronoBackgroundSound.currentTime = 0;
        }
        if (gameplayAmbient) {
            gameplayAmbient.volume = 0.35;
        }
        const chronoCircle = document.querySelector('.chrono-circle');
        if (chronoCircle) chronoCircle.classList.remove('urgent');
        frogStarted = false;
    }
    
    function ensureQuestionPhaseReady() {
        if (!isQuestionUsable(currentQuestion)) {
            return false;
        }
        
        if (!gameLayoutReady) {
            showGameLayout();
        }
        
        if (!buzzed && !isRedirecting) {
            startTimer();
        }
        
        return true;
    }
    
    function redirectOnce(url, delay = 0) {
        if (isRedirecting) return;
        isRedirecting = true;
        // Update Bridge UI visual-state payload before navigating
        const _qt = document.querySelector('.question-text') || document.querySelector('.question-text-display');
        const _ps = playerScoreEl ? parseInt(playerScoreEl.textContent.trim(), 10) || 0 : 0;
        const _os = opponentScoreEl ? parseInt(opponentScoreEl.textContent.trim(), 10) || 0 : 0;
        window.GR_SAVE_STATE_EXTRA = {
            // Tâche #77 P77.1: phase = currentPhase reçu de Node, sans fallback hardcodé.
            // Si Node n'a encore rien envoyé, phase reste null — Node restera la seule autorité.
            phase:          currentPhase || null,
            current_page:   'question',
            question_text:  _qt ? _qt.textContent.trim() : '',
            player_score:   _ps,
            opponent_score: _os,
            phaseEndsAtMs:  phaseEndsAtMs || undefined,
        };
        setTimeout(() => {
            (window.duoNavigate || function(u) { window.location.href = u; })(url);
        }, delay);
    }
    
    // NOTE (Task #38 NOYAU STATS LIVE): URL params ?ps=&os= are gone.
    // Scores live exclusively in window.SB_LIVE_STATS, fed by GameplayRuntime
    // listeners (player_stats_updated / score_update). Answer page reads them
    // synchronously from there. This kills the last client→client trust path.
    function getScoreParams() { return ''; }

    function handleBuzz() {
        if (buzzed || isRedirecting || currentPhase !== 'QUESTION_ACTIVE') return;
        
        buzzed = true;
        // D-B — Haptic feedback: short sharp pulse on buzz (mobile devices)
        if (navigator.vibrate) { navigator.vibrate([60]); }
        // Persister localement pour le handler ANSWER_COLLECTION (position-2).
        try {
            const qIdx = (window.SB_LIVE_STATS && window.SB_LIVE_STATS.questionIndex !== undefined)
                ? window.SB_LIVE_STATS.questionIndex
                : null;
            markBuzzedForQuestion(qIdx);
        } catch (e) {}
        stopTimer();
        
        buzzerSound.currentTime = 0;
        buzzerSound.play().catch(e => console.log('Erreur audio:', e));
        
        buzzButton.disabled = true;
        buzzContainer.classList.add('buzzed');
        setBuzzerState('hidden');

        if (window.DuoSocketClient && window.DuoSocketClient.isConnected()) {
            window.DuoSocketClient.buzz(Date.now());
        }

        // UX : feedback visible pendant l'attente de la confirmation Node.
        // Navigation déclenchée uniquement par handleBuzzWinner() ou phase_changed:ANSWER_SELECTION.
        showSkillMessage('{{ __("Buzzer envoyé\u00a0— en attente\u2026") }}', 'info', 3000);
    }
    
    function handleNoBuzz() {
        if (isRedirecting) return;
        
        noBuzzSound.currentTime = 0;
        noBuzzSound.play().catch(e => console.log('Erreur audio:', e));
        
        buzzButton.disabled = true;
        setBuzzerState('waiting');
        
        // No client-side redirect on timeout: Node is sole phase authority.
        // We stay on /duo/question; the phase_changed:RESULT handler navigates.
        console.log('[DuoQuestion] handleNoBuzz: waiting for phase_changed:RESULT');
    }
    
    function handleOpponentBuzz() {
        // Task #55 — Replaces the legacy text banner. When the opponent buzzes
        // first, briefly pulse-glow the player's still-available offensive
        // skills that target the opponent (data-affects-others="true"), as a
        // discreet reminder that they can riposte. Buzzer stays active so the
        // player can still grab 2nd position; no other behaviour changes.
        var circles = document.querySelectorAll(
            '.skill-circle.active[data-affects-others="true"]'
        );
        circles.forEach(function(circle) {
            // Defensive: skip used / depleted / passive / non-targeting circles.
            if (circle.classList.contains('used')) return;
            if (circle.classList.contains('depleted')) return;
            if (circle.getAttribute('data-passive') === 'true') return;
            // Restart the animation if it was already running.
            circle.classList.remove('reactive-glow');
            // Force reflow so re-adding the class restarts the keyframes.
            // eslint-disable-next-line no-unused-expressions
            void circle.offsetWidth;
            circle.classList.add('reactive-glow');
            setTimeout(function() {
                circle.classList.remove('reactive-glow');
            }, 3600);
        });
    }
    
    function showResult(isCorrect, points) {
        resultOverlay.style.display = 'block';
        resultOverlay.className = 'result-overlay ' + (isCorrect ? 'correct' : 'incorrect');
        resultText.textContent = isCorrect ? '{{ __("Correct!") }}' : '{{ __("Incorrect!") }}';
        pointsText.textContent = (points >= 0 ? '+' : '') + points + ' {{ __("points") }}';
        
        setTimeout(() => {
            resultOverlay.style.display = 'none';
        }, 2000);
    }
    
    function updateScores(playerScore, opponentScore) {
        if (playerScoreEl && playerScore !== undefined && playerScore !== null) {
            playerScoreEl.textContent = playerScore;
        }
        if (opponentScoreEl && opponentScore !== undefined && opponentScore !== null) {
            opponentScoreEl.textContent = opponentScore;
        }
    }
    
    function applyPhaseVisualState() {
        switch (currentPhase) {
            case 'QUESTION_ACTIVE':
                // Ensure any SYNC brain overlay is hidden when the question becomes active
                if (window.hideBrainSpin) window.hideBrainSpin();
                ensureQuestionPhaseReady();
                break;

            case 'SYNC':
                // Inter-question sync: waiting for both players' question_page_ready.
                // Directly show brain overlay even though NO_BRAIN_OVERLAY=true (bypass it).
                stopTimer();
                setBuzzerState('waiting');
                updateLoadingText('{{ __("Synchronisation...") }}');
                if (window.showBrainSpin) window.showBrainSpin('{{ __("Synchronisation...") }}');
                // D-D — After 4s in SYNC, hint that the opponent may still be loading
                // (bot reconnect grace period, slow connection). Uses existing translation key.
                setTimeout(function() {
                    if (currentPhase === 'SYNC') {
                        var waitMsg = '{{ __("En attente de l\'autre joueur") }}';
                        updateLoadingText(waitMsg);
                        if (window.showBrainSpin) window.showBrainSpin(waitMsg);
                    }
                }, 4000);
                break;
                
            case 'ANSWER_SELECTION':
                stopTimer();
                setBuzzerState('hidden');
                if (!isRedirecting && gameLayoutReady) {
                    updateLoadingText('{{ __("Passage à la réponse...") }}');
                }
                break;
                
            case 'ANSWER_COLLECTION':
                stopTimer();
                setBuzzerState('hidden');
                if (!isRedirecting && gameLayoutReady) {
                    updateLoadingText('{{ __("Collecte des réponses...") }}');
                }
                break;
                
            case 'RESULT':
                stopTimer();
                setBuzzerState('hidden');
                break;

            case 'REVEAL':
                stopTimer();
                setBuzzerState('hidden');
                if (!isRedirecting && gameLayoutReady) {
                    updateLoadingText('{{ __("Révélation de la réponse...") }}');
                }
                break;
                
            case 'ROUND_SCOREBOARD':
            case 'MATCH_END':
                stopTimer();
                setBuzzerState('hidden');
                break;
                
            case 'INTRO':
                stopTimer();  // also clears introInterval
                setBuzzerState('waiting');
                updateLoadingText('🎮 ' + Math.max(1, Math.ceil(Math.max(0, phaseEndsAtMs - Date.now()) / 1000)) + ' 🎮');
                // FIX: store the interval so stopTimer() can clean it up on phase change
                introInterval = setInterval(() => {
                    if (!phaseEndsAtMs) return;
                    const seconds = Math.max(1, Math.ceil(Math.max(0, phaseEndsAtMs - Date.now()) / 1000));
                    updateLoadingText('🎮 ' + seconds + ' 🎮');
                }, 250);
                break;
                
            case 'WAITING':
                stopTimer();
                setBuzzerState('waiting');
                updateLoadingText('{{ __("En attente des joueurs...") }}');
                break;
                
            case 'LOBBY':
            default:
                stopTimer();
                setBuzzerState('waiting');
                updateLoadingText('{{ __("En attente du démarrage de la question...") }}');
                break;
        }
    }
    
    function handleGameState(data) {
        console.log('[DuoQuestion] État du jeu reçu:', data);
        
        if (data.phase) {
            currentPhase = data.phase;
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        if (data.currentQuestion) {
            updateQuestionUI(data.currentQuestion, data.questionIndex, data.totalQuestions);
        }
        
        if (data.players) {
            let myScore = undefined;
            let enemyScore = undefined;
            
            Object.entries(data.players).forEach(([playerId, player]) => {
                const pid = String(playerId);
                const objectId = player && player.id !== undefined ? String(player.id) : null;
                
                if (pid === CURRENT_USER_ID || objectId === CURRENT_USER_ID) {
                    myScore = player.score;
                } else {
                    enemyScore = player.score;
                }
            });
            
            updateScores(myScore, enemyScore);
        }
        
        if (currentPhase === 'ANSWER_SELECTION' || currentPhase === 'BUZZ_WINNER_ANSWERING') {
            // game_state uses lockedAnswerPlayerId (flat), phase_changed uses lockedPlayerId
            const lockedId = data.lockedAnswerPlayerId || data.lockedPlayerId || '';
            if (lockedId && String(lockedId) === CURRENT_USER_ID) {
                redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 150);
                return;
            }
        }

        if (currentPhase === 'ANSWER_COLLECTION') {
            // Reconnect / state-restore mid-grace: if we already buzzed for this question,
            // jump to /duo/answer to use the remaining 2s window. Server allows it.
            const qIdx = (data && data.questionIndex !== undefined) ? data.questionIndex : null;
            if (hasBuzzedForQuestion(qIdx)) {
                redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 50);
                return;
            }
        }

        if (currentPhase === 'REVEAL') {
            // Pre-navigation: result page sent us here early during REVEAL.
            // FIX: set isRedirecting so phase_changed:REVEAL can't send us back to result.
            // QUESTION_ACTIVE handler resets isRedirecting when the next question starts.
            isRedirecting = true;
            stopTimer();
            setBuzzerState('hidden');
            updateLoadingText('{{ __("Révélation de la réponse...") }}');
            return;
        }
        
        if (currentPhase === 'ANSWER_COLLECTION') {
            applyPhaseVisualState();
            return;
        }

        if (currentPhase === 'RESULT') {
            redirectOnce(RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 300);
            return;
        }

        if (currentPhase === 'SYNC') {
            // V3: SYNC is the normal inter-question state. Both players must send
            // question_page_ready before the server advances to QUESTION_ACTIVE.
            // Explicitly lift NO_BRAIN_OVERLAY BEFORE applyPhaseVisualState() so the
            // brain shows reliably — applyPhaseVisualState(SYNC) calls showBrainSpin()
            // directly and also enforces stopTimer() + setBuzzerState('waiting').
            window.NO_BRAIN_OVERLAY = false;
            sendQuestionPageReady();
            applyPhaseVisualState();
            return;
        }
        
        if (currentPhase === 'ROUND_SCOREBOARD') {
            redirectOnce((window.ROUND_SCOREBOARD_URL || RESULT_URL) + '?match_id=' + encodeURIComponent(MATCH_ID), 200);
            return;
        }
        
        if (currentPhase === 'MATCH_END') {
            setTimeout(function() { navigateToMatchResult(_matchEndedData); }, 300);
            return;
        }
        
        applyPhaseVisualState();
    }
    
    function handlePhaseChanged(data) {
        console.log('[DuoQuestion] Phase changée:', data);
        
        if (data.phase) {
            currentPhase = data.phase;
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        }
        
        // phase_changed does not carry question data; question arrives via question_published
       
        if (currentPhase === 'QUESTION_ACTIVE') {
            buzzed = false;
            // New question — drop the persisted "I buzzed" flag from the previous one.
            clearBuzzedFlag();
            isRedirecting = false;
            window.NO_BRAIN_OVERLAY = true;
            applyPhaseVisualState();
            return;
        }
        
        if (currentPhase === 'ANSWER_SELECTION') {
            // Server sends lockedPlayerId in phase_changed (lockedAnswerPlayerId in game_state)
            const lockedId = data.lockedPlayerId || data.lockedAnswerPlayerId || '';
            if (String(lockedId) === CURRENT_USER_ID) {
                redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 150);
            } else {
                // Non-buzzer: go to Answer too (Duo participatif, 0-point path).
                // Node enforces scoring via didBuzz=false / buzzOrder=0.
                handleOpponentBuzz();
                redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 150);
            }
            return;
        }

        if (currentPhase === 'ANSWER_COLLECTION') {
            // V3 grace period (2s). Position-2 buzzer who got 302-bounced back to /question
            // during ANSWER_SELECTION must navigate to /duo/answer now to use their window.
            // The backend allows ANSWER_COLLECTION on /duo/answer for everyone.
            const qIdx = (data && data.questionIndex !== undefined) ? data.questionIndex : null;
            if (hasBuzzedForQuestion(qIdx)) {
                redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 50);
                return;
            }
            applyPhaseVisualState();
            return;
        }

        if (currentPhase === 'RESULT') {
            // V3: navigate to per-question result page
            stopTimer();
            setBuzzerState('hidden');
            redirectOnce(RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID), 300);
            return;
        }

        if (currentPhase === 'SYNC') {
            // V3: server is synchronizing for next question — emit question_page_ready
            // (This fires when reconnecting during SYNC or rare edge case)
            stopTimer();
            setBuzzerState('waiting');
            sendQuestionPageReady();
            // Temporarily lift NO_BRAIN_OVERLAY so GameplayRuntime can show the brain
            window.NO_BRAIN_OVERLAY = false;
            if (window.showBrainSpin) window.showBrainSpin('{{ __("Synchronisation...") }}');
            return;
        }
        
        if (currentPhase === 'REVEAL') {
            // REVEAL ≠ scoreboard — do not navigate here.
            // duo_answer handles reveal display; ROUND_SCOREBOARD below drives navigation.
            applyPhaseVisualState();
            return;
        }
        
        if (currentPhase === 'ROUND_SCOREBOARD') {
            redirectOnce((window.ROUND_SCOREBOARD_URL || RESULT_URL) + '?match_id=' + encodeURIComponent(MATCH_ID), 200);
            return;
        }
        
        if (currentPhase === 'MATCH_END') {
            setTimeout(function() { navigateToMatchResult(_matchEndedData); }, 300);
            return;
        }
        
        applyPhaseVisualState();
    }
    
    function handleQuestionPublished(data) {
        console.log('[DuoQuestion] Question publiée:', data);
        
        // The event data is flat: {text, choices, questionIndex, ...} — NOT data.question
        var questionObj = data.question || (data.text ? {
            text: data.text,
            choices: data.choices,
            theme: data.theme || data.category || data.subCategory || null
        } : null);
        if (questionObj) {
            updateQuestionUI(questionObj, data.questionIndex, data.totalQuestions);
        }
        
        if (data.phaseEndsAtMs) {
            syncTimerWithServer(data.phaseEndsAtMs);
        } else if (data.timeLimitMs) {
            // fallback: compute phaseEndsAtMs from timeLimitMs
            syncTimerWithServer(Date.now() + data.timeLimitMs);
        }
        
        if (data.reduceTimeActive) {
            showSkillMessage('{{ __("Temps réduit par un skill adverse!") }}', 'warning', 3000);
        }

        if (data.activeEffects && data.activeEffects.length > 0) {
            renderActiveEffects(data.activeEffects);
        }
        
        currentPhase = 'QUESTION_ACTIVE';
        buzzed = false;
        isRedirecting = false;
        
        applyPhaseVisualState();
    }
    
    var questionPageReadySent = false;
    function sendQuestionPageReady() {
        if (questionPageReadySent) return;
        questionPageReadySent = true;
        if (window.DuoSocketClient && window.DuoSocketClient.questionPageReady) {
            window.DuoSocketClient.questionPageReady();
            console.log('[DuoQuestion] question_page_ready emitted');
        }
    }

    function handleBuzzWinner(data) {
        console.log('[DuoQuestion] Gagnant du buzz:', data);
        
        if (String(data.playerId || '') === CURRENT_USER_ID) {
            // V3: I won a buzz position — save state and navigate to answer page.
            // Tâche #77 P77.1/P77.2 — Le client n'envoie plus de phase claim
            // (GR_RESTORED_PHASE est retiré côté GameplayRuntime, restoreState ne
            // ré-applique plus la phase saved). Seul Node est autorité.
            if (window.DuoSocketClient && window.DuoSocketClient.saveState) {
                window.DuoSocketClient.saveState({
                    buzzPosition: data.position || 1
                });
            }
            stopTimer();
            buzzButton.disabled = true;
            setBuzzerState('hidden');
            redirectOnce(ANSWER_URL + '?match_id=' + encodeURIComponent(MATCH_ID) + '&buzzed=true', 150);
        } else {
            // V3: opponent buzzed — NON-BLOCKING, I can still buzz for 2nd position
            handleOpponentBuzz();
        }
    }
    
    function handleAnswerRevealed(data) {
        console.log('[DuoQuestion] Réponse révélée:', data);
        
        if (data.playerScore !== undefined) {
            updateScores(data.playerScore, undefined);
        }
        if (data.opponentScore !== undefined) {
            updateScores(undefined, data.opponentScore);
        }

        if (data.skillsTriggered && data.skillsTriggered.length > 0) {
            data.skillsTriggered.forEach(function(triggered) {
                const isMe = String(triggered.playerId) === CURRENT_USER_ID;
                if (triggered.effect === 'score_shield') {
                    showSkillMessage(isMe ? '{{ __("Bouclier: pénalité bloquée!") }}' : '{{ __("Bouclier adverse: pénalité bloquée!") }}', 'info', 4000);
                } else if (triggered.effect === 'double_points') {
                    showSkillMessage(isMe ? '{{ __("Points doublés!") }}' : '{{ __("Double points adverse!") }}', 'success', 4000);
                }
            });
        }

        // didYouKnow is displayed on the answer page and round scoreboard — not here.
    }
    
    function handleScoreUpdate(data) {
        console.log('[DuoQuestion] Mise à jour des scores:', data);
        
        if (data.playerId && data.score !== undefined) {
            if (String(data.playerId) === CURRENT_USER_ID) {
                updateScores(data.score, undefined);
            } else {
                updateScores(undefined, data.score);
            }
        } else if (data.scores) {
            Object.entries(data.scores).forEach(([playerId, score]) => {
                if (String(playerId) === CURRENT_USER_ID) {
                    updateScores(score, undefined);
                } else {
                    updateScores(undefined, score);
                }
            });
        }
    }
    
    var _matchEndedData = null;
    var _isFinishingMatch = false;

    function _callFinishSocketIO(logPrefix, retryCount) {
        var matchId = window.MATCH_ID;
        var token   = window.JWT_TOKEN;
        if (!matchId || !token) return Promise.resolve();
        retryCount = retryCount || 0;
        var controller = new AbortController();
        var tid = setTimeout(function() { controller.abort(); }, 4000);
        return fetch('/api/duo/match/' + matchId + '/finish-socketio', {
            method: 'POST',
            headers: {
                'Content-Type':  'application/json',
                'Authorization': 'Bearer ' + token,
            },
            body: JSON.stringify({}),
            signal: controller.signal,
        }).then(function(res) {
            clearTimeout(tid);
            if (res.status === 202 && retryCount < 3) {
                // Game server hasn't written Redis result yet — retry after 1s
                console.log(logPrefix + ' finishSocketIO pending, retry ' + (retryCount + 1));
                return new Promise(function(resolve) {
                    setTimeout(function() {
                        _callFinishSocketIO(logPrefix, retryCount + 1).then(resolve);
                    }, 1000);
                });
            }
            console.log(logPrefix + ' finishSocketIO status:', res.status);
        }).catch(function(err) {
            clearTimeout(tid);
            console.warn(logPrefix + ' finishSocketIO failed (navigating anyway):', err.message);
        });
    }

    function navigateToMatchResult(data) {
        if (_isFinishingMatch) return;
        _isFinishingMatch = true;
        stopTimer();
        _callFinishSocketIO('[DuoQuestion]').finally(function() {
            isRedirecting = true;
            (window.duoNavigate || function(u) { window.location.href = u; })(
                MATCH_RESULT_URL + '?match_id=' + encodeURIComponent(MATCH_ID)
            );
        });
    }

    function handleMatchEnded(data) {
        console.log('[DuoQuestion] Match terminé:', data);
        _matchEndedData = data;
        navigateToMatchResult(data);
    }
    
    function handleSkillActivated(data) {
        console.log('[DuoQuestion] Skill activé:', data);
        const isMe = String(data.activatedBy) === CURRENT_USER_ID;
        const isTarget = String(data.targetPlayerId) === CURRENT_USER_ID;

        switch (data.effect) {
            case 'reduce_time':
                showSkillMessage(isTarget ? '{{ __("Temps réduit par l\'adversaire!") }}' : '{{ __("Temps de l\'adversaire réduit!") }}', isTarget ? 'warning' : 'success', 3000);
                break;
            case 'score_shield':
                if (isMe) showSkillMessage('{{ __("Bouclier de score activé!") }}', 'info', 3000);
                break;
            case 'double_points':
                if (isMe) showSkillMessage('{{ __("Points doublés pour ce round!") }}', 'success', 3000);
                break;
            case 'reveal_correct':
                if (isMe) showSkillMessage('{{ __("Réponse correcte révélée!") }}', 'info', 3000);
                break;
            case 'shuffle_answers':
                showSkillMessage(isTarget ? '{{ __("Réponses mélangées!") }}' : '{{ __("Réponses adverses mélangées!") }}', isTarget ? 'warning' : 'success', 3000);
                break;
        }

        if (isMe && data.skillId !== undefined && data.charges !== undefined) {
            updateSkillChargeUI(data.skillId, data.charges);
        }
    }

    function handleSkillFailed(data) {
        console.log('[DuoQuestion] Skill échoué:', data);
        const msgs = {
            no_charges: '{{ __("Plus de charges!") }}',
            not_applicable: '{{ __("Skill non applicable ici.") }}',
            cooldown: '{{ __("Skill en recharge.") }}',
            invalid_target: '{{ __("Cible invalide.") }}'
        };
        showSkillMessage(msgs[data.reason] || '{{ __("Impossible d\'activer ce skill.") }}', 'error', 3000);
    }

    function handleRateLimited(data) {
        console.log('[DuoQuestion] Limité:', data);
        const reason = (data.reason || '').toLowerCase();
        if (reason.includes('already buzzed') || reason.includes('already_buzzed')) {
            // Player already buzzed this question — navigate to answer page
            if (!isRedirecting) {
                redirectOnce(ANSWER_URL + '?buzzed=true&match_id=' + encodeURIComponent(MATCH_ID) + getScoreParams(), 100);
            }
            return;
        }
        showSkillMessage('{{ __("Action trop rapide – réessayez.") }}', 'warning', 2000);
    }

    function updateSkillChargeUI(skillId, charges) {
        const skillEl = document.querySelector('[data-skill-id="' + skillId + '"]');
        if (!skillEl) return;
        if (charges <= 0) {
            skillEl.classList.remove('active');
            skillEl.classList.add('depleted');
            skillEl.style.opacity = '0.4';
        }
    }

    function renderActiveEffects(activeEffects) {
        let panel = document.getElementById('active-effects-panel');
        if (!panel) {
            panel = document.createElement('div');
            panel.id = 'active-effects-panel';
            panel.style.cssText = 'position:fixed;top:10px;right:10px;z-index:8000;display:flex;flex-direction:column;gap:6px;';
            document.body.appendChild(panel);
        }
        panel.innerHTML = '';
        activeEffects.forEach(function(eff) {
            const isMe = String(eff.activatedBy) === CURRENT_USER_ID;
            const labels = {
                score_shield: '{{ __("Bouclier") }}',
                double_points: '{{ __("x2 Points") }}',
                reduce_time: '{{ __("Temps réduit") }}',
                reveal_correct: '{{ __("Oracle") }}',
                shuffle_answers: '{{ __("Chaos") }}'
            };
            const colors = { score_shield:'#3498DB', double_points:'#2ECC71', reduce_time:'#E74C3C', reveal_correct:'#9B59B6', shuffle_answers:'#E67E22' };
            const badge = document.createElement('div');
            badge.style.cssText = 'padding:4px 10px;border-radius:20px;font-size:12px;font-weight:bold;color:#fff;background:' + (colors[eff.effect] || '#555') + ';opacity:' + (isMe ? '1' : '0.7') + ';';
            badge.textContent = (isMe ? '' : '⚔ ') + (labels[eff.effect] || eff.effect);
            panel.appendChild(badge);
        });
        panel.style.display = activeEffects.length === 0 ? 'none' : 'flex';
    }

    function showDidYouKnow(text) {
        const div = document.createElement('div');
        // D-E — safe-area-inset-bottom: prevents overlap with home indicator on notched phones
        div.style.cssText = 'position:fixed;bottom:calc(80px + env(safe-area-inset-bottom, 0px));left:50%;transform:translateX(-50%);max-width:600px;width:90%;padding:14px 20px;border-radius:12px;background:linear-gradient(135deg,#8E44AD,#6C3483);color:#fff;font-size:14px;z-index:9998;box-shadow:0 4px 16px rgba(0,0,0,0.4);';
        div.innerHTML = '<strong>{{ __("Le saviez-vous?") }}</strong> ' + text;
        document.body.appendChild(div);
        setTimeout(function() { div.style.transition = 'opacity 0.5s'; div.style.opacity = '0'; }, 5500);
        setTimeout(function() { div.remove(); }, 6100);
    }

    function showSkillMessage(message, type, duration = 3000) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'skill-message skill-message-' + type;
        msgDiv.innerHTML = message;
        msgDiv.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 10px; font-weight: bold; z-index: 9999; animation: fadeInOut ' + (duration / 1000) + 's ease-in-out;';
        
        if (type === 'success') {
            msgDiv.style.background = 'linear-gradient(135deg, #2ECC71, #27AE60)';
        } else if (type === 'error') {
            msgDiv.style.background = 'linear-gradient(135deg, #E74C3C, #C0392B)';
        } else if (type === 'warning') {
            msgDiv.style.background = 'linear-gradient(135deg, #F39C12, #E67E22)';
        } else {
            msgDiv.style.background = 'linear-gradient(135deg, #3498DB, #2980B9)';
        }
        msgDiv.style.color = 'white';
        
        document.body.appendChild(msgDiv);
        setTimeout(() => msgDiv.remove(), duration);
    }
    
    async function loadQuestionFromServer() {
        try {
            const response = await fetch('/game/duo/fetch-question', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            const data = await response.json();
            
            if (data.success && data.question) {
                updateQuestionUI(data.question, data.current_question ? (Number(data.current_question) - 1) : null, data.total_questions);
                return true;
            }
        } catch (fetchError) {
            console.error('[DuoQuestion] Erreur chargement question:', fetchError);
        }
        return false;
    }
    
    // Register DuoSocketClient handlers after all scripts have loaded.
    // DOMContentLoaded is guaranteed to fire after ALL blocking <script src=""> tags have
    // fetched and executed — including DuoSocketClient.js (loaded later in the layout).
    // setTimeout(0) was unreliable: it is a macrotask that can fire DURING a script fetch,
    // before window.DuoSocketClient is set.
    document.addEventListener('DOMContentLoaded', function() {
        var ds = window.DuoSocketClient;
        if (ds) {
            // V3: emit question_page_ready after connect so server can advance from SYNC.
            // Reset the one-shot flag on every new connection so reconnects re-emit.
            ds.on('connect', function() {
                questionPageReadySent = false;
                setTimeout(function() { sendQuestionPageReady(); }, 350);
            });

            // Safety fallback: if the socket is already connected when this page loads
            // (bridge navigation reuses an existing socket, so no 'connect' event fires),
            // emit question_page_ready directly. The server will only act on it if the
            // room is currently in SYNC — otherwise it is a no-op.
            if (ds.isConnected && ds.isConnected()) {
                setTimeout(function() { sendQuestionPageReady(); }, 350);
            }
            // Handle raw 'state' event (emitted on join_room) in addition to 'game_state'.
            // Normalizes the raw room state into the game_state format consumed by handleGameState.
            ds.on('state', function(data) {
                if (!data || !data.state) return;
                var s = data.state;
                handleGameState({
                    phase:                s.phase,
                    phaseEndsAtMs:        s.phaseEndsAtMs,
                    lockedAnswerPlayerId: s.lockedAnswerPlayerId,
                    currentQuestion:      s.currentQuestion,
                    questionIndex:        s.questionIndex,
                    totalQuestions:       s.questions ? s.questions.length : undefined,
                    players:              s.players,
                });
            });
            ds.on('game_state',         handleGameState);
            ds.on('phase_changed',      handlePhaseChanged);
            ds.on('question_published', handleQuestionPublished);
            ds.on('buzz_winner',        handleBuzzWinner);
            ds.on('answer_revealed',    handleAnswerRevealed);
            ds.on('score_update',       handleScoreUpdate);
            ds.on('match_ended',        handleMatchEnded);
            ds.on('skill_activated',    handleSkillActivated);
            ds.on('skill_failed',       handleSkillFailed);
            ds.on('rate_limited',       handleRateLimited);
        } else {
            console.warn('[DuoQuestion] DuoSocketClient unavailable — falling back to HTTP question load');
            if (typeof loadQuestionFromServer === 'function') loadQuestionFromServer();
        }
    });

    buzzButton.addEventListener('click', handleBuzz);
    
    document.addEventListener('keydown', function(e) {
        if (e.code === 'Space' || e.key === ' ') {
            e.preventDefault();
            if (!buzzButton.disabled && !buzzed && !isRedirecting && currentPhase === 'QUESTION_ACTIVE') {
                handleBuzz();
            }
        }
    });
    
    // Skills are LOCKED during QUESTION_ACTIVE phase — they can only be activated
    // on the Result page (REVEAL / ROUND_SCOREBOARD phases).
    // Passive skills (faster_buzz) are auto-applied and need no click.
    document.querySelectorAll('.skill-circle.active').forEach(skill => {
        skill.addEventListener('click', function() {
            const name = this.getAttribute('title') || '';
            const label = name.split(':')[0] || '{{ __("Skill") }}';
            showSkillMessage('🔒 ' + label + ' — {{ __("Disponible après la question") }}', 'info');
        });
    });
    
    applyPhaseVisualState();
    
    window.addEventListener('beforeunload', () => {
        if (duoSocket && duoSocket.isConnected()) {
            // REMOVED disconnect (shared socket lifecycle)
        }
    });
})();
</script>

@endsection

@section('scripts')
{{-- Handlers registered via setTimeout(0) inside @section('content') IIFE above --}}
@endsection
