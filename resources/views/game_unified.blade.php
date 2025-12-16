@extends('layouts.app')

@section('content')
@php
$mode = $params['mode'] ?? 'solo';
$opponentType = $params['opponent_type'] ?? 'ai';
$opponentInfo = $params['opponent_info'] ?? [];
$currentQuestion = $params['current'] ?? 1;
$totalQuestions = $params['nb_questions'] ?? 10;
$niveau = $params['niveau'] ?? 1;
$theme = $params['theme'] ?? 'Culture générale';
$subTheme = $params['sub_theme'] ?? '';
$playerScore = $params['score'] ?? 0;
$opponentScore = $params['opponent_score'] ?? 0;
$currentRound = $params['current_round'] ?? 1;
$playerRoundsWon = $params['player_rounds_won'] ?? 0;
$opponentRoundsWon = $params['opponent_rounds_won'] ?? 0;
$scoring = $params['scoring'] ?? [];
$avatarName = $params['avatar'] ?? 'Aucun';
$avatarSkillsFull = $params['avatar_skills_full'] ?? ['rarity' => null, 'skills' => []];

$usedSkills = session('used_skills', []);
$skills = [];
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skillData) {
        $skillId = $skillData['id'];
        $isUsed = in_array($skillId, $usedSkills);
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
            'uses_left' => $maxUses > 0 ? max(0, $maxUses - $usesCount) : -1,
        ];
    }
}

$selectedAvatar = session('selected_avatar', 'default');
if (strpos($selectedAvatar, '/') !== false || strpos($selectedAvatar, 'images/') === 0) {
    $playerAvatarPath = asset($selectedAvatar);
} else {
    $playerAvatarPath = asset("images/avatars/standard/{$selectedAvatar}.png");
}

