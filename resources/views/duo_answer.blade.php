@extends('layouts.game')

@section('game-data')
@include('partials.game-context', [
    'roomId'         => $room_id ?? '',
    'lobbyCode'      => $lobby_code ?? null,
    'jwtToken'       => $jwt_token ?? '',
    'matchId'        => $match_id ?? '',
    'mode'           => 'duo',
    'page'           => 'answer',
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
window.RESULT_URL           = @json(route('game.duo.result'));
window.ROUND_SCOREBOARD_URL = @json(route('game.duo.round-scoreboard'));
window.MATCH_RESULT_URL     = @json(route('game.duo.match-result'));
window.CURRENT_PAGE         = 'answer';
window.NO_BRAIN_OVERLAY     = true;
// Bridge UI: page-specific visual state saved on every navigation
// phase is initialised to null and updated dynamically by _onAnswerPhaseChanged (F4)
window.GR_SAVE_STATE_EXTRA  = {
    phase:         null,
    current_page:  'answer',
    question_text: @json($questionText ?? ''),
    choices:       @json($choices ?? []),
    player_score:  {{ (int)($playerScore ?? 0) }},
    opponent_score: {{ (int)($opponentScore ?? 0) }},
    phaseEndsAtMs: null,
};
</script>
@endsection

@section('content')
@php
$mode = 'duo';
$choices = $question['choices'] ?? [];
$questionText = $question['text'] ?? '';
$correct_index = $question['correct_answer'] ?? $question['correct_index'] ?? null;
$isBuzzWinner = ($buzz_winner ?? 'player') === 'player';
$buzzTime = $buzz_time ?? 0;
$noBuzz = ($no_buzz ?? false) || !$isBuzzWinner && $buzzTime == 0;

// V3: quadri-état buzzeur (rendu initial PHP — la source de vérité finale est le socket)
// 'first'   = buzzé position 1 (IS_BUZZ_WINNER)
// 'second'  = buzzé position 2+ (adversaire en premier, mais joueur a aussi buzzé)
// 'no_buzz' = round explicitement sans buzz (timer expiré sans aucun buzz)
// 'none'    = pas buzzé du tout, adversaire a buzzé → waiting overlay
$playerBuzzPosition = 'none';
if ($no_buzz ?? false) {
    $playerBuzzPosition = 'no_buzz';
} elseif ($isBuzzWinner) {
    $playerBuzzPosition = 'first';
} elseif ($buzzTime > 0) {
    $playerBuzzPosition = 'second';
}

// Skills Challenger - passés par le contrôleur
$shuffleAnswersActive = $shuffleAnswersActive ?? false;
$shuffleQuestionsLeft = $shuffleQuestionsLeft ?? 0;
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
    
    .game-container {
        max-width: 600px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
        position: relative;
        padding: 20px;
    }
    
    .header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(30, 50, 70, 0.8);
        padding: 15px 25px;
        border-radius: 15px;
        border: 2px solid rgba(78, 205, 196, 0.3);
    }
    
    .question-label {
        font-size: 1.3rem;
        font-weight: 700;
        color: #fff;
    }
    
    .potential-points {
        font-size: 1.8rem;
        font-weight: 900;
        transition: all 0.3s ease;
    }
    
    .potential-points.points-2 {
        color: #4ECDC4;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
    }
    
    .potential-points.points-1 {
        color: #FFD700;
        text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
    }
    
    .potential-points.points-0 {
        color: #FF6B6B;
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.8);
    }
    
    .score-display {
        font-size: 1.1rem;
        font-weight: 600;
        color: #aaa;
    }

    .efficiency-display {
        font-size: 0.9rem;
        font-weight: 600;
        color: #FFD700;
        opacity: 0.85;
    }

    .opponent-mini {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        color: #FF6B6B;
        font-weight: 600;
        padding-left: 10px;
        margin-left: 6px;
        border-left: 1px solid rgba(255, 107, 107, 0.3);
    }

    .opponent-mini .om-label { opacity: 0.7; font-size: 0.75rem; }
    .opponent-mini .om-value { font-weight: 800; }
    
    .question-text-box {
        background: rgba(30, 50, 70, 0.6);
        padding: 20px;
        border-radius: 12px;
        font-size: 1.2rem;
        font-weight: 500;
        line-height: 1.5;
        text-align: center;
        border: 1px solid rgba(78, 205, 196, 0.2);
    }
    
    .timer-section {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px 0;
    }
    
    .timer-label {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        white-space: nowrap;
    }
    
    .timer-bar-container {
        flex: 1;
        height: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        overflow: hidden;
    }
    
    .timer-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4, #667eea);
        border-radius: 4px;
        transition: width 0.3s linear;
        width: 100%;
    }
    
    .timer-bar.warning {
        background: linear-gradient(90deg, #FF6B6B, #FF8E53);
    }
    
    .timer-seconds {
        font-size: 1rem;
        font-weight: 700;
        color: #4ECDC4;
        min-width: 30px;
        text-align: right;
    }
    
    .timer-seconds.warning {
        color: #FF6B6B;
    }
    
    .answers-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin: 10px 0;
    }
    
    .answer-button {
        display: flex;
        align-items: center;
        gap: 15px;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 18px 20px;
        color: #fff;
        font-size: 1.1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        width: 100%;
    }
    
    .answer-number {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .answer-text {
        flex: 1;
    }
    
    .answer-button:hover:not(.disabled):not(.selected) {
        background: rgba(78, 205, 196, 0.15);
        border-color: rgba(78, 205, 196, 0.5);
        transform: translateX(5px);
    }
    
    .answer-button.selected {
        background: rgba(78, 205, 196, 0.25);
        border-color: #4ECDC4;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.4);
    }
    
    .answer-button.selected .answer-number {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    }
    
    .answer-button.correct {
        background: rgba(78, 205, 196, 0.3);
        border-color: #4ECDC4;
        box-shadow: 0 0 25px rgba(78, 205, 196, 0.6);
    }
    
    .answer-button.correct .answer-number {
        background: linear-gradient(135deg, #4ECDC4 0%, #2ECC71 100%);
    }
    
    .answer-button.incorrect {
        background: rgba(255, 107, 107, 0.3);
        border-color: #FF6B6B;
        box-shadow: 0 0 25px rgba(255, 107, 107, 0.6);
    }
    
    .answer-button.incorrect .answer-number {
        background: linear-gradient(135deg, #FF6B6B 0%, #E74C3C 100%);
    }
    
    .answer-button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .answer-button.waiting {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .answer-indicator {
        font-size: 1.4rem;
        margin-left: auto;
    }
    
    .buzz-status-banner {
        padding: 12px 20px;
        border-radius: 10px;
        text-align: center;
        font-size: 1rem;
        font-weight: 600;
        margin-top: 10px;
    }
    
    .buzz-status-banner.buzzed {
        background: rgba(78, 205, 196, 0.15);
        border: 2px solid rgba(78, 205, 196, 0.5);
        color: #4ECDC4;
    }
    
    .buzz-status-banner.no-buzz {
        background: rgba(255, 165, 0, 0.15);
        border: 2px solid rgba(255, 165, 0, 0.5);
        color: #FFA500;
    }
    
    .buzz-status-banner.opponent-buzz {
        background: rgba(255, 107, 107, 0.15);
        border: 2px solid rgba(255, 107, 107, 0.5);
        color: #FF6B6B;
    }
    
    .buzz-status-banner.historian-active {
        background: rgba(139, 90, 43, 0.2);
        border: 2px solid rgba(205, 133, 63, 0.6);
        color: #DEB887;
    }
    
    .historian-skill-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        margin: 15px 0;
    }
    
    .historian-skill-button {
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, rgba(139, 90, 43, 0.3) 0%, rgba(205, 133, 63, 0.3) 100%);
        border: 2px solid rgba(205, 133, 63, 0.6);
        border-radius: 15px;
        padding: 15px 25px;
        color: #DEB887;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        animation: pulse-historian 2s ease-in-out infinite;
    }
    
    @keyframes pulse-historian {
        0%, 100% { box-shadow: 0 0 15px rgba(205, 133, 63, 0.4); }
        50% { box-shadow: 0 0 25px rgba(205, 133, 63, 0.7); }
    }
    
    .historian-skill-button:hover {
        background: linear-gradient(135deg, rgba(139, 90, 43, 0.5) 0%, rgba(205, 133, 63, 0.5) 100%);
        transform: scale(1.05);
        box-shadow: 0 0 30px rgba(205, 133, 63, 0.8);
    }
    
    .historian-skill-button .skill-icon {
        font-size: 1.8rem;
    }
    
    .historian-skill-button .skill-text {
        font-weight: 700;
    }
    
    .historian-skill-button .skill-points {
        background: rgba(78, 205, 196, 0.3);
        padding: 4px 10px;
        border-radius: 8px;
        color: #4ECDC4;
        font-size: 0.9rem;
    }
    
    .skill-hint {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
        margin: 0;
    }
    
    .active-skills-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
        margin: 10px 0;
        padding: 10px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 12px;
    }
    
    .skill-action-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        border: 2px solid;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .skill-action-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .skill-action-btn.used {
        opacity: 0.3;
        text-decoration: line-through;
    }
    
    .skill-action-btn .skill-icon {
        font-size: 1.2rem;
    }
    
    .skill-action-btn.mathematicien {
        background: rgba(147, 112, 219, 0.2);
        border-color: rgba(147, 112, 219, 0.6);
        color: #B19CD9;
    }
    
    .skill-action-btn.scientifique {
        background: rgba(0, 255, 136, 0.15);
        border-color: rgba(0, 255, 136, 0.5);
        color: #00FF88;
    }
    
    .skill-action-btn.ia-junior {
        background: rgba(0, 191, 255, 0.15);
        border-color: rgba(0, 191, 255, 0.5);
        color: #00BFFF;
    }
    
    .skill-action-btn.visionnaire {
        background: rgba(255, 215, 0, 0.15);
        border-color: rgba(255, 215, 0, 0.5);
        color: #FFD700;
    }
    
    .skill-action-btn.sprinteur {
        background: rgba(255, 165, 0, 0.15);
        border-color: rgba(255, 165, 0, 0.5);
        color: #FFA500;
    }
    
    .skill-action-btn.historien {
        background: rgba(139, 90, 43, 0.2);
        border-color: rgba(205, 133, 63, 0.6);
        color: #DEB887;
    }
    
    .skill-action-btn.pending {
        opacity: 0.6;
        cursor: wait;
        pointer-events: none;
        animation: pulse 0.8s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 0.6; }
        50% { opacity: 0.9; }
    }
    .skill-action-btn:not(:disabled):hover {
        transform: scale(1.05);
        filter: brightness(1.2);
    }
    
    .illuminated-number {
        color: #FFD700;
        font-weight: 700;
        text-shadow: 0 0 8px rgba(255, 215, 0, 0.8), 0 0 16px rgba(255, 215, 0, 0.5);
        animation: pulse-illuminate 1.5s ease-in-out infinite;
    }
    
    @keyframes pulse-illuminate {
        0%, 100% { text-shadow: 0 0 8px rgba(255, 215, 0, 0.8); }
        50% { text-shadow: 0 0 20px rgba(255, 215, 0, 1), 0 0 35px rgba(255, 215, 0, 0.7); }
    }
    
    .answer-button.acidified {
        background: rgba(0, 255, 136, 0.1);
        border-color: #00FF88;
        box-shadow: 0 0 15px rgba(0, 255, 136, 0.4);
    }
    
    .answer-button.acidified::after {
        content: '🧪';
        position: absolute;
        right: 10px;
        font-size: 1.2rem;
    }
    
    .answer-button.eliminated {
        opacity: 0.2;
        pointer-events: none;
        text-decoration: line-through;
    }
    
    .answer-button.ai-suggested {
        background: linear-gradient(135deg, rgba(0, 191, 255, 0.3) 0%, rgba(30, 144, 255, 0.3) 100%);
        border-color: #00BFFF;
        box-shadow: 0 0 20px rgba(0, 191, 255, 0.5);
    }
    
    .answer-button.ai-suggested::before {
        content: '🤖';
        position: absolute;
        left: 10px;
        font-size: 1rem;
    }
    
    .answer-button.locked-correct {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 165, 0, 0.3) 100%);
        border-color: #FFD700;
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    }
    
    .result-overlay {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.95);
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
    
    .correct-answer-text {
        font-size: 1.2rem;
        margin-top: 15px;
        color: #FFD700;
    }
    
    .waiting-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 150;
    }
    
    .waiting-message {
        background: rgba(0, 0, 0, 0.9);
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        border: 3px solid #FFD700;
        box-shadow: 0 0 50px rgba(255, 215, 0, 0.5);
    }
    
    .waiting-message h2 {
        font-size: 1.8rem;
        color: #FFD700;
        margin-bottom: 10px;
    }
    
    .waiting-message p {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    /* voice-mic-button styles: provided by layouts.game */
    
    @media (max-width: 480px) {
        .game-container {
            padding: 15px;
        }
        
        .header-row {
            padding: 12px 18px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .question-label {
            font-size: 1.1rem;
        }
        
        .potential-points {
            font-size: 1.5rem;
        }
        
        .answer-button {
            padding: 15px;
            font-size: 1rem;
        }
        
        .answer-number {
            width: 32px;
            height: 32px;
            font-size: 0.9rem;
        }
    }
    
    /* Shuffle Answers Animation (Challenger Skill) */
    .answers-container.shuffle-active .answer-button,
    .answers-grid.shuffle-active .answer-button {
        transition: transform 0.3s ease, opacity 0.3s ease;
    }
    
    .answers-container.shuffle-active .answer-button.shuffling,
    .answers-grid.shuffle-active .answer-button.shuffling {
        animation: shuffleBounce 0.3s ease;
    }
    
    @keyframes shuffleBounce {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(0.95); opacity: 0.7; }
        100% { transform: scale(1); opacity: 1; }
    }
    
    .shuffle-indicator {
        grid-column: 1 / -1;
        text-align: center;
        color: #ff5722;
        font-weight: 700;
        font-size: 1rem;
        padding: 10px;
        background: rgba(255, 87, 34, 0.2);
        border-radius: 10px;
        border: 1px solid rgba(255, 87, 34, 0.4);
        animation: shufflePulse 1.5s infinite;
    }
    
    @keyframes shufflePulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    /* Hide connection badge when connected — only show when disconnected */
    #connectionStatus.connected { display: none !important; }

    /* Hide voice mic on answer page — only shown on result page */
    #voiceMicButton { display: none !important; }

    /* Hide shared layout #gameHeader on Duo answer page — this view already
       integrates its own header + player/opponent panels so the shared header
       would duplicate "Question X/Y · Manche Z · Bot" on top. */
    #gameHeader { display: none !important; }
</style>

{{-- connection-status, voice-mic-button: provided by layouts.game --}}

<div class="game-container">
    <div class="header-row">
        <div class="question-label">{{ __('Question') }} #{{ $currentQuestion ?? 1 }}</div>
        <div class="potential-points points-2" id="potentialPoints">+2</div>
        <div class="score-display" id="scoreDisplay">{{ __('Score') }} <span id="playerScoreValue" data-stat="score" data-player="self">{{ $playerScore ?? 0 }}</span></div>
        <div class="efficiency-display" id="efficiencyDisplay">⚡ <span id="efficiencyValue" data-stat="efficiencyPercent" data-player="self">—</span></div>
        <div class="opponent-mini" aria-label="{{ __('Adversaire') }}">
            <span class="om-label">{{ __('Adv.') }}</span>
            <span class="om-value" data-stat="score" data-player="opponent">{{ $opponentScore ?? 0 }}</span>
            <span class="om-label">⚡</span>
            <span class="om-value" data-stat="efficiencyPercent" data-player="opponent">0%</span>
            <span class="om-label">·</span>
            <span class="om-label">{{ __('Série') }}</span>
            <span class="om-value" data-stat="currentStreak" data-player="opponent">0</span>
        </div>
    </div>
    
    {{-- Question text intentionally hidden on the Answer page — it was already
         shown on the Question page and should not reappear here. The DOM node
         is kept (display:none) because skill effects (e.g. illuminate-numbers
         at line ~1083) and the auto-recovery logic (line ~1604) read from
         .question-text-box; removing it would break those code paths. --}}
    <div class="question-text-box" style="display: none;">
        {{ $questionText }}
    </div>

    <div class="timer-section">
        <span class="timer-label">{{ __('Temps pour répondre') }}</span>
        <div class="timer-bar-container">
            <div class="timer-bar" id="timerBar"></div>
        </div>
        <span class="timer-seconds" id="timerSeconds">10s</span>
    </div>
    
    <div class="answers-container{{ $shuffleAnswersActive ? ' shuffle-active' : '' }}" id="answersContainer">
        @if($shuffleAnswersActive)
        <div class="shuffle-indicator">
            🔀 {{ __('Réponses en mouvement') }}
        </div>
        @endif
        @foreach($choices as $index => $choice)
            <button class="answer-button {{ $playerBuzzPosition === 'none' ? 'waiting' : '' }}" 
                    data-index="{{ $index }}"
                    data-text="{{ $choice }}"
                    {{ $playerBuzzPosition === 'none' ? 'disabled' : '' }}>
                <span class="answer-number">{{ $index + 1 }}</span>
                <span class="answer-text">{{ $choice }}</span>
                <span class="answer-indicator" id="indicator{{ $index }}"></span>
            </button>
        @endforeach
    </div>
    
    @php
        $hasHistorianSkill = false;
        $hasIlluminateNumbers = false;
        $hasAcidifyError = false;
        $hasEliminateTwo = false;
        $hasAiSuggestion = false;
        $hasLockCorrect = false;
        $hasExtraAnswerTime = false;
        
        if (isset($skills) && is_array($skills)) {
            foreach ($skills as $skill) {
                $skillId = $skill['id'] ?? '';
                if ($skillId === 'knowledge_without_time') {
                    $hasHistorianSkill = true;
                }
                if ($skillId === 'illuminate_numbers') $hasIlluminateNumbers = true;
                if ($skillId === 'acidify_error') $hasAcidifyError = true;
                if ($skillId === 'eliminate_two') $hasEliminateTwo = true;
                if ($skillId === 'ai_suggestion') $hasAiSuggestion = true;
                if ($skillId === 'lock_correct') $hasLockCorrect = true;
                if ($skillId === 'extra_answer_time') $hasExtraAnswerTime = true;
            }
        }
        
        $correctIndex = $correct_index ?? null;
        $choicesJson = json_encode($choices);
    @endphp
    
    @if($hasIlluminateNumbers || $hasAcidifyError || $hasEliminateTwo || $hasAiSuggestion || $hasLockCorrect || $hasExtraAnswerTime)
    <div class="active-skills-bar" id="activeSkillsBar">
        @if($hasIlluminateNumbers)
            <button class="skill-action-btn mathematicien" id="skillIlluminate" title="{{ __('Illumine une réponse si elle contient un chiffre') }}">
                <span class="skill-icon">💡</span>
                <span>{{ __('Illuminer') }}</span>
            </button>
        @endif
        @if($hasAcidifyError)
            <button class="skill-action-btn scientifique" id="skillAcidify" title="{{ __('Acidifie une mauvaise réponse') }}">
                <span class="skill-icon">🧪</span>
                <span>{{ __('Acidifier') }}</span>
            </button>
        @endif
        @if($hasEliminateTwo)
            <button class="skill-action-btn ia-junior" id="skillEliminate" title="{{ __('Élimine 2 mauvaises réponses') }}">
                <span class="skill-icon">❌</span>
                <span>{{ __('Éliminer 2') }}</span>
            </button>
        @endif
        @if($hasAiSuggestion)
            <button class="skill-action-btn ia-junior" id="skillAiSuggest" title="{{ __('L\'IA suggère une réponse') }}">
                <span class="skill-icon">🤖</span>
                <span>{{ __('Suggestion IA') }}</span>
            </button>
        @endif
        @if($hasLockCorrect)
            <button class="skill-action-btn visionnaire" id="skillLockCorrect" title="{{ __('Seule la bonne réponse sélectionnable') }}">
                <span class="skill-icon">🔒</span>
                <span>{{ __('2 pts sécurisés') }}</span>
            </button>
        @endif
        @if($hasExtraAnswerTime)
            <button class="skill-action-btn historien" id="skillExtraTime" title="{{ __('Ajoute 2 secondes') }}">
                <span class="skill-icon">⏰</span>
                <span>{{ __('+2s') }}</span>
            </button>
        @endif
    </div>
    @endif

    @if($noBuzz && $hasHistorianSkill)
        {{-- Plume (Savoir sans temps): le joueur n'a pas buzzé mais peut répondre pour +1 pt max --}}
        <div class="buzz-status-banner historian-active" style="background: rgba(78, 205, 196, 0.15); border-color: rgba(78, 205, 196, 0.3);">
            🪶 {{ __('Savoir sans temps') }} - {{ __('Vous pouvez répondre') }} (+1 {{ __('point max') }})
        </div>
        <input type="hidden" id="featherSkillActive" value="1">
    @elseif($noBuzz)
        <div class="buzz-status-banner no-buzz">
            ⚠️ {{ __('Pas buzzé - Vous pouvez quand même répondre (0 point)') }}
        </div>
    @elseif($isBuzzWinner)
        <div class="buzz-status-banner buzzed">
            {{ __('Vous avez buzzé en') }} {{ number_format($buzzTime, 1) }}s 💚
        </div>
    @else
        <div class="buzz-status-banner opponent-buzz">
            ⏳ {{ __(':name a buzzé - En attente de sa réponse...', ['name' => $opponentName ?? __('Adversaire')]) }}
        </div>
    @endif
</div>

<div class="result-overlay" id="resultOverlay">
    <div class="result-text" id="resultText"></div>
    <div class="points-text" id="pointsText"></div>
    <div class="correct-answer-text" id="correctAnswerText"></div>
</div>

<div class="waiting-overlay" id="waitingOverlay">
    <div class="waiting-message">
        <h2>⏳ {{ __('En attente...') }}</h2>
        <p id="waitingText">{{ __(':name répond à la question...', ['name' => $opponentName ?? __('Adversaire')]) }}</p>
    </div>
</div>

<audio id="correctSound" preload="auto">
    <source src="{{ asset('audio/buzzers/correct/correct1.mp3') }}" type="audio/mpeg">
</audio>

<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('audio/buzzers/incorrect/incorrect1.mp3') }}" type="audio/mpeg">
</audio>

{{-- socket.io, DuoSocketClient, GameEffectsRuntime: loaded by layouts.game --}}

<script>
(function() {
    'use strict';
    
    const MATCH_ID   = window.MATCH_ID   || '';
    const ROOM_ID    = window.ROOM_ID    || '';
    const LOBBY_CODE = window.LOBBY_CODE || '';
    const JWT_TOKEN  = window.JWT_TOKEN  || '';
    const PLAYER_ID = {{ auth()->id() ?? 0 }};

    // Task #38 NOYAU STATS LIVE — initial score & efficiency hydration from
    // the GameplayRuntime cache (window.SB_LIVE_STATS), populated by socket
    // events (player_stats_updated / state / game_state). NO MORE URL params.
    // If the page lands before any socket event fired (cold reload), values
    // stay at 0; the next player_stats_updated will repaint via [data-stat].
    (function initScoresFromLiveStats() {
        var cache = window.SB_LIVE_STATS || {};
        var meId  = String({{ auth()->id() ?? 0 }});
        var meStats = cache[meId];
        if (!meStats) return;
        var scoreEl = document.getElementById('playerScoreValue');
        if (scoreEl) scoreEl.textContent = String(meStats.score || 0);
        var effEl   = document.getElementById('efficiencyValue');
        if (effEl)   effEl.textContent   = String(Math.round(meStats.efficiencyPercent || 0)) + '%';
    })();

    function getGameServerUrl() {
        return window.location.origin;
    }
    const GAME_SERVER_URL = getGameServerUrl();
    // Legacy snapshot consts — kept for backward-compat in skill checks
    const IS_BUZZ_WINNER = {{ $isBuzzWinner ? 'true' : 'false' }};
    const NO_BUZZ = {{ ($noBuzz ?? false) ? 'true' : 'false' }};
    const HAS_HISTORIAN_SKILL = {{ ($hasHistorianSkill ?? false) ? 'true' : 'false' }};

    // V3: quadri-état buzzeur — initialisé depuis PHP, mis à jour par socket (source de vérité finale)
    // 'first'   → buzzé position 1 (+2 pts potentiels)
    // 'second'  → buzzé position 2+ (+1 pt potentiel)
    // 'no_buzz' → round sans buzz (0 pt, peut répondre)
    // 'none'    → pas buzzé, adversaire a buzzé (waiting overlay, ne peut pas répondre)
    // Exposé globalement pour permettre l'inspection/debug et les helpers externes
    let PLAYER_BUZZ_POSITION = @json($playerBuzzPosition ?? 'none');
    window.PLAYER_BUZZ_POSITION = PLAYER_BUZZ_POSITION;

    function canAnswer() {
        return PLAYER_BUZZ_POSITION === 'first' || PLAYER_BUZZ_POSITION === 'second' || PLAYER_BUZZ_POSITION === 'no_buzz';
    }

    /**
     * Recalcule la position buzzeur depuis le buzzQueue serveur.
     * Retourne null si le joueur n'est pas dans la queue (ne pas écraser 'no_buzz').
     */
    function _deriveBuzzPositionFromQueue(buzzQueue) {
        if (!Array.isArray(buzzQueue) || buzzQueue.length === 0) return null;
        var myId = String(PLAYER_ID);
        var idx = buzzQueue.findIndex(function(b) {
            var bid = String(b.playerId || '').replace('player:', '');
            return bid === myId;
        });
        if (idx === 0) return 'first';
        if (idx >= 1) return 'second';
        return null; // pas dans la queue — ne pas override
    }

    /**
     * Applique un nouveau quadri-état buzzeur depuis le serveur.
     * Met à jour PLAYER_BUZZ_POSITION (local + window), les boutons, l'overlay et le timer.
     */
    function applyBuzzPosition(newPosition) {
        if (!newPosition || PLAYER_BUZZ_POSITION === newPosition) return;
        console.log('[DuoAnswer] PLAYER_BUZZ_POSITION: ' + PLAYER_BUZZ_POSITION + ' → ' + newPosition);
        PLAYER_BUZZ_POSITION = newPosition;
        window.PLAYER_BUZZ_POSITION = newPosition; // maintenir la copie globale synchronisée
        var isAnswerable = canAnswer();
        // Mettre à jour les boutons
        answerButtons.forEach(function(btn) {
            if (isAnswerable && !answered) {
                btn.classList.remove('waiting');
                btn.disabled = false;
            } else if (!isAnswerable && !answered) {
                btn.classList.add('waiting');
                btn.disabled = true;
            }
        });
        // Mettre à jour l'overlay d'attente
        if (!answered) {
            if (isAnswerable) {
                if (waitingOverlay) waitingOverlay.style.display = 'none';
                if (!timerInterval) startTimer();
            } else {
                if (waitingOverlay) waitingOverlay.style.display = 'flex';
            }
        }
        updatePotentialPointsDisplay(calculatePotentialPoints(timeLeft));
    }
    
    // Sprinteur passive skill: extra_reflection adds +3 seconds
    @php
        $hasExtraReflection = false;
        if (isset($skills) && is_array($skills)) {
            foreach ($skills as $skill) {
                if (($skill['id'] ?? '') === 'extra_reflection') {
                    $hasExtraReflection = true;
                    break;
                }
            }
        }
    @endphp
    const HAS_EXTRA_REFLECTION = {{ $hasExtraReflection ? 'true' : 'false' }};
    let ANSWER_TIME = HAS_EXTRA_REFLECTION ? 13 : 10;
    let timeLeft = ANSWER_TIME;
    let timerInterval = null;
    let answered = false;
    let selectedIndex = null;
    let isRedirecting = false;
    let historianSkillUsed = false;
    let phaseEndsAtMs = null;  // FIX: track server-side phase end time
    
    const CHOICES = @json($choices);
    const HAS_ILLUMINATE = {{ ($hasIlluminateNumbers ?? false) ? 'true' : 'false' }};
    const HAS_ACIDIFY = {{ ($hasAcidifyError ?? false) ? 'true' : 'false' }};
    const HAS_ELIMINATE = {{ ($hasEliminateTwo ?? false) ? 'true' : 'false' }};
    const HAS_AI_SUGGEST = {{ ($hasAiSuggestion ?? false) ? 'true' : 'false' }};
    const HAS_LOCK_CORRECT = {{ ($hasLockCorrect ?? false) ? 'true' : 'false' }};
    const HAS_EXTRA_ANSWER_TIME = {{ ($hasExtraAnswerTime ?? false) ? 'true' : 'false' }};
    
    let skillsUsed = {
        illuminate: false,
        acidify: false,
        eliminate: false,
        aiSuggest: false,
        lockCorrect: false,
        extraTime: false
    };
    
    // Skill Challenger: Shuffle Answers
    const SHUFFLE_ACTIVE = {{ $shuffleAnswersActive ? 'true' : 'false' }};
    let shuffleInterval = null;
    
    function shuffleAnswers() {
        if (answered) return;
        
        const container = document.getElementById('answersContainer');
        const buttons = Array.from(container.querySelectorAll('.answer-button'));
        const indicator = container.querySelector('.shuffle-indicator');
        
        // Fisher-Yates shuffle pour l'ordre des boutons
        for (let i = buttons.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [buttons[i], buttons[j]] = [buttons[j], buttons[i]];
        }
        
        // Ajouter l'animation de shuffle
        buttons.forEach(btn => btn.classList.add('shuffling'));
        
        // Réorganiser le DOM
        buttons.forEach(btn => container.appendChild(btn));
        
        // Remettre l'indicateur en haut si présent
        if (indicator) {
            container.insertBefore(indicator, container.firstChild);
        }
        
        // Retirer l'animation après 0.3s
        setTimeout(() => {
            buttons.forEach(btn => btn.classList.remove('shuffling'));
        }, 300);
    }
    
    function startShuffleInterval() {
        if (!SHUFFLE_ACTIVE) return;
        shuffleInterval = setInterval(shuffleAnswers, 1500);
    }
    
    function stopShuffleInterval() {
        if (shuffleInterval) {
            clearInterval(shuffleInterval);
            shuffleInterval = null;
        }
    }
    
    const timerBar = document.getElementById('timerBar');
    const timerSeconds = document.getElementById('timerSeconds');
    const potentialPoints = document.getElementById('potentialPoints');
    const connectionStatus = document.getElementById('connectionStatus');
    const resultOverlay = document.getElementById('resultOverlay');
    const resultText = document.getElementById('resultText');
    const pointsText = document.getElementById('pointsText');
    const correctAnswerText = document.getElementById('correctAnswerText');
    const waitingOverlay = document.getElementById('waitingOverlay');
    const answersContainer = document.getElementById('answersContainer');
    const correctSound = document.getElementById('correctSound');
    const incorrectSound = document.getElementById('incorrectSound');
    let answerButtons = document.querySelectorAll('.answer-button');

    // ── Bridge UI: warm restore — immediately render cached scores before socket arrives ──
    (function() {
        var rps = window.GR_RESTORED_PLAYER_SCORE;
        var ros = window.GR_RESTORED_OPPONENT_SCORE;
        var playerScoreEl   = document.getElementById('playerScore');
        var opponentScoreEl = document.getElementById('opponentScore');
        if (rps !== undefined && playerScoreEl)   { playerScoreEl.textContent   = String(rps); }
        if (ros !== undefined && opponentScoreEl) { opponentScoreEl.textContent = String(ros); }
    })();
    
    function _applyIlluminateEffect() {
        // Highlight every digit sequence inside the question text, not answer options
        var questionBox = document.querySelector('.question-text-box');
        if (!questionBox) return;
        var html = questionBox.textContent || '';
        if (!/\d/.test(html)) return;
        // Wrap digits in the raw innerHTML (preserve existing content)
        questionBox.innerHTML = questionBox.innerHTML.replace(
            /(\d+)/g,
            '<span class="illuminated-number">$1</span>'
        );
        console.log('[Skills] Illuminate numbers applied to question text');
    }

    function _applyAcidifyEffect(wrongIndices) {
        if (Array.isArray(wrongIndices) && wrongIndices.length > 0) {
            wrongIndices.forEach(function(idx) {
                if (answerButtons[idx]) answerButtons[idx].classList.add('acidified');
            });
        } else {
            // Fallback: pick one random wrong answer client-side
            const available = [];
            answerButtons.forEach(function(button, idx) {
                if (!button.classList.contains('correct')) {
                    available.push(idx);
                }
            });
            if (available.length > 0) {
                const r = available[Math.floor(Math.random() * available.length)];
                answerButtons[r].classList.add('acidified');
            }
        }
        console.log('[Skills] Acidify error visual applied', wrongIndices);
    }

    function _applyAiSuggestionEffect(suggestedIndex) {
        if (suggestedIndex !== undefined && suggestedIndex !== null && answerButtons[suggestedIndex]) {
            answerButtons[suggestedIndex].classList.add('ai-suggested');
        } else {
            // Fallback: pick random available answer
            const available = [];
            answerButtons.forEach(function(button, idx) {
                if (!button.classList.contains('eliminated') && !button.classList.contains('acidified')) {
                    available.push(idx);
                }
            });
            if (available.length > 0) {
                const r = available[Math.floor(Math.random() * available.length)];
                answerButtons[r].classList.add('ai-suggested');
            }
        }
        console.log('[Skills] AI suggestion visual applied', suggestedIndex);
    }

    function _onSkillEffect(data) {
        const skillId = data && data.skillId;
        if (!skillId) return;

        if (skillId === 'illuminate_numbers') {
            const btn = document.getElementById('skillIlluminate');
            if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
            _applyIlluminateEffect();
        } else if (skillId === 'acidify_error') {
            const btn = document.getElementById('skillAcidify');
            if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
            _applyAcidifyEffect(data.wrongIndices);
        } else if (skillId === 'ai_suggestion') {
            const btn = document.getElementById('skillAiSuggest');
            if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
            _applyAiSuggestionEffect(data.suggestedIndex);
        }
    }

    function _onSkillFailed(data) {
        const skillId = data && data.skillId;
        if (!skillId) return;
        // Restore the button and clear the "used" guard so the player can retry
        if (skillId === 'illuminate_numbers') {
            skillsUsed.illuminate = false;
            const btn = document.getElementById('skillIlluminate');
            if (btn) { btn.classList.remove('pending'); }
        } else if (skillId === 'acidify_error') {
            skillsUsed.acidify = false;
            const btn = document.getElementById('skillAcidify');
            if (btn) { btn.classList.remove('pending'); }
        } else if (skillId === 'ai_suggestion') {
            skillsUsed.aiSuggest = false;
            const btn = document.getElementById('skillAiSuggest');
            if (btn) { btn.classList.remove('pending'); }
        }
        console.log('[Skills] Skill activation failed:', skillId, data.reason || '');
    }

    function activateIlluminateSkill() {
        if (skillsUsed.illuminate || answered) return;
        skillsUsed.illuminate = true;
        const btn = document.getElementById('skillIlluminate');
        if (btn) btn.classList.add('pending');
        if (window.DuoSocketClient && window.DuoSocketClient.isConnected()) {
            window.DuoSocketClient.useSkill('illuminate_numbers');
        } else {
            // No server connection: apply effect immediately client-side
            if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
            _applyIlluminateEffect();
        }
        console.log('[Skills] Illuminate numbers requested');
    }
    
    function activateAcidifySkill() {
        if (skillsUsed.acidify || answered) return;
        skillsUsed.acidify = true;
        const btn = document.getElementById('skillAcidify');
        if (btn) btn.classList.add('pending');
        if (window.DuoSocketClient && window.DuoSocketClient.isConnected()) {
            window.DuoSocketClient.useSkill('acidify_error');
        } else {
            if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
            _applyAcidifyEffect(null);
        }
        console.log('[Skills] Acidify error requested');
    }
    
    function activateEliminateSkill() {
        if (skillsUsed.eliminate || answered) return;
        skillsUsed.eliminate = true;
        
        const btn = document.getElementById('skillEliminate');
        if (btn) btn.classList.add('used');
        
        const wrongAnswers = [];
        answerButtons.forEach(function(button, idx) {
            if (!button.classList.contains('ai-suggested')) {
                wrongAnswers.push(idx);
            }
        });
        
        for (let i = wrongAnswers.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [wrongAnswers[i], wrongAnswers[j]] = [wrongAnswers[j], wrongAnswers[i]];
        }
        
        let eliminated = 0;
        for (let i = 0; i < wrongAnswers.length && eliminated < 2; i++) {
            const idx = wrongAnswers[i];
            if (answerButtons.length - eliminated > 2) {
                answerButtons[idx].classList.add('eliminated');
                eliminated++;
            }
        }
        
        console.log('[Skills] Eliminate 2 activated, removed', eliminated, 'answers');
    }
    
    function activateAiSuggestSkill() {
        if (skillsUsed.aiSuggest || answered) return;
        skillsUsed.aiSuggest = true;
        const btn = document.getElementById('skillAiSuggest');
        if (btn) btn.classList.add('pending');
        if (window.DuoSocketClient && window.DuoSocketClient.isConnected()) {
            window.DuoSocketClient.useSkill('ai_suggestion');
        } else {
            if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
            _applyAiSuggestionEffect(null);
        }
        console.log('[Skills] AI suggestion requested');
    }
    
    function activateLockCorrectSkill() {
        if (skillsUsed.lockCorrect || answered) return;
        
        const currentPoints = calculatePotentialPoints(timeLeft);
        if (currentPoints !== 2) {
            alert('{{ __("Ce skill ne fonctionne que si vous êtes sur 2 points !") }}');
            return;
        }
        
        skillsUsed.lockCorrect = true;
        
        const btn = document.getElementById('skillLockCorrect');
        if (btn) btn.classList.add('used');
        
        answerButtons.forEach(function(button) {
            button.classList.add('locked-correct');
        });
        
        console.log('[Skills] Lock correct activated - 2 points secured');
    }
    
    function activateExtraTimeSkill() {
        if (skillsUsed.extraTime || answered) return;
        skillsUsed.extraTime = true;
        
        const btn = document.getElementById('skillExtraTime');
        if (btn) btn.classList.add('used');
        
        timeLeft += 2;
        ANSWER_TIME += 2;
        
        timerSeconds.textContent = timeLeft + 's';
        const percentage = (timeLeft / ANSWER_TIME) * 100;
        timerBar.style.width = percentage + '%';
        
        console.log('[Skills] Extra time activated, +2s');
    }
    
    function initSkillButtons() {
        const illuminateBtn = document.getElementById('skillIlluminate');
        if (illuminateBtn) {
            illuminateBtn.addEventListener('click', activateIlluminateSkill);
        }
        
        const acidifyBtn = document.getElementById('skillAcidify');
        if (acidifyBtn) {
            acidifyBtn.addEventListener('click', activateAcidifySkill);
        }
        
        const eliminateBtn = document.getElementById('skillEliminate');
        if (eliminateBtn) {
            eliminateBtn.addEventListener('click', activateEliminateSkill);
        }
        
        const aiSuggestBtn = document.getElementById('skillAiSuggest');
        if (aiSuggestBtn) {
            aiSuggestBtn.addEventListener('click', activateAiSuggestSkill);
        }
        
        const lockCorrectBtn = document.getElementById('skillLockCorrect');
        if (lockCorrectBtn) {
            lockCorrectBtn.addEventListener('click', activateLockCorrectSkill);
        }
        
        const extraTimeBtn = document.getElementById('skillExtraTime');
        if (extraTimeBtn) {
            extraTimeBtn.addEventListener('click', activateExtraTimeSkill);
        }
    }
    
    function calculatePotentialPoints(remainingTime) {
        if (historianSkillUsed) return 1;
        // V3: scoring is based on buzz order (PLAYER_BUZZ_POSITION), not remaining time
        if (PLAYER_BUZZ_POSITION === 'first') return 2;
        if (PLAYER_BUZZ_POSITION === 'second') return 1;
        return 0; // 'no_buzz' or 'none' → 0 pts
    }
    
    function updatePotentialPointsDisplay(points) {
        potentialPoints.textContent = '+' + points;
        potentialPoints.className = 'potential-points points-' + points;
    }
    
    function updateConnectionStatus(status) {
        connectionStatus.className = 'connection-status ' + status;
        switch(status) {
            case 'connected':
                connectionStatus.textContent = '{{ __("Connecté") }}';
                break;
            case 'disconnected':
                connectionStatus.textContent = '{{ __("Déconnecté") }}';
                break;
            case 'connecting':
                connectionStatus.textContent = '{{ __("Connexion...") }}';
                break;
        }
    }
    
    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        
        // Sync starting timeLeft with server's remaining ANSWER_SELECTION time, but
        // absorb up to 1.5s of network/page-load latency: if the server reports
        // remaining ≥ (ANSWER_TIME − 1.5)s, snap the visible countdown back up to
        // the full ANSWER_TIME so the player sees a clean fresh "10s" on arrival.
        // The setInterval below still re-syncs every tick, so the actual timeout
        // remains driven by the server's authoritative phaseEndsAtMs.
        if (phaseEndsAtMs) {
            const remaining = Math.max(0, phaseEndsAtMs - Date.now());
            const ceilLeft = Math.ceil(remaining / 1000);
            if (remaining >= (ANSWER_TIME - 1.5) * 1000) {
                timeLeft = ANSWER_TIME;
            } else {
                timeLeft = ceilLeft;
            }
            if (timeLeft <= 0) timeLeft = 1; // at least 1 tick before auto-timeout
        }
        
        // Démarrer le shuffle des réponses si actif
        startShuffleInterval();
        
        updatePotentialPointsDisplay(calculatePotentialPoints(timeLeft));
        
        timerInterval = setInterval(function() {
            // P0.3 — Recompute timeLeft from Node-authoritative phaseEndsAtMs at every
            // tick (mirrors the duo_question canonical pattern). This survives
            // backgrounded tabs / throttled timers and keeps the two players' chronos
            // in sync to within one tick. Fallback to local decrement only if the
            // server hasn't published a deadline yet (shouldn't happen post-buzz).
            if (phaseEndsAtMs) {
                const remainingMs = Math.max(0, phaseEndsAtMs - Date.now());
                timeLeft = Math.ceil(remainingMs / 1000);
            } else {
                timeLeft--;
            }

            const percentage = Math.max(0, (timeLeft / ANSWER_TIME) * 100);
            timerBar.style.width = percentage + '%';
            timerSeconds.textContent = Math.max(0, timeLeft) + 's';
            
            if (timeLeft <= 5) {
                timerBar.classList.add('warning');
                timerSeconds.classList.add('warning');
            }
            
            updatePotentialPointsDisplay(calculatePotentialPoints(timeLeft));
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                // UX safety net: auto-submit a timeout. The Node server is still
                // the sole authority on the phase transition (handleAnswerTimeout) —
                // a late client submission is harmless because the orchestrator
                // ignores submissions outside the answer window.
                if (!answered && canAnswer()) {
                    handleTimeout();
                }
            }
        }, 250);
    }
    
    function handleTimeout() {
        if (answered) return;
        answered = true;
        
        // Arrêter le shuffle des réponses
        stopShuffleInterval();
        
        answerButtons.forEach(function(btn) {
            btn.classList.add('disabled');
        });
        
        DuoSocketClient.answer(-1);
    }
    
    function selectAnswer(index) {
        // V3: canAnswer() vérifie PLAYER_BUZZ_POSITION (first/second/no_buzz) — 'none' bloqué
        // Le skill Historien (historianSkillUsed) peut débloquer 'none' explicitement
        if (answered || (!canAnswer() && !historianSkillUsed)) return;
        
        answered = true;
        selectedIndex = index;
        
        // Arrêter le shuffle des réponses
        stopShuffleInterval();
        
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        answerButtons.forEach(function(btn) {
            btn.classList.remove('selected');
            btn.classList.add('disabled');
        });
        
        answerButtons[index].classList.add('selected');
        answerButtons[index].classList.remove('disabled');
        
        // V3: calculatePotentialPoints lit PLAYER_BUZZ_POSITION — correct pour tous les cas
        let pointsToSend = historianSkillUsed ? 1 : calculatePotentialPoints(timeLeft);
        
        DuoSocketClient.answer(index, { 
            potentialPoints: pointsToSend,
            historianSkillUsed: historianSkillUsed
        });

        // Show waiting overlay until answer_revealed or RESULT phase
        if (waitingOverlay) {
            waitingOverlay.style.display = 'flex';
        }
    }
    
    function activateHistorianSkill() {
        // Historien disponible uniquement pour 'none' et 'no_buzz' (pas encore actif)
        // 'first' et 'second' buzzeurs ont déjà accès aux boutons de réponse
        if (historianSkillUsed || answered || PLAYER_BUZZ_POSITION === 'first' || PLAYER_BUZZ_POSITION === 'second') return;
        
        historianSkillUsed = true;
        
        const historianSection = document.getElementById('historianSkillSection');
        const waitingBanner = document.getElementById('waitingBanner');
        const historianActiveBanner = document.getElementById('historianActiveBanner');
        
        if (historianSection) historianSection.style.display = 'none';
        if (waitingBanner) waitingBanner.style.display = 'none';
        if (historianActiveBanner) historianActiveBanner.style.display = 'block';
        
        answerButtons.forEach(function(btn) {
            btn.classList.remove('waiting');
            btn.disabled = false;
        });
        
        updatePotentialPointsDisplay(1);
        
        if (!timerInterval) {
            startTimer();
        }
        
        console.log('[DuoAnswer] Historian skill activated - can answer for 1 point');
    }
    
    function showResult(isCorrect, correctIndex, pointsEarned) {
        // Always: play sound
        if (isCorrect && correctSound) {
            correctSound.play().catch(function() {});
        } else if (!isCorrect && incorrectSound) {
            incorrectSound.play().catch(function() {});
        }

        // Always: highlight answer buttons
        answerButtons.forEach(function(btn, idx) {
            btn.classList.remove('selected');
            const indicator = document.getElementById('indicator' + idx);
            if (idx === correctIndex) {
                btn.classList.add('correct');
                if (indicator) indicator.textContent = '✓';
            } else if (idx === selectedIndex && !isCorrect) {
                btn.classList.add('incorrect');
                if (indicator) indicator.textContent = '✗';
            }
        });

        // Popup overlay: ONLY for incorrect answers
        if (!isCorrect) {
            resultOverlay.className = 'result-overlay incorrect';
            resultText.textContent = '{{ __("Mauvaise réponse !") }}';
            if (historianSkillUsed) {
                pointsText.textContent = '{{ __("0 point") }}';
            } else {
                pointsText.textContent = '{{ __("-2 points") }}';
            }
            if (correctIndex !== undefined && correctIndex >= 0) {
                const choices = @json($choices);
                if (choices[correctIndex]) {
                    correctAnswerText.textContent = '{{ __("La bonne réponse était :") }} ' + choices[correctIndex];
                }
            }
            resultOverlay.style.display = 'block';
        }
    }
    
    answerButtons.forEach(function(btn, index) {
        btn.addEventListener('click', function() {
            selectAnswer(index);
        });
    });
    
    const historianSkillBtn = document.getElementById('historianSkillBtn');
    if (historianSkillBtn) {
        historianSkillBtn.addEventListener('click', activateHistorianSkill);
    }
    
    // ── Named socket handlers (closures over IIFE vars) ──────────────────────
    // connect() + joinRoom() handled by GameplayRuntime — view-specific only
    function _onAnswerConnect() {
        console.log('[DuoAnswer] Socket connected (room join handled by GameplayRuntime)');
    }
    function _onAnswerGameState(data) {
        if (!data) return;
        var phase = data.phase || '';
        // V3: accept QUESTION_ACTIVE (nominal answer phase) in addition to legacy phases
        var answerPhases = ['QUESTION_ACTIVE', 'ANSWER_SELECTION', 'BUZZ_WINNER_ANSWERING', 'ANSWER_COLLECTION'];
        if (!answerPhases.includes(phase)) return;

        // Verrou source de vérité: mettre à jour PLAYER_BUZZ_POSITION depuis buzzQueue serveur
        // Fallback: si buzzQueue absent, utiliser playerBuzzOrder si disponible
        if (data.buzzQueue) {
            var derived = _deriveBuzzPositionFromQueue(data.buzzQueue);
            if (derived) applyBuzzPosition(derived);
        } else if (typeof data.playerBuzzOrder === 'number' && data.playerBuzzOrder > 0) {
            applyBuzzPosition(data.playerBuzzOrder === 1 ? 'first' : 'second');
        }

        if (!data.phaseEndsAtMs) return;
        phaseEndsAtMs = data.phaseEndsAtMs;
        var remaining = Math.max(0, phaseEndsAtMs - Date.now());
        var serverLeft = Math.ceil(remaining / 1000);
        if (Math.abs(serverLeft - timeLeft) > 1) {
            console.log('[DuoAnswer] game_state timer resync: local=' + timeLeft + 's server=' + serverLeft + 's');
            timeLeft = serverLeft;
        }
    }

    /**
     * state — fires on (re)connect with full server state.
     * If PHP rendered an empty choices list (cold reconnect), rebuild the UI from
     * state.currentQuestion so the answer page is usable.
     */
    function _onAnswerState(payload) {
        if (!payload) return;
        var data = payload.state || payload;

        // ── Reconnect choice hydration ───────────────────────────────────────
        var container = document.getElementById('answersContainer');
        if (container) {
            var existingBtns = container.querySelectorAll('.answer-button');
            var choicesEmpty = existingBtns.length === 0 ||
                (existingBtns.length === 1 && existingBtns[0].querySelector('.answer-text') &&
                 existingBtns[0].querySelector('.answer-text').textContent.trim() === '');

            var cq = data.currentQuestion || (data.state && data.state.currentQuestion);
            if (choicesEmpty && cq) {
                var serverChoices = cq.choices || cq.answers || [];
                if (serverChoices.length) {
                    console.log('[DuoAnswer] Hydrating choices from state:', serverChoices);
                    // Rebuild choice buttons
                    container.innerHTML = '';
                    serverChoices.forEach(function(choice, idx) {
                        var btn = document.createElement('button');
                        btn.className = 'answer-button' + (canAnswer() ? '' : ' waiting');
                        btn.dataset.index = idx;
                        btn.dataset.text  = choice;
                        if (!canAnswer()) btn.disabled = true;
                        btn.innerHTML =
                            '<span class="answer-number">' + (idx + 1) + '</span>' +
                            '<span class="answer-text">' + _escapeHtml(choice) + '</span>' +
                            '<span class="answer-indicator" id="indicator' + idx + '"></span>';
                        container.appendChild(btn);
                    });
                    // Refresh the module-level answerButtons reference
                    answerButtons = container.querySelectorAll('.answer-button');
                    // Re-attach click listeners
                    _attachAnswerBtnListeners();
                }
                // Rebuild question text if empty
                var qtBox = document.querySelector('.question-text-box');
                if (qtBox && !qtBox.textContent.trim() && cq.text) {
                    qtBox.textContent = cq.text;
                }
            }
        }

        // ── Verrou source de vérité: PLAYER_BUZZ_POSITION depuis buzzQueue serveur ──
        // Fallback: si buzzQueue absent, utiliser playerBuzzOrder si disponible
        var bq = data.buzzQueue || (data.state && data.state.buzzQueue);
        if (bq) {
            var derivedBp = _deriveBuzzPositionFromQueue(bq);
            if (derivedBp) applyBuzzPosition(derivedBp);
        } else if (typeof data.playerBuzzOrder === 'number' && data.playerBuzzOrder > 0) {
            applyBuzzPosition(data.playerBuzzOrder === 1 ? 'first' : 'second');
        }

        // ── Timer resync ─────────────────────────────────────────────────────
        if (data.phaseEndsAtMs) {
            var phase = data.phase || '';
            // V3: QUESTION_ACTIVE is the nominal answer phase; include it with legacy phases
            var timerPhases = ['QUESTION_ACTIVE', 'ANSWER_SELECTION', 'BUZZ_WINNER_ANSWERING', 'ANSWER_COLLECTION'];
            if (timerPhases.includes(phase)) {
                phaseEndsAtMs = data.phaseEndsAtMs;
                var rem = Math.max(0, phaseEndsAtMs - Date.now());
                var srvLeft = Math.ceil(rem / 1000);
                if (Math.abs(srvLeft - timeLeft) > 1) {
                    console.log('[DuoAnswer] state: timer resync local=' + timeLeft + ' server=' + srvLeft);
                    timeLeft = srvLeft;
                }
            }
        }
    }

    function _escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // P0.3 — "Le saviez-vous?" toast. Mirrors duo_question.blade.php's helper but
    // uses textContent for the trivia body (XSS-safe) since the funFact comes
    // straight from question content. Auto-dismisses after ~6 s.
    function showDidYouKnow(text) {
        if (!text) return;
        var div = document.createElement('div');
        div.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);max-width:600px;width:90%;padding:14px 20px;border-radius:12px;background:linear-gradient(135deg,#8E44AD,#6C3483);color:#fff;font-size:14px;z-index:9998;box-shadow:0 4px 16px rgba(0,0,0,0.4);';
        var label = document.createElement('strong');
        label.textContent = '{{ __("Le saviez-vous?") }} ';
        var body = document.createTextNode(String(text));
        div.appendChild(label);
        div.appendChild(body);
        document.body.appendChild(div);
        setTimeout(function() { div.style.transition = 'opacity 0.5s'; div.style.opacity = '0'; }, 5500);
        setTimeout(function() { if (div.parentNode) div.remove(); }, 6100);
    }

    function _attachAnswerBtnListeners() {
        var container = document.getElementById('answersContainer');
        if (!container) return;
        container.querySelectorAll('.answer-button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (btn.disabled || answered) return;
                var idx = parseInt(btn.dataset.index, 10);
                selectAnswer(idx);
            });
        });
    }
    function _onAnswerDisconnect(reason) {
        updateConnectionStatus('disconnected');
    }
    function _onAnswerError(error) {
        console.error('[DuoAnswer] Socket error:', error);
    }
    function _onAnswerRevealed(data) {
        if (isRedirecting) return;
        waitingOverlay.style.display = 'none';
        const isCorrect    = data.isCorrect || false;
        const correctIndex = data.correctIndex !== undefined ? data.correctIndex : data.correctAnswer;
        const pointsEarned = data.points || data.pointsEarned || 0;
        showResult(isCorrect, correctIndex, pointsEarned);

        // Visual feedback only — navigation is driven exclusively by phase_changed events
        // (QUESTION_ACTIVE for next question, ROUND_SCOREBOARD for scoreboard, MATCH_END for final).
        // Exception: matchEnded flag for immediate end-of-match redirect.
        if (data.matchEnded) {
            const delay = isCorrect ? 1200 : 3000;
            setTimeout(function() {
                if (isRedirecting) return;
                isRedirecting = true;
                (window.duoNavigate || function(u) { window.location.href = u; })(
                    (window.MATCH_RESULT_URL || '/game/duo/match-result') + '?match_id=' + encodeURIComponent(MATCH_ID)
                );
            }, delay);
        }
    }
    function _onAnswerRoundEnded(data) {
        // round_ended fires at end of a full round → V3 round scoreboard
        if (isRedirecting) return;
        setTimeout(function() {
            if (isRedirecting) return;
            isRedirecting = true;
            (window.duoNavigate || function(u) { window.location.href = u; })(
                (window.ROUND_SCOREBOARD_URL || window.RESULT_URL || '/game/duo/round-scoreboard') + '?match_id=' + encodeURIComponent(MATCH_ID)
            );
        }, 2000);
    }
    function _onAnswerMatchEnded(data) {
        if (isRedirecting) return;
        isRedirecting = true;
        setTimeout(function() {
            (window.duoNavigate || function(u) { window.location.href = u; })(
                (window.MATCH_RESULT_URL || '/game/duo/match-result') + '?match_id=' + encodeURIComponent(MATCH_ID)
            );
        }, 2000);
    }
    function _onAnswerPhaseChanged(data) {
        if (isRedirecting || !data || !data.phase) return;
        var phase = data.phase;
        var _nav  = window.duoNavigate || function(u) { window.location.href = u; };

        // F4: mettre à jour GR_SAVE_STATE_EXTRA.phase dynamiquement
        if (window.GR_SAVE_STATE_EXTRA) {
            window.GR_SAVE_STATE_EXTRA.phase = phase;
        }

        if (phase === 'ANSWER_COLLECTION') {
            // Grace period: all players have buzzed, server collecting answers.
            // Ensure waiting overlay is visible if this player already submitted.
            if (answered && waitingOverlay) {
                waitingOverlay.style.display = 'flex';
            }
            return;
        }

        if (phase === 'REVEAL') {
            // REVEAL ≠ scoreboard — stay on page so answer_revealed can show visual feedback.
            return;
        }

        if (phase === 'RESULT') {
            // V3: per-question result — navigate to result page after visual feedback.
            // F3: if an incorrect-answer overlay is visible, give 2500ms to read it;
            //     otherwise 600ms is enough for a correct answer.
            var hasIncorrectOverlay = resultOverlay &&
                resultOverlay.style.display !== 'none' &&
                resultOverlay.classList.contains('incorrect');
            var resultDelay = hasIncorrectOverlay ? 2500 : 600;
            setTimeout(function() {
                if (isRedirecting) return;
                isRedirecting = true;
                _nav((window.RESULT_URL || '/game/duo/result') + '?match_id=' + encodeURIComponent(MATCH_ID));
            }, resultDelay);
            return;
        }

        if (phase === 'QUESTION_ACTIVE' || phase === 'SYNC') {
            // Reconnect edge case: server already moved past RESULT while we were on answer page.
            setTimeout(function() {
                if (isRedirecting) return;
                isRedirecting = true;
                _nav((window.QUESTION_URL || '/game/duo/question') + '?match_id=' + encodeURIComponent(MATCH_ID));
            }, 800);
            return;
        }

        if (phase === 'ROUND_SCOREBOARD') {
            setTimeout(function() {
                if (isRedirecting) return;
                isRedirecting = true;
                _nav((window.ROUND_SCOREBOARD_URL || '/game/duo/round-scoreboard') + '?match_id=' + encodeURIComponent(MATCH_ID));
            }, 2500);
            return;
        }

        if (phase === 'MATCH_END') {
            setTimeout(function() {
                if (isRedirecting) return;
                isRedirecting = true;
                _nav((window.MATCH_RESULT_URL || '/game/duo/match-result') + '?match_id=' + encodeURIComponent(MATCH_ID));
            }, 1000);
        }
    }
    // Task #38 NOYAU STATS LIVE: efficiency is server-authoritative.
    // The DOM nodes are now wired via [data-stat="..."][data-player="self"]
    // and updated by GameplayRuntime listeners. These two thin wrappers stay
    // for backward-compat with code paths that still call them — they just
    // ask GameplayRuntime to repaint from the cache.
    function _updateEfficiencyDisplay(/* score */) {
        if (typeof window.GRRepaintStats === 'function') window.GRRepaintStats();
    }
    function _onAnswerScoreUpdate(/* data */) {
        if (typeof window.GRRepaintStats === 'function') window.GRRepaintStats();
    }
    function _initAnswerEffects() {
        GameEffectsRuntime.registerEffect('shuffle_answers', {
            onStart: function() {
                stopShuffleInterval();
                var container = document.getElementById('answersContainer');
                if (container) container.classList.add('shuffle-active');
                var ind = container ? container.querySelector('.shuffle-indicator') : null;
                if (!ind && container) {
                    ind = document.createElement('div');
                    ind.className = 'shuffle-indicator';
                    ind.textContent = '🔀 {{ __("Réponses mélangées!") }}';
                    container.insertBefore(ind, container.firstChild);
                }
                if (ind) ind.style.display = '';
                shuffleAnswers();
                shuffleInterval = setInterval(shuffleAnswers, 1500);
            },
            onStop: function() {
                stopShuffleInterval();
                var container = document.getElementById('answersContainer');
                if (container) container.classList.remove('shuffle-active');
                var ind = container ? container.querySelector('.shuffle-indicator') : null;
                if (ind) ind.style.display = 'none';
            }
        });
        if (GAME_SERVER_URL) {
            updateConnectionStatus('connecting');
            GameEffectsRuntime.init(DuoSocketClient, PLAYER_ID);
        }
    }
    // Expose for the scripts section — .on() bindings done there after DuoSocketClient.js loads
    window._duoAnswerHandlers = {
        connect:         _onAnswerConnect,
        disconnect:      _onAnswerDisconnect,
        error:           _onAnswerError,
        state:           _onAnswerState,
        game_state:      _onAnswerGameState,
        answer_revealed: _onAnswerRevealed,
        round_ended:     _onAnswerRoundEnded,
        match_ended:     _onAnswerMatchEnded,
        phase_changed:   _onAnswerPhaseChanged,
        score_update:    _onAnswerScoreUpdate,
        skill_effect:    _onSkillEffect,
        skill_failed:    _onSkillFailed,
        initEffects:     _initAnswerEffects
    };

    initSkillButtons();
    
    // V3: canAnswer() dérive de PLAYER_BUZZ_POSITION (first/second/no_buzz = actif, none = attente)
    if (canAnswer()) {
        startTimer();
    } else {
        if (waitingOverlay) waitingOverlay.style.display = 'flex';
    }

    window.addEventListener('beforeunload', function() {
        GameEffectsRuntime.dispose();
    });
})();
</script>

