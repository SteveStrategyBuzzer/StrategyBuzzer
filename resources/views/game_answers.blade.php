@extends('layouts.app')

@section('content')
@php
$mode = $params['mode'] ?? 'solo';
$opponentType = $params['opponent_type'] ?? 'ai';
$opponentInfo = $params['opponent_info'] ?? [];
$currentQuestion = $params['current'] ?? 1;
$totalQuestions = $params['nb_questions'] ?? 10;
$theme = $params['theme'] ?? 'Culture générale';
$subTheme = $params['sub_theme'] ?? '';
$playerScore = $params['score'] ?? 0;
$opponentScore = $params['opponent_score'] ?? 0;
$currentRound = $params['current_round'] ?? 1;
$playerRoundsWon = $params['player_rounds_won'] ?? 0;
$opponentRoundsWon = $params['opponent_rounds_won'] ?? 0;
$avatarName = $params['avatar'] ?? 'Aucun';
$avatarSkillsFull = $params['avatar_skills_full'] ?? ['rarity' => null, 'skills' => []];
$buzzTime = $params['buzz_time'] ?? 0;
$buzzWinner = $params['buzz_winner'] ?? 'player';
$question = $params['question'] ?? [];
$answers = $params['answers'] ?? [];
$correctIndex = $params['correct_answer_index'] ?? 0;

$usedSkills = session('used_skills', []);
$skills = [];
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skillData) {
        $skillId = $skillData['id'];
        $usesCount = 0;
        foreach ($usedSkills as $used) {
            if (strpos($used, $skillId) === 0) {
                $usesCount++;
            }
        }
        $maxUses = $skillData['uses_per_match'] ?? 1;
        $isFullyUsed = ($maxUses > 0 && $usesCount >= $maxUses);
        
        $skills[] = [
            'id' => $skillId,
            'icon' => $isFullyUsed ? '⚪' : $skillData['icon'],
            'name' => $skillData['name'],
            'description' => $skillData['description'],
            'type' => $skillData['type'],
            'trigger' => $skillData['trigger'],
            'auto' => $skillData['auto'] ?? false,
            'used' => $isFullyUsed,
        ];
    }
}

