@extends('layouts.app')

@section('content')
@php
$mode = $params['mode'] ?? 'solo';
$opponentType = $params['opponent_type'] ?? 'ai';
$opponentInfo = $params['opponent_info'] ?? [];
$currentQuestion = $params['current'] ?? 1;
$totalQuestions = $params['nb_questions'] ?? 10;
$theme = $params['theme'] ?? 'Culture générale';
$playerScore = $params['score'] ?? 0;
$opponentScore = $params['opponent_score'] ?? 0;
$currentRound = $params['current_round'] ?? 1;
$playerRoundsWon = $params['player_rounds_won'] ?? 0;
$opponentRoundsWon = $params['opponent_rounds_won'] ?? 0;

$wasCorrect = $params['was_correct'] ?? false;
$pointsEarned = $params['points_earned'] ?? 0;
$opponentPointsEarned = $params['opponent_points_earned'] ?? 0;
$buzzWinner = $params['buzz_winner'] ?? 'player';
$buzzTime = $params['buzz_time'] ?? 0;
$opponentWasCorrect = $params['opponent_was_correct'] ?? false;
$noBuzz = $params['no_buzz'] ?? false;
$timeout = $params['timeout'] ?? false;
$correctAnswer = $params['correct_answer'] ?? '';

$selectedAvatar = session('selected_avatar', 'default');
// Normalize avatar path: handle full paths, category/slug format, and simple names (PHP 7.x compatible)
if (strpos($selectedAvatar, 'http://') === 0 || strpos($selectedAvatar, 'https://') === 0 || strpos($selectedAvatar, '//') === 0) {
    $playerAvatarPath = $selectedAvatar;
} elseif (strpos($selectedAvatar, 'images/') === 0) {
    $playerAvatarPath = asset($selectedAvatar);
} elseif (strpos($selectedAvatar, '/') !== false && strpos($selectedAvatar, '.png') === false) {
    $playerAvatarPath = asset("images/avatars/{$selectedAvatar}.png");
} elseif (strpos($selectedAvatar, '/') !== false) {
    $playerAvatarPath = asset($selectedAvatar);
} else {
    $playerAvatarPath = asset("images/avatars/standard/{$selectedAvatar}.png");
}

$opponentName = $opponentInfo['name'] ?? __('Adversaire');
$opponentAvatar = '';
if ($opponentType === 'ai') {
    if ($opponentInfo['is_boss'] ?? false) {
        $opponentAvatar = asset("images/avatars/bosses/{$opponentInfo['avatar']}.png");
    } else {
        $opponentAvatar = asset("images/avatars/students/{$opponentInfo['avatar']}.png");
    }
} else {
    $opponentAvatar = asset("images/avatars/standard/{$opponentInfo['avatar']}.png");
}

$isLastQuestion = $currentQuestion >= $totalQuestions;
$isFirebaseMode = in_array($mode, ['duo', 'league_individual', 'master']);
$isSyncMode = in_array($mode, ['duo', 'league_individual', 'league_team']);
$isMasterMode = $mode === 'master';
$playerId = auth()->id();
$opponentId = $params['opponent_id'] ?? null;
@endphp