$strategicAvatarPath = '';
if ($avatarName !== 'Aucun') {
    $strategicAvatarSlug = strtolower($avatarName);
    $strategicAvatarSlug = str_replace(['é', 'è', 'ê'], 'e', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(['à', 'â'], 'a', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(' ', '-', $strategicAvatarSlug);
    $strategicAvatarPath = asset("images/avatars/{$strategicAvatarSlug}.png");
}

$opponentName = $opponentInfo['name'] ?? __('Adversaire');
$opponentAvatar = '';
$opponentDescription = '';

if ($opponentType === 'ai') {
    if ($opponentInfo['is_boss'] ?? false) {
        $opponentAvatar = asset("images/avatars/bosses/{$opponentInfo['avatar']}.png");
    } else {
        $opponentAge = $opponentInfo['age'] ?? 8;
        $nextBoss = $opponentInfo['next_boss'] ?? 'Le Stratège';
        $opponentAvatar = asset("images/avatars/students/{$opponentInfo['avatar']}.png");
        $opponentDescription = __('Votre adversaire') . " {$opponentName} {$opponentAge} " . __('ans élève du') . " {$nextBoss}";
    }
} else {
    $opponentAvatar = asset("images/avatars/standard/{$opponentInfo['avatar']}.png");
    $opponentDivision = $opponentInfo['division'] ?? 'Bronze';
    $opponentLevel = $opponentInfo['level'] ?? $opponentInfo['league_level'] ?? 1;
    $opponentDescription = "{$opponentDivision} - " . __('Niveau') . " {$opponentLevel}";
}

$isFirebaseMode = in_array($mode, ['duo', 'league_individual', 'master']);
$matchId = $params['match_id'] ?? null;
$roomCode = $params['room_code'] ?? null;
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
        padding-bottom: 200px;
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
    
    .round-indicator {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .round-dot {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: transparent;
    }
    
    .round-dot.player-won { background: #4ECDC4; border-color: #4ECDC4; }
    .round-dot.opponent-won { background: #FF6B6B; border-color: #FF6B6B; }
    .round-dot.current { border-color: #FFD700; box-shadow: 0 0 10px #FFD700; }
    
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
    
    .player-circle, .opponent-circle {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .player-avatar, .opponent-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .player-avatar {
        border: 3px solid #4ECDC4;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.5);
    }
    
    .opponent-avatar {
        border: 3px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
    }
    
    .opponent-avatar.human {
        border-color: #f39c12;
        box-shadow: 0 8px 30px rgba(243, 156, 18, 0.5);
    }
    
    .player-name { color: #4ECDC4; font-weight: 600; }
    .opponent-name { color: #FF6B6B; font-weight: 600; }
    .opponent-name.human { color: #f39c12; }
    
    .player-score, .opponent-score {
        font-size: 2rem;
        font-weight: 900;
    }
    
    .player-score { color: #4ECDC4; text-shadow: 0 0 20px rgba(78, 205, 196, 0.8); }
    .opponent-score { color: #FF6B6B; text-shadow: 0 0 20px rgba(255, 107, 107, 0.8); }
    .opponent-score.human { color: #f39c12; text-shadow: 0 0 20px rgba(243, 156, 18, 0.8); }
    
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
        0%, 100% { box-shadow: 0 15px 50px rgba(102, 126, 234, 0.6); }
        50% { box-shadow: 0 15px 70px rgba(102, 126, 234, 0.9); }
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
        cursor: pointer;
    }
    
    .skill-circle.active {
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.2);
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
    }
    
    .skill-circle.empty { opacity: 0.3; cursor: default; }
    .skill-circle.used { opacity: 0.5; cursor: default; }
    
    .opponent-strategic-indicator {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, #9B59B6, #8E44AD);
        border: 2px solid #FFD700;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #FFD700;
        font-weight: 900;
        box-shadow: 0 4px 15px rgba(155, 89, 182, 0.6);
        animation: strategic-pulse 2s ease-in-out infinite;
        z-index: 10;
    }
    
    @keyframes strategic-pulse {
        0%, 100% { transform: scale(1); box-shadow: 0 4px 15px rgba(155, 89, 182, 0.6); }
        50% { transform: scale(1.1); box-shadow: 0 6px 25px rgba(155, 89, 182, 0.9); }
    }
    
    .attack-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 9999;
        display: none;
    }
    
    .attack-overlay.active {
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 0, 0, 0.2);
        animation: attack-flash 0.5s ease-out;
    }
    
    .attack-overlay.blocked {
        background: rgba(78, 205, 196, 0.3);
        animation: block-flash 0.6s ease-out;
    }
    
    @keyframes attack-flash {
        0% { background: rgba(255, 0, 0, 0.5); }
        100% { background: rgba(255, 0, 0, 0); }
    }
    
    @keyframes block-flash {
        0% { background: rgba(78, 205, 196, 0.6); }
        50% { background: rgba(78, 205, 196, 0.3); }
        100% { background: rgba(78, 205, 196, 0); }
    }
    
    .attack-icon {
        font-size: 8rem;
        animation: attack-icon-anim 0.5s ease-out;
    }
    
    @keyframes attack-icon-anim {
        0% { transform: scale(0) rotate(-30deg); opacity: 0; }
        50% { transform: scale(1.2) rotate(10deg); opacity: 1; }
        100% { transform: scale(1) rotate(0deg); opacity: 0; }
    }
    
    .opponent-circle {
        position: relative;
    }
    
    .answers-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        max-width: 800px;
        margin: 20px auto;
        padding: 0 20px;
    }
    
    .answer-option {
        background: linear-gradient(135deg, #0074D9 0%, #005fa3 100%);
        border: none;
        border-radius: 15px;
        padding: 20px;
        color: white;
        font-size: 1.1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .answer-option:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 116, 217, 0.4);
    }
    
    .answer-option.correct {
        background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
    }
    
    .answer-option.incorrect {
        background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
    }
    
    .answer-option.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .buzz-container-bottom {
        position: fixed;
        bottom: calc(30px + env(safe-area-inset-bottom, 0px));
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .buzz-button {
        background: none;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease, opacity 0.3s ease, filter 0.3s ease;
        padding: 0;
        display: block;
    }
    
    /* Default state: buzzer visible but inactive (waiting for question) */
    .buzz-container-bottom.buzzer-waiting .buzz-button {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .buzz-container-bottom.buzzer-waiting .buzz-button img {
        filter: drop-shadow(0 5px 15px rgba(128, 128, 128, 0.4)) grayscale(0.5);
    }
    
    /* Active state: buzzer ready to press */
    .buzz-container-bottom.buzzer-ready .buzz-button {
        opacity: 1;
        cursor: pointer;
        pointer-events: auto;
        animation: buzzerPulse 0.4s ease-out;
    }
    
    @keyframes buzzerPulse {
        0% { transform: scale(0.9); opacity: 0.7; }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    .buzz-button:hover { transform: scale(1.05); }
    .buzz-button:active { transform: scale(0.95); }
    
    .buzz-button img {
        width: 180px;
        height: 180px;
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.6));
        transition: filter 0.3s ease;
    }
    
    .buzz-container-bottom.buzzer-ready .buzz-button img {
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.8));
    }
    
    /* Hidden state: after buzz/answer shown */
    .buzz-container-bottom.buzzer-hidden {
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }
    
    .waiting-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 200;
        flex-direction: column;
        gap: 20px;
    }
    
    .waiting-overlay.active { display: flex; }
    
    .waiting-text {
        font-size: 1.5rem;
        color: #4ECDC4;
    }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 4px solid rgba(255, 255, 255, 0.2);
        border-top-color: #4ECDC4;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .firebase-status {
        position: fixed;
        bottom: 10px;
        right: 10px;
        padding: 5px 10px;
        border-radius: 10px;
        font-size: 0.75rem;
        background: rgba(0, 0, 0, 0.5);
    }
    
    .firebase-status.connected { color: #2ECC71; }
    .firebase-status.disconnected { color: #E74C3C; }
    
    @media (max-width: 768px) {
        .game-layout { gap: 15px; }
        .player-avatar, .opponent-avatar { width: 70px; height: 70px; }
        .chrono-circle { width: 120px; height: 120px; }
        .chrono-time { font-size: 2.5rem; }
        .buzz-button img { width: 130px; height: 130px; }
        .answers-grid { grid-template-columns: 1fr; gap: 10px; padding: 0 10px; }
        .question-header { padding: 15px 10px; }
        .question-text { font-size: 1.1rem; }
        .mode-indicator { top: 5px; right: 5px; padding: 5px 10px; font-size: 0.75rem; }
        .game-container { gap: 15px; padding-bottom: 160px; }
        .buzz-container-bottom { bottom: calc(15px + env(safe-area-inset-bottom, 0px)); }
    }
    
    @media (max-width: 480px) {
        body { padding: 5px; }
        .game-layout { 
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }
        .player-avatar, .opponent-avatar { width: 55px; height: 55px; }
        .chrono-circle { width: 90px; height: 90px; }
        .chrono-time { font-size: 2rem; }
        .buzz-button img { width: 110px; height: 110px; }
        .question-header { padding: 10px 8px; margin-bottom: 5px; }
        .question-text { font-size: 1rem; }
        .question-number { font-size: 0.75rem; margin-bottom: 8px; }
        .game-container { gap: 10px; padding-bottom: calc(140px + env(safe-area-inset-bottom, 0px)); min-height: calc(100dvh - 10px); }
        .buzz-container-bottom { bottom: calc(10px + env(safe-area-inset-bottom, 0px)); }
        .player-name, .opponent-name { font-size: 0.85rem; }
        .player-score, .opponent-score { font-size: 1.5rem; }
        .skill-bar { gap: 5px; }
        .skill-icon { font-size: 1.2rem; }
    }
    
    @media (max-height: 700px) and (orientation: portrait) {
        .buzz-button img { width: 100px; height: 100px; }
        .game-container { padding-bottom: calc(120px + env(safe-area-inset-bottom, 0px)); }
        .buzz-container-bottom { bottom: calc(8px + env(safe-area-inset-bottom, 0px)); }
        .chrono-circle { width: 80px; height: 80px; }
        .chrono-time { font-size: 1.8rem; }
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
        <div class="round-indicator">
            @for($i = 1; $i <= 3; $i++)
                @php
                    $dotClass = '';
                    if ($i <= $playerRoundsWon) $dotClass = 'player-won';
                    elseif ($i <= ($playerRoundsWon + $opponentRoundsWon) && $i > $playerRoundsWon) $dotClass = 'opponent-won';
                    if ($i === $currentRound) $dotClass .= ' current';
                @endphp
                <div class="round-dot {{ $dotClass }}"></div>
            @endfor
        </div>
        
        <div class="question-number" id="questionNumber">
            {{ $theme }} @if($subTheme)- {{ $subTheme }}@endif | {{ __('Question') }} {{ $currentQuestion }}/{{ $totalQuestions }}
        </div>
        
        <div class="question-text" id="questionText">
            {{ $params['question_text'] ?? __('Chargement de la question...') }}
        </div>
    </div>
    
    <div class="game-layout">
        <div class="left-column">
            <div class="player-circle">
                <img src="{{ $playerAvatarPath }}" alt="Joueur" class="player-avatar">
                <div class="player-name">{{ auth()->user()->name }}</div>
                <div class="player-score" id="playerScore">{{ $playerScore }}</div>
            </div>
            
            <div class="opponent-circle">
                @if($opponentAvatar)
                    <img src="{{ $opponentAvatar }}" alt="Adversaire" class="opponent-avatar {{ $opponentType === 'human' ? 'human' : '' }}">
                @else
                    <div class="opponent-avatar {{ $opponentType === 'human' ? 'human' : '' }}" style="display: flex; align-items: center; justify-content: center; background: rgba(255, 107, 107, 0.2); font-size: 2rem;">
                        {{ $opponentType === 'human' ? '👤' : '🤖' }}
                    </div>
                @endif
                @if($opponentType === 'human' && ($params['opponent_has_strategic'] ?? false))
                    <div class="opponent-strategic-indicator" title="{{ __('Adversaire avec avatar stratégique') }}">?</div>
                @endif
                @if($mode === 'duo')
                    <div id="opponentSpeakingIndicator" class="opponent-speaking-indicator">🎤</div>
                @endif
                <div class="opponent-name {{ $opponentType === 'human' ? 'human' : '' }}">{{ $opponentName }}</div>
                @if($opponentDescription)
                    <div style="font-size: 0.75rem; opacity: 0.8; text-align: center;">{{ $opponentDescription }}</div>
                @endif
                <div class="opponent-score {{ $opponentType === 'human' ? 'human' : '' }}" id="opponentScore">{{ $opponentScore }}</div>
            </div>
        </div>
        
        <div class="center-column">
            <div class="chrono-circle">
                <div class="chrono-time" id="chronoTimer">8</div>
            </div>
        </div>
        
        <div class="right-column">
            @if($avatarName !== 'Aucun' && $strategicAvatarPath)
                <div class="strategic-avatar-circle">
                    <img src="{{ $strategicAvatarPath }}" alt="Avatar stratégique" class="strategic-avatar-image">
                </div>
            @else
                <div class="strategic-avatar-circle empty"></div>
            @endif
            
            <div class="skills-container">
                @for($i = 0; $i < 3; $i++)
                    @if(isset($skills[$i]))
                        @php
                            $skill = $skills[$i];
                            $isUsed = $skill['used'];
                            $isAuto = $skill['auto'];
                            $isDisabled = $isUsed || $isAuto;
                        @endphp
                        <div class="skill-circle {{ $isUsed ? 'used' : 'active' }} {{ $isAuto ? 'auto' : 'clickable' }}" 
                             data-skill-id="{{ $skill['id'] }}"
                             data-skill-type="{{ $skill['type'] ?? 'personal' }}"
                             data-skill-trigger="{{ $skill['trigger'] }}"
                             data-affects-opponent="{{ ($skill['affects_opponent'] ?? false) ? 'true' : 'false' }}"
                             title="{{ $skill['name'] }}: {{ $skill['description'] }}">
                            {{ $skill['icon'] }}
                        </div>
                    @else
                        <div class="skill-circle empty"></div>
                    @endif
                @endfor
            </div>
        </div>
    </div>
    
    <div class="answers-grid" id="answersGrid" style="display: none;">
        @foreach($params['answers'] ?? [] as $index => $answer)
            <button class="answer-option" 
                    data-index="{{ $index }}" 
                    data-correct="{{ $index === ($params['correct_answer_index'] ?? 0) ? 'true' : 'false' }}">
                {{ $answer['text'] ?? $answer }}
            </button>
        @endforeach
    </div>
    
    <div class="buzz-container-bottom buzzer-waiting" id="buzzContainer">
        <button id="buzzButton" class="buzz-button" disabled>
            <img src="{{ asset('images/buzzer.png') }}" alt="Strategy Buzzer">
        </button>
    </div>
</div>

<div class="waiting-overlay" id="waitingOverlay">
    <div class="spinner"></div>
    <div class="waiting-text" id="waitingText">{{ __('En attente de l\'adversaire...') }}</div>
</div>

@if($isFirebaseMode)
    <div class="firebase-status disconnected" id="firebaseStatus">
        {{ __('Connexion...') }}
    </div>
@endif

<div class="attack-overlay" id="attackOverlay">
    <div class="attack-icon" id="attackIcon">⚔️</div>
</div>

@if($mode === 'duo')
<!-- Contrôles communication Duo - disponibles en permanence -->
<div id="duoCommFloating" class="duo-comm-floating">
    <!-- Bouton Micro -->
    <button id="duoMicToggleBtn" class="duo-mic-toggle-btn" onclick="toggleDuoMic()">
        🎤
        <span id="duoMicStatus" class="duo-mic-status">OFF</span>
    </button>
    <!-- Bouton Chat -->
    <button id="duoChatToggleBtn" class="duo-chat-toggle-btn" onclick="toggleDuoChatPanel()">
        💬
        <span id="duoChatUnreadBadge" class="duo-chat-unread" style="display: none;">0</span>
    </button>
    <div id="duoChatPanel" class="duo-chat-panel" style="display: none;">
        <div class="duo-chat-panel-header">
            <span>💬 {{ __('Chat') }}</span>
            <button onclick="toggleDuoChatPanel()">&times;</button>
        </div>
        <div id="duoChatMessages" class="duo-chat-panel-messages"></div>
        <div class="duo-chat-panel-input">
            <input type="text" id="duoChatInput" placeholder="{{ __('Message...') }}" maxlength="200" onkeypress="if(event.key==='Enter')sendDuoChatMessage()">
            <button onclick="sendDuoChatMessage()">➤</button>
        </div>
    </div>
</div>

<style>
.duo-comm-floating {
    position: fixed;
    bottom: 80px;
    left: 15px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.duo-mic-toggle-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    color: white;
    font-size: 1.3rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    position: relative;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.duo-mic-toggle-btn.active {
    background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
    box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
}

.duo-mic-toggle-btn.speaking {
    animation: micPulse 0.5s infinite;
}

@keyframes micPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); }
    50% { box-shadow: 0 0 0 10px rgba(46, 204, 113, 0); }
}

.duo-mic-status {
    font-size: 0.5rem;
    font-weight: bold;
    margin-top: 2px;
}

.duo-chat-toggle-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    position: relative;
    transition: transform 0.2s;
}

.duo-chat-toggle-btn:hover {
    transform: scale(1.1);
}

/* Indicateur de parole de l'adversaire */
.opponent-speaking-indicator {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 20px;
    height: 20px;
    background: #2ECC71;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    animation: speakPulse 0.6s infinite;
}

.opponent-speaking-indicator.active {
    display: flex;
}

@keyframes speakPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.duo-chat-unread {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #E74C3C;
    color: white;
    font-size: 0.7rem;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.duo-chat-panel {
    position: absolute;
    bottom: 60px;
    left: 0;
    width: 280px;
    max-height: 350px;
    background: rgba(20, 20, 40, 0.95);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(102, 126, 234, 0.3);
    display: flex;
    flex-direction: column;
}

.duo-chat-panel-header {
    padding: 10px 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    font-weight: 600;
}

.duo-chat-panel-header button {
    background: none;
    border: none;
    color: white;
    font-size: 1.3rem;
    cursor: pointer;
}

.duo-chat-panel-messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    max-height: 220px;
    min-height: 100px;
}

.duo-chat-msg {
    padding: 6px 10px;
    margin-bottom: 6px;
    border-radius: 8px;
    font-size: 0.85em;
    max-width: 85%;
    word-wrap: break-word;
}

.duo-chat-msg.mine {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin-left: auto;
    text-align: right;
}

.duo-chat-msg.theirs {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    margin-right: auto;
}

.duo-chat-panel-input {
    display: flex;
    padding: 8px;
    gap: 6px;
    background: rgba(0, 0, 0, 0.3);
}

.duo-chat-panel-input input {
    flex: 1;
    padding: 8px 10px;
    border: none;
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.9);
    font-size: 0.85em;
}

.duo-chat-panel-input button {
    padding: 8px 12px;
    border: none;
    border-radius: 15px;
    background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    color: white;
    font-weight: bold;
    cursor: pointer;
}

@media (max-width: 768px) {
    .duo-comm-floating { bottom: 70px; left: 10px; }
    .duo-chat-panel { width: 250px; }
}
</style>

<!-- WebRTC Voice Chat pour Duo -->
<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getAuth, signInAnonymously, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';
import { getFirestore, doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, setDoc, serverTimestamp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bagWp_dHw",
    authDomain: "strategybuzzergame.firebaseapp.com",
    projectId: "strategybuzzergame",
    storageBucket: "strategybuzzergame.appspot.com",
    messagingSenderId: "68047817391",
    appId: "1:68047817391:web:ba6b3bc148ef187bfeae9a"
};

const app = initializeApp(firebaseConfig, 'webrtc-game');
const auth = getAuth(app);
const db = getFirestore(app);

const matchId = '{{ $matchId ?? '' }}';
const currentPlayerId = {{ auth()->id() }};
const opponentId = {{ $params['opponent_info']['user_id'] ?? 0 }};

let voiceChat = null;
let firebaseAuthReady = false;

function initVoiceChat() {
    if (!firebaseAuthReady || voiceChat) return;
    
    voiceChat = new GameVoiceChat();
    window.duoVoiceChat = voiceChat;
    
    if (matchId && opponentId) {
        voiceChat.startVoiceChat().then(() => {
            console.log('[Voice] Ready - click mic to unmute');
        });
    }
}

onAuthStateChanged(auth, (user) => {
    if (user) {
        console.log('[Firebase] Authenticated for WebRTC');
        firebaseAuthReady = true;
        initVoiceChat();
    }
});

signInAnonymously(auth).catch(e => console.error('[Firebase] Auth error:', e));

class GameVoiceChat {
    constructor() {
        this.peerConnection = null;
        this.localStream = null;
        this.remoteAudio = null;
        this.isMuted = true;
        this.audioContext = null;
        this.analyser = null;
        this.unsubscribers = [];
        
        this.iceServers = [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'turn:global.relay.metered.ca:80', username: 'free', credential: 'free' },
            { urls: 'turn:global.relay.metered.ca:443', username: 'free', credential: 'free' }
        ];
    }
    
    getSignalingPath() {
        return `gameSessions/${matchId}/webrtc`;
    }
    
    getPresencePath() {
        return `gameSessions/${matchId}/voice_presence`;
    }
    
    async startVoiceChat() {
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({ 
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } 
            });
            
            this.localStream.getAudioTracks().forEach(t => t.enabled = false);
            this.setupVoiceActivityDetection();
            this.listenForSignaling();
            this.listenForPresence();
            
            if (currentPlayerId < opponentId) {
                await this.createPeerConnection(true);
            }
            
            console.log('[Voice] Chat initialized');
            return true;
        } catch (error) {
            console.error('[Voice] Failed to start:', error);
            return false;
        }
    }
    
    setupVoiceActivityDetection() {
        if (!this.localStream) return;
        
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const source = this.audioContext.createMediaStreamSource(this.localStream);
        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 512;
        source.connect(this.analyser);
        
        const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
        let speaking = false;
        
        const checkLevel = () => {
            if (!this.analyser || this.isMuted) {
                if (speaking) {
                    speaking = false;
                    this.updateMicUI(false);
                }
                requestAnimationFrame(checkLevel);
                return;
            }
            
            this.analyser.getByteFrequencyData(dataArray);
            const avg = dataArray.reduce((a, b) => a + b, 0) / dataArray.length;
            const isSpeaking = avg > 15;
            
            if (isSpeaking !== speaking) {
                speaking = isSpeaking;
                this.updateMicUI(speaking);
                this.updatePresence(!this.isMuted, speaking);
            }
            
            requestAnimationFrame(checkLevel);
        };
        
        checkLevel();
    }
    
    updateMicUI(speaking) {
        const btn = document.getElementById('duoMicToggleBtn');
        if (btn) {
            btn.classList.toggle('speaking', speaking && !this.isMuted);
        }
    }
    
    async updatePresence(micEnabled, speaking) {
        try {
            const presenceRef = doc(db, this.getPresencePath(), String(currentPlayerId));
            await setDoc(presenceRef, {
                odPlayerId: currentPlayerId,
                muted: !micEnabled,
                speaking: speaking,
                updatedAt: serverTimestamp()
            }, { merge: true });
        } catch (e) { console.error('[Voice] Presence error:', e); }
    }
    
    listenForPresence() {
        const presenceRef = collection(db, this.getPresencePath());
        
        const unsubscribe = onSnapshot(presenceRef, (snapshot) => {
            snapshot.docChanges().forEach((change) => {
                const data = change.doc.data();
                const odPlayerId = data.odPlayerId || parseInt(change.doc.id);
                
                if (odPlayerId === currentPlayerId) return;
                
                if (change.type === 'added' || change.type === 'modified') {
                    const indicator = document.getElementById('opponentSpeakingIndicator');
                    if (indicator) {
                        indicator.classList.toggle('active', data.speaking && !data.muted);
                    }
                    
                    if (!this.peerConnection && !data.muted) {
                        this.createPeerConnection(currentPlayerId < opponentId);
                    }
                } else if (change.type === 'removed') {
                    const indicator = document.getElementById('opponentSpeakingIndicator');
                    if (indicator) indicator.classList.remove('active');
                }
            });
        });
        
        this.unsubscribers.push(unsubscribe);
    }
    
    listenForSignaling() {
        const signalingRef = collection(db, this.getSignalingPath());
        const q = query(signalingRef, where('to', '==', currentPlayerId));
        
        const unsubscribe = onSnapshot(q, (snapshot) => {
            snapshot.docChanges().forEach(async (change) => {
                if (change.type !== 'added') return;
                
                const data = change.doc.data();
                
                try {
                    if (data.type === 'offer') {
                        await this.handleOffer(data.sdp);
                    } else if (data.type === 'answer') {
                        await this.handleAnswer(data.sdp);
                    } else if (data.type === 'candidate') {
                        await this.handleCandidate(data.candidate);
                    }
                } finally {
                    await deleteDoc(change.doc.ref);
                }
            });
        });
        
        this.unsubscribers.push(unsubscribe);
    }
    
    async createPeerConnection(initiator) {
        if (this.peerConnection) return;
        
        const pc = new RTCPeerConnection({ iceServers: this.iceServers });
        this.peerConnection = pc;
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => pc.addTrack(track, this.localStream));
        }
        
        pc.ontrack = (event) => {
            if (event.streams && event.streams[0]) {
                this.handleRemoteTrack(event.streams[0]);
            }
        };
        
        pc.onicecandidate = async (event) => {
            if (event.candidate) {
                await this.sendSignal('candidate', null, event.candidate.toJSON());
            }
        };
        
        pc.onconnectionstatechange = () => {
            console.log('[Voice] Connection state:', pc.connectionState);
        };
        
        if (initiator) {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await this.sendSignal('offer', offer.sdp);
        }
    }
    
    async handleOffer(sdp) {
        if (!this.peerConnection) await this.createPeerConnection(false);
        
        await this.peerConnection.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp }));
        const answer = await this.peerConnection.createAnswer();
        await this.peerConnection.setLocalDescription(answer);
        await this.sendSignal('answer', answer.sdp);
    }
    
    async handleAnswer(sdp) {
        if (!this.peerConnection) return;
        await this.peerConnection.setRemoteDescription(new RTCSessionDescription({ type: 'answer', sdp }));
    }
    
    async handleCandidate(candidateData) {
        if (!this.peerConnection) return;
        await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidateData));
    }
    
    async sendSignal(type, sdp = null, candidate = null) {
        try {
            await addDoc(collection(db, this.getSignalingPath()), {
                from: currentPlayerId,
                to: opponentId,
                type: type,
                sdp: sdp,
                candidate: candidate,
                createdAt: serverTimestamp()
            });
        } catch (e) { console.error('[Voice] Signal error:', e); }
    }
    
    handleRemoteTrack(stream) {
        if (!this.remoteAudio) {
            this.remoteAudio = document.createElement('audio');
            this.remoteAudio.autoplay = true;
            this.remoteAudio.playsInline = true;
            this.remoteAudio.style.display = 'none';
            document.body.appendChild(this.remoteAudio);
        }
        
        this.remoteAudio.srcObject = stream;
        this.remoteAudio.play().catch(e => {
            document.addEventListener('click', () => this.remoteAudio.play(), { once: true });
        });
    }
    
    toggleMute() {
        this.isMuted = !this.isMuted;
        
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach(t => t.enabled = !this.isMuted);
        }
        
        const btn = document.getElementById('duoMicToggleBtn');
        const status = document.getElementById('duoMicStatus');
        
        if (btn) btn.classList.toggle('active', !this.isMuted);
        if (status) status.textContent = this.isMuted ? 'OFF' : 'ON';
        
        this.updatePresence(!this.isMuted, false);
        
        return !this.isMuted;
    }
    
    async cleanup() {
        this.unsubscribers.forEach(u => u());
        
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(t => t.stop());
            this.localStream = null;
        }
        
        if (this.remoteAudio) {
            this.remoteAudio.srcObject = null;
            this.remoteAudio.remove();
        }
        
        if (this.audioContext) {
            this.audioContext.close();
        }
        
        try {
            await deleteDoc(doc(db, this.getPresencePath(), String(currentPlayerId)));
        } catch (e) {}
    }
}