$selectedAvatar = session('selected_avatar', 'default');
if (strpos($selectedAvatar, '/') !== false || strpos($selectedAvatar, 'images/') === 0) {
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

$isFirebaseMode = in_array($mode, ['duo', 'league_individual', 'master']);
$matchId = $params['match_id'] ?? null;
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
        align-items: flex-start;
        justify-content: center;
        padding: 10px;
        box-sizing: border-box;
    }
    
    .game-container {
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
        position: relative;
        min-height: calc(100vh - 20px);
        min-height: calc(100dvh - 20px);
        box-sizing: border-box;
    }
    
    .mode-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .mode-solo { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .mode-duo { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
    .mode-league { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
    .mode-master { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); }
    
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
    
    .question-text {
        font-size: 1.4rem;
        font-weight: 600;
        line-height: 1.5;
    }
    
    .buzz-winner-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .buzz-winner-badge.player { background: linear-gradient(135deg, #4ECDC4 0%, #44a08d 100%); }
    .buzz-winner-badge.opponent { background: linear-gradient(135deg, #FF6B6B 0%, #e74c3c 100%); }
    
    .scores-row {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin-bottom: 20px;
    }
    
    .score-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }
    
    .score-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #4ECDC4;
    }
    
    .score-avatar.opponent { border-color: #FF6B6B; }
    
    .score-name {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .score-value {
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .score-value.player { color: #4ECDC4; }
    .score-value.opponent { color: #FF6B6B; }
    
    .answer-timer {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }
    
    .timer-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        border: 4px solid #4ECDC4;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: border-color 0.3s;
    }
    
    .timer-circle.warning { border-color: #f39c12; }
    .timer-circle.danger { border-color: #e74c3c; animation: pulse 0.5s infinite; }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .timer-value {
        font-size: 2rem;
        font-weight: bold;
    }
    
    .answers-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        max-width: 800px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .answer-option {
        background: rgba(255, 255, 255, 0.1);
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 15px;
        padding: 20px;
        font-size: 1.1rem;
        color: #fff;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
        min-height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .answer-option:hover:not(:disabled) {
        background: rgba(78, 205, 196, 0.2);
        border-color: #4ECDC4;
        transform: translateY(-2px);
    }
    
    .answer-option:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .answer-option.selected {
        background: rgba(78, 205, 196, 0.3);
        border-color: #4ECDC4;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.4);
    }
    
    .answer-option.correct {
        background: rgba(46, 204, 113, 0.3);
        border-color: #2ecc71;
        box-shadow: 0 0 20px rgba(46, 204, 113, 0.4);
    }
    
    .answer-option.incorrect {
        background: rgba(231, 76, 60, 0.3);
        border-color: #e74c3c;
        box-shadow: 0 0 20px rgba(231, 76, 60, 0.4);
    }
    
    .skills-row {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 20px;
    }
    
    .skill-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        border: 2px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .skill-circle.active { border-color: #9b59b6; background: rgba(155, 89, 182, 0.2); }
    .skill-circle.used { opacity: 0.5; cursor: not-allowed; }
    .skill-circle.auto { border-color: #3498db; background: rgba(52, 152, 219, 0.2); }
    
    .waiting-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 20px;
    }
    
    .waiting-overlay.active { display: flex; }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top-color: #4ECDC4;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin { to { transform: rotate(360deg); } }
    
    @media (max-width: 600px) {
        .answers-grid {
            grid-template-columns: 1fr;
        }
        .answer-option {
            padding: 15px;
            font-size: 1rem;
            min-height: 60px;
        }
        .scores-row {
            gap: 20px;
        }
        .score-avatar {
            width: 40px;
            height: 40px;
        }
    }
</style>

<div class="game-container">
    <div class="mode-indicator mode-{{ $mode }}">
        @if($mode === 'solo')
            {{ __('Solo') }}
        @elseif($mode === 'duo')
            {{ __('Duo') }}
        @elseif($mode === 'league_individual')
            {{ __('Ligue') }}
        @elseif($mode === 'master')
            {{ __('Maître') }}
        @endif
    </div>
    
    <div class="question-header">
        <div class="buzz-winner-badge {{ $buzzWinner }}">
            @if($buzzWinner === 'player')
                {{ __('Vous avez buzzé !') }} ({{ number_format($buzzTime, 1) }}s)
            @else
                {{ $opponentName }} {{ __('a buzzé !') }} ({{ number_format($buzzTime, 1) }}s)
            @endif
        </div>
        
        <div class="question-number">
            {{ $theme }} @if($subTheme)- {{ $subTheme }}@endif | {{ __('Question') }} {{ $currentQuestion }}/{{ $totalQuestions }}
        </div>
        
        <div class="question-text">
            {{ $question['text'] ?? __('Question') }}
        </div>
    </div>
    
    <div class="scores-row">
        <div class="score-box">
            <img src="{{ $playerAvatarPath }}" alt="Joueur" class="score-avatar">
            <div class="score-name">{{ auth()->user()->name }}</div>
            <div class="score-value player" id="playerScore">{{ $playerScore }}</div>
        </div>
        <div class="score-box">
            <img src="{{ $opponentAvatar }}" alt="Adversaire" class="score-avatar opponent">
            <div class="score-name">{{ $opponentName }}</div>
            <div class="score-value opponent" id="opponentScore">{{ $opponentScore }}</div>
        </div>
    </div>
    
    <div class="answer-timer">
        <div class="timer-circle" id="timerCircle">
            <div class="timer-value" id="answerTimer">5</div>
        </div>
    </div>
    
    <div class="answers-grid" id="answersGrid">
        @foreach($answers as $index => $answer)
            <button class="answer-option" 
                    data-index="{{ $index }}" 
                    data-correct="{{ $index === $correctIndex ? 'true' : 'false' }}"
                    {{ $buzzWinner !== 'player' ? 'disabled' : '' }}>
                {{ is_array($answer) ? ($answer['text'] ?? $answer) : $answer }}
            </button>
        @endforeach
    </div>
    
    <div class="skills-row">
        @foreach($skills as $skill)
            @if(in_array($skill['trigger'], ['answer', 'pre_answer']))
                <div class="skill-circle {{ $skill['used'] ? 'used' : 'active' }} {{ $skill['auto'] ? 'auto' : '' }}" 
                     data-skill-id="{{ $skill['id'] }}"
                     title="{{ $skill['name'] }}: {{ $skill['description'] }}">
                    {{ $skill['icon'] }}
                </div>
            @endif
        @endforeach
    </div>
</div>

<div class="waiting-overlay" id="waitingOverlay">
    <div class="spinner"></div>
    <div class="waiting-text">{{ __('Traitement de la réponse...') }}</div>
</div>

<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
</audio>
<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
</audio>

<script>
const answerConfig = {
    mode: '{{ $mode }}',
    buzzWinner: '{{ $buzzWinner }}',
    correctIndex: {{ $correctIndex }},
    isFirebaseMode: {{ $isFirebaseMode ? 'true' : 'false' }},
    matchId: '{{ $matchId ?? '' }}',
    playerId: '{{ auth()->id() }}',
    currentQuestion: {{ $currentQuestion }},
    csrfToken: '{{ csrf_token() }}',
    routes: {
        answer: '/game/{{ $mode }}/answer',
        transition: '/game/{{ $mode }}/transition',
    }
};

let timeLeft = 5;
let timerInterval;
let answered = false;

const answerTimer = document.getElementById('answerTimer');
const timerCircle = document.getElementById('timerCircle');
const answerButtons = document.querySelectorAll('.answer-option');
const waitingOverlay = document.getElementById('waitingOverlay');

function startAnswerTimer() {
    if (answerConfig.buzzWinner !== 'player') {
        handleOpponentAnswering();
        return;
    }
    
    timerInterval = setInterval(() => {
        timeLeft--;
        answerTimer.textContent = timeLeft;
        
        if (timeLeft <= 2) {
            timerCircle.classList.add('danger');
            timerCircle.classList.remove('warning');
        } else if (timeLeft <= 3) {
            timerCircle.classList.add('warning');
        }
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            handleTimeOut();
        }
    }, 1000);
}

function handleOpponentAnswering() {
    answerTimer.textContent = '...';
    
    setTimeout(() => {
        const opponentAnswerIndex = Math.floor(Math.random() * 4);
        const isCorrect = opponentAnswerIndex === answerConfig.correctIndex;
        
        answerButtons.forEach((btn, idx) => {
            if (idx === opponentAnswerIndex) {
                btn.classList.add('selected');
            }
            if (idx === answerConfig.correctIndex) {
                btn.classList.add('correct');
            } else if (idx === opponentAnswerIndex && !isCorrect) {
                btn.classList.add('incorrect');
            }
        });
        
        setTimeout(() => {
            window.location.href = answerConfig.routes.transition + 
                '?opponent_answered=1&correct=' + (isCorrect ? '1' : '0');
        }, 1500);
    }, 2000);
}

async function submitAnswer(index) {
    if (answered) return;
    answered = true;
    clearInterval(timerInterval);
    
    const selectedButton = answerButtons[index];
    const isCorrect = selectedButton.dataset.correct === 'true';
    
    selectedButton.classList.add('selected');
    
    answerButtons.forEach((btn, idx) => {
        btn.disabled = true;
        if (idx === answerConfig.correctIndex) {
            btn.classList.add('correct');
        } else if (idx === index && !isCorrect) {
            btn.classList.add('incorrect');
        }
    });
    
    const sound = document.getElementById(isCorrect ? 'correctSound' : 'incorrectSound');
    if (sound) {
        sound.currentTime = 0;
        sound.play().catch(() => {});
    }
    
    waitingOverlay.classList.add('active');
    
    try {
        const response = await fetch(answerConfig.routes.answer, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': answerConfig.csrfToken
            },
            body: JSON.stringify({
                answer_id: index,
                is_correct: isCorrect,
                buzz_time: 5 - timeLeft
            })
        });
        
        const data = await response.json();
        
        // Sync Firebase APRÈS confirmation serveur
        if (typeof window.firebaseSyncAnswerAfterServer === 'function') {
            await window.firebaseSyncAnswerAfterServer(index, isCorrect, data);
        }
        
        setTimeout(() => {
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = answerConfig.routes.transition + 
                    '?correct=' + (isCorrect ? '1' : '0') + 
                    '&points=' + (data.points || 0);
            }
        }, 1000);
        
    } catch (error) {
        console.error('Answer error:', error);
        waitingOverlay.classList.remove('active');
    }
}

function handleTimeOut() {
    answered = true;
    
    answerButtons.forEach((btn, idx) => {
        btn.disabled = true;
        if (idx === answerConfig.correctIndex) {
            btn.classList.add('correct');
        }
    });
    
    const sound = document.getElementById('incorrectSound');
    if (sound) {
        sound.currentTime = 0;
        sound.play().catch(() => {});
    }
    
    setTimeout(() => {
        window.location.href = answerConfig.routes.transition + '?timeout=1&correct=0';
    }, 1500);
}

answerButtons.forEach((btn, index) => {
    btn.addEventListener('click', () => submitAnswer(index));
});

startAnswerTimer();
</script>

@if($isFirebaseMode)
<script src="/js/firebase-game-sync.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function() {
    const isFirebaseMode = {{ $isFirebaseMode ? 'true' : 'false' }};
    const matchId = '{{ $matchId ?? "" }}';
    const mode = '{{ $mode }}';
    const laravelUserId = '{{ auth()->id() }}';
    const isHost = {{ ($params['is_host'] ?? false) ? 'true' : 'false' }};
    
    if (!isFirebaseMode || !matchId) return;
    
    try {
        await FirebaseGameSync.init({
            matchId: matchId,
            mode: mode,
            laravelUserId: laravelUserId,
            isHost: isHost,
            callbacks: {
                onReady: () => {
                    console.log('[AnswersPage] Firebase ready');
                },
                onAnswerSubmit: (answer, data, isOpponentAnswer) => {
                    console.log('[AnswersPage] Answer submitted:', answer);
                    if (isOpponentAnswer) {
                        window.location.href = '{{ route("game.transition", ["mode" => $mode]) }}';
                    }
                },
                onPhaseChange: (phase, data) => {
                    console.log('[AnswersPage] Phase changed to:', phase);
                    if (phase === 'transition') {
                        window.location.href = '{{ route("game.transition", ["mode" => $mode]) }}';
                    }
                }
            }
        });
        
        window.firebaseSyncAnswerAfterServer = async function(answerIndex, isCorrect, serverResult) {
            if (FirebaseGameSync.isReady) {
                await FirebaseGameSync.sendAnswerAfterServerConfirm(
                    { answerIndex, isCorrect }, 
                    serverResult
                );
            }
        };
    } catch (error) {
        console.error('[AnswersPage] Firebase init error:', error);
    }
});
</script>
@endif

@endsection