<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getFirestore, doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, getDoc, setDoc, serverTimestamp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "{{ config('services.firebase.api_key', 'AIzaSyC2D2lVq3D_lRFM3kvbLmLUFJpv8Dh35qU') }}",
    authDomain: "{{ config('services.firebase.project_id', 'strategybuzzer') }}.firebaseapp.com",
    projectId: "{{ config('services.firebase.project_id', 'strategybuzzer') }}",
    storageBucket: "{{ config('services.firebase.project_id', 'strategybuzzer') }}.appspot.com",
    messagingSenderId: "{{ config('services.firebase.messaging_sender_id', '681234567890') }}",
    appId: "{{ config('services.firebase.app_id', '1:681234567890:web:abc123') }}"
};

const app = initializeApp(firebaseConfig, 'voice-chat-app');
const db = getFirestore(app);
window.voiceChatFirebase = { doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, getDoc, setDoc, serverTimestamp };
(function(_db, fn) {
    function wrapDoc(ref) {
        return { get: () => fn.getDoc(ref), set: (d, o) => fn.setDoc(ref, d, o||{}), delete: () => fn.deleteDoc(ref), onSnapshot: cb => fn.onSnapshot(ref, cb), collection: n => wrapCol(fn.collection(ref, n)) };
    }
    function wrapCol(ref) {
        return { doc: id => wrapDoc(fn.doc(ref, id)), add: d => fn.addDoc(ref, d), where: (f,op,v) => wrapQ(fn.query(ref, fn.where(f,op,v))), onSnapshot: cb => fn.onSnapshot(ref, cb), get: () => fn.getDocs(ref) };
    }
    function wrapQ(ref) { return { get: () => fn.getDocs(ref), onSnapshot: cb => fn.onSnapshot(ref, cb) }; }
    window.voiceChatDb = { collection: n => wrapCol(fn.collection(_db, n)) };
    window.firebase = window.firebase || {};
    window.firebase.firestore = window.firebase.firestore || {};
    window.firebase.firestore.FieldValue = { serverTimestamp: () => fn.serverTimestamp() };
})(db, window.voiceChatFirebase);
</script>