window.toggleDuoMic = async function() {
    if (!voiceChat) return;
    if (!voiceChat.localStream) {
        const started = await voiceChat.startVoiceChat();
        if (started) {
            voiceChat.toggleMute();
        }
    } else {
        voiceChat.toggleMute();
    }
};

window.addEventListener('beforeunload', () => {
    if (voiceChat) voiceChat.cleanup();
});
</script>
@endif

<audio id="swordAttackSound" preload="auto">
    <source src="{{ asset('sounds/sword_swish.wav') }}" type="audio/wav">
</audio>
<audio id="swordBlockSound" preload="auto">
    <source src="{{ asset('sounds/sword_shield.wav') }}" type="audio/wav">
</audio>

<audio id="buzzerSound" preload="auto">
    <source id="buzzerSource" src="{{ asset('sounds/buzzer_default_1.mp3') }}" type="audio/mpeg">
</audio>
<audio id="noBuzzSound" preload="auto">
    <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
</audio>
<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
</audio>
<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
</audio>

<script>
const gameConfig = {
    mode: '{{ $mode }}',
    opponentType: '{{ $opponentType }}',
    isFirebaseMode: {{ $isFirebaseMode ? 'true' : 'false' }},
    matchId: '{{ $matchId ?? '' }}',
    roomCode: '{{ $roomCode ?? '' }}',
    playerId: '{{ auth()->id() }}',
    opponentId: '{{ $params['opponent_info']['user_id'] ?? '' }}',
    currentQuestion: {{ $currentQuestion }},
    totalQuestions: {{ $totalQuestions }},
    currentRound: {{ $currentRound }},
    csrfToken: '{{ csrf_token() }}',
    routes: {
        buzz: '/game/{{ $mode }}/buzz',
        answer: '/game/{{ $mode }}/answer',
        roundResult: '/game/{{ $mode }}/round-result',
        matchResult: '/game/{{ $mode }}/match-result',
        sync: '/game/{{ $mode }}/sync',
    }
};