<style>
    html, body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        width: 100%;
        max-width: 100vw;
    }
    
    body {
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        color: #fff;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }
    
    .transition-container {
        max-width: 600px;
        width: 100%;
        text-align: center;
    }
    
    .result-icon {
        font-size: 5rem;
        margin-bottom: 20px;
        animation: popIn 0.5s ease-out;
    }
    
    @keyframes popIn {
        0% { transform: scale(0); opacity: 0; }
        70% { transform: scale(1.2); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    .result-title {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    .result-title.correct { color: #2ecc71; }
    .result-title.incorrect { color: #e74c3c; }
    .result-title.neutral { color: #f39c12; }
    
    .correct-answer-box {
        background: rgba(255, 255, 255, 0.1);
        border: 2px solid rgba(46, 204, 113, 0.5);
        border-radius: 15px;
        padding: 15px 20px;
        margin: 20px auto;
        max-width: 400px;
    }
    
    .correct-answer-label {
        font-size: 0.9rem;
        color: #2ecc71;
        margin-bottom: 5px;
    }
    
    .correct-answer-text {
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .points-display {
        margin: 30px 0;
    }
    
    .points-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 40px;
    }
    
    .points-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .points-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #4ECDC4;
    }
    
    .points-avatar.opponent { border-color: #FF6B6B; }
    
    .points-name {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .points-earned {
        font-size: 1.5rem;
        font-weight: bold;
        padding: 5px 15px;
        border-radius: 20px;
    }
    
    .points-earned.positive {
        color: #2ecc71;
        background: rgba(46, 204, 113, 0.2);
    }
    
    .points-earned.neutral {
        color: #95a5a6;
        background: rgba(149, 165, 166, 0.2);
    }
    
    .points-earned.negative {
        color: #e74c3c;
        background: rgba(231, 76, 60, 0.2);
    }
    
    .total-scores {
        margin: 30px 0;
        padding: 20px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
    }
    
    .scores-label {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .scores-row {
        display: flex;
        justify-content: center;
        gap: 60px;
    }
    
    .score-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }
    
    .score-player-name {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .score-value {
        font-size: 2.5rem;
        font-weight: bold;
    }
    
    .score-value.player { color: #4ECDC4; }
    .score-value.opponent { color: #FF6B6B; }
    
    .progress-bar {
        margin: 30px auto;
        max-width: 300px;
    }
    
    .progress-label {
        font-size: 0.85rem;
        opacity: 0.8;
        margin-bottom: 10px;
    }
    
    .progress-track {
        height: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4, #44a08d);
        border-radius: 4px;
        transition: width 0.5s ease-out;
    }
    
    .next-button {
        margin-top: 30px;
        padding: 15px 40px;
        font-size: 1.1rem;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(135deg, #4ECDC4 0%, #44a08d 100%);
        border: none;
        border-radius: 30px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .next-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.4);
    }
    
    .next-button.round-end {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    }
    
    .countdown-text {
        margin-top: 15px;
        font-size: 0.9rem;
        opacity: 0.7;
    }
    
    @media (max-width: 480px) {
        .result-icon { font-size: 4rem; }
        .result-title { font-size: 1.5rem; }
        .points-row { gap: 20px; }
        .scores-row { gap: 30px; }
        .score-value { font-size: 2rem; }
    }
    
    .go-button {
        margin-top: 20px;
        padding: 18px 50px;
        font-size: 1.3rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        border: none;
        border-radius: 35px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .go-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(46, 204, 113, 0.5);
    }
    
    .go-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .go-button.clicked {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        animation: pulse-ready 1.5s infinite;
    }
    
    @keyframes pulse-ready {
        0%, 100% { box-shadow: 0 0 20px rgba(46, 204, 113, 0.4); }
        50% { box-shadow: 0 0 30px rgba(46, 204, 113, 0.7); }
    }
    
    .waiting-message {
        margin-top: 15px;
        font-size: 0.9rem;
        opacity: 0.8;
        animation: blink 1.5s infinite;
    }
    
    @keyframes blink {
        0%, 100% { opacity: 0.8; }
        50% { opacity: 0.4; }
    }
</style>

<div class="transition-container">
    @if($noBuzz)
        <div class="result-icon">⏱️</div>
        <div class="result-title neutral">{{ __('Temps écoulé !') }}</div>
        <p style="opacity: 0.8;">{{ __('Personne n\'a buzzé à temps') }}</p>
    @elseif($timeout)
        <div class="result-icon">⏱️</div>
        <div class="result-title incorrect">{{ __('Temps de réponse écoulé !') }}</div>
    @elseif($buzzWinner === 'player')
        @if($wasCorrect)
            <div class="result-icon">✅</div>
            <div class="result-title correct">{{ __('Bonne réponse !') }}</div>
        @else
            <div class="result-icon">❌</div>
            <div class="result-title incorrect">{{ __('Mauvaise réponse') }}</div>
        @endif
    @else
        @if($opponentWasCorrect)
            <div class="result-icon">😔</div>
            <div class="result-title incorrect">{{ $opponentName }} {{ __('a bien répondu') }}</div>
        @else
            <div class="result-icon">😅</div>
            <div class="result-title neutral">{{ $opponentName }} {{ __('s\'est trompé !') }}</div>
        @endif
    @endif
    
    @if($correctAnswer && !$wasCorrect)
        <div class="correct-answer-box">
            <div class="correct-answer-label">{{ __('La bonne réponse était') }}</div>
            <div class="correct-answer-text">{{ $correctAnswer }}</div>
        </div>
    @endif
    
    <div class="points-display">
        <div class="points-row">
            <div class="points-box">
                <img src="{{ $playerAvatarPath }}" alt="Joueur" class="points-avatar">
                <div class="points-name">{{ auth()->user()->name }}</div>
                <div class="points-earned {{ $pointsEarned > 0 ? 'positive' : ($pointsEarned < 0 ? 'negative' : 'neutral') }}">
                    {{ $pointsEarned > 0 ? '+' : '' }}{{ $pointsEarned }} pts
                </div>
            </div>
            <div class="points-box">
                <img src="{{ $opponentAvatar }}" alt="Adversaire" class="points-avatar opponent">
                <div class="points-name">{{ $opponentName }}</div>
                <div class="points-earned {{ $opponentPointsEarned > 0 ? 'positive' : ($opponentPointsEarned < 0 ? 'negative' : 'neutral') }}">
                    {{ $opponentPointsEarned > 0 ? '+' : '' }}{{ $opponentPointsEarned }} pts
                </div>
            </div>
        </div>
    </div>
    
    <div class="total-scores">
        <div class="scores-label">{{ __('Score - Manche') }} {{ $currentRound }}</div>
        <div class="scores-row">
            <div class="score-item">
                <div class="score-player-name">{{ auth()->user()->name }}</div>
                <div class="score-value player" id="totalPlayerScore">{{ $playerScore }}</div>
            </div>
            <div class="score-item">
                <div class="score-player-name">{{ $opponentName }}</div>
                <div class="score-value opponent" id="totalOpponentScore">{{ $opponentScore }}</div>
            </div>
        </div>
    </div>
    
    <div class="progress-bar">
        <div class="progress-label">{{ __('Question') }} {{ $currentQuestion }}/{{ $totalQuestions }}</div>
        <div class="progress-track">
            <div class="progress-fill" style="width: {{ ($currentQuestion / $totalQuestions) * 100 }}%"></div>
        </div>
    </div>
    
    @if($isLastQuestion)
        <button class="next-button round-end" id="nextButton">
            {{ __('Voir le résultat de la manche') }}
        </button>
        <div class="countdown-text" id="countdownText">
            {{ __('Suite automatique dans') }} <span id="countdown">5</span>s
        </div>
    @elseif($isMasterMode)
        @if($params['is_host'] ?? false)
            <button class="go-button" id="nextButton">{{ __('Question suivante') }}</button>
            <div class="countdown-text" id="countdownText">
                {{ __('Suite automatique dans') }} <span id="countdown">5</span>s
            </div>
        @else
            <button class="next-button" id="nextButton" disabled style="opacity: 0.6;">
                {{ __('En attente du maître de jeu...') }}
            </button>
        @endif
    @else
        {{-- Même interface pour Solo, Duo, League - bouton GO + countdown --}}
        <button class="next-button" id="nextButton">
            {{ __('Question suivante') }}
        </button>
        <div class="countdown-text" id="countdownText">
            {{ __('Suite automatique dans') }} <span id="countdown">5</span>s
        </div>
    @endif
</div>

<script>
const transitionConfig = {
    mode: '{{ $mode }}',
    isLastQuestion: {{ ($params['is_last_question'] ?? false) ? 'true' : 'false' }},
    currentQuestion: {{ $params['current'] ?? 1 }},
    isFirebaseMode: {{ (in_array($params['mode'] ?? 'solo', ['duo', 'league_individual', 'master'])) ? 'true' : 'false' }},
    isSyncMode: {{ $isSyncMode ? 'true' : 'false' }},
    isMasterMode: {{ $isMasterMode ? 'true' : 'false' }},
    playerId: '{{ $playerId }}',
    opponentId: '{{ $opponentId ?? "" }}',
    routes: {
        nextQuestion: '{{ route("game.next-question", ["mode" => $params["mode"] ?? "solo"]) }}',
        roundResult: '{{ route("game.round-result", ["mode" => $params["mode"] ?? "solo"]) }}',
    }
};

let countdown = 5;
let countdownInterval;
let playerIsReady = false;
let opponentIsReady = false;
let hasNavigated = false;
const currentQuestionNum = transitionConfig.currentQuestion;

const countdownSpan = document.getElementById('countdown');
const nextButton = document.getElementById('nextButton');
const goButton = document.getElementById('goButton');

async function goToNext() {
    if (hasNavigated) return;
    hasNavigated = true;
    
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    
    if (transitionConfig.isLastQuestion) {
        window.location.href = transitionConfig.routes.roundResult;
    } else {
        window.location.href = transitionConfig.routes.nextQuestion;
    }
}

// Countdown unifié pour tous les modes (Solo, Duo, League)
// Pour Master non-host, pas de countdown
const isMasterNonHost = transitionConfig.isMasterMode && !nextButton?.disabled === false;

function startCountdown() {
    if (!countdownSpan) return;
    countdownInterval = setInterval(() => {
        countdown--;
        countdownSpan.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            goToNext();
        }
    }, 1000);
}

if (nextButton && !nextButton.disabled) {
    nextButton.addEventListener('click', function() {
        goToNext();
    });
}

// Démarrer le countdown pour tous les modes sauf Master non-host
if (countdownSpan) {
    startCountdown();
}

// Pour les modes Firebase, signaler qu'on est prêt en arrière-plan (invisible)
if (transitionConfig.isFirebaseMode && !transitionConfig.isLastQuestion) {
    // Signaler automatiquement qu'on est prêt quand on arrive sur cette page
    setTimeout(async () => {
        if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
            await FirebaseGameSync.setPlayerReady(currentQuestionNum);
        }
    }, 500);
}
</script>

@if($isFirebaseMode)
@php
$matchId = $params['match_id'] ?? null;
$isHost = $params['is_host'] ?? false;
@endphp
<script src="/js/firebase-game-sync.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function() {
    const isFirebaseMode = {{ $isFirebaseMode ? 'true' : 'false' }};
    const matchId = '{{ $matchId ?? "" }}';
    const mode = '{{ $mode }}';
    const laravelUserId = '{{ auth()->id() }}';
    const isHost = {{ $isHost ? 'true' : 'false' }};
    
    if (!isFirebaseMode || !matchId) return;
    
    try {
        await FirebaseGameSync.init({
            matchId: matchId,
            mode: mode,
            laravelUserId: laravelUserId,
            isHost: isHost,
            callbacks: {
                onReady: async () => {
                    console.log('[TransitionPage] Firebase ready, isHost:', isHost);
                },
                onPhaseChange: (phase, data) => {
                    console.log('[TransitionPage] Phase changed to:', phase);
                    if (phase === 'question' || phase === 'round_result') {
                        goToNext();
                    }
                },
                onQuestionChange: (questionNum, data) => {
                    console.log('[TransitionPage] Question changed to:', questionNum);
                    if (questionNum > transitionConfig.currentQuestion) {
                        goToNext();
                    }
                }
            }
        });
        
    } catch (error) {
        console.error('[TransitionPage] Firebase init error:', error);
    }
});
</script>
@endif

@endsection
