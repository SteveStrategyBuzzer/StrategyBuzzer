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
        // Catalogue des skills à trigger="answer" rendus dans la barre d'action.
        // L'historien (knowledge_without_time) est rendu séparément en bandeau
        // (cf. bloc @if($noBuzz && $hasHistorianSkill) plus bas), donc exclu ici.
        // CSS class + icône + libellé conservés à l'identique pour ne pas
        // changer l'UX. Pilote: AvatarSkillService::getAvatarSkills() →
        // DuoController::getPlayerSkillsWithTriggers() → $skills.
        $answerSkillMeta = [
            'illuminate_numbers' => ['class' => 'mathematicien', 'icon' => '💡', 'label' => __('Illuminer'),       'title' => __('Illumine une réponse si elle contient un chiffre')],
            'acidify_error'      => ['class' => 'scientifique',  'icon' => '🧪', 'label' => __('Acidifier'),       'title' => __('Acidifie une mauvaise réponse')],
            'eliminate_two'      => ['class' => 'ia-junior',     'icon' => '❌', 'label' => __('Éliminer 2'),      'title' => __('Élimine 2 mauvaises réponses')],
            'ai_suggestion'      => ['class' => 'ia-junior',     'icon' => '🤖', 'label' => __('Suggestion IA'),   'title' => __('L\'IA suggère une réponse')],
            'secure_answer'      => ['class' => 'visionnaire',   'icon' => '🎯', 'label' => __('2 pts sécurisés'), 'title' => __('Seule la bonne réponse sélectionnable')],
            'time_bonus'         => ['class' => 'sprinteur',     'icon' => '🕒', 'label' => __('+2s'),             'title' => __('Ajoute 2 secondes')],
        ];

        // Filter by ID presence in $answerSkillMeta only — DO NOT filter by
        // trigger. Several of these skills are declared trigger='question' in
        // AvatarSkillService (illuminate_numbers, ai_suggestion, eliminate_two,
        // lock_correct) but are still rendered + activated on the Answer page,
        // matching pre-Task-#56 behavior (which used per-ID @if checks).
        $answerActionSkills = collect($skills ?? [])
            ->filter(fn($s) => isset($answerSkillMeta[$s['id'] ?? '']))
            ->values();

        $hasHistorianSkill = collect($skills ?? [])
            ->contains(fn($s) => ($s['id'] ?? '') === 'knowledge_without_time');

        $correctIndex = $correct_index ?? null;
        $choicesJson  = json_encode($choices);
    @endphp

    @if($answerActionSkills->isNotEmpty())
    <div class="active-skills-bar" id="activeSkillsBar">
        @foreach($answerActionSkills as $skill)
            @php $meta = $answerSkillMeta[$skill['id']]; @endphp
            <button class="skill-action-btn {{ $meta['class'] }}"
                    data-skill-id="{{ $skill['id'] }}"
                    title="{{ $meta['title'] }}">
                <span class="skill-icon">{{ $meta['icon'] }}</span>
                <span>{{ $meta['label'] }}</span>
            </button>
        @endforeach
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

{{-- Skill effects + activation handlers + answer shuffling for the Duo Answer page.
     Extracted from this Blade in Task #56 to keep the inline IIFE focused on
     timer/scoring/socket plumbing. The module exposes window.DuoSkillEffects
     and is initialised below with closures over local timer/state vars. --}}
<script src="{{ asset('js/duo-skill-effects.js') }}"></script>