let timeLeft = 8;
let timerInterval;
let buzzed = false;
let answersShown = false;
let playerBuzzTime = null;

const buzzButton = document.getElementById('buzzButton');
const buzzContainer = document.getElementById('buzzContainer');
const answersGrid = document.getElementById('answersGrid');
const chronoTimer = document.getElementById('chronoTimer');
const buzzerSound = document.getElementById('buzzerSound');
const waitingOverlay = document.getElementById('waitingOverlay');

const selectedBuzzer = localStorage.getItem('selectedBuzzer') || 'buzzer_default_1';
document.getElementById('buzzerSource').src = `/sounds/${selectedBuzzer}.mp3`;
buzzerSound.load();

function startTimer() {
    // Activer le buzzer quand le timer démarre
    const buzzContainer = document.getElementById('buzzContainer');
    const buzzButton = document.getElementById('buzzButton');
    if (buzzContainer) {
        buzzContainer.classList.remove('buzzer-waiting', 'buzzer-hidden');
        buzzContainer.classList.add('buzzer-ready');
    }
    if (buzzButton) {
        buzzButton.disabled = false;
    }
    
    timerInterval = setInterval(() => {
        timeLeft--;
        chronoTimer.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            if (!buzzed) {
                handleTimeout();
            }
        }
    }, 1000);
}