<script src="{{ asset('js/VoiceChat.js') }}"></script>

<script>
(function() {
    'use strict';
    
    let voiceChat = null;
    let isMicActive = false;
    const VOICE_LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const CURRENT_PLAYER_ID = {{ auth()->id() ?? 0 }};
    
    const micButton = document.getElementById('voiceMicButton');
    const micIcon = document.getElementById('micIcon');
    
    function updateMicButtonState(active) {
        isMicActive = active;
        if (micButton && micIcon) {
            if (active) {
                micButton.classList.add('active');
                micButton.classList.remove('muted');
                micIcon.textContent = '🎤';
            } else {
                micButton.classList.remove('active');
                micButton.classList.add('muted');
                micIcon.textContent = '🔇';
            }
        }
    }
    
    async function toggleMicrophone() {
        if (!voiceChat) return;
        try {
            const newState = await voiceChat.toggleMicrophone();
            updateMicButtonState(newState);
        } catch (error) {
            console.error('[VoiceChat] Toggle mic error:', error);
        }
    }
    
    async function initVoiceChat() {
        if (!VOICE_LOBBY_CODE || !window.voiceChatDb) {
            console.log('[VoiceChat] Missing lobby code or Firebase - hiding mic button');
            if (micButton) micButton.style.display = 'none';
            return;
        }
        
        try {
            voiceChat = new VoiceChat({
                sessionId: VOICE_LOBBY_CODE,
                localUserId: CURRENT_PLAYER_ID,
                mode: 'duo',
                db: window.voiceChatDb,
                onConnectionChange: (state) => {
                    if (state.muted !== undefined) updateMicButtonState(!state.muted);
                },
                onError: (error) => console.error('[VoiceChat] Error:', error)
            });
            
            await voiceChat.initialize();
            console.log('[VoiceChat] Background audio initialized successfully');
            
            if (micButton) {
                micButton.addEventListener('click', toggleMicrophone);
                updateMicButtonState(false);
            }
        } catch (error) {
            console.error('[VoiceChat] Init error:', error);
            if (micButton) micButton.style.display = 'none';
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initVoiceChat, 1000);
    });
    
    window.addEventListener('beforeunload', () => {
        if (voiceChat) voiceChat.cleanup();
    });

    // Register DuoSocketClient handlers after all scripts have loaded.
    // DOMContentLoaded is guaranteed to fire after ALL blocking <script src=""> tags —
    // including DuoSocketClient.js. setTimeout(0) is unreliable: it is a macrotask that
    // can fire DURING a script fetch, before window.DuoSocketClient is set.
    document.addEventListener('DOMContentLoaded', function() {
        var ds = window.DuoSocketClient;
        var h  = window._duoAnswerHandlers;
        if (!ds || !h) { console.error('[DuoAnswer] DuoSocketClient or handlers missing'); return; }
        ds.on('connect',         h.connect);
        ds.on('disconnect',      h.disconnect);
        ds.on('error',           h.error);
        ds.on('state',           h.state);
        ds.on('game_state',      h.game_state);
        ds.on('answer_revealed', h.answer_revealed);
        ds.on('round_ended',     h.round_ended);
        ds.on('match_ended',     h.match_ended);
        ds.on('phase_changed',   h.phase_changed);
        ds.on('score_update',    h.score_update);
        ds.on('skill_effect',    h.skill_effect);
        ds.on('skill_failed',    h.skill_failed);
        h.initEffects();
    });
})();
</script>
@endsection