<script>
(function() {
    'use strict';
    
    const MATCH_ID   = window.MATCH_ID   || '';
    const ROOM_ID    = window.ROOM_ID    || '';
    const LOBBY_CODE = window.LOBBY_CODE || '';
    const JWT_TOKEN  = window.JWT_TOKEN  || '';
    const PLAYER_ID = {{ auth()->id() ?? 0 }};

    // Stats: GameplayRuntime owns score/efficiency via [data-stat][data-player].
    // The PHP first paint of #playerScoreValue is the only local seed.
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
    // Patch 4 — Node is the sole authority on the answer countdown.
    // phaseEndsAtMs is set EXCLUSIVELY from server events and never
    // locally extended. The snap/serverPhaseEndsAtMs/snapActiveUntilMs
    // bookkeeping was removed in favour of the real ANSWER_SELECTION
    // phase (10s) emitted by the orchestrator after QUESTION_ACTIVE.
    let phaseEndsAtMs = null;
    // Tracks the latest server-published phase. Updated by the three handlers
    // that receive a phase tag (_onAnswerPhaseChanged, _onAnswerGameState,
    // _onAnswerState). Used to gate the auto-timeout safety net so we do NOT
    // auto-submit while we are still bleeding off residual QUESTION_ACTIVE
    // time — the real answer window opens only with ANSWER_SELECTION.
    let currentPhase = null;
    
    const CHOICES = @json($choices);

    // Skill Challenger: Shuffle Answers (passive flag, server-driven via game_effects)
    const SHUFFLE_ACTIVE = {{ $shuffleAnswersActive ? 'true' : 'false' }};

    // Skill effects + activation handlers + answers shuffle live in
    // public/js/duo-skill-effects.js (extracted in Task #56). The init call
    // is deferred until calculatePotentialPoints is defined further below;
    // these forward-declared wrappers are referenced by startTimer() /
    // selectAnswer() / handleTimeout() before that point.
    let skillEffects = null;
    function shuffleAnswers()        { if (skillEffects) skillEffects.shuffleAnswers(); }
    function startShuffleInterval()  { if (skillEffects) skillEffects.startShuffleInterval(false); }
    function stopShuffleInterval()   { if (skillEffects) skillEffects.stopShuffleInterval(); }

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
        
        // ── Node calcule, Blade affiche ────────────────────────────────────────
        // The setInterval below derives `timeLeft` from `phaseEndsAtMs` on
        // every tick (never from a local `timeLeft--` in the nominal path).
        // The defensive decrement only fires if the server has not yet
        // published a deadline. Node owns the ANSWER_SELECTION window (10 s),
        // so every buzzer gets a fair chrono regardless of when they buzzed.
        if (phaseEndsAtMs) {
            const remaining = Math.max(0, phaseEndsAtMs - Date.now());
            timeLeft = Math.ceil(remaining / 1000);
            if (timeLeft <= 0) timeLeft = 1; // at least 1 tick before auto-timeout
        }
        
        // Démarrer le shuffle des réponses si actif
        startShuffleInterval();
        
        updatePotentialPointsDisplay(calculatePotentialPoints(timeLeft));
        
        timerInterval = setInterval(function() {
            // P0.3 / P57.1 — Recompute timeLeft from the (possibly
            // locally-extended) phaseEndsAtMs at every tick. This mirrors the
            // duo_question canonical pattern, survives backgrounded tabs /
            // throttled timers, and keeps the two players' chronos in sync to
            // within one tick. The defensive `timeLeft--` branch only fires
            // if the server has not yet published any deadline (shouldn't
            // happen post-buzz).
            let computed;
            if (phaseEndsAtMs) {
                const remainingMs = Math.max(0, phaseEndsAtMs - Date.now());
                computed = Math.ceil(remainingMs / 1000);
            } else {
                // Defensive fallback: phaseEndsAtMs absent — decrement locally.
                computed = timeLeft - 1;
            }
            // Patch 4 — No monotone-decreasing guard anymore. The chrono MUST
            // be allowed to jump UP when Node transitions QUESTION_ACTIVE
            // (residual ≤ 8 s) → ANSWER_SELECTION (fresh 10 s). This is the
            // whole point of giving every buzzer a fair answer window.
            timeLeft = computed;

            const percentage = Math.max(0, (timeLeft / ANSWER_TIME) * 100);
            timerBar.style.width = percentage + '%';
            timerSeconds.textContent = Math.max(0, timeLeft) + 's';
            
            if (timeLeft <= 5) {
                timerBar.classList.add('warning');
                timerSeconds.classList.add('warning');
            }
            
            updatePotentialPointsDisplay(calculatePotentialPoints(timeLeft));
            
            if (timeLeft <= 0) {
                // The auto-timeout is a UX safety net for the REAL answer
                // window only. While the buzzer is still bleeding off
                // residual QUESTION_ACTIVE time (they buzzed mid-question
                // and were redirected here before Node opened the official
                // ANSWER_SELECTION phase), reaching 0s does NOT mean their
                // answer time is up — Node will publish ANSWER_SELECTION on
                // the next phase transition and the chrono will jump UP to
                // 10s. Auto-submitting here would cut their answer window
                // short. We therefore restrict the safety net to phases
                // where the chrono is authoritative for the answer window.
                var inAnswerWindow =
                    currentPhase === 'ANSWER_SELECTION' ||
                    currentPhase === 'ANSWER_COLLECTION' ||
                    currentPhase === 'BUZZ_WINNER_ANSWERING';
                if (!inAnswerWindow) {
                    return; // keep ticking; do NOT auto-submit yet
                }
                clearInterval(timerInterval);
                timerInterval = null;
                // Node remains the sole authority on the phase transition
                // (handleAnswerTimeout) — a late client submission is
                // harmless because the orchestrator ignores submissions
                // outside the answer window.
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

        // Bug #1 fix — Option B (documented in docs/decisions/2026-04-26-duo-immediate-result-nav.md):
        // After the answer is submitted, navigate immediately to /duo/result so the
        // player never lingers on the Answer page waiting for the opponent. The
        // Result page renders in "pending" mode (overlay visible, ✓/✗/points hidden)
        // and hydrates from server events:
        //   - `answer_revealed` (filtered by playerId): fills header/points/answer
        //   - `score_update` / `match_stats` / `round_stats` (via GameplayRuntime):
        //     repaints [data-stat="score"] nodes
        //   - `phase_changed RESULT`: idempotent no-op when already on Result page
        // The 250 ms delay lets the socket flush the `answer` event first.
        // `isRedirecting` guard ensures only one navigation fires (this branch OR
        // the `phase === 'RESULT'` branch in _onAnswerPhaseChanged, never both).
        setTimeout(function () {
            if (isRedirecting) return;
            isRedirecting = true;
            var _nav = window.duoNavigate || function (u) { window.location.href = u; };
            _nav((window.RESULT_URL || '/game/duo/result') + '?match_id=' + encodeURIComponent(MATCH_ID));
        }, 250);
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
        currentPhase = phase;

        // Verrou source de vérité: mettre à jour PLAYER_BUZZ_POSITION depuis buzzQueue serveur
        // Fallback: si buzzQueue absent, utiliser playerBuzzOrder si disponible
        if (data.buzzQueue) {
            var derived = _deriveBuzzPositionFromQueue(data.buzzQueue);
            if (derived) applyBuzzPosition(derived);
        } else if (typeof data.playerBuzzOrder === 'number' && data.playerBuzzOrder > 0) {
            applyBuzzPosition(data.playerBuzzOrder === 1 ? 'first' : 'second');
        }

        if (!data.phaseEndsAtMs) return;
        // Patch 4 — Honest resync. Node is the sole authority and now
        // publishes a real ANSWER_SELECTION phase, so stale-snapshot
        // and snap-protection guards are gone. The chrono follows the
        // server deadline exactly, even when it jumps UP on the
        // QUESTION_ACTIVE → ANSWER_SELECTION transition.
        phaseEndsAtMs = data.phaseEndsAtMs;
        var remaining = Math.max(0, data.phaseEndsAtMs - Date.now());
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
                currentPhase = phase;
                // Patch 4 — Honest resync on (re)connect. Stale-snapshot and
                // snap-protection guards removed: Node now publishes a real
                // 10 s ANSWER_SELECTION phase, so we trust phaseEndsAtMs as-is.
                phaseEndsAtMs = data.phaseEndsAtMs;
                var rem = Math.max(0, data.phaseEndsAtMs - Date.now());
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

        // Bug #63 fix — `answer_revealed` is broadcast room-wide by Node, with one
        // event per buzzer. Without this filter, a fast-buzzing opponent who answers
        // wrong would flip MY cards to "incorrect" before MY own answer_revealed
        // arrives. Use the canonical SB_GAME_CONTEXT.currentUserId (auth()->id() as
        // string) — already published by partials/game-context.blade.php via the
        // include directive at the top of this view; no new global needed.
        var _myId = (window.SB_GAME_CONTEXT && window.SB_GAME_CONTEXT.currentUserId)
            || window.CURRENT_USER_ID
            || '';
        if (_myId && data && data.playerId != null && String(data.playerId) !== String(_myId)) {
            // Event belongs to the other player — do not mutate my visual state and
            // do not stash my sessionStorage with their fun-fact / question index.
            return;
        }

        waitingOverlay.style.display = 'none';
        const isCorrect    = data.isCorrect || false;
        const correctIndex = data.correctIndex !== undefined ? data.correctIndex : data.correctAnswer;
        const pointsEarned = data.points || data.pointsEarned || 0;
        showResult(isCorrect, correctIndex, pointsEarned);

        // Stash the server-provided "Le saviez-vous?" fun fact and the question
        // number into sessionStorage so the next page (duo_result) can show them
        // even when the controller's own server-side fallback (Redis room state)
        // returns nothing — e.g. if the orchestrator already advanced questionIndex
        // by the time the Result controller runs, or if funFact is absent on the
        // persisted question record. Server-side data still wins; this is purely
        // a client-side belt-and-braces fallback scoped to the current tab.
        try {
            var funText = (data.didYouKnow || data.funFact || '').toString().trim();
            if (funText) {
                sessionStorage.setItem('duo_last_fun_fact', funText);
            } else {
                sessionStorage.removeItem('duo_last_fun_fact');
            }
            if (typeof data.questionIndex === 'number') {
                // Server uses 0-indexed questionIndex; display is 1-indexed.
                sessionStorage.setItem('duo_last_question_number', String(data.questionIndex + 1));
            } else if (typeof data.currentQuestion === 'number') {
                sessionStorage.setItem('duo_last_question_number', String(data.currentQuestion));
            }
        } catch (e) { /* sessionStorage may be unavailable in private mode */ }

        // Visual feedback only — navigation is driven SOLELY by phase_changed events
        // per architecture rule: Node = sole phase authority. ROUND_SCOREBOARD and
        // MATCH_END phases are emitted by GameOrchestrator; this view reacts only to
        // those phase transitions. Legacy `data.matchEnded` branch + `_onAnswerRoundEnded`
        // + `_onAnswerMatchEnded` removed (they raced with phase_changed and the
        // `isRedirecting` guard always picked the first arrival, making them dead).
    }
    function _onAnswerPhaseChanged(data) {
        if (isRedirecting || !data || !data.phase) return;
        var phase = data.phase;
        var _nav  = window.duoNavigate || function(u) { window.location.href = u; };

        // Track latest phase for the auto-timeout safety-net gate.
        currentPhase = phase;

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
            // UX: previous 600ms delay for correct answers was too snappy — players
            // barely had time to read "Bonne réponse !" before the page swapped.
            // Bumped to 2500ms in all cases (matches incorrect-answer delay) so the
            // visual feedback on the Answer page stays readable for ~2.5s.
            var resultDelay = 2500;
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
    // Stats note: efficiency + score are written canonically by GameplayRuntime.js
    // (subscribes to player_stats_updated / score_update → [data-stat][data-player]).
    function _initAnswerEffects() {
        GameEffectsRuntime.registerEffect('shuffle_answers', {
            onStart: function() {
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
                // Force-start shuffle even if SHUFFLE_ACTIVE flag wasn't set at
                // page render: this is the server telling us shuffle is now on.
                if (skillEffects) skillEffects.startShuffleInterval(true);
            },
            onStop: function() {
                if (skillEffects) skillEffects.stopShuffleInterval();
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
    // Expose for the scripts section — .on() bindings done there after DuoSocketClient.js loads.
    // round_ended / match_ended / score_update bindings removed: round_ended + match_ended
    // navigations are now handled solely via phase_changed; score_update is handled
    // canonically by GameplayRuntime.js (no view-local handler needed).
    window._duoAnswerHandlers = {
        connect:         _onAnswerConnect,
        disconnect:      _onAnswerDisconnect,
        error:           _onAnswerError,
        state:           _onAnswerState,
        game_state:      _onAnswerGameState,
        answer_revealed: _onAnswerRevealed,
        phase_changed:   _onAnswerPhaseChanged,
        // Skill effect/failed routing is owned by public/js/duo-skill-effects.js
        // (Task #56). Closures resolve `skillEffects` lazily, after init below.
        skill_effect:    function (data) { if (skillEffects) skillEffects.onSkillEffect(data); },
        skill_failed:    function (data) { if (skillEffects) skillEffects.onSkillFailed(data); },
        initEffects:     _initAnswerEffects
    };

    // Wire skill action buttons + effect listeners via the extracted module.
    // Replaces the legacy initSkillButtons() + 12 inline handlers (Task #56).
    skillEffects = window.DuoSkillEffects.init({
        socket: window.DuoSocketClient,
        isAnswered: function () { return answered; },
        computeCurrentPotentialPoints: function () { return calculatePotentialPoints(timeLeft); },
        extendTime: function (seconds) {
            timeLeft     += seconds;
            ANSWER_TIME  += seconds;
            if (timerSeconds) timerSeconds.textContent = timeLeft + 's';
            if (timerBar)     timerBar.style.width = ((timeLeft / ANSWER_TIME) * 100) + '%';
        },
        shuffleActive: SHUFFLE_ACTIVE,
        labels: {
            lockCorrectError: '{{ __("Ce skill ne fonctionne que si vous êtes sur 2 points !") }}',
        },
    });
    
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
        ds.on('phase_changed',   h.phase_changed);
        ds.on('skill_effect',    h.skill_effect);
        ds.on('skill_failed',    h.skill_failed);
        h.initEffects();
    });
})();
</script>
@endsection