buzzButton.addEventListener('click', function() {
    if (buzzed) return;
    
    buzzed = true;
    playerBuzzTime = 8 - timeLeft;
    clearInterval(timerInterval);
    
    buzzerSound.currentTime = 0;
    buzzerSound.play();
    
    buzzButton.disabled = true;
    
    if (gameConfig.isFirebaseMode) {
        sendBuzzToServer();
    } else {
        setTimeout(() => {
            showAnswers();
        }, 1500);
    }
});

function sendBuzzToServer() {
    fetch(gameConfig.routes.buzz, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': gameConfig.csrfToken
        },
        body: JSON.stringify({
            buzz_time: playerBuzzTime
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('[Buzz] Server response:', data);
        if (data.show_answers || !data.waiting_for_opponent) {
            setTimeout(() => {
                showAnswers();
            }, 500);
        } else if (data.waiting_for_opponent) {
            showWaitingOverlay('{{ __("En attente de l\'adversaire...") }}');
        }
    })
    .catch(error => {
        console.error('Buzz error:', error);
        showAnswers();
    });
}

function showAnswers() {
    if (answersShown) return;
    answersShown = true;
    
    // Use classes for buzzer visibility instead of display:none
    buzzContainer.classList.remove('buzzer-ready', 'buzzer-waiting');
    buzzContainer.classList.add('buzzer-hidden');
    answersGrid.style.display = 'grid';
    
    const answerButtons = answersGrid.querySelectorAll('.answer-option');
    answerButtons.forEach((btn, index) => {
        btn.addEventListener('click', () => handleAnswerClick(btn, index));
    });
}

function handleAnswerClick(button, index) {
    const isCorrect = button.dataset.correct === 'true';
    
    answersGrid.querySelectorAll('.answer-option').forEach(btn => {
        btn.classList.add('disabled');
        if (btn.dataset.correct === 'true') {
            btn.classList.add('correct');
        }
    });
    
    if (!isCorrect) {
        button.classList.add('incorrect');
    }
    
    const sound = isCorrect ? document.getElementById('correctSound') : document.getElementById('incorrectSound');
    sound.currentTime = 0;
    sound.play().catch(e => console.log('Sound error:', e));
    
    submitAnswer(index, isCorrect);
}

function submitAnswer(answerIndex, isCorrect) {
    if (gameConfig.isFirebaseMode) {
        showWaitingOverlay('{{ __("En attente du résultat...") }}');
    }
    
    fetch(gameConfig.routes.answer, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': gameConfig.csrfToken
        },
        body: JSON.stringify({
            answer_id: answerIndex,
            is_correct: isCorrect,
            buzz_time: playerBuzzTime
        })
    })
    .then(response => response.json())
    .then(data => {
        hideWaitingOverlay();
        
        document.getElementById('playerScore').textContent = data.player_score;
        
        if (data.opponent) {
            document.getElementById('opponentScore').textContent = data.opponent.opponent_score;
        }
        
        setTimeout(() => {
            if (data.has_next_question) {
                if (gameConfig.isFirebaseMode && window.GameFlowController) {
                    GameFlowController.advanceToNextQuestion();
                } else {
                    window.location.reload();
                }
            } else {
                window.location.href = gameConfig.routes.roundResult;
            }
        }, 2000);
    })
    .catch(error => {
        console.error('Answer error:', error);
        hideWaitingOverlay();
    });
}

const GameFlowController = {
    isHost: {{ ($params['is_host'] ?? false) ? 'true' : 'false' }},
    lastQuestionNumber: {{ $currentQuestion }},
    
    async advanceToNextQuestion() {
        const nextQ = this.lastQuestionNumber + 1;
        console.log('[GameFlow] Advancing to question', nextQ, 'isHost:', this.isHost);
        
        if (this.isHost) {
            showWaitingOverlay('{{ __("Chargement de la question...") }}');
            
            await this.waitForFirebaseReady();
            
            const questionData = await this.fetchQuestion(nextQ);
            if (questionData && questionData.success) {
                await this.publishQuestionToFirestore(nextQ, questionData);
                this.lastQuestionNumber = nextQ;
            } else {
                console.error('[GameFlow] Failed to fetch question');
                window.location.reload();
            }
        } else {
            showWaitingOverlay('{{ __("En attente de la question...") }}');
        }
    },
    
    async waitForFirebaseReady(maxWait = 5000) {
        if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
            return true;
        }
        
        const startTime = Date.now();
        while (Date.now() - startTime < maxWait) {
            if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
                return true;
            }
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        console.warn('[GameFlow] Firebase not ready after', maxWait, 'ms');
        return false;
    },
    
    async fetchQuestion(questionNumber) {
        try {
            const response = await fetch('/game/{{ $mode }}/fetch-question', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': gameConfig.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    question_number: questionNumber
                })
            });
            
            if (!response.ok) throw new Error('Fetch failed');
            return await response.json();
        } catch (error) {
            console.error('[GameFlow] fetchQuestion error:', error);
            return null;
        }
    },
    
    async publishQuestionToFirestore(questionNumber, questionData) {
        if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
            await FirebaseGameSync.publishQuestion(questionNumber, questionData);
            console.log('[GameFlow] Question published to Firestore');
            this.displayQuestion(questionData);
        } else {
            console.error('[GameFlow] FirebaseGameSync not ready');
            window.location.reload();
        }
    },
    
    displayQuestion(questionData) {
        hideWaitingOverlay();
        
        this.resetGameState();
        
        gameConfig.currentQuestion = questionData.question_number;
        this.lastQuestionNumber = questionData.question_number;
        
        document.getElementById('questionText').textContent = questionData.question_text;
        document.getElementById('questionNumber').textContent = 
            `${questionData.theme}${questionData.sub_theme ? ' - ' + questionData.sub_theme : ''} | {{ __("Question") }} ${questionData.question_number}/${questionData.total_questions}`;
        
        const grid = document.getElementById('answersGrid');
        grid.innerHTML = '';
        grid.style.display = 'none';
        
        questionData.answers.forEach((answer, idx) => {
            const btn = document.createElement('button');
            btn.className = 'answer-option';
            btn.dataset.index = idx;
            btn.textContent = answer.text;
            btn.addEventListener('click', function() {
                if (btn.classList.contains('disabled')) return;
                
                grid.querySelectorAll('.answer-option').forEach(b => b.classList.add('disabled'));
                
                fetch(gameConfig.routes.answer, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': gameConfig.csrfToken
                    },
                    body: JSON.stringify({
                        answer_id: idx,
                        is_correct: false,
                        buzz_time: playerBuzzTime || 0
                    })
                })
                .then(r => r.json())
                .then(data => {
                    const isCorrect = data.is_correct || data.was_correct;
                    
                    if (isCorrect) {
                        btn.classList.add('correct');
                        document.getElementById('correctSound')?.play().catch(() => {});
                    } else {
                        btn.classList.add('incorrect');
                        document.getElementById('incorrectSound')?.play().catch(() => {});
                        grid.querySelectorAll('.answer-option').forEach((b, i) => {
                            if (i === data.correct_index) b.classList.add('correct');
                        });
                    }
                    
                    document.getElementById('playerScore').textContent = data.player_score;
                    if (data.opponent) {
                        document.getElementById('opponentScore').textContent = data.opponent.opponent_score;
                    }
                    
                    setTimeout(() => {
                        if (data.has_next_question) {
                            GameFlowController.advanceToNextQuestion();
                        } else {
                            window.location.href = gameConfig.routes.roundResult;
                        }
                    }, 2000);
                })
                .catch(e => console.error('Answer error:', e));
            });
            grid.appendChild(btn);
        });
        
        startTimer();
    },
    
    resetGameState() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        buzzed = false;
        answersShown = false;
        playerBuzzTime = null;
        timeLeft = 8;
        
        const buzzContainer = document.getElementById('buzzContainer');
        const buzzButton = document.getElementById('buzzButton');
        const chronoTimer = document.getElementById('chronoTimer');
        const grid = document.getElementById('answersGrid');
        
        // Use classes for buzzer visibility - set to waiting first, will be activated by timer start
        if (buzzContainer) {
            buzzContainer.classList.remove('buzzer-hidden', 'buzzer-ready');
            buzzContainer.classList.add('buzzer-waiting');
        }
        if (buzzButton) {
            buzzButton.disabled = true; // Keep disabled until timer starts
        }
        if (chronoTimer) chronoTimer.textContent = '8';
        if (grid) {
            grid.style.display = 'none';
            grid.querySelectorAll('.answer-option').forEach(btn => {
                btn.classList.remove('correct', 'incorrect', 'disabled');
            });
        }
    },
    
    onQuestionDataReceived(questionData, questionNumber) {
        console.log('[GameFlow] Question received from Firestore:', questionNumber);
        if (questionNumber > this.lastQuestionNumber) {
            this.lastQuestionNumber = questionNumber;
            this.displayQuestion({
                question_number: questionNumber,
                question_text: questionData.question_text,
                answers: questionData.answers.map((a, i) => ({
                    text: a.text || a,
                    is_correct: i === questionData.correct_index
                })),
                theme: questionData.theme || '{{ $theme }}',
                sub_theme: questionData.sub_theme || '',
                total_questions: gameConfig.totalQuestions
            });
        }
    }
};

window.GameFlowController = GameFlowController;

function handleTimeout() {
    document.getElementById('noBuzzSound').play().catch(e => console.log('Sound error:', e));
    
    setTimeout(() => {
        submitAnswer(-1, false);
    }, 2000);
}

function showWaitingOverlay(text) {
    document.getElementById('waitingText').textContent = text;
    waitingOverlay.classList.add('active');
}

function hideWaitingOverlay() {
    waitingOverlay.classList.remove('active');
}

@if($isFirebaseMode)
import('https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js').then(({ initializeApp }) => {
    Promise.all([
        import('https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js'),
        import('https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js')
    ]).then(([{ getAuth, signInAnonymously }, { getFirestore, doc, onSnapshot }]) => {
        const firebaseConfig = {
            apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bagWp_dHw",
            authDomain: "strategybuzzergame.firebaseapp.com",
            projectId: "strategybuzzergame",
            storageBucket: "strategybuzzergame.appspot.com",
            messagingSenderId: "68047817391",
            appId: "1:68047817391:web:ba6b3bc148ef187bfeae9a"
        };
        
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const db = getFirestore(app);
        
        signInAnonymously(auth).then(() => {
            console.log('[Firebase] Anonymous auth successful');
            
            const statusEl = document.getElementById('firebaseStatus');
            statusEl.textContent = '{{ __("Connecté") }}';
            statusEl.classList.remove('disconnected');
            statusEl.classList.add('connected');
            
            if (gameConfig.matchId) {
                const firestoreGameId = 'duo-match-' + gameConfig.matchId;
                const matchRef = doc(db, 'games', firestoreGameId);
                
                console.log('[Firebase] Listening to:', firestoreGameId);
                
                onSnapshot(matchRef, (snapshot) => {
                    if (snapshot.exists()) {
                        const data = snapshot.data();
                        console.log('[Firebase] Game state update:', data);
                        handleFirebaseUpdate(data);
                    }
                }, (error) => {
                    console.error('[Firebase] Listener error:', error);
                });
            }
        }).catch(e => console.error('[Firebase] Auth error:', e));
    });
});

let lastQuestionPublishedAt = 0;

function handleFirebaseUpdate(data) {
    const isPlayer1 = gameConfig.playerId === (data.player1Id || '').toString();
    const isPlayer2 = gameConfig.playerId === (data.player2Id || '').toString();
    
    const questionPublishedAt = data.questionPublishedAt || 0;
    if (data.currentQuestionData && questionPublishedAt > lastQuestionPublishedAt) {
        lastQuestionPublishedAt = questionPublishedAt;
        const qNum = data.currentQuestion || 1;
        console.log('[Firebase] New question detected via timestamp:', qNum, 'at', questionPublishedAt);
        if (qNum >= GameFlowController.lastQuestionNumber) {
            GameFlowController.onQuestionDataReceived(data.currentQuestionData, qNum);
            return;
        }
    }
    
    let opponentBuzzed = false;
    if (isPlayer1 && data.player2Buzzed) {
        opponentBuzzed = true;
    } else if (isPlayer2 && data.player1Buzzed) {
        opponentBuzzed = true;
    } else if (data.buzzedPlayerId && data.buzzedPlayerId !== gameConfig.playerId) {
        opponentBuzzed = true;
    }
    
    if (opponentBuzzed && !answersShown) {
        console.log('[Firebase] Opponent buzzed - showing answers');
        hideWaitingOverlay();
        showAnswers();
    }
    
    if (isPlayer1 && data.player2Score !== undefined) {
        document.getElementById('opponentScore').textContent = data.player2Score;
    } else if (isPlayer2 && data.player1Score !== undefined) {
        document.getElementById('opponentScore').textContent = data.player1Score;
    }
    
    if (data.incoming_attack && data.incoming_attack.target === gameConfig.playerId && !data.incoming_attack.processed) {
        handleIncomingAttack(data.incoming_attack);
    }
}

function handleIncomingAttack(attack) {
    const hasDefense = checkDefenseSkill(attack.skill_id);
    
    if (hasDefense) {
        playBlockEffect();
        showAttackMessage('🛡️ {{ __("Attaque bloquée !") }}', 'blocked');
    } else {
        playAttackEffect();
        applyAttackEffect(attack.skill_id, attack.params);
    }
    
    markAttackProcessed(attack.id);
}

function checkDefenseSkill(attackSkillId) {
    const defenseSkills = ['block_attack', 'counter_challenger'];
    const playerSkills = @json($skills ?? []);
    
    for (const skill of playerSkills) {
        if (defenseSkills.includes(skill.id) && !skill.used) {
            if (attackSkillId === 'shuffle_answers' && skill.id === 'counter_challenger') {
                return true;
            }
            if (skill.id === 'block_attack') {
                markSkillUsed(skill.id);
                return true;
            }
        }
    }
    return false;
}

function playAttackEffect() {
    const overlay = document.getElementById('attackOverlay');
    const icon = document.getElementById('attackIcon');
    const sound = document.getElementById('swordAttackSound');
    
    icon.textContent = '⚔️';
    overlay.classList.add('active');
    
    if (sound) {
        sound.currentTime = 0;
        sound.volume = 0.7;
        sound.play().catch(e => console.log('Sound error:', e));
    }
    
    setTimeout(() => {
        overlay.classList.remove('active');
    }, 600);
}

function playBlockEffect() {
    const overlay = document.getElementById('attackOverlay');
    const icon = document.getElementById('attackIcon');
    const sound = document.getElementById('swordBlockSound');
    
    icon.textContent = '🛡️';
    overlay.classList.add('active', 'blocked');
    
    if (sound) {
        sound.currentTime = 0;
        sound.volume = 0.8;
        sound.play().catch(e => console.log('Sound error:', e));
    }
    
    setTimeout(() => {
        overlay.classList.remove('active', 'blocked');
    }, 800);
}

function applyAttackEffect(skillId, params) {
    switch (skillId) {
        case 'reduce_time':
            const reduction = params?.seconds || 3;
            timeLeft = Math.max(1, timeLeft - reduction);
            chronoTimer.textContent = timeLeft;
            showAttackMessage('⏱️ -' + reduction + 's {{ __("temps réduit !") }}', 'attack');
            break;
            
        case 'shuffle_answers':
            startAnswerShuffle(params?.interval || 1000);
            showAttackMessage('🔀 {{ __("Réponses en mouvement !") }}', 'attack');
            break;
            
        case 'invert_answers':
            invertAnswersVisually();
            showAttackMessage('🔄 {{ __("Réponses inversées !") }}', 'attack');
            break;
    }
}

function showAttackMessage(message, type) {
    const msgDiv = document.createElement('div');
    msgDiv.className = 'attack-message ' + type;
    msgDiv.innerHTML = message;
    msgDiv.style.cssText = `
        position: fixed;
        top: 20%;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'blocked' ? 'rgba(78, 205, 196, 0.9)' : 'rgba(255, 107, 107, 0.9)'};
        color: white;
        padding: 15px 30px;
        border-radius: 15px;
        font-size: 1.3rem;
        font-weight: 700;
        z-index: 10000;
        animation: fadeInOut 2s ease-in-out forwards;
    `;
    document.body.appendChild(msgDiv);
    
    setTimeout(() => msgDiv.remove(), 2000);
}

function startAnswerShuffle(interval) {
    const answers = answersGrid.querySelectorAll('.answer-option');
    const shuffleInterval = setInterval(() => {
        const shuffled = Array.from(answers).sort(() => Math.random() - 0.5);
        shuffled.forEach((btn, i) => {
            btn.style.order = i;
        });
    }, interval);
    
    setTimeout(() => clearInterval(shuffleInterval), 8000);
}

function invertAnswersVisually() {
    const answers = answersGrid.querySelectorAll('.answer-option');
    const reversed = Array.from(answers).reverse();
    reversed.forEach((btn, i) => {
        btn.style.order = i;
    });
}

function markSkillUsed(skillId) {
    const skillEl = document.querySelector(`[data-skill-id="${skillId}"]`);
    if (skillEl) {
        skillEl.classList.add('used');
        skillEl.classList.remove('active');
    }
}

function markAttackProcessed(attackId) {
    if (gameConfig.matchId && typeof firebase !== 'undefined') {
        import('https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js').then(({ getFirestore, doc, updateDoc }) => {
            const db = getFirestore();
            updateDoc(doc(db, 'gameSessions', gameConfig.matchId), {
                'incoming_attack.processed': true
            });
        });
    }
}

function sendAttackToOpponent(skillId, params = {}) {
    if (!gameConfig.isFirebaseMode || !gameConfig.matchId) return;
    
    playAttackEffect();
    
    import('https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js').then(({ getFirestore, doc, updateDoc }) => {
        const db = getFirestore();
        updateDoc(doc(db, 'gameSessions', gameConfig.matchId), {
            'incoming_attack': {
                id: Date.now(),
                skill_id: skillId,
                attacker: gameConfig.playerId,
                target: gameConfig.opponentId,
                params: params,
                processed: false,
                timestamp: new Date().toISOString()
            }
        });
    });
}

window.sendAttackToOpponent = sendAttackToOpponent;

function initAttackSkills() {
    document.querySelectorAll('.skill-circle.clickable[data-affects-opponent="true"]').forEach(skillEl => {
        skillEl.addEventListener('click', function() {
            if (this.classList.contains('used') || this.classList.contains('auto')) return;
            
            const skillId = this.dataset.skillId;
            const skillType = this.dataset.skillType;
            
            if (skillType === 'attack' || this.dataset.affectsOpponent === 'true') {
                let params = {};
                if (skillId === 'reduce_time') params = { seconds: 3 };
                if (skillId === 'shuffle_answers') params = { interval: 1000 };
                
                sendAttackToOpponent(skillId, params);
                markSkillUsed(skillId);
                
                fetch('/game/{{ $mode }}/use-skill', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': gameConfig.csrfToken
                    },
                    body: JSON.stringify({ skill_id: skillId })
                });
            }
        });
    });
}

initAttackSkills();
@endif

function initPersonalSkills() {
    document.querySelectorAll('.skill-circle.clickable:not([data-affects-opponent="true"])').forEach(skillEl => {
        skillEl.addEventListener('click', function() {
            if (this.classList.contains('used') || this.classList.contains('auto')) return;
            
            const skillId = this.dataset.skillId;
            console.log('[Skill] Personal skill clicked:', skillId);
        });
    });
}

initPersonalSkills();

// ============ DUO CHAT FUNCTIONS ============
@if($mode === 'duo')
let duoChatPanelOpen = false;
let lastMessageCount = 0;

function toggleDuoChatPanel() {
    const panel = document.getElementById('duoChatPanel');
    const badge = document.getElementById('duoChatUnreadBadge');
    if (!panel) return;
    
    duoChatPanelOpen = !duoChatPanelOpen;
    panel.style.display = duoChatPanelOpen ? 'flex' : 'none';
    
    if (duoChatPanelOpen) {
        loadDuoChatMessages();
        badge.style.display = 'none';
        badge.textContent = '0';
    }
}

function loadDuoChatMessages() {
    if (!gameConfig.opponentId) return;
    
    fetch(`/chat/conversation/${gameConfig.opponentId}`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('duoChatMessages');
        if (!container) return;
        
        const messages = data.messages || [];
        if (messages.length === 0) {
            container.innerHTML = '<div style="text-align:center;opacity:0.5;padding:20px;font-size:0.85em;">{{ __("Aucun message") }}</div>';
            return;
        }
        
        const currentUserId = parseInt(gameConfig.playerId);
        container.innerHTML = messages.map(m => {
            const isMine = m.sender_id === currentUserId;
            return `<div class="duo-chat-msg ${isMine ? 'mine' : 'theirs'}">${escapeHtmlChat(m.message)}</div>`;
        }).join('');
        
        container.scrollTop = container.scrollHeight;
        
        if (!duoChatPanelOpen && messages.length > lastMessageCount) {
            const newCount = messages.length - lastMessageCount;
            const badge = document.getElementById('duoChatUnreadBadge');
            if (badge) {
                badge.textContent = newCount;
                badge.style.display = 'flex';
            }
        }
        lastMessageCount = messages.length;
    })
    .catch(e => console.log('Chat load error:', e));
}

function sendDuoChatMessage() {
    const input = document.getElementById('duoChatInput');
    const message = input?.value?.trim();
    
    if (!message || !gameConfig.opponentId) return;
    
    input.disabled = true;
    
    fetch('/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': gameConfig.csrfToken
        },
        body: JSON.stringify({
            receiver_id: gameConfig.opponentId,
            message: message
        })
    })
    .then(r => r.json())
    .then(data => {
        input.disabled = false;
        if (data.success) {
            input.value = '';
            loadDuoChatMessages();
        }
    })
    .catch(e => {
        input.disabled = false;
        console.log('Chat send error:', e);
    });
}

function escapeHtmlChat(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

setInterval(() => {
    loadDuoChatMessages();
}, 5000);

loadDuoChatMessages();
@endif

startTimer();
</script>
@endsection
