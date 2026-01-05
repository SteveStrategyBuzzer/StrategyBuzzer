@extends('layouts.app')

@section('content')
@php
$currentUser = auth()->user();
$selectedAvatar = 'default';

if ($currentUser) {
    $profileSettings = $currentUser->profile_settings;
    if (is_string($profileSettings)) {
        $profileSettings = json_decode($profileSettings, true) ?? [];
    } elseif (is_object($profileSettings)) {
        $profileSettings = (array) $profileSettings;
    }
    
    if (is_array($profileSettings) && isset($profileSettings['avatar'])) {
        $avatarData = $profileSettings['avatar'];
        if (is_object($avatarData)) {
            $avatarData = (array) $avatarData;
        }
        if (is_array($avatarData)) {
            $selectedAvatar = $avatarData['url'] ?? $avatarData['id'] ?? 'default';
        } elseif (is_string($avatarData)) {
            $selectedAvatar = $avatarData;
        }
    }
}

if (strpos($selectedAvatar, 'http://') === 0 || strpos($selectedAvatar, 'https://') === 0 || strpos($selectedAvatar, '//') === 0) {
    $playerAvatarPath = $selectedAvatar;
} elseif (strpos($selectedAvatar, 'images/') === 0) {
    if (substr($selectedAvatar, -4) !== '.png') {
        $selectedAvatar .= '.png';
    }
    $playerAvatarPath = asset($selectedAvatar);
} elseif (strpos($selectedAvatar, '/') !== false && substr($selectedAvatar, -4) !== '.png') {
    $playerAvatarPath = asset("images/avatars/{$selectedAvatar}.png");
} elseif (strpos($selectedAvatar, '/') !== false) {
    $playerAvatarPath = asset($selectedAvatar);
} elseif (substr($selectedAvatar, -4) === '.png') {
    $baseName = preg_replace('/\.png$/', '', $selectedAvatar);
    $playerAvatarPath = asset("images/avatars/standard/{$baseName}.png");
} else {
    $playerAvatarPath = asset("images/avatars/standard/{$selectedAvatar}.png");
}

$playerScore = $player_score ?? 0;
$opponentScore = $opponent_score ?? 0;
$currentRound = $current_round ?? 1;
$playerRoundsWon = $player_rounds_won ?? 0;
$opponentRoundsWon = $opponent_rounds_won ?? 0;
$currentQuestion = $current_question ?? 1;
$totalQuestions = $total_questions ?? 10;
$theme = $theme ?? __('Culture générale');
$themeDisplay = $theme === 'Culture générale' ? __('Général') : __($theme);

$opponentName = $opponent_name ?? __('Adversaire');
$rawOpponentAvatar = $opponent_avatar ?? 'default';
if (strpos($rawOpponentAvatar, 'http://') === 0 || strpos($rawOpponentAvatar, 'https://') === 0 || strpos($rawOpponentAvatar, '//') === 0) {
    $opponentAvatarPath = $rawOpponentAvatar;
} elseif (strpos($rawOpponentAvatar, 'images/') === 0) {
    $opponentAvatarPath = asset($rawOpponentAvatar);
} elseif (strpos($rawOpponentAvatar, '/') !== false && strpos($rawOpponentAvatar, '.png') === false) {
    $opponentAvatarPath = asset("images/avatars/{$rawOpponentAvatar}.png");
} elseif (strpos($rawOpponentAvatar, '/') !== false) {
    $opponentAvatarPath = asset($rawOpponentAvatar);
} else {
    $opponentAvatarPath = asset("images/avatars/standard/{$rawOpponentAvatar}.png");
}

$avatarName = $strategic_avatar ?? 'Aucun';
$strategicAvatarPath = '';
if ($avatarName !== 'Aucun') {
    $strategicAvatarSlug = strtolower($avatarName);
    $strategicAvatarSlug = str_replace(['é', 'è', 'ê'], 'e', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(['à', 'â'], 'a', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(' ', '-', $strategicAvatarSlug);
    $strategicAvatarPath = asset("images/avatars/{$strategicAvatarSlug}.png");
}

$skills = $skills ?? [];
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
    
    .mode-duo { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
    
    .question-header {
        background: rgba(78, 205, 196, 0.1);
        padding: 20px;
        border-radius: 20px;
        text-align: center;
        border: 2px solid rgba(78, 205, 196, 0.3);
        margin-bottom: 10px;
    }
    
    .question-header.waiting-for-question {
        opacity: 0.3;
    }
    .question-header.waiting-for-question .question-number,
    .question-header.waiting-for-question .question-text {
        visibility: hidden;
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
        position: relative;
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
        border: 3px solid #f39c12;
        box-shadow: 0 8px 30px rgba(243, 156, 18, 0.5);
    }
    
    .player-name { color: #4ECDC4; font-weight: 600; }
    .opponent-name { color: #f39c12; font-weight: 600; }
    
    .player-score, .opponent-score {
        font-size: 2rem;
        font-weight: 900;
    }
    
    .player-score { color: #4ECDC4; text-shadow: 0 0 20px rgba(78, 205, 196, 0.8); }
    .opponent-score { color: #f39c12; text-shadow: 0 0 20px rgba(243, 156, 18, 0.8); }
    
    .buzz-indicator {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        background: transparent;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
    }
    
    .buzz-indicator.buzzed {
        background: #ffd700;
        animation: buzzPulse 0.5s;
    }
    
    @keyframes buzzPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.5); }
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
        position: relative;
    }
    
    .skill-circle.active {
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.2);
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
    }
    
    .skill-circle.empty { opacity: 0.3; cursor: default; }
    .skill-circle.used { 
        opacity: 0.5; 
        cursor: default;
        border-color: rgba(128, 128, 128, 0.5);
        background: rgba(128, 128, 128, 0.2);
        box-shadow: none;
    }
    
    .skill-circle.locked { 
        opacity: 0.4; 
        cursor: not-allowed; 
        border-color: rgba(255, 255, 255, 0.2);
        background: rgba(100, 100, 100, 0.3);
        box-shadow: none;
    }
    
    .skill-circle.usable-now:not(.used):not(.locked) {
        animation: skill-shimmer 1.5s ease-in-out infinite;
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.25);
        cursor: pointer;
    }
    
    @keyframes skill-shimmer {
        0%, 100% { 
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.6), 0 0 30px rgba(255, 215, 0, 0.3);
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.9), 0 0 50px rgba(255, 215, 0, 0.5);
            transform: scale(1.08);
        }
    }
    
    .skill-circle.available:not(.used):not(.locked):not(.usable-now) {
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.15);
        box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        cursor: pointer;
    }
    
    .skill-circle.auto:not(.used) {
        border-color: rgba(100, 200, 255, 0.6);
        background: rgba(100, 200, 255, 0.15);
        box-shadow: 0 0 8px rgba(100, 200, 255, 0.3);
        cursor: default;
    }
    
    .skill-circle.auto:not(.used)::after {
        content: '⚡';
        position: absolute;
        bottom: -5px;
        right: -5px;
        font-size: 0.7rem;
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
        position: relative;
    }
    
    .answer-option:hover:not(.disabled) {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 116, 217, 0.4);
    }
    
    .answer-option.correct {
        background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
    }
    
    .answer-option.incorrect {
        background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
    }
    
    .answer-option.selected {
        background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        box-shadow: 0 0 20px rgba(155, 89, 182, 0.6);
    }
    
    .answer-option.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .point-badge {
        position: absolute;
        top: 5px;
        right: 8px;
        font-size: 0.75rem;
        font-weight: bold;
        padding: 3px 8px;
        border-radius: 10px;
        background: rgba(0, 0, 0, 0.3);
    }
    
    .point-badge.points-high {
        background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
        color: white;
    }
    
    .point-badge.points-medium {
        background: linear-gradient(135deg, #F39C12 0%, #E67E22 100%);
        color: white;
    }
    
    .point-badge.points-low {
        background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
        color: white;
    }
    
    .buzz-container-bottom {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 100;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .buzz-button {
        background: none;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease;
        padding: 0;
        display: block;
    }
    
    .buzz-button:hover { transform: scale(1.05); }
    .buzz-button:active { transform: scale(0.95); }
    .buzz-button:disabled { opacity: 0.4; cursor: not-allowed; }
    
    .buzz-button img {
        width: 180px;
        height: 180px;
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.6));
    }
    
    .phase-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 300;
        flex-direction: column;
        gap: 20px;
        animation: phaseIn 0.3s ease-out;
    }
    
    .phase-overlay.active { display: flex; }
    
    @keyframes phaseIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .intro-overlay {
        background: linear-gradient(135deg, rgba(15, 32, 39, 0.98) 0%, rgba(32, 58, 67, 0.98) 50%, rgba(44, 83, 100, 0.98) 100%);
    }
    
    .intro-content {
        text-align: center;
        animation: introSlide 0.5s ease-out;
    }
    
    @keyframes introSlide {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .intro-question-number {
        font-size: 3rem;
        font-weight: 900;
        color: #4ECDC4;
        text-shadow: 0 0 30px rgba(78, 205, 196, 0.8);
        margin-bottom: 15px;
    }
    
    .intro-theme {
        font-size: 1.5rem;
        color: #FFD700;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .intro-subtheme {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.7);
        margin-top: 8px;
    }
    
    .reveal-overlay {
        background: rgba(0, 0, 0, 0.96);
    }
    
    .reveal-content {
        text-align: center;
        animation: revealPop 0.4s ease-out;
    }
    
    @keyframes revealPop {
        from { opacity: 0; transform: scale(0.8); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .reveal-icon {
        font-size: 5rem;
        margin-bottom: 20px;
    }
    
    .reveal-icon.correct {
        animation: correctPulse 0.6s ease-out;
    }
    
    @keyframes correctPulse {
        0% { transform: scale(0); }
        50% { transform: scale(1.3); }
        100% { transform: scale(1); }
    }
    
    .reveal-message {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .reveal-message.correct {
        color: #2ECC71;
        text-shadow: 0 0 20px rgba(46, 204, 113, 0.6);
    }
    
    .reveal-message.incorrect {
        color: #E74C3C;
        text-shadow: 0 0 20px rgba(231, 76, 60, 0.6);
    }
    
    .reveal-message.timeout {
        color: #F39C12;
        text-shadow: 0 0 20px rgba(243, 156, 18, 0.6);
    }
    
    .reveal-answer {
        font-size: 1.3rem;
        color: #4ECDC4;
        background: rgba(78, 205, 196, 0.15);
        padding: 15px 30px;
        border-radius: 15px;
        border: 2px solid rgba(78, 205, 196, 0.4);
    }
    
    .reveal-points {
        font-size: 1.5rem;
        color: #FFD700;
        margin-top: 15px;
        font-weight: 700;
    }
    
    /* Enhanced Reveal Overlay Styles */
    .reveal-overlay {
        background: rgba(0, 0, 0, 0.96);
        overflow-y: auto;
        padding: 20px;
    }
    
    .reveal-full-content {
        max-width: 500px;
        width: 100%;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .reveal-vs-header {
        padding: 10px 15px;
        background: rgba(102, 126, 234, 0.2);
        border-radius: 10px;
        border: 2px solid rgba(102, 126, 234, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .reveal-vs-label {
        font-size: 1rem;
        font-weight: 700;
        color: #4ECDC4;
    }
    
    .reveal-opponent-name {
        font-size: 1.2rem;
        font-weight: 700;
        color: #667eea;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .reveal-result-section {
        text-align: center;
        padding: 10px 0;
    }
    
    .reveal-round-details {
        display: flex;
        justify-content: center;
        gap: 20px;
        padding: 10px;
        background: rgba(0,0,0,0.3);
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }
    
    .reveal-round-player, .reveal-round-opponent {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    
    .reveal-round-label {
        font-size: 0.85rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .reveal-round-opponent .reveal-round-label {
        color: #f39c12;
    }
    
    .reveal-round-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .reveal-points-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .reveal-points-badge.points-gained {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .reveal-points-badge.points-lost {
        background: linear-gradient(135deg, #f093fb, #f5576c);
        color: white;
    }
    
    .reveal-points-badge.points-neutral {
        background: rgba(255,255,255,0.1);
        color: #95a5a6;
    }
    
    .reveal-speed-indicator {
        font-size: 0.75rem;
        color: #95a5a6;
        padding: 2px 8px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
    }
    
    .reveal-speed-indicator.first {
        color: #f39c12;
        background: rgba(243, 156, 18, 0.2);
    }
    
    .reveal-score-battle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
    }
    
    .reveal-score-player, .reveal-score-opponent {
        width: 120px;
        padding: 12px;
        border-radius: 12px;
        text-align: center;
        backdrop-filter: blur(10px);
    }
    
    .reveal-score-player {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.2) 0%, rgba(68, 160, 141, 0.2) 100%);
        border: 2px solid #4ECDC4;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.3);
    }
    
    .reveal-score-opponent {
        background: linear-gradient(145deg, rgba(243, 156, 18, 0.2) 0%, rgba(230, 126, 34, 0.2) 100%);
        border: 2px solid #f39c12;
        box-shadow: 0 5px 20px rgba(243, 156, 18, 0.3);
    }
    
    .reveal-score-label {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .reveal-score-number {
        font-size: 2rem;
        font-weight: 900;
        line-height: 1;
    }
    
    .reveal-score-player .reveal-score-number {
        color: #4ECDC4;
    }
    
    .reveal-score-opponent .reveal-score-number {
        color: #f39c12;
    }
    
    .reveal-vs-divider {
        font-size: 1rem;
        font-weight: bold;
        color: #4ECDC4;
        background: rgba(78, 205, 196, 0.2);
        padding: 8px;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #4ECDC4;
    }
    
    .reveal-skills-container {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 12px;
        padding: 12px;
    }
    
    .reveal-skills-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 10px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .reveal-skills-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 1px solid #667eea;
    }
    
    .reveal-skills-grid {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .reveal-skill-item {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(102, 126, 234, 0.3);
        border-radius: 8px;
        padding: 8px 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .reveal-skill-item.used {
        opacity: 0.5;
    }
    
    .reveal-skill-icon {
        font-size: 1.5rem;
    }
    
    .reveal-skill-info {
        flex: 1;
    }
    
    .reveal-skill-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: #667eea;
    }
    
    .reveal-skill-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .reveal-skill-btn:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(102, 126, 234, 0.5);
    }
    
    .reveal-skill-btn:disabled {
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.5);
        cursor: not-allowed;
    }
    
    .reveal-answers {
        background: rgba(0,0,0,0.4);
        padding: 12px;
        border-radius: 12px;
        border: 2px solid rgba(255,255,255,0.1);
    }
    
    .reveal-answer-display {
        padding: 8px 12px;
        border-radius: 10px;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        backdrop-filter: blur(5px);
    }
    
    .reveal-answer-display:last-child {
        margin-bottom: 0;
    }
    
    .reveal-answer-correct {
        background: rgba(46, 204, 113, 0.25);
        border: 2px solid #2ECC71;
    }
    
    .reveal-answer-user {
        background: rgba(231, 76, 60, 0.25);
        border: 2px solid #E74C3C;
    }
    
    .reveal-answer-user.was-correct {
        background: rgba(46, 204, 113, 0.25);
        border: 2px solid #2ECC71;
    }
    
    .reveal-answer-label {
        opacity: 0.9;
        font-size: 0.85rem;
        font-weight: 600;
        flex-shrink: 0;
        min-width: 100px;
    }
    
    .reveal-answer-text {
        flex: 1;
        text-align: left;
        font-weight: 500;
    }
    
    .reveal-answer-icon {
        font-size: 1.4rem;
    }
    
    .reveal-stats {
        background: rgba(0,0,0,0.3);
        border: 2px solid rgba(78, 205, 196, 0.3);
        border-radius: 10px;
        padding: 10px;
        backdrop-filter: blur(10px);
    }
    
    .reveal-stats-columns {
        display: flex;
        gap: 10px;
    }
    
    .reveal-stats-column {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .reveal-stats-column.left {
        border-right: 1px solid rgba(78, 205, 196, 0.3);
        padding-right: 10px;
    }
    
    .reveal-stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 6px;
        background: rgba(78, 205, 196, 0.08);
        border-radius: 5px;
    }
    
    .reveal-stat-label {
        font-size: 0.7rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .reveal-stat-value {
        font-size: 0.8rem;
        color: white;
        font-weight: bold;
    }
    
    .reveal-stat-row.no-answer .reveal-stat-value {
        color: #F39C12;
    }
    
    .reveal-stat-row.correct .reveal-stat-value {
        color: #2ECC71;
    }
    
    .reveal-stat-row.wrong .reveal-stat-value {
        color: #E74C3C;
    }
    
    .reveal-did-you-know {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 10px;
        padding: 12px;
        backdrop-filter: blur(10px);
    }
    
    .reveal-did-you-know-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 8px;
        text-align: center;
    }
    
    .reveal-did-you-know-content {
        font-size: 0.85rem;
        line-height: 1.5;
        color: rgba(255, 255, 255, 0.9);
        text-align: center;
        font-style: italic;
    }
    
    .reveal-countdown {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 12px;
        border-radius: 12px;
        font-size: 0.9rem;
        border: 2px solid rgba(78, 205, 196, 0.3);
        text-align: center;
    }
    
    .reveal-countdown-timer {
        font-size: 1.8rem;
        font-weight: 900;
        color: #4ECDC4;
        display: inline-block;
        margin: 0 5px;
        animation: pulse 1s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.15); }
    }
    
    .scoreboard-overlay {
        background: linear-gradient(135deg, rgba(15, 32, 39, 0.97) 0%, rgba(32, 58, 67, 0.97) 50%, rgba(44, 83, 100, 0.97) 100%);
    }
    
    .scoreboard-content {
        text-align: center;
        animation: scoreSlide 0.5s ease-out;
        width: 90%;
        max-width: 500px;
    }
    
    @keyframes scoreSlide {
        from { opacity: 0; transform: translateY(-30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .scoreboard-title {
        font-size: 1.5rem;
        color: #FFD700;
        margin-bottom: 30px;
        text-transform: uppercase;
        letter-spacing: 3px;
    }
    
    .scoreboard-players {
        display: flex;
        justify-content: space-around;
        align-items: center;
        gap: 40px;
    }
    
    .scoreboard-player {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .scoreboard-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .scoreboard-avatar.player {
        border: 3px solid #4ECDC4;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.5);
    }
    
    .scoreboard-avatar.opponent {
        border: 3px solid #f39c12;
        box-shadow: 0 0 20px rgba(243, 156, 18, 0.5);
    }
    
    .scoreboard-name {
        font-size: 1rem;
        font-weight: 600;
    }
    
    .scoreboard-name.player { color: #4ECDC4; }
    .scoreboard-name.opponent { color: #f39c12; }
    
    .scoreboard-score {
        font-size: 3rem;
        font-weight: 900;
    }
    
    .scoreboard-score.player {
        color: #4ECDC4;
        text-shadow: 0 0 30px rgba(78, 205, 196, 0.8);
    }
    
    .scoreboard-score.opponent {
        color: #f39c12;
        text-shadow: 0 0 30px rgba(243, 156, 18, 0.8);
    }
    
    .scoreboard-score.leading {
        animation: scoreGlow 1s ease-in-out infinite;
    }
    
    @keyframes scoreGlow {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .scoreboard-vs {
        font-size: 2rem;
        color: rgba(255, 255, 255, 0.5);
        font-weight: 700;
    }
    
    .scoreboard-progress {
        margin-top: 30px;
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
    }
    
    /* Tiebreaker Overlay */
    .tiebreaker-overlay {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.95) 0%, rgba(192, 57, 43, 0.95) 100%);
    }
    
    .tiebreaker-content {
        text-align: center;
        animation: scoreSlide 0.5s ease-out;
    }
    
    .tiebreaker-title {
        font-size: 3rem;
        font-weight: 900;
        color: #FFD700;
        text-shadow: 0 0 30px rgba(255, 215, 0, 0.8);
        margin-bottom: 10px;
    }
    
    .tiebreaker-subtitle {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 30px;
    }
    
    .tiebreaker-options {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .tiebreaker-option {
        background: rgba(255, 255, 255, 0.15);
        border: 2px solid rgba(255, 255, 255, 0.4);
        border-radius: 15px;
        padding: 20px 30px;
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .tiebreaker-option:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.05);
    }
    
    .tiebreaker-option .option-icon {
        font-size: 2.5rem;
    }
    
    .tiebreaker-option .option-text {
        font-size: 1rem;
        font-weight: 600;
    }
    
    .tiebreaker-timer {
        margin-top: 30px;
        font-size: 2rem;
        font-weight: 700;
        color: #FFD700;
    }
    
    /* Match End Overlay */
    .match-end-overlay {
        background: linear-gradient(135deg, rgba(15, 32, 39, 0.98) 0%, rgba(32, 58, 67, 0.98) 50%, rgba(44, 83, 100, 0.98) 100%);
    }
    
    .match-end-content {
        text-align: center;
        animation: scoreSlide 0.5s ease-out;
        max-width: 400px;
        width: 90%;
    }
    
    .match-result-icon {
        font-size: 5rem;
        margin-bottom: 15px;
    }
    
    .match-result-title {
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 30px;
    }
    
    .match-result-title.victory {
        color: #2ECC71;
        text-shadow: 0 0 30px rgba(46, 204, 113, 0.8);
    }
    
    .match-result-title.defeat {
        color: #E74C3C;
        text-shadow: 0 0 30px rgba(231, 76, 60, 0.8);
    }
    
    .match-result-title.draw {
        color: #F39C12;
        text-shadow: 0 0 30px rgba(243, 156, 18, 0.8);
    }
    
    .match-result-scores {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .final-score {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .final-name {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .final-score.player .final-name { color: #4ECDC4; }
    .final-score.opponent .final-name { color: #f39c12; }
    
    .final-points {
        font-size: 2.5rem;
        font-weight: 900;
    }
    
    .final-score.player .final-points { color: #4ECDC4; }
    .final-score.opponent .final-points { color: #f39c12; }
    
    .final-vs {
        font-size: 1.5rem;
        color: rgba(255, 255, 255, 0.5);
        font-weight: 700;
    }
    
    .match-rewards {
        margin: 20px 0;
    }
    
    .reward-item {
        font-size: 1.2rem;
        color: #FFD700;
        margin: 10px 0;
    }
    
    .match-efficiency {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 25px;
    }
    
    .match-end-btn {
        background: linear-gradient(135deg, #4ECDC4 0%, #44B39D 100%);
        border: none;
        padding: 15px 40px;
        border-radius: 30px;
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .match-end-btn:hover {
        transform: scale(1.05);
    }
    
    .waiting-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.95);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 350;
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
    
    /* Attack Overlay */
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
        0% { transform: scale(0.5); opacity: 0; }
        50% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(1); opacity: 0; }
    }
    
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translate(-50%, -20px); }
        20% { opacity: 1; transform: translate(-50%, 0); }
        80% { opacity: 1; transform: translate(-50%, 0); }
        100% { opacity: 0; transform: translate(-50%, 20px); }
    }
    
    @media (max-width: 768px) {
        .game-layout { gap: 15px; }
        .player-avatar, .opponent-avatar { width: 70px; height: 70px; }
        .chrono-circle { width: 120px; height: 120px; }
        .chrono-time { font-size: 2.5rem; }
        .buzz-button img { width: 150px; height: 150px; }
        .answers-grid { grid-template-columns: 1fr; gap: 10px; padding: 0 10px; }
        .question-header { padding: 15px 10px; }
        .question-text { font-size: 1.1rem; }
        .mode-indicator { top: 5px; right: 5px; padding: 5px 10px; font-size: 0.75rem; }
        .game-container { gap: 15px; padding-bottom: 160px; }
        .buzz-container-bottom { bottom: 15px; }
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
        .buzz-button img { width: 130px; height: 130px; }
        .question-header { padding: 10px 8px; margin-bottom: 5px; }
        .question-text { font-size: 1rem; }
        .question-number { font-size: 0.75rem; margin-bottom: 8px; }
        .game-container { gap: 10px; padding-bottom: 140px; min-height: calc(100dvh - 10px); }
        .buzz-container-bottom { bottom: 10px; }
        .player-name, .opponent-name { font-size: 0.85rem; }
        .player-score, .opponent-score { font-size: 1.5rem; }
        .intro-question-number { font-size: 2rem; }
        .intro-theme { font-size: 1.1rem; }
        .reveal-icon { font-size: 3.5rem; }
        .reveal-message { font-size: 1.5rem; }
        .scoreboard-players { gap: 20px; }
        .scoreboard-avatar { width: 60px; height: 60px; }
        .scoreboard-score { font-size: 2.5rem; }
    }
    
    @media (max-height: 700px) and (orientation: portrait) {
        .buzz-button img { width: 110px; height: 110px; }
        .game-container { padding-bottom: 120px; }
        .buzz-container-bottom { bottom: 8px; }
        .chrono-circle { width: 80px; height: 80px; }
        .chrono-time { font-size: 1.8rem; }
    }
    
    /* Waiting Block Overlay */
    .waiting-block-overlay {
        background: linear-gradient(135deg, rgba(15, 32, 39, 0.95) 0%, rgba(32, 58, 67, 0.95) 50%, rgba(44, 83, 100, 0.95) 100%);
    }
    
    .waiting-block-content {
        text-align: center;
        animation: scoreSlide 0.5s ease-out;
    }
    
    .waiting-block-title {
        font-size: 2rem;
        font-weight: 700;
        color: #FFD700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .waiting-block-info {
        margin-bottom: 30px;
    }
    
    .waiting-block-range {
        font-size: 1.5rem;
        color: #4ECDC4;
        font-weight: 600;
        background: rgba(78, 205, 196, 0.15);
        padding: 15px 30px;
        border-radius: 15px;
        border: 2px solid rgba(78, 205, 196, 0.4);
        display: inline-block;
    }
    
    .waiting-block-countdown {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .waiting-block-countdown-label {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
    }
    
    .waiting-block-timer {
        font-size: 4rem;
        font-weight: 900;
        color: #FFD700;
        text-shadow: 0 0 30px rgba(255, 215, 0, 0.6);
        animation: pulse-glow 1s ease-in-out infinite;
    }
</style>

<div class="game-container">
    <div class="mode-indicator mode-duo">
        {{ __('Duo') }}
    </div>
    
    <div class="question-header waiting-for-question" id="questionHeader">
        <div class="round-indicator">
            @for($i = 1; $i <= 3; $i++)
                @php
                    $dotClass = '';
                    if ($i <= $playerRoundsWon) $dotClass = 'player-won';
                    elseif ($i <= ($playerRoundsWon + $opponentRoundsWon) && $i > $playerRoundsWon) $dotClass = 'opponent-won';
                    if ($i === $currentRound) $dotClass .= ' current';
                @endphp
                <div class="round-dot {{ $dotClass }}" id="roundDot{{ $i }}"></div>
            @endfor
        </div>
        
        <div class="question-number" id="questionNumber">
            {{ $themeDisplay }} | {{ __('Question') }} <span id="currentQuestionNum">{{ $currentQuestion }}</span>/{{ $totalQuestions }}
        </div>
        
        <div class="question-text" id="questionText">
            {{ __('Chargement...') }}
        </div>
    </div>
    
    <div class="game-layout">
        <div class="left-column">
            <div class="player-circle">
                <img src="{{ $playerAvatarPath }}" alt="{{ __('Joueur') }}" class="player-avatar">
                <div class="player-name">{{ $currentUser->name ?? __('Vous') }}</div>
                <div class="player-score" id="playerScore">{{ $playerScore }}</div>
                <div class="buzz-indicator" id="playerBuzzIndicator"></div>
            </div>
            
            <div class="opponent-circle">
                <img src="{{ $opponentAvatarPath }}" alt="{{ __('Adversaire') }}" class="opponent-avatar" id="opponentAvatarImg">
                <div class="opponent-name" id="opponentName">{{ $opponentName }}</div>
                <div class="opponent-score" id="opponentScore">{{ $opponentScore }}</div>
                <div class="buzz-indicator" id="opponentBuzzIndicator"></div>
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
                    <img src="{{ $strategicAvatarPath }}" alt="{{ __('Avatar stratégique') }}" class="strategic-avatar-image">
                </div>
            @else
                <div class="strategic-avatar-circle empty"></div>
            @endif
            
            <div class="skills-container">
                @for($i = 0; $i < 3; $i++)
                    @if(isset($skills[$i]))
                        @php $skill = $skills[$i]; @endphp
                        <div class="skill-circle {{ $skill['used'] ?? false ? 'used' : 'available' }} {{ ($skill['auto'] ?? false) ? 'auto' : '' }}" 
                             data-skill-id="{{ $skill['id'] ?? '' }}"
                             data-trigger="{{ $skill['trigger'] ?? 'on_question' }}"
                             data-auto="{{ ($skill['auto'] ?? false) ? 'true' : 'false' }}"
                             title="{{ ($skill['name'] ?? '') . ': ' . ($skill['description'] ?? '') }}">
                            {{ $skill['icon'] ?? '❓' }}
                        </div>
                    @else
                        <div class="skill-circle empty"></div>
                    @endif
                @endfor
            </div>
        </div>
    </div>
    
    <div class="answers-grid" id="answersGrid" style="display: none;">
        <button class="answer-option" data-index="0" id="answer0">
            <span class="point-badge" id="badge0"></span>
            <span class="answer-text"></span>
        </button>
        <button class="answer-option" data-index="1" id="answer1">
            <span class="point-badge" id="badge1"></span>
            <span class="answer-text"></span>
        </button>
        <button class="answer-option" data-index="2" id="answer2">
            <span class="point-badge" id="badge2"></span>
            <span class="answer-text"></span>
        </button>
        <button class="answer-option" data-index="3" id="answer3">
            <span class="point-badge" id="badge3"></span>
            <span class="answer-text"></span>
        </button>
    </div>
    
    <div class="buzz-container-bottom" id="buzzContainer">
        <button id="buzzButton" class="buzz-button" disabled>
            <img src="{{ asset('images/buzzer.png') }}" alt="Buzzer">
        </button>
    </div>
</div>

<div class="waiting-overlay" id="waitingOverlay">
    <div class="spinner"></div>
    <div class="waiting-text" id="waitingText">{{ __('En attente de l\'adversaire...') }}</div>
</div>

<div class="phase-overlay intro-overlay" id="introOverlay">
    <div class="intro-content">
        <div class="intro-question-number" id="introQuestionNumber">{{ __('Question') }} 1/{{ $totalQuestions }}</div>
        <div class="intro-theme" id="introTheme">{{ $theme }}</div>
        <div class="intro-subtheme" id="introSubtheme"></div>
    </div>
</div>

<div class="phase-overlay reveal-overlay" id="revealOverlay">
    <div class="reveal-content reveal-full-content">
        <!-- VS Header -->
        <div class="reveal-vs-header" id="revealVsHeader">
            <span class="reveal-vs-label">VS</span>
            <span class="reveal-opponent-name" id="revealOpponentName">{{ $opponentName }}</span>
        </div>
        
        <!-- Result Icon & Message -->
        <div class="reveal-result-section">
            <div class="reveal-icon" id="revealIcon">✓</div>
            <div class="reveal-message" id="revealMessage">{{ __('Bonne réponse !') }}</div>
        </div>
        
        <!-- Round Details: Player vs Opponent points -->
        <div class="reveal-round-details" id="revealRoundDetails">
            <div class="reveal-round-player">
                <div class="reveal-round-label">{{ __('Vous') }}</div>
                <div class="reveal-round-info">
                    <span class="reveal-points-badge points-gained" id="revealPlayerPoints">+0</span>
                    <span class="reveal-speed-indicator" id="revealPlayerSpeed"></span>
                </div>
            </div>
            <div class="reveal-round-opponent">
                <div class="reveal-round-label" id="revealOpponentLabel">{{ __('Adversaire') }}</div>
                <div class="reveal-round-info">
                    <span class="reveal-points-badge points-neutral" id="revealOpponentPoints">+0</span>
                    <span class="reveal-speed-indicator" id="revealOpponentSpeed"></span>
                </div>
            </div>
        </div>
        
        <!-- Score Battle -->
        <div class="reveal-score-battle" id="revealScoreBattle">
            <div class="reveal-score-player">
                <div class="reveal-score-label">{{ __('VOUS') }}</div>
                <div class="reveal-score-number" id="revealScorePlayer">0</div>
            </div>
            <div class="reveal-vs-divider">VS</div>
            <div class="reveal-score-opponent">
                <div class="reveal-score-label" id="revealScoreOpponentLabel">{{ __('ADVERSAIRE') }}</div>
                <div class="reveal-score-number" id="revealScoreOpponent">0</div>
            </div>
        </div>
        
        <!-- Skills Section -->
        <div class="reveal-skills-container" id="revealSkillsContainer">
            <div class="reveal-skills-title">
                <span>{{ __('Compétences') }}</span>
                @if($strategicAvatarPath)
                    <img src="{{ $strategicAvatarPath }}" alt="{{ $avatarName }}" class="reveal-skills-avatar">
                @endif
            </div>
            <div class="reveal-skills-grid" id="revealSkillsGrid">
                @foreach($skills as $index => $skill)
                    @if($skill)
                        <div class="reveal-skill-item" data-skill-index="{{ $index }}" id="revealSkill{{ $index }}">
                            <span class="reveal-skill-icon">{{ $skill['icon'] ?? '❓' }}</span>
                            <div class="reveal-skill-info">
                                <span class="reveal-skill-name">{{ $skill['name'] ?? '' }}</span>
                            </div>
                            <button class="reveal-skill-btn" onclick="activateRevealSkill({{ $index }})" id="revealSkillBtn{{ $index }}">{{ __('Activer') }}</button>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        
        <!-- Answers Section -->
        <div class="reveal-answers" id="revealAnswers">
            <div class="reveal-answer-display reveal-answer-user" id="revealUserAnswerRow">
                <span class="reveal-answer-label">{{ __('Votre réponse') }}</span>
                <span class="reveal-answer-text" id="revealUserAnswer">—</span>
                <span class="reveal-answer-icon" id="revealUserAnswerIcon">❌</span>
            </div>
            <div class="reveal-answer-display reveal-answer-correct">
                <span class="reveal-answer-label">{{ __('Bonne réponse') }}</span>
                <span class="reveal-answer-text" id="revealCorrectAnswer">—</span>
                <span class="reveal-answer-icon">✅</span>
            </div>
        </div>
        
        <!-- Stats Section (2 columns) -->
        <div class="reveal-stats" id="revealStats">
            <div class="reveal-stats-columns">
                <div class="reveal-stats-column left">
                    <div class="reveal-stat-row">
                        <span class="reveal-stat-label">{{ __('Score Match') }}</span>
                        <span class="reveal-stat-value" id="revealStatMatchScore">0</span>
                    </div>
                    <div class="reveal-stat-row">
                        <span class="reveal-stat-label">{{ __('Vie') }}</span>
                        <span class="reveal-stat-value" id="revealStatLives">3</span>
                    </div>
                    <div class="reveal-stat-row">
                        <span class="reveal-stat-label">{{ __('Question') }}</span>
                        <span class="reveal-stat-value" id="revealStatQuestion">1/10</span>
                    </div>
                </div>
                <div class="reveal-stats-column right">
                    <div class="reveal-stat-row no-answer">
                        <span class="reveal-stat-label">{{ __('Sans Réponse') }}</span>
                        <span class="reveal-stat-value" id="revealStatNoAnswer">0</span>
                    </div>
                    <div class="reveal-stat-row correct">
                        <span class="reveal-stat-label">{{ __('Bonne') }}</span>
                        <span class="reveal-stat-value" id="revealStatCorrect">0</span>
                    </div>
                    <div class="reveal-stat-row wrong">
                        <span class="reveal-stat-label">{{ __('Échec') }}</span>
                        <span class="reveal-stat-value" id="revealStatWrong">0</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Did You Know Section -->
        <div class="reveal-did-you-know" id="revealDidYouKnow">
            <div class="reveal-did-you-know-title">{{ __('Le saviez-vous ?') }}</div>
            <div class="reveal-did-you-know-content" id="revealDidYouKnowContent">...</div>
        </div>
        
        <!-- Countdown Timer -->
        <div class="reveal-countdown" id="revealCountdown">
            {{ __('Prochaine question dans') }} <span class="reveal-countdown-timer" id="revealCountdownTimer">6</span> {{ __('secondes...') }}
        </div>
    </div>
</div>

<div class="phase-overlay scoreboard-overlay" id="scoreboardOverlay">
    <div class="scoreboard-content">
        <div class="scoreboard-title">{{ __('Scores') }}</div>
        <div class="scoreboard-players">
            <div class="scoreboard-player">
                <img src="{{ $playerAvatarPath }}" alt="{{ __('Joueur') }}" class="scoreboard-avatar player">
                <div class="scoreboard-name player" id="scoreboardPlayerName">{{ $currentUser->name ?? __('Vous') }}</div>
                <div class="scoreboard-score player" id="scoreboardPlayerScore">0</div>
            </div>
            <div class="scoreboard-vs">VS</div>
            <div class="scoreboard-player">
                <img src="{{ $opponentAvatarPath }}" alt="{{ __('Adversaire') }}" class="scoreboard-avatar opponent" id="scoreboardOpponentAvatar">
                <div class="scoreboard-name opponent" id="scoreboardOpponentName">{{ $opponentName }}</div>
                <div class="scoreboard-score opponent" id="scoreboardOpponentScore">0</div>
            </div>
        </div>
        <div class="scoreboard-progress" id="scoreboardProgress">{{ __('Question suivante...') }}</div>
    </div>
</div>

<div class="phase-overlay tiebreaker-overlay" id="tiebreakerChoiceOverlay">
    <div class="tiebreaker-content">
        <div class="tiebreaker-title">{{ __('Égalité !') }}</div>
        <div class="tiebreaker-subtitle">{{ __('Choisissez le mode de départage') }}</div>
        <div class="tiebreaker-options">
            <button class="tiebreaker-option" data-mode="question" onclick="selectTiebreakerMode('question')">
                <span class="option-icon">❓</span>
                <span class="option-text">{{ __('Question décisive') }}</span>
            </button>
            <button class="tiebreaker-option" data-mode="speed" onclick="selectTiebreakerMode('speed')">
                <span class="option-icon">⚡</span>
                <span class="option-text">{{ __('Buzz le plus rapide') }}</span>
            </button>
        </div>
        <div class="tiebreaker-timer" id="tiebreakerTimer">10</div>
    </div>
</div>

<div class="phase-overlay match-end-overlay" id="matchEndOverlay">
    <div class="match-end-content">
        <div class="match-result-icon" id="matchResultIcon">🏆</div>
        <div class="match-result-title" id="matchResultTitle">{{ __('Victoire !') }}</div>
        <div class="match-result-scores">
            <div class="final-score player">
                <span class="final-name" id="finalPlayerName">{{ $currentUser->name ?? __('Vous') }}</span>
                <span class="final-points" id="finalPlayerScore">0</span>
            </div>
            <div class="final-vs">-</div>
            <div class="final-score opponent">
                <span class="final-name" id="finalOpponentName">{{ $opponentName }}</span>
                <span class="final-points" id="finalOpponentScore">0</span>
            </div>
        </div>
        <div class="match-rewards" id="matchRewards">
            <div class="reward-item" id="rewardCoins"></div>
            <div class="reward-item" id="rewardBet"></div>
        </div>
        <div class="match-efficiency" id="matchEfficiency"></div>
        <button class="match-end-btn" onclick="goToResults()">{{ __('Voir les détails') }}</button>
    </div>
</div>

<div class="attack-overlay" id="attackOverlay">
    <div class="attack-icon" id="attackIcon">⚔️</div>
</div>

<div class="phase-overlay waiting-block-overlay" id="waitingBlockOverlay">
    <div class="waiting-block-content">
        <h2 class="waiting-block-title" id="waitingBlockTitle">{{ __('Prochain bloc') }}</h2>
        <div class="waiting-block-info">
            <span class="waiting-block-range" id="waitingBlockRange">Questions 2-5</span>
        </div>
        <div class="waiting-block-countdown">
            <span class="waiting-block-countdown-label">{{ __('Début dans') }}</span>
            <span class="waiting-block-timer" id="waitingBlockTimer">5</span>
        </div>
    </div>
</div>

<audio id="buzzerSound" preload="auto">
    <source id="buzzerSource" src="{{ asset('sounds/buzzer_default_1.mp3') }}" type="audio/mpeg">
</audio>
<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
</audio>
<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
</audio>
<audio id="timerTickSound" preload="auto">
    <source src="{{ asset('sounds/tic_tac.mp3') }}" type="audio/mpeg">
</audio>
<audio id="noBuzzSound" preload="auto">
    <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
</audio>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<script>
const matchId = {{ $match_id }};
const userId = {{ Auth::id() }};
const csrfToken = '{{ csrf_token() }}';
const totalQuestions = {{ $totalQuestions }};
const isHost = {{ $match->player1_id == Auth::id() ? 'true' : 'false' }};

const gameServerUrl = '{{ $game_server_url ?? "" }}';
const roomId = '{{ $room_id ?? "" }}';
const lobbyCode = '{{ $lobby_code ?? "" }}';
const jwtToken = '{{ $jwt_token ?? "" }}';

let gameState = null;
let canBuzz = false;
let hasBuzzed = false;
let currentPhase = 'waiting';
let timeLeft = 8;
let timerInterval = null;
let answerTimeLeft = 10;
let answerTimerInterval = null;
let currentQuestionData = null;
let useSocketIO = false;
let phaseEndsAtMs = null;
let isProcessingPhase = false;
let pendingQuestionQueue = [];
let lastAcceptedQuestionNum = 0;

const selectedBuzzer = localStorage.getItem('selectedBuzzer') || 'buzzer_default_1';
document.getElementById('buzzerSource').src = `/sounds/${selectedBuzzer}.mp3`;
document.getElementById('buzzerSound').load();

const PhaseController = {
    currentPhase: 'waiting',
    phases: ['intro', 'question', 'buzz', 'reveal', 'scoreboard'],
    phaseTimers: {
        intro: 9000,
        reveal: 6000,
        scoreboard: 2500
    },
    revealCountdownInterval: null,
    questionStats: { correct: 0, wrong: 0, noAnswer: 0 },
    isHost: isHost,
    
    setPhase(phase, phaseData = {}) {
        if (!this.phases.includes(phase) && !['tiebreaker_choice', 'match_end', 'waiting', 'answer'].includes(phase)) {
            console.warn('[PhaseController] Invalid phase:', phase);
            return;
        }
        
        const previousPhase = this.currentPhase;
        this.currentPhase = phase;
        currentPhase = phase;
        console.log('[PhaseController] Phase transition:', previousPhase, '->', phase, 'isHost:', this.isHost);
        
        this.hideAllOverlays();
        
        // Publish phase to Socket.IO for multiplayer sync (host only to avoid loops)
        if (this.isHost && useSocketIO && typeof duoSocket !== 'undefined' && typeof duoSocket.isConnected === 'function' && duoSocket.isConnected()) {
            try {
                duoSocket.emit('phase_change', { phase, phaseData });
            } catch (err) {
                console.error('[PhaseController] Socket.IO phase publish error:', err);
            }
        }
        
        // Ensure overlays are hidden locally after a short delay (guard for question/buzz phases)
        if (previousPhase !== phase && ['question', 'buzz'].includes(phase)) {
            setTimeout(() => this.hideAllOverlays(), 100);
        }
        
        return phase;
    },
    
    hideAllOverlays() {
        document.getElementById('introOverlay')?.classList.remove('active');
        document.getElementById('revealOverlay')?.classList.remove('active');
        document.getElementById('scoreboardOverlay')?.classList.remove('active');
        document.getElementById('tiebreakerChoiceOverlay')?.classList.remove('active');
        document.getElementById('matchEndOverlay')?.classList.remove('active');
        document.getElementById('waitingBlockOverlay')?.classList.remove('active');
        document.getElementById('waitingOverlay')?.classList.remove('active');
    },
    
    showIntro(questionData) {
        // Stop any running timer before showing intro
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        if (answerTimerInterval) {
            clearInterval(answerTimerInterval);
            answerTimerInterval = null;
        }
        
        // Hide waiting overlay first
        document.getElementById('waitingOverlay')?.classList.remove('active');
        
        this.setPhase('intro', { questionData });
        updateSkillStates('intro');
        
        const introOverlay = document.getElementById('introOverlay');
        const questionNum = document.getElementById('introQuestionNumber');
        const theme = document.getElementById('introTheme');
        
        if (questionNum) {
            questionNum.textContent = `{{ __('Question') }} ${questionData.question_number}/${questionData.total_questions}`;
        }
        if (theme) {
            theme.textContent = questionData.theme || '{{ __("Culture générale") }}';
        }
        
        introOverlay?.classList.add('active');
        
        return new Promise(resolve => {
            setTimeout(() => {
                this.hideAllOverlays();
                resolve();
            }, this.phaseTimers.intro);
        });
    },
    
    startQuestion() {
        this.setPhase('question');
        updateSkillStates('question');
        canBuzz = true;
        hasBuzzed = false;
        
        // Reset UI state for new question
        document.getElementById('questionHeader').classList.remove('waiting-for-question');
        document.getElementById('buzzContainer').style.display = 'flex';
        document.getElementById('answersGrid').style.display = 'none';
        document.getElementById('buzzButton').disabled = false;
        
        // Clear buzz indicators
        document.getElementById('playerBuzzIndicator').classList.remove('buzzed');
        document.getElementById('opponentBuzzIndicator').classList.remove('buzzed');
        
        // Reset answer button states (clear correct/incorrect/disabled/selected classes)
        document.querySelectorAll('.answer-option').forEach(btn => {
            btn.classList.remove('correct', 'incorrect', 'disabled', 'selected');
            btn.disabled = false;
        });
        
        startBuzzTimer();
        
        setTimeout(() => applyAutoSkills('question'), 500);
    },
    
    onBuzz() {
        this.setPhase('buzz');
        
        const answersGrid = document.getElementById('answersGrid');
        if (answersGrid) answersGrid.style.display = 'grid';
    },
    
    showReveal(options = {}) {
        const {
            isCorrect = false,
            correctAnswer = '',
            points = 0,
            wasTimeout = false,
            playerScore = 0,
            opponentScore = 0,
            playerPoints = 0,
            opponentPoints = 0,
            wasPlayerFaster = false,
            wasOpponentFaster = false,
            userAnswerText = '—',
            userAnswerIndex = -1,
            questionNum = 1,
            totalQuestionsCount = totalQuestions,
            explanation = '',
            revealEndsAt = null
        } = options;
        
        // Stop any running timers before showing reveal
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        if (answerTimerInterval) {
            clearInterval(answerTimerInterval);
            answerTimerInterval = null;
        }
        if (this.revealCountdownInterval) {
            clearInterval(this.revealCountdownInterval);
            this.revealCountdownInterval = null;
        }
        
        this.setPhase('reveal', { isCorrect, correctAnswer, points, wasTimeout });
        updateSkillStates('reveal');
        canBuzz = false;
        
        // Track stats
        if (wasTimeout) {
            this.questionStats.noAnswer++;
        } else if (isCorrect) {
            this.questionStats.correct++;
        } else {
            this.questionStats.wrong++;
        }
        
        let finalIsCorrect = isCorrect;
        let finalPoints = points;
        const errorSkillResult = !isCorrect && !wasTimeout ? applyErrorSkills() : null;
        if (errorSkillResult?.cancelled) {
            finalIsCorrect = true;
            finalPoints = errorSkillResult.points || 0;
            this.questionStats.wrong--;
            this.questionStats.correct++;
        }
        
        const revealOverlay = document.getElementById('revealOverlay');
        const icon = document.getElementById('revealIcon');
        const message = document.getElementById('revealMessage');
        
        icon?.classList.remove('correct');
        message?.classList.remove('correct', 'incorrect', 'timeout');
        
        // Update result icon and message
        if (wasTimeout) {
            if (icon) icon.textContent = '⏱️';
            if (message) {
                message.textContent = '{{ __("Temps écoulé !") }}';
                message.classList.add('timeout');
            }
        } else if (finalIsCorrect) {
            if (icon) {
                icon.textContent = '✓';
                icon.classList.add('correct');
            }
            if (message) {
                message.textContent = errorSkillResult?.cancelled 
                    ? '{{ __("Erreur annulée !") }} ✨' 
                    : '{{ __("Bonne réponse !") }}';
                message.classList.add('correct');
            }
        } else {
            if (icon) icon.textContent = '✗';
            if (message) {
                message.textContent = '{{ __("Mauvaise réponse") }}';
                message.classList.add('incorrect');
            }
        }
        
        // Update round details - Player points
        const revealPlayerPoints = document.getElementById('revealPlayerPoints');
        const revealPlayerSpeed = document.getElementById('revealPlayerSpeed');
        if (revealPlayerPoints) {
            revealPlayerPoints.textContent = finalPoints > 0 ? `+${finalPoints}` : '+0';
            revealPlayerPoints.className = 'reveal-points-badge ' + (finalPoints > 0 ? 'points-gained' : 'points-neutral');
        }
        if (revealPlayerSpeed) {
            if (wasPlayerFaster) {
                revealPlayerSpeed.textContent = '1er';
                revealPlayerSpeed.className = 'reveal-speed-indicator first';
            } else if (wasOpponentFaster) {
                revealPlayerSpeed.textContent = '2ème';
                revealPlayerSpeed.className = 'reveal-speed-indicator';
            } else {
                revealPlayerSpeed.textContent = '';
            }
        }
        
        // Update round details - Opponent points
        const revealOpponentPoints = document.getElementById('revealOpponentPoints');
        const revealOpponentSpeed = document.getElementById('revealOpponentSpeed');
        if (revealOpponentPoints) {
            revealOpponentPoints.textContent = opponentPoints > 0 ? `+${opponentPoints}` : '+0';
            revealOpponentPoints.className = 'reveal-points-badge ' + (opponentPoints > 0 ? 'points-gained' : 'points-neutral');
        }
        if (revealOpponentSpeed) {
            if (wasOpponentFaster) {
                revealOpponentSpeed.textContent = '1er';
                revealOpponentSpeed.className = 'reveal-speed-indicator first';
            } else if (wasPlayerFaster) {
                revealOpponentSpeed.textContent = '2ème';
                revealOpponentSpeed.className = 'reveal-speed-indicator';
            } else {
                revealOpponentSpeed.textContent = '';
            }
        }
        
        // Update score battle
        const revealScorePlayer = document.getElementById('revealScorePlayer');
        const revealScoreOpponent = document.getElementById('revealScoreOpponent');
        if (revealScorePlayer) revealScorePlayer.textContent = playerScore;
        if (revealScoreOpponent) revealScoreOpponent.textContent = opponentScore;
        
        // Update answers section
        const revealUserAnswer = document.getElementById('revealUserAnswer');
        const revealUserAnswerRow = document.getElementById('revealUserAnswerRow');
        const revealUserAnswerIcon = document.getElementById('revealUserAnswerIcon');
        const revealCorrectAnswer = document.getElementById('revealCorrectAnswer');
        
        if (revealUserAnswer) {
            revealUserAnswer.textContent = wasTimeout ? '{{ __("Pas de réponse") }}' : userAnswerText;
        }
        if (revealUserAnswerRow) {
            revealUserAnswerRow.classList.toggle('was-correct', finalIsCorrect && !wasTimeout);
        }
        if (revealUserAnswerIcon) {
            revealUserAnswerIcon.textContent = finalIsCorrect && !wasTimeout ? '✅' : '❌';
        }
        if (revealCorrectAnswer) {
            revealCorrectAnswer.textContent = correctAnswer || '—';
        }
        
        // Update stats
        const revealStatMatchScore = document.getElementById('revealStatMatchScore');
        const revealStatQuestion = document.getElementById('revealStatQuestion');
        const revealStatNoAnswer = document.getElementById('revealStatNoAnswer');
        const revealStatCorrect = document.getElementById('revealStatCorrect');
        const revealStatWrong = document.getElementById('revealStatWrong');
        
        if (revealStatMatchScore) revealStatMatchScore.textContent = playerScore;
        if (revealStatQuestion) revealStatQuestion.textContent = `${questionNum}/${totalQuestionsCount}`;
        if (revealStatNoAnswer) revealStatNoAnswer.textContent = this.questionStats.noAnswer;
        if (revealStatCorrect) revealStatCorrect.textContent = this.questionStats.correct;
        if (revealStatWrong) revealStatWrong.textContent = this.questionStats.wrong;
        
        // Update skills button states in reveal overlay
        this.updateRevealSkillButtons();
        
        // Update "Le saviez-vous?" section with explanation
        const didYouKnowContent = document.getElementById('revealDidYouKnowContent');
        const didYouKnowSection = document.getElementById('revealDidYouKnow');
        const actualExplanation = explanation || currentQuestionData?.explanation || '';
        if (didYouKnowContent) {
            if (actualExplanation && actualExplanation.trim()) {
                didYouKnowContent.textContent = actualExplanation;
                if (didYouKnowSection) didYouKnowSection.style.display = 'block';
            } else {
                didYouKnowContent.textContent = '{{ __("Explication en cours de chargement...") }}';
                if (didYouKnowSection) didYouKnowSection.style.display = 'block';
            }
        }
        
        // Calculate synchronized countdown using revealEndsAt timestamp
        const effectiveRevealEndsAt = revealEndsAt || (Date.now() + this.phaseTimers.reveal);
        const calculateRemainingTime = () => Math.max(0, Math.ceil((effectiveRevealEndsAt - Date.now()) / 1000));
        
        let remainingSeconds = calculateRemainingTime();
        const countdownTimer = document.getElementById('revealCountdownTimer');
        
        // If time already expired, proceed immediately
        if (remainingSeconds <= 0) {
            if (countdownTimer) countdownTimer.textContent = '0';
            revealOverlay?.classList.add('active');
            return Promise.resolve();
        }
        
        if (countdownTimer) {
            countdownTimer.textContent = remainingSeconds;
            this.revealCountdownInterval = setInterval(() => {
                remainingSeconds = calculateRemainingTime();
                if (countdownTimer) countdownTimer.textContent = remainingSeconds;
                if (remainingSeconds <= 0) {
                    clearInterval(this.revealCountdownInterval);
                    this.revealCountdownInterval = null;
                }
            }, 200); // Update more frequently for better sync
        }
        
        revealOverlay?.classList.add('active');
        
        // Calculate actual remaining duration for Promise resolution
        const remainingDuration = Math.max(0, effectiveRevealEndsAt - Date.now());
        
        return new Promise(resolve => {
            setTimeout(() => {
                if (this.revealCountdownInterval) {
                    clearInterval(this.revealCountdownInterval);
                    this.revealCountdownInterval = null;
                }
                this.hideAllOverlays();
                resolve();
            }, remainingDuration);
        });
    },
    
    updateRevealSkillButtons() {
        const skillCircles = document.querySelectorAll('.skill-circle');
        skillCircles.forEach((circle, index) => {
            const isUsed = circle.classList.contains('used');
            const revealBtn = document.getElementById(`revealSkillBtn${index}`);
            if (revealBtn) {
                revealBtn.disabled = isUsed;
                revealBtn.textContent = isUsed ? '{{ __("Utilisé") }}' : '{{ __("Activer") }}';
            }
            const revealItem = document.getElementById(`revealSkill${index}`);
            if (revealItem) {
                revealItem.classList.toggle('used', isUsed);
            }
        });
    },
    
    showScoreboard(playerScore, opponentScore, hasNextQuestion, questionNum, totalQuestions) {
        // Stop any running timers before showing scoreboard
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        if (answerTimerInterval) {
            clearInterval(answerTimerInterval);
            answerTimerInterval = null;
        }
        
        this.setPhase('scoreboard', { playerScore, opponentScore, hasNextQuestion, questionNum, totalQuestions });
        
        const scoreboardOverlay = document.getElementById('scoreboardOverlay');
        
        const playerScoreEl = document.getElementById('scoreboardPlayerScore');
        const opponentScoreEl = document.getElementById('scoreboardOpponentScore');
        const progressEl = document.getElementById('scoreboardProgress');
        
        if (playerScoreEl) {
            playerScoreEl.textContent = playerScore;
            playerScoreEl.classList.toggle('leading', playerScore > opponentScore);
        }
        
        if (opponentScoreEl) {
            opponentScoreEl.textContent = opponentScore;
            opponentScoreEl.classList.toggle('leading', opponentScore > playerScore);
        }
        
        if (progressEl) {
            if (hasNextQuestion) {
                progressEl.textContent = `{{ __("Question") }} ${questionNum + 1}/${totalQuestions} {{ __("à venir...") }}`;
            } else {
                progressEl.textContent = '{{ __("Fin de la manche !") }}';
            }
        }
        
        if (scoreboardOverlay) {
            scoreboardOverlay.classList.add('active');
        }
        
        return new Promise(resolve => {
            setTimeout(() => {
                this.hideAllOverlays();
                resolve();
            }, this.phaseTimers.scoreboard);
        });
    },
    
    async finishPhase(options) {
        const {
            showRevealPhase = true,
            isCorrect = false,
            correctAnswer = '',
            points = 0,
            wasTimeout = false,
            playerScore = 0,
            opponentScore = 0,
            playerPoints = 0,
            opponentPoints = 0,
            wasPlayerFaster = false,
            wasOpponentFaster = false,
            userAnswerText = '—',
            userAnswerIndex = -1,
            hasNextQuestion = false,
            questionNum = 1,
            totalQuestionsParam = totalQuestions,
            explanation = '',
            revealEndsAt = null
        } = options;
        
        isProcessingPhase = true;
        
        // Calculate revealEndsAt if not provided (host broadcasts this timestamp)
        const effectiveRevealEndsAt = revealEndsAt || (Date.now() + this.phaseTimers.reveal);
        
        try {
            if (showRevealPhase && currentPhase !== 'reveal') {
                await this.showReveal({
                    isCorrect,
                    correctAnswer,
                    points,
                    wasTimeout,
                    playerScore,
                    opponentScore,
                    playerPoints: playerPoints || points,
                    opponentPoints,
                    wasPlayerFaster,
                    wasOpponentFaster,
                    userAnswerText,
                    userAnswerIndex,
                    questionNum,
                    totalQuestionsCount: totalQuestionsParam,
                    explanation: explanation || currentQuestionData?.explanation || '',
                    revealEndsAt: effectiveRevealEndsAt
                });
            }
            await this.showScoreboard(playerScore, opponentScore, hasNextQuestion, questionNum, totalQuestionsParam);
        } finally {
            isProcessingPhase = false;
        }
        
        if (!hasNextQuestion) {
            this.hideAllOverlays();
            return { proceed: false };
        }
        
        this.processQueue();
        
        return { proceed: hasNextQuestion };
    },
    
    async onAnswerComplete(options) {
        const {
            isCorrect = false,
            correctAnswer = '',
            points = 0,
            playerScore = 0,
            opponentScore = 0,
            playerPoints = 0,
            opponentPoints = 0,
            wasPlayerFaster = false,
            wasOpponentFaster = false,
            userAnswerText = '—',
            userAnswerIndex = -1,
            hasNextQuestion = false,
            questionNum = 1,
            totalQuestionsParam = totalQuestions,
            wasTimeout = false
        } = typeof options === 'object' ? options : {};
        
        // Support legacy call signature for backward compatibility
        if (typeof options !== 'object') {
            const args = arguments;
            return this.finishPhase({
                showRevealPhase: true,
                isCorrect: args[0] || false,
                correctAnswer: args[1] || '',
                points: args[2] || 0,
                playerScore: args[3] || 0,
                opponentScore: args[4] || 0,
                hasNextQuestion: args[5] || false,
                questionNum: args[6] || 1,
                totalQuestionsParam: args[7] || totalQuestions,
                wasTimeout: args[8] || false,
                playerPoints: args[2] || 0,
                opponentPoints: 0,
                wasPlayerFaster: false,
                wasOpponentFaster: false,
                userAnswerText: '—',
                userAnswerIndex: -1
            });
        }
        
        return this.finishPhase({
            showRevealPhase: true,
            isCorrect,
            correctAnswer,
            points,
            wasTimeout,
            playerScore,
            opponentScore,
            playerPoints: playerPoints || points,
            opponentPoints,
            wasPlayerFaster,
            wasOpponentFaster,
            userAnswerText,
            userAnswerIndex,
            hasNextQuestion,
            questionNum,
            totalQuestionsParam
        });
    },
    
    async onRoundComplete(playerScore, opponentScore, hasNextQuestion, questionNum, totalQuestionsParam) {
        return this.finishPhase({
            showRevealPhase: false,
            playerScore,
            opponentScore,
            hasNextQuestion,
            questionNum,
            totalQuestionsParam
        });
    },
    
    queueQuestion(questionData) {
        const qNum = questionData.question_number;
        
        if (qNum <= lastAcceptedQuestionNum) {
            console.log('[PhaseController] Ignoring already accepted question:', qNum);
            return;
        }
        
        const exists = pendingQuestionQueue.some(q => q.question_number === qNum);
        if (!exists) {
            pendingQuestionQueue.push(questionData);
            pendingQuestionQueue.sort((a, b) => a.question_number - b.question_number);
            console.log('[PhaseController] Queued question:', qNum, 'queue length:', pendingQuestionQueue.length);
        }
    },
    
    processQueue() {
        if (isProcessingPhase || pendingQuestionQueue.length === 0) {
            return;
        }
        
        const nextQ = pendingQuestionQueue.shift();
        
        if (nextQ.question_number <= lastAcceptedQuestionNum) {
            this.processQueue();
            return;
        }
        
        this.startNextQuestion(nextQ);
    },
    
    startNextQuestion(questionData, retryCount = 0) {
        const qNum = questionData.question_number;
        const MAX_RETRIES = 2;
        
        if (qNum <= lastAcceptedQuestionNum) {
            console.log('[PhaseController] Skipping already accepted question:', qNum);
            return;
        }
        
        currentQuestionData = questionData;
        isProcessingPhase = true;
        
        this.showIntro(questionData).then(() => {
            lastAcceptedQuestionNum = qNum;
            loadQuestionIntoUI(questionData);
            resetGameplayState();
            this.startQuestion();
        }).catch(err => {
            console.error('[PhaseController] Intro error:', err);
            if (retryCount < MAX_RETRIES) {
                console.log('[PhaseController] Retrying intro, attempt:', retryCount + 1);
                setTimeout(() => this.startNextQuestion(questionData, retryCount + 1), 500);
            } else {
                console.log('[PhaseController] Max retries reached, proceeding to question');
                lastAcceptedQuestionNum = qNum;
                loadQuestionIntoUI(questionData);
                resetGameplayState();
                this.hideAllOverlays();
                this.startQuestion();
            }
        });
    },
    
    showTiebreakerChoice() {
        this.setPhase('tiebreaker_choice');
        
        const overlay = document.getElementById('tiebreakerChoiceOverlay');
        const timerEl = document.getElementById('tiebreakerTimer');
        let countdown = 10;
        
        if (timerEl) timerEl.textContent = countdown;
        overlay?.classList.add('active');
        
        const tiebreakerInterval = setInterval(() => {
            countdown--;
            if (timerEl) timerEl.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(tiebreakerInterval);
                selectTiebreakerMode('question');
            }
        }, 1000);
        
        window.tiebreakerInterval = tiebreakerInterval;
    },
    
    showMatchEnd(isVictory, playerScore, opponentScore, rewards = {}) {
        this.setPhase('match_end', { isVictory, playerScore, opponentScore, rewards });
        
        const overlay = document.getElementById('matchEndOverlay');
        const iconEl = document.getElementById('matchResultIcon');
        const titleEl = document.getElementById('matchResultTitle');
        const playerScoreEl = document.getElementById('finalPlayerScore');
        const opponentScoreEl = document.getElementById('finalOpponentScore');
        const coinsEl = document.getElementById('rewardCoins');
        const betEl = document.getElementById('rewardBet');
        const efficiencyEl = document.getElementById('matchEfficiency');
        
        titleEl?.classList.remove('victory', 'defeat', 'draw');
        
        if (playerScore === opponentScore) {
            if (iconEl) iconEl.textContent = '🤝';
            if (titleEl) {
                titleEl.textContent = '{{ __("Égalité !") }}';
                titleEl.classList.add('draw');
            }
        } else if (isVictory) {
            if (iconEl) iconEl.textContent = '🏆';
            if (titleEl) {
                titleEl.textContent = '{{ __("Victoire !") }}';
                titleEl.classList.add('victory');
            }
        } else {
            if (iconEl) iconEl.textContent = '😔';
            if (titleEl) {
                titleEl.textContent = '{{ __("Défaite") }}';
                titleEl.classList.add('defeat');
            }
        }
        
        if (playerScoreEl) playerScoreEl.textContent = playerScore;
        if (opponentScoreEl) opponentScoreEl.textContent = opponentScore;
        
        if (rewards.coins && coinsEl) {
            coinsEl.textContent = `🪙 +${rewards.coins} {{ __("pièces") }}`;
            coinsEl.style.display = 'block';
        }
        
        if (rewards.bet && betEl) {
            betEl.textContent = `💰 ${rewards.bet > 0 ? '+' : ''}${rewards.bet} {{ __("mise") }}`;
            betEl.style.display = 'block';
        }
        
        if (rewards.efficiency && efficiencyEl) {
            efficiencyEl.textContent = `{{ __("Efficacité") }}: ${rewards.efficiency}%`;
        }
        
        overlay?.classList.add('active');
    }
};

function selectTiebreakerMode(mode) {
    if (window.tiebreakerInterval) {
        clearInterval(window.tiebreakerInterval);
    }
    
    PhaseController.hideAllOverlays();
    
    fetch(`/duo/matches/${matchId}/tiebreaker-choice`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ mode: mode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadGameState();
        }
    })
    .catch(err => console.error('Tiebreaker choice error:', err));
}

function goToResults() {
    window.location.href = `/duo/result/${matchId}`;
}

function showMatchEndOverlay() {
    const playerScore = parseInt(document.getElementById('playerScore').textContent) || 0;
    const opponentScore = parseInt(document.getElementById('opponentScore').textContent) || 0;
    const isVictory = playerScore > opponentScore;
    const efficiency = totalQuestions > 0 ? Math.round((playerScore / (totalQuestions * 10)) * 100) : 0;
    PhaseController.showMatchEnd(isVictory, playerScore, opponentScore, {
        coins: 10,
        efficiency: Math.min(efficiency, 100)
    });
}

function startBuzzTimer() {
    timeLeft = 8;
    updateChronoDisplay();
    
    if (timerInterval) clearInterval(timerInterval);
    
    timerInterval = setInterval(() => {
        timeLeft--;
        updateChronoDisplay();
        
        if (timeLeft <= 3 && timeLeft > 0) {
            playSound('timerTickSound');
        }
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            if (!hasBuzzed) {
                handleTimeout();
            }
        }
    }, 1000);
}

function startAnswerTimer() {
    answerTimeLeft = 10;
    updatePointBadges();
    
    if (answerTimerInterval) clearInterval(answerTimerInterval);
    
    answerTimerInterval = setInterval(() => {
        answerTimeLeft--;
        updatePointBadges();
        
        if (answerTimeLeft <= 0) {
            clearInterval(answerTimerInterval);
            handleAnswerTimeout();
        }
    }, 1000);
}

function updateChronoDisplay() {
    const chronoTimer = document.getElementById('chronoTimer');
    if (chronoTimer) chronoTimer.textContent = timeLeft;
}

function updatePointBadges() {
    let points, badgeClass;
    if (answerTimeLeft > 3) {
        points = 2;
        badgeClass = 'points-high';
    } else if (answerTimeLeft >= 1) {
        points = 1;
        badgeClass = 'points-medium';
    } else {
        points = 0;
        badgeClass = 'points-low';
    }
    
    for (let i = 0; i < 4; i++) {
        const badge = document.getElementById(`badge${i}`);
        if (badge) {
            badge.textContent = points > 0 ? `+${points}` : '0';
            badge.className = `point-badge ${badgeClass}`;
        }
    }
}

function playSound(soundId) {
    const sound = document.getElementById(soundId);
    if (sound) {
        sound.currentTime = 0;
        sound.play().catch(e => console.log('Audio play failed:', e));
    }
}

function handleTimeout() {
    playSound('noBuzzSound');
    canBuzz = false;
    document.getElementById('buzzButton').disabled = true;
    
    if (useSocketIO) {
        return;
    }
    
    fetch(`/duo/matches/${matchId}/timeout`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            question_id: currentQuestionData?.question_number?.toString() || '1',
            timeout_type: 'buzz'
        })
    })
    .then(response => response.json())
    .then(data => {
        const rawCorrectAnswer = data.correct_answer || '';
        const correctAnswer = typeof rawCorrectAnswer === 'object' ? (rawCorrectAnswer.text || rawCorrectAnswer.label || '') : (rawCorrectAnswer || '');
        
        PhaseController.onAnswerComplete(
            false,
            correctAnswer,
            0,
            parseInt(document.getElementById('playerScore').textContent) || 0,
            parseInt(document.getElementById('opponentScore').textContent) || 0,
            currentQuestionData?.has_next_question ?? true,
            currentQuestionData?.question_number || 1,
            totalQuestions,
            true
        ).then(() => {
            if (currentQuestionData?.has_next_question) {
                loadGameState();
            } else {
                showMatchEndOverlay();
            }
        });
    })
    .catch(() => {
        PhaseController.onAnswerComplete(
            false,
            '',
            0,
            parseInt(document.getElementById('playerScore').textContent) || 0,
            parseInt(document.getElementById('opponentScore').textContent) || 0,
            currentQuestionData?.has_next_question ?? true,
            currentQuestionData?.question_number || 1,
            totalQuestions,
            true
        ).then(() => {
            if (currentQuestionData?.has_next_question) {
                loadGameState();
            } else {
                showMatchEndOverlay();
            }
        });
    });
}

function handleAnswerTimeout() {
    const buttons = document.querySelectorAll('.answer-option');
    buttons.forEach(btn => btn.classList.add('disabled'));
    
    if (useSocketIO) {
        duoSocket.answer(-1);
        return;
    }
    
    fetch(`/duo/matches/${matchId}/timeout`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            question_id: currentQuestionData?.question_number?.toString() || '1',
            timeout_type: 'answer'
        })
    })
    .then(response => response.json())
    .then(data => {
        const rawCorrectAnswer = data.correct_answer || '';
        const correctAnswer = typeof rawCorrectAnswer === 'object' ? (rawCorrectAnswer.text || rawCorrectAnswer.label || '') : (rawCorrectAnswer || '');
        const correctIndex = data.correct_index ?? -1;
        
        if (correctIndex >= 0) {
            highlightAnswersAfterReveal(-1, correctIndex, false);
        }
        
        PhaseController.onAnswerComplete(
            false,
            correctAnswer,
            0,
            parseInt(document.getElementById('playerScore').textContent) || 0,
            parseInt(document.getElementById('opponentScore').textContent) || 0,
            currentQuestionData?.has_next_question ?? true,
            currentQuestionData?.question_number || 1,
            totalQuestions,
            true
        ).then(() => {
            if (currentQuestionData?.has_next_question) {
                loadGameState();
            } else {
                showMatchEndOverlay();
            }
        });
    })
    .catch(() => {
        PhaseController.onAnswerComplete(
            false,
            '',
            0,
            parseInt(document.getElementById('playerScore').textContent) || 0,
            parseInt(document.getElementById('opponentScore').textContent) || 0,
            currentQuestionData?.has_next_question ?? true,
            currentQuestionData?.question_number || 1,
            totalQuestions,
            true
        ).then(() => {
            if (currentQuestionData?.has_next_question) {
                loadGameState();
            } else {
                showMatchEndOverlay();
            }
        });
    });
}

function loadGameState() {
    fetch(`/duo/matches/${matchId}/game-state`)
        .then(response => response.json())
        .then(data => {
            gameState = data.game_state;
            updateUI(data);
        })
        .catch(err => console.error('Error loading game state:', err));
}

function updateUI(data) {
    if (!data) return;
    
    const state = data.game_state || data;
    const serverPhase = (state.phase || state.game_phase || 'WAITING').toUpperCase();
    
    document.getElementById('playerScore').textContent = state.score || state.player_score || 0;
    document.getElementById('opponentScore').textContent = state.opponent_score || 0;
    
    if (state.opponent_name) {
        document.getElementById('opponentName').textContent = state.opponent_name;
        document.getElementById('scoreboardOpponentName').textContent = state.opponent_name;
    }
    
    updateRoundIndicators(state.player_rounds_won || 0, state.opponent_rounds_won || 0, state.current_round || 1);
    
    switch (serverPhase) {
        case 'TIEBREAKER_CHOICE':
            if (currentPhase !== 'tiebreaker_choice') {
                PhaseController.showTiebreakerChoice();
            }
            return;
            
        case 'TIEBREAKER_QUESTION':
            if (state.tiebreaker_question && currentPhase !== 'question' && currentPhase !== 'answer') {
                currentQuestionData = {
                    question_number: 'TB',
                    total_questions: 1,
                    theme: state.theme || '{{ $theme }}',
                    question_text: state.tiebreaker_question.text,
                    answers: state.tiebreaker_question.answers || [],
                    correct_answer: '',
                    correct_index: -1,
                    has_next_question: false,
                    explanation: state.tiebreaker_question.explanation || ''
                };
                loadQuestionIntoUI(currentQuestionData);
                resetGameplayState();
                currentPhase = 'waiting';
                PhaseController.hideAllOverlays();
                PhaseController.startQuestion();
            }
            return;
            
        case 'MATCH_END':
            if (currentPhase !== 'match_end') {
                const playerScore = state.score || state.player_score || 0;
                const opponentScore = state.opponent_score || 0;
                const isVictory = playerScore > opponentScore;
                const rewards = {
                    coins: state.coins_earned || 0,
                    bet: state.bet_result || 0,
                    efficiency: state.match_efficiency || 0
                };
                PhaseController.showMatchEnd(isVictory, playerScore, opponentScore, rewards);
            }
            return;
            
        case 'REVEAL':
            if (currentPhase !== 'reveal') {
                currentPhase = 'reveal';
                const result = state.last_answer_result || {};
                const rawCorrectAnswer = result.correct_answer || state.correct_answer || '';
                const correctAnswer = typeof rawCorrectAnswer === 'object' ? (rawCorrectAnswer.text || rawCorrectAnswer.label || '') : (rawCorrectAnswer || '');
                
                PhaseController.showReveal({
                    isCorrect: result.is_correct || false,
                    correctAnswer: correctAnswer,
                    points: result.points || 0,
                    wasTimeout: result.was_timeout || false,
                    explanation: currentQuestionData?.explanation || '',
                    revealEndsAt: state.reveal_ends_at || (Date.now() + PhaseController.phaseTimers.reveal)
                });
            }
            return;
            
        case 'ROUND_SCOREBOARD':
        case 'SCOREBOARD':
            if (currentPhase !== 'scoreboard') {
                currentPhase = 'scoreboard';
                const pScore = state.score || state.player_score || 0;
                const oScore = state.opponent_score || 0;
                const hasNext = state.has_next_question ?? true;
                const qNum = state.current_question_number || 1;
                PhaseController.showScoreboard(pScore, oScore, hasNext, qNum, totalQuestions);
            }
            return;
            
        case 'INTRO':
            if (state.current_question) {
                const qNum = state.current_question_number || 1;
                
                const questionData = {
                    question_number: qNum,
                    total_questions: totalQuestions,
                    theme: state.theme || '{{ $theme }}',
                    question_text: state.current_question.text,
                    answers: state.current_question.answers || [],
                    correct_answer: '',
                    correct_index: -1,
                    has_next_question: state.has_next_question ?? true,
                    explanation: state.current_question.explanation || ''
                };
                
                if (isProcessingPhase || currentPhase === 'reveal' || currentPhase === 'scoreboard') {
                    PhaseController.queueQuestion(questionData);
                    return;
                }
                
                if (currentPhase === 'intro' || currentPhase === 'question' || currentPhase === 'answer') {
                    return;
                }
                
                PhaseController.startNextQuestion(questionData);
            }
            return;
            
        case 'QUESTION_ACTIVE':
        case 'QUESTION':
            if (currentPhase === 'question' || currentPhase === 'answer' || currentPhase === 'intro') {
                return;
            }
            
            if (state.current_question) {
                const qNum = state.current_question_number || 1;
                
                if (qNum <= lastAcceptedQuestionNum) {
                    return;
                }
                
                const questionData = {
                    question_number: qNum,
                    total_questions: totalQuestions,
                    theme: state.theme || '{{ $theme }}',
                    question_text: state.current_question.text,
                    answers: state.current_question.answers || [],
                    correct_answer: '',
                    correct_index: -1,
                    has_next_question: state.has_next_question ?? true,
                    explanation: state.current_question.explanation || ''
                };
                
                if (isProcessingPhase || currentPhase === 'reveal' || currentPhase === 'scoreboard') {
                    PhaseController.queueQuestion(questionData);
                    return;
                }
                
                PhaseController.startNextQuestion(questionData);
            }
            return;
            
        case 'ANSWER_SELECTION':
            return;
            
        case 'WAITING':
        default:
            break;
    }
    
    if (state.match_finished) {
        if (currentPhase !== 'match_end') {
            const playerScore = state.score || state.player_score || 0;
            const opponentScore = state.opponent_score || 0;
            const isVictory = playerScore > opponentScore;
            const rewards = {
                coins: state.coins_earned || 0,
                bet: state.bet_result || 0,
                efficiency: state.match_efficiency || 0
            };
            PhaseController.showMatchEnd(isVictory, playerScore, opponentScore, rewards);
        }
        return;
    }
    
    if (state.current_question) {
        const qNum = state.current_question_number || 1;
        
        if (qNum <= lastAcceptedQuestionNum) {
            return;
        }
        
        const questionData = {
            question_number: qNum,
            total_questions: totalQuestions,
            theme: state.theme || '{{ $theme }}',
            question_text: state.current_question.text,
            answers: state.current_question.answers || [],
            correct_answer: '',
            correct_index: -1,
            has_next_question: state.has_next_question ?? true,
            explanation: state.current_question.explanation || ''
        };
        
        if (isProcessingPhase || currentPhase === 'reveal' || currentPhase === 'scoreboard') {
            PhaseController.queueQuestion(questionData);
            return;
        }
        
        if (currentPhase === 'waiting') {
            PhaseController.startNextQuestion(questionData);
        }
    }
}

function loadQuestionIntoUI(questionData) {
    document.getElementById('currentQuestionNum').textContent = questionData.question_number;
    document.getElementById('questionText').textContent = questionData.question_text;
    document.getElementById('questionHeader').classList.remove('waiting-for-question');
    
    if (questionData.answers && questionData.answers.length >= 4) {
        for (let i = 0; i < 4; i++) {
            const btn = document.getElementById(`answer${i}`);
            if (btn) {
                // Normalize answer: can be string or {text: "...", value: ...} object
                const answer = questionData.answers[i];
                const answerTextValue = typeof answer === 'object' ? (answer.text || answer.label || '') : (answer || '');
                
                const answerText = btn.querySelector('.answer-text');
                if (answerText) {
                    answerText.textContent = answerTextValue;
                } else {
                    btn.innerHTML = `<span class="point-badge" id="badge${i}"></span><span class="answer-text">${answerTextValue}</span>`;
                }
                btn.classList.remove('correct', 'incorrect', 'disabled');
                btn.disabled = false;
            }
        }
    }
}

function updateRoundIndicators(playerWon, opponentWon, currentRound) {
    for (let i = 1; i <= 3; i++) {
        const dot = document.getElementById(`roundDot${i}`);
        if (dot) {
            dot.classList.remove('player-won', 'opponent-won', 'current');
            if (i <= playerWon) {
                dot.classList.add('player-won');
            } else if (i <= (playerWon + opponentWon) && i > playerWon) {
                dot.classList.add('opponent-won');
            }
            if (i === currentRound) {
                dot.classList.add('current');
            }
        }
    }
}

document.getElementById('buzzButton').addEventListener('click', function() {
    if (!canBuzz || hasBuzzed) return;
    
    hasBuzzed = true;
    canBuzz = false;
    this.disabled = true;
    
    if (timerInterval) clearInterval(timerInterval);
    
    playSound('buzzerSound');
    document.getElementById('playerBuzzIndicator').classList.add('buzzed');
    
    if (useSocketIO) {
        duoSocket.buzz(Date.now());
    } else {
        fetch(`/duo/matches/${matchId}/buzz`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                question_id: currentQuestionData?.question_number?.toString() || '1',
                client_time: Date.now() / 1000
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success || data.canAnswer) {
                showAnswers();
            }
        })
        .catch(err => {
            console.error('Buzz error:', err);
            showAnswers();
        });
    }
});

function showAnswers() {
    currentPhase = 'answer';
    updateSkillStates('answer');
    
    document.getElementById('buzzContainer').style.display = 'none';
    document.getElementById('answersGrid').style.display = 'grid';
    
    startAnswerTimer();
}

document.querySelectorAll('.answer-option').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.classList.contains('disabled')) return;
        
        const answerIndex = parseInt(this.dataset.index);
        
        if (answerTimerInterval) clearInterval(answerTimerInterval);
        
        document.querySelectorAll('.answer-option').forEach(b => {
            b.classList.add('disabled');
        });
        
        this.classList.add('selected');
        
        submitAnswer(answerIndex);
    });
});

function submitAnswer(answerIndex) {
    if (useSocketIO) {
        duoSocket.answer(answerIndex);
        return;
    }
    
    fetch(`/duo/matches/${matchId}/submit-answer`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            question_id: currentQuestionData?.question_number?.toString() || '1',
            answer: ['A', 'B', 'C', 'D'][answerIndex] || 'A',
            answer_index: answerIndex
        })
    })
    .then(response => response.json())
    .then(data => {
        const rawCorrectAnswer = data.correct_answer || '';
        const correctAnswer = typeof rawCorrectAnswer === 'object' ? (rawCorrectAnswer.text || rawCorrectAnswer.label || '') : (rawCorrectAnswer || '');
        const points = data.points ?? 0;
        const correctIndex = data.correct_index ?? -1;
        currentQuestionData.correct_answer = correctAnswer;
        
        highlightAnswersAfterReveal(answerIndex, correctIndex, isCorrect);
        
        playSound(isCorrect ? 'correctSound' : 'incorrectSound');
        
        const playerScore = data.player_score || data.gameState?.score || parseInt(document.getElementById('playerScore').textContent);
        const opponentScore = data.opponent_score || data.gameState?.opponent_score || parseInt(document.getElementById('opponentScore').textContent);
        
        document.getElementById('playerScore').textContent = playerScore;
        document.getElementById('opponentScore').textContent = opponentScore;
        
        const hasNextQuestion = data.hasMoreQuestions ?? data.has_next_question ?? true;
        const questionNum = currentQuestionData?.question_number || 1;
        
        PhaseController.onAnswerComplete(
            isCorrect,
            correctAnswer,
            points,
            playerScore,
            opponentScore,
            hasNextQuestion,
            questionNum,
            totalQuestions,
            false
        ).then(() => {
            if (hasNextQuestion) {
                resetForNextQuestion();
                loadGameState();
            } else {
                if (data.roundFinished || data.round_finished) {
                    const pWon = data.gameState?.player_rounds_won || data.player_rounds_won || 0;
                    const oWon = data.gameState?.opponent_rounds_won || data.opponent_rounds_won || 0;
                    
                    if (pWon >= 2 || oWon >= 2) {
                        showMatchEndOverlay();
                    } else {
                        resetForNextQuestion();
                        loadGameState();
                    }
                } else {
                    showMatchEndOverlay();
                }
            }
        });
    })
    .catch(err => {
        console.error('Answer submission error:', err);
    });
}

function highlightAnswersAfterReveal(selectedIndex, correctIndex, isCorrect) {
    document.querySelectorAll('.answer-option').forEach((btn, i) => {
        btn.classList.add('disabled');
        btn.classList.remove('selected');
        if (i === correctIndex) {
            btn.classList.add('correct');
        }
    });
    
    if (!isCorrect && selectedIndex >= 0) {
        const selectedBtn = document.getElementById(`answer${selectedIndex}`);
        if (selectedBtn) {
            selectedBtn.classList.add('incorrect');
        }
    }
}

function resetGameplayState() {
    hasBuzzed = false;
    canBuzz = false;
    
    if (timerInterval) clearInterval(timerInterval);
    if (answerTimerInterval) clearInterval(answerTimerInterval);
    
    document.getElementById('buzzButton').disabled = true;
    document.getElementById('playerBuzzIndicator').classList.remove('buzzed');
    document.getElementById('opponentBuzzIndicator').classList.remove('buzzed');
    
    // Reset answer button states
    document.querySelectorAll('.answer-option').forEach(btn => {
        btn.classList.remove('correct', 'incorrect', 'disabled', 'selected');
        btn.disabled = false;
    });
    
    // Reset UI to waiting-for-question state
    document.getElementById('answersGrid').style.display = 'none';
    document.getElementById('buzzContainer').style.display = 'flex';
    document.getElementById('questionHeader').classList.add('waiting-for-question');
}

function resetForNextQuestion() {
    resetGameplayState();
    currentPhase = 'waiting';
}

function initSocketIO() {
    if (typeof duoSocket === 'undefined' || typeof io === 'undefined') {
        console.log('[DuoGame] Socket.IO client not loaded, using HTTP polling');
        return Promise.resolve(false);
    }
    
    if (!gameServerUrl || !roomId || !jwtToken) {
        console.log('[DuoGame] Socket.IO not available, using HTTP polling');
        return Promise.resolve(false);
    }
    
    console.log('[DuoGame] Initializing Socket.IO connection...');
    
    duoSocket.onConnect = () => {
        console.log('[DuoGame] Socket.IO connected');
        useSocketIO = true;
        
        duoSocket.joinRoom(roomId, lobbyCode, {
            playerId: userId,
            playerName: '{{ $match->player1_id == Auth::id() ? ($match->player1->name ?? "Joueur") : ($match->player2->name ?? "Joueur") }}',
            token: jwtToken
        });
    };
    
    duoSocket.onDisconnect = (reason) => {
        console.log('[DuoGame] Socket.IO disconnected:', reason);
        useSocketIO = false;
    };
    
    duoSocket.onError = (error) => {
        console.error('[DuoGame] Socket.IO error:', error);
    };
    
    duoSocket.onPhaseChanged = (data) => {
        console.log('[DuoGame] Phase changed:', data);
        phaseEndsAtMs = data.phaseEndsAtMs;
        handleServerPhase(data.phase, data);
    };
    
    duoSocket.onQuestionPublished = (data) => {
        console.log('[DuoGame] Question published:', data);
        currentQuestionData = {
            question_number: data.questionIndex + 1,
            total_questions: data.totalQuestions || totalQuestions,
            theme: data.category || '{{ $theme }}',
            question_text: data.text,
            answers: data.choices || [],
            correct_answer: '',
            correct_index: -1,
            has_next_question: (data.questionIndex + 1) < (data.totalQuestions || totalQuestions),
            explanation: data.explanation || ''
        };
        loadQuestionIntoUI(currentQuestionData);
    };
    
    duoSocket.onBuzzWinner = (data) => {
        console.log('[DuoGame] Buzz winner:', data);
        
        if (data.playerId == userId) {
            document.getElementById('playerBuzzIndicator').classList.add('buzzed');
            hasBuzzed = true;
            showAnswers();
            startAnswerTimer();
        } else {
            document.getElementById('opponentBuzzIndicator').classList.add('buzzed');
            canBuzz = false;
            document.getElementById('buzzButton').disabled = true;
        }
    };
    
    duoSocket.onAnswerRevealed = (data) => {
        console.log('[DuoGame] Answer revealed:', data);
        
        const isMyAnswer = data.playerId == userId;
        const isCorrect = data.isCorrect || false;
        const points = data.pointsEarned || 0;
        const wasTimeout = data.timeout || false;
        const correctIndex = data.correctIndex ?? -1;
        const rawCorrectAnswer = data.correctAnswer?.toString() || '';
        const correctAnswer = typeof rawCorrectAnswer === 'object' ? (rawCorrectAnswer.text || rawCorrectAnswer.label || '') : (rawCorrectAnswer || '');
        const selectedIndex = data.answerIndex ?? -1;
        const revealEndsAt = data.revealEndsAt || phaseEndsAtMs || (Date.now() + PhaseController.phaseTimers.reveal);
        
        if (currentQuestionData) {
            currentQuestionData.correct_index = correctIndex;
            currentQuestionData.correct_answer = correctAnswer;
        }
        
        if (isMyAnswer) {
            document.getElementById('playerScore').textContent = data.totalScore || 0;
            
            if (correctIndex >= 0) {
                highlightAnswersAfterReveal(selectedIndex, correctIndex, isCorrect);
            }
            
            playSound(isCorrect ? 'correctSound' : 'incorrectSound');
            
            PhaseController.showReveal({
                isCorrect: isCorrect,
                correctAnswer: correctAnswer,
                points: points,
                wasTimeout: wasTimeout,
                explanation: data.explanation || currentQuestionData?.explanation || '',
                revealEndsAt: revealEndsAt
            });
        } else {
            document.getElementById('opponentScore').textContent = data.totalScore || 0;
            if (currentPhase !== 'reveal') {
                currentPhase = 'reveal';
                PhaseController.showReveal({
                    isCorrect: isCorrect,
                    correctAnswer: correctAnswer,
                    points: points,
                    wasTimeout: wasTimeout,
                    explanation: data.explanation || currentQuestionData?.explanation || '',
                    revealEndsAt: revealEndsAt
                });
            }
        }
    };
    
    duoSocket.onScoreUpdate = (data) => {
        console.log('[DuoGame] Score update:', data);
        if (data.playerId == userId) {
            document.getElementById('playerScore').textContent = data.score || 0;
        } else {
            document.getElementById('opponentScore').textContent = data.score || 0;
        }
    };
    
    duoSocket.onRoundEnded = async (data) => {
        console.log('[DuoGame] Round ended:', data);
        const playerScore = data.playerScores?.[userId] || 0;
        const opponentId = Object.keys(data.playerScores || {}).find(id => id != userId);
        const opponentScore = opponentId ? data.playerScores[opponentId] : 0;
        const qNum = currentQuestionData?.question_number || 1;
        const isMatchOver = data.matchEnded === true || data.isMatchOver === true;
        const hasNextQuestion = !isMatchOver && (data.hasNextQuestion !== false);
        
        updateRoundIndicators(
            data.playerRoundsWon?.[userId] || 0,
            opponentId ? (data.playerRoundsWon?.[opponentId] || 0) : 0,
            data.roundNumber
        );
        
        await PhaseController.onRoundComplete(
            playerScore,
            opponentScore,
            hasNextQuestion,
            qNum,
            totalQuestions
        );
    };
    
    duoSocket.onMatchEnded = (data) => {
        console.log('[DuoGame] Match ended:', data);
        const isVictory = data.winnerId == userId;
        const playerScore = data.finalScores?.[userId] || 0;
        const opponentId = Object.keys(data.finalScores || {}).find(id => id != userId);
        const opponentScore = opponentId ? data.finalScores[opponentId] : 0;
        
        PhaseController.showMatchEnd(isVictory, playerScore, opponentScore, {
            coins: 0,
            bet: 0,
            efficiency: 0
        });
    };
    
    duoSocket.onSkillUsed = (data) => {
        console.log('[DuoGame] Skill used:', data);
        if (data.sourcePlayerId != userId) {
            handleOpponentSkill(data);
        }
    };
    
    duoSocket.onWaitingBlock = (data) => {
        console.log('[DuoGame] Waiting block:', data);
        showWaitingBlockOverlay(data.nextBlockStart, data.nextBlockEnd, data.waitingEndsAtMs);
    };
    
    return duoSocket.connect(gameServerUrl, jwtToken)
        .then(() => {
            console.log('[DuoGame] Socket.IO connection established');
            return true;
        })
        .catch(err => {
            console.error('[DuoGame] Socket.IO connection failed:', err);
            return false;
        });
}

function handleServerPhase(phase, data) {
    const normalizedPhase = phase.toUpperCase();
    const phaseData = data.phaseData || data || {};
    const previousPhase = currentPhase;
    console.log('[DuoGame] Handling server phase:', normalizedPhase, 'from:', previousPhase, 'phaseData:', phaseData);
    
    // Helper to check if transitioning from reveal/scoreboard phases
    const isFromRevealOrScoreboard = ['reveal', 'scoreboard', 'round_scoreboard'].includes(previousPhase);
    
    switch (normalizedPhase) {
        case 'INTRO':
            if (currentPhase !== 'intro') {
                // Reset state when transitioning from reveal/scoreboard
                if (isFromRevealOrScoreboard) {
                    resetGameplayState();
                }
                const questionData = phaseData.questionData || currentQuestionData;
                if (questionData) {
                    PhaseController.showIntro(questionData).then(() => {
                        PhaseController.startQuestion();
                    });
                }
            }
            break;
            
        case 'QUESTION_ACTIVE':
        case 'QUESTION':
            if (currentPhase !== 'question' && currentPhase !== 'answer') {
                // Reset state when transitioning from reveal/scoreboard
                if (isFromRevealOrScoreboard) {
                    resetGameplayState();
                }
                // Hide waiting overlay and show intro first if we have question data
                hideWaitingOverlay();
                hideWaitingBlockOverlay();
                const questionData = phaseData.questionData || currentQuestionData;
                if (questionData && currentPhase !== 'intro') {
                    PhaseController.showIntro(questionData).then(() => {
                        PhaseController.startQuestion();
                    });
                } else {
                    PhaseController.hideAllOverlays();
                    PhaseController.startQuestion();
                }
            }
            break;
            
        case 'WAITING':
            currentPhase = 'waiting_block';
            break;
            
        case 'ANSWER_SELECTION':
            break;
            
        case 'REVEAL':
            if (currentPhase !== 'reveal' && phaseData) {
                const rawCorrectAnswer = phaseData.correctAnswer || '';
                const correctAnswer = typeof rawCorrectAnswer === 'object' ? (rawCorrectAnswer.text || rawCorrectAnswer.label || '') : (rawCorrectAnswer || '');
                
                PhaseController.showReveal({
                    isCorrect: phaseData.isCorrect || false,
                    correctAnswer: correctAnswer,
                    points: phaseData.points || 0,
                    wasTimeout: phaseData.wasTimeout || false,
                    explanation: phaseData.explanation || currentQuestionData?.explanation || '',
                    revealEndsAt: phaseData.revealEndsAt || phaseEndsAtMs || (Date.now() + PhaseController.phaseTimers.reveal)
                });
            }
            break;
            
        case 'ROUND_SCOREBOARD':
        case 'SCOREBOARD':
            if (currentPhase !== 'scoreboard' && phaseData) {
                PhaseController.showScoreboard(
                    phaseData.playerScore || 0,
                    phaseData.opponentScore || 0,
                    phaseData.hasNextQuestion !== false,
                    phaseData.questionNum || 1,
                    phaseData.totalQuestions || totalQuestions
                );
            }
            break;
            
        case 'TIEBREAKER_CHOICE':
            if (currentPhase !== 'tiebreaker_choice') {
                PhaseController.showTiebreakerChoice();
            }
            break;
            
        case 'TIEBREAKER_QUESTION':
            if (currentPhase !== 'question' && currentPhase !== 'answer') {
                // Reset state when transitioning from any overlay phase
                if (isFromRevealOrScoreboard) {
                    resetGameplayState();
                }
                currentPhase = 'waiting';
                PhaseController.hideAllOverlays();
                PhaseController.startQuestion();
            }
            break;
            
        case 'MATCH_END':
            if (phaseData) {
                PhaseController.showMatchEnd(
                    phaseData.isVictory || false,
                    phaseData.playerScore || 0,
                    phaseData.opponentScore || 0,
                    phaseData.rewards || { coins: 0, bet: 0, efficiency: 0 }
                );
            }
            break;
            
        default:
            console.log('[DuoGame] Unknown phase:', normalizedPhase);
    }
}

let waitingBlockCountdownInterval = null;

function showWaitingBlockOverlay(blockStart, blockEnd, endsAtMs) {
    const overlay = document.getElementById('waitingBlockOverlay');
    const blockRange = document.getElementById('waitingBlockRange');
    const timer = document.getElementById('waitingBlockTimer');
    
    if (!overlay) return;
    
    currentPhase = 'waiting_block';
    
    blockRange.textContent = `{{ __('Questions') }} ${blockStart}-${blockEnd}`;
    
    overlay.classList.add('active');
    
    if (waitingBlockCountdownInterval) {
        clearInterval(waitingBlockCountdownInterval);
    }
    
    const updateTimer = () => {
        const remaining = Math.ceil((endsAtMs - Date.now()) / 1000);
        if (remaining <= 0) {
            clearInterval(waitingBlockCountdownInterval);
            waitingBlockCountdownInterval = null;
            hideWaitingBlockOverlay();
        } else {
            timer.textContent = remaining;
        }
    };
    
    updateTimer();
    waitingBlockCountdownInterval = setInterval(updateTimer, 100);
}

function hideWaitingBlockOverlay() {
    const overlay = document.getElementById('waitingBlockOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
    if (waitingBlockCountdownInterval) {
        clearInterval(waitingBlockCountdownInterval);
        waitingBlockCountdownInterval = null;
    }
}

function showWaitingOverlay(text = null) {
    const overlay = document.getElementById('waitingOverlay');
    const waitingText = document.getElementById('waitingText');
    if (overlay) {
        overlay.classList.add('active');
        if (text && waitingText) {
            waitingText.textContent = text;
        }
    }
    // Stop the timer when showing waiting overlay
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function hideWaitingOverlay() {
    const overlay = document.getElementById('waitingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

function handleBuzzClick() {
    if (!canBuzz || hasBuzzed) return;
    
    if (useSocketIO) {
        duoSocket.buzz(Date.now());
    }
    
    hasBuzzed = true;
    canBuzz = false;
    document.getElementById('buzzButton').disabled = true;
    document.getElementById('playerBuzzIndicator').classList.add('buzzed');
    playSound('buzzerSound');
    
    if (!useSocketIO) {
        showAnswers();
        startAnswerTimer();
    }
}

function submitAnswerSocket(answerIndex) {
    if (useSocketIO) {
        duoSocket.answer(answerIndex);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initSocketIO().then(connected => {
        if (!connected) {
            loadGameState();
            setInterval(loadGameState, 3000);
        }
    }).catch(() => {
        loadGameState();
        setInterval(loadGameState, 3000);
    });
    
    initSkillClickHandlers();
});

function initSkillClickHandlers() {
    document.querySelectorAll('.skill-circle:not(.empty)').forEach(skillEl => {
        skillEl.addEventListener('click', function() {
            if (this.classList.contains('used') || this.classList.contains('locked') || this.classList.contains('empty')) {
                return;
            }
            if (this.dataset.auto === 'true') {
                return;
            }
            if (!this.classList.contains('usable-now')) {
                showAttackMessage('⏳ {{ __("Pas maintenant") }}', 'info');
                return;
            }
            
            const skillId = this.dataset.skillId;
            if (!skillId) return;
            
            const isAttackSkill = ['reduce_time', 'shuffle_answers', 'invert_answers'].includes(skillId);
            
            if (isAttackSkill) {
                if (useSocketIO && duoSocket) {
                    duoSocket.activateSkill(skillId);
                } else {
                    activateSkillHttp(skillId);
                }
            } else {
                applyOwnSkill(skillId);
            }
            
            markSkillAsUsed(skillId);
        });
    });
}

const skillsData = @json($skills ?? []);

function applyOwnSkill(skillId) {
    const answersGrid = document.getElementById('answersGrid');
    const answersButtons = answersGrid ? answersGrid.querySelectorAll('.answer-option') : [];
    
    switch (skillId) {
        case 'acidify_error':
            if (currentQuestionData && answersButtons.length > 0) {
                const correctIdx = currentQuestionData.correctIndex ?? -1;
                const wrongIndices = [];
                answersButtons.forEach((btn, i) => {
                    if (i !== correctIdx) wrongIndices.push(i);
                });
                if (wrongIndices.length > 0) {
                    const randomWrong = wrongIndices[Math.floor(Math.random() * wrongIndices.length)];
                    const answer = currentQuestionData.answers[randomWrong];
                    const answerTextValue = typeof answer === 'object' ? (answer.text || answer.label || '') : (answer || '');
                    
                    answersButtons[randomWrong].classList.add('acidified');
                    answersButtons[randomWrong].style.opacity = '0.5';
                    answersButtons[randomWrong].style.textDecoration = 'line-through';
                }
                showAttackMessage('🧪 {{ __("Une réponse éliminée !") }}', 'skill');
            }
            break;
            
        case 'eliminate_two':
            if (currentQuestionData && answersButtons.length > 0) {
                const correctIdx = currentQuestionData.correctIndex ?? -1;
                const wrongIndices = [];
                answersButtons.forEach((btn, i) => {
                    if (i !== correctIdx) wrongIndices.push(i);
                });
                const shuffled = wrongIndices.sort(() => Math.random() - 0.5).slice(0, 2);
                shuffled.forEach(idx => {
                    const answer = currentQuestionData.answers[idx];
                    const answerTextValue = typeof answer === 'object' ? (answer.text || answer.label || '') : (answer || '');
                    
                    answersButtons[idx].classList.add('eliminated');
                    answersButtons[idx].style.opacity = '0.3';
                    answersButtons[idx].style.pointerEvents = 'none';
                    answersButtons[idx].innerHTML = '<span style="text-decoration: line-through">' + (answersButtons[idx].textContent || answerTextValue) + '</span>';
                });
                showAttackMessage('❌ {{ __("2 réponses éliminées !") }}', 'skill');
            }
            break;
            
        case 'text_hint':
            if (currentQuestionData) {
                requestHintFromServer(skillId);
            }
            break;
            
        case 'ai_suggestion':
            if (currentQuestionData) {
                requestAISuggestion();
            }
            break;
            
        case 'preview_questions':
            requestFutureQuestions();
            break;
            
        case 'bonus_question':
            if (useSocketIO && duoSocket) {
                duoSocket.activateSkill(skillId);
            }
            showAttackMessage('❓ {{ __("Question bonus ajoutée !") }}', 'skill');
            break;
            
        case 'replay':
            if (useSocketIO && duoSocket) {
                duoSocket.activateSkill(skillId);
            }
            showAttackMessage('🔁 {{ __("Rejouer cette question !") }}', 'skill');
            break;
            
        default:
            showAttackMessage('✨ {{ __("Compétence activée !") }}', 'skill');
    }
}

function requestHintFromServer(skillId) {
    showAttackMessage('📜 {{ __("Chargement de l\'indice...") }}', 'info');
    fetch(`/duo/match/${matchId}/hint`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ 
            skill_id: skillId,
            question: currentQuestionData?.question || ''
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.hint) {
            showHintOverlay(data.hint);
        }
    })
    .catch(() => {
        showAttackMessage('📜 {{ __("Erreur chargement indice") }}', 'attack');
    });
}

function requestAISuggestion() {
    showAttackMessage('🤖 {{ __("L\'IA réfléchit...") }}', 'info');
    fetch(`/duo/match/${matchId}/ai-suggest`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ 
            question: currentQuestionData?.question || '',
            answers: currentQuestionData?.answers || []
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.suggestion !== undefined) {
            highlightAISuggestion(data.suggestion, data.confidence || 80);
        }
    })
    .catch(() => {
        showAttackMessage('🤖 {{ __("Erreur IA") }}', 'attack');
    });
}

function requestFutureQuestions() {
    showAttackMessage('🔮 {{ __("Vision des questions futures...") }}', 'info');
    fetch(`/duo/match/${matchId}/preview-questions`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.questions && data.questions.length > 0) {
            showFutureQuestionsOverlay(data.questions);
        }
    })
    .catch(() => {
        showAttackMessage('🔮 {{ __("Erreur vision") }}', 'attack');
    });
}

function showHintOverlay(hint) {
    const overlay = document.createElement('div');
    overlay.className = 'hint-overlay';
    overlay.innerHTML = `
        <div class="hint-content">
            <div class="hint-icon">📜</div>
            <div class="hint-text">${hint}</div>
            <button class="hint-close" onclick="this.parentElement.parentElement.remove()">OK</button>
        </div>
    `;
    overlay.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.8); display: flex; align-items: center;
        justify-content: center; z-index: 10000;
    `;
    overlay.querySelector('.hint-content').style.cssText = `
        background: linear-gradient(135deg, #2c1810, #4a2c20);
        padding: 30px; border-radius: 15px; max-width: 400px;
        text-align: center; border: 2px solid #c9a227;
    `;
    overlay.querySelector('.hint-icon').style.cssText = 'font-size: 3rem; margin-bottom: 15px;';
    overlay.querySelector('.hint-text').style.cssText = 'font-size: 1.2rem; margin-bottom: 20px; color: #f5e6d3;';
    overlay.querySelector('.hint-close').style.cssText = `
        background: #c9a227; color: #1a0f0a; border: none; padding: 10px 30px;
        border-radius: 8px; cursor: pointer; font-weight: bold;
    `;
    document.body.appendChild(overlay);
    setTimeout(() => overlay.remove(), 8000);
}

function highlightAISuggestion(suggestionIndex, confidence) {
    const answersGrid = document.getElementById('answersGrid');
    const answersButtons = answersGrid ? answersGrid.querySelectorAll('.answer-option') : [];
    
    if (answersButtons[suggestionIndex]) {
        answersButtons[suggestionIndex].classList.add('ai-suggested');
        answersButtons[suggestionIndex].style.boxShadow = '0 0 20px rgba(147, 112, 219, 0.8)';
        answersButtons[suggestionIndex].style.border = '3px solid #9370DB';
        
        const badge = document.createElement('span');
        badge.className = 'ai-badge';
        badge.innerHTML = `🤖 ${confidence}%`;
        badge.style.cssText = `
            position: absolute; top: -10px; right: -10px;
            background: #9370DB; color: white; padding: 3px 8px;
            border-radius: 10px; font-size: 0.8rem; font-weight: bold;
        `;
        answersButtons[suggestionIndex].style.position = 'relative';
        answersButtons[suggestionIndex].appendChild(badge);
        
        showAttackMessage(`🤖 {{ __("Suggestion IA : Réponse") }} ${suggestionIndex + 1} (${confidence}%)`, 'skill');
    }
}

function showFutureQuestionsOverlay(questions) {
    const overlay = document.createElement('div');
    overlay.className = 'future-questions-overlay';
    
    let questionsHtml = questions.slice(0, 5).map((q, i) => `
        <div class="future-q-item">
            <span class="q-num">Q${i + 1}</span>
            <span class="q-theme">${q.theme || q.subtheme || '{{ __("Question") }}'}</span>
        </div>
    `).join('');
    
    overlay.innerHTML = `
        <div class="future-content">
            <div class="future-title">🔮 {{ __("Questions à venir") }}</div>
            ${questionsHtml}
            <button class="future-close" onclick="this.parentElement.parentElement.remove()">OK</button>
        </div>
    `;
    overlay.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.85); display: flex; align-items: center;
        justify-content: center; z-index: 10000;
    `;
    overlay.querySelector('.future-content').style.cssText = `
        background: linear-gradient(135deg, #1a1a2e, #16213e);
        padding: 25px; border-radius: 15px; max-width: 350px;
        text-align: center; border: 2px solid #9370DB;
    `;
    overlay.querySelector('.future-title').style.cssText = 'font-size: 1.5rem; margin-bottom: 20px; color: #9370DB;';
    overlay.querySelectorAll('.future-q-item').forEach(el => {
        el.style.cssText = 'display: flex; justify-content: space-between; padding: 8px; margin: 5px 0; background: rgba(255,255,255,0.1); border-radius: 5px;';
    });
    overlay.querySelector('.future-close').style.cssText = `
        background: #9370DB; color: white; border: none; padding: 10px 30px;
        border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 15px;
    `;
    document.body.appendChild(overlay);
    setTimeout(() => overlay.remove(), 10000);
}

function applyAutoSkills(phase) {
    const playerSkills = skillsData || [];
    
    playerSkills.forEach(skill => {
        if (!skill.auto) return;
        
        const skillEl = document.querySelector(`[data-skill-id="${skill.id}"]`);
        if (skillEl && skillEl.classList.contains('used')) return;
        
        switch (skill.id) {
            case 'illuminate_numbers':
                if (phase === 'question' && currentQuestionData) {
                    applyIlluminateNumbers();
                }
                break;
                
            case 'extra_time':
                if (phase === 'question') {
                    timeLeft += 2;
                    document.getElementById('chronoTimer').textContent = timeLeft;
                    showAttackMessage('⏳ +2s {{ __("automatique") }}', 'skill');
                }
                break;
                
            case 'extra_reflection':
                if (phase === 'question') {
                    timeLeft += 3;
                    document.getElementById('chronoTimer').textContent = timeLeft;
                    showAttackMessage('🧠 +3s {{ __("réflexion") }}', 'skill');
                }
                break;
                
            case 'faster_buzz':
                break;
                
            case 'see_opponent_choice':
                if (phase === 'answer') {
                    enableOpponentChoiceTracking();
                }
                break;
        }
    });
}

let opponentChoiceTrackingEnabled = false;

function enableOpponentChoiceTracking() {
    if (opponentChoiceTrackingEnabled) return;
    opponentChoiceTrackingEnabled = true;
    
    if (useSocketIO && duoSocket) {
        duoSocket.on('opponent_answer_preview', (data) => {
            showOpponentChoiceIndicator(data.answerIndex);
        });
    }
}

function showOpponentChoiceIndicator(answerIndex) {
    const answersButtons = document.querySelectorAll('#answersGrid .answer-option');
    
    document.querySelectorAll('.opponent-choice-indicator').forEach(el => el.remove());
    
    if (answersButtons[answerIndex]) {
        const indicator = document.createElement('div');
        indicator.className = 'opponent-choice-indicator';
        indicator.innerHTML = '👁️';
        indicator.style.cssText = `
            position: absolute; top: -8px; left: -8px;
            background: #e74c3c; color: white; 
            width: 25px; height: 25px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; animation: pulse 1s infinite;
            z-index: 10;
        `;
        answersButtons[answerIndex].style.position = 'relative';
        answersButtons[answerIndex].appendChild(indicator);
        
        showAttackMessage('👁️ {{ __("Adversaire hésite sur cette réponse") }}', 'info');
    }
}

function applyErrorSkills() {
    const playerSkills = skillsData || [];
    let result = { cancelled: false, points: 0, replay: false };
    
    for (const skill of playerSkills) {
        const skillEl = document.querySelector(`[data-skill-id="${skill.id}"]`);
        if (skillEl && skillEl.classList.contains('used')) continue;
        
        switch (skill.id) {
            case 'cancel_error':
                if (skill.auto) {
                    markSkillAsUsed('cancel_error');
                    showAttackMessage('✨ {{ __("Erreur annulée automatiquement !") }}', 'skill');
                    result.cancelled = true;
                    result.points = 0;
                    return result;
                }
                break;
                
            case 'lock_correct':
                if (skill.auto) {
                    markSkillAsUsed('lock_correct');
                    showAttackMessage('🔒 {{ __("2 points sécurisés !") }}', 'skill');
                    result.cancelled = true;
                    result.points = 2;
                    return result;
                }
                break;
                
            case 'replay':
                showReplayOption();
                break;
        }
    }
    
    return result;
}

function showReplayOption() {
    const replayBtn = document.createElement('button');
    replayBtn.className = 'replay-skill-btn';
    replayBtn.innerHTML = '🔁 {{ __("Rejouer cette question ?") }}';
    replayBtn.style.cssText = `
        position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%);
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
        color: white; border: none; padding: 15px 30px;
        border-radius: 25px; font-size: 1.1rem; cursor: pointer;
        z-index: 10001; animation: pulse 1.5s infinite;
        box-shadow: 0 5px 20px rgba(155, 89, 182, 0.5);
    `;
    
    replayBtn.onclick = () => {
        markSkillAsUsed('replay');
        replayBtn.remove();
        
        if (useSocketIO && duoSocket) {
            duoSocket.activateSkill('replay');
        }
        
        showAttackMessage('🔁 {{ __("Question rejouée !") }}', 'skill');
    };
    
    document.body.appendChild(replayBtn);
    
    setTimeout(() => replayBtn.remove(), 5000);
}

function applyIlluminateNumbers() {
    const answersButtons = document.querySelectorAll('#answersGrid .answer-option');
    const correctIdx = currentQuestionData?.correctIndex ?? -1;
    
    if (correctIdx >= 0 && answersButtons[correctIdx]) {
        const answerText = answersButtons[correctIdx].textContent;
        if (/\d/.test(answerText)) {
            answersButtons[correctIdx].classList.add('illuminated');
            answersButtons[correctIdx].style.boxShadow = '0 0 15px rgba(255, 215, 0, 0.6)';
            answersButtons[correctIdx].style.border = '2px solid gold';
            showAttackMessage('💡 {{ __("Réponse numérique illuminée !") }}', 'skill');
        }
    }
}

function markSkillAsUsed(skillId) {
    const skillEl = document.querySelector(`[data-skill-id="${skillId}"]`);
    if (skillEl) {
        skillEl.classList.remove('active', 'usable-now', 'available');
        skillEl.classList.add('used');
    }
}

function updateSkillStates(phase) {
    const triggerToPhaseMap = {
        'on_question': ['question'],
        'on_answer': ['answer'],
        'on_result': ['reveal'],
        'on_error': ['reveal'],
        'on_victory': ['scoreboard', 'match_end'],
        'always': ['question', 'answer', 'reveal', 'scoreboard'],
        'match_start': []
    };
    
    document.querySelectorAll('.skill-circle:not(.used):not(.locked):not(.empty)').forEach(skillEl => {
        const trigger = skillEl.dataset.trigger || 'on_question';
        const isAuto = skillEl.dataset.auto === 'true';
        const validPhases = triggerToPhaseMap[trigger] || [];
        const isUsableNow = validPhases.includes(phase) && !isAuto;
        
        skillEl.classList.remove('active');
        
        if (isUsableNow) {
            skillEl.classList.add('usable-now');
            skillEl.classList.remove('available');
        } else {
            skillEl.classList.remove('usable-now');
            if (!isAuto) {
                skillEl.classList.add('available');
            }
        }
    });
}

function activateSkillHttp(skillId) {
    fetch(`/duo/match/${matchId}/skill`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ skill_id: skillId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            markSkillAsUsed(skillId);
            showAttackMessage('✨ {{ __("Compétence activée !") }}', 'skill');
        }
    })
    .catch(err => console.error('Skill activation error:', err));
}

function activateRevealSkill(index) {
    const skillCircles = document.querySelectorAll('.skill-circle');
    const skillCircle = skillCircles[index];
    
    if (!skillCircle || skillCircle.classList.contains('used') || skillCircle.classList.contains('empty')) {
        return;
    }
    
    const skillId = skillCircle.dataset.skillId;
    if (!skillId) return;
    
    skillCircle.classList.add('used');
    skillCircle.classList.remove('available', 'usable-now');
    
    const revealBtn = document.getElementById(`revealSkillBtn${index}`);
    if (revealBtn) {
        revealBtn.disabled = true;
        revealBtn.textContent = '{{ __("Utilisé") }}';
    }
    const revealItem = document.getElementById(`revealSkill${index}`);
    if (revealItem) {
        revealItem.classList.add('used');
    }
    
    if (useSocketIO && typeof duoSocket !== 'undefined' && duoSocket.isConnected()) {
        duoSocket.emit('activate_skill', { skill_id: skillId });
    } else {
        activateSkillHttp(skillId);
    }
    
    showAttackMessage('✨ {{ __("Compétence activée !") }}', 'skill');
}

function playAttackEffect() {
    const overlay = document.getElementById('attackOverlay');
    const icon = document.getElementById('attackIcon');
    
    if (!overlay) return;
    
    icon.textContent = '⚔️';
    overlay.classList.add('active');
    
    setTimeout(() => {
        overlay.classList.remove('active');
    }, 600);
}

function playBlockEffect() {
    const overlay = document.getElementById('attackOverlay');
    const icon = document.getElementById('attackIcon');
    
    if (!overlay) return;
    
    icon.textContent = '🛡️';
    overlay.classList.add('active', 'blocked');
    
    setTimeout(() => {
        overlay.classList.remove('active', 'blocked');
    }, 800);
}

function showAttackMessage(message, type) {
    const msgDiv = document.createElement('div');
    msgDiv.className = 'attack-message ' + type;
    msgDiv.innerHTML = message;
    
    const bgColors = {
        'blocked': 'rgba(78, 205, 196, 0.9)',
        'skill': 'rgba(155, 89, 182, 0.9)',
        'info': 'rgba(52, 152, 219, 0.9)',
        'attack': 'rgba(255, 107, 107, 0.9)'
    };
    
    msgDiv.style.cssText = `
        position: fixed;
        top: 20%;
        left: 50%;
        transform: translateX(-50%);
        background: ${bgColors[type] || bgColors['attack']};
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

function applyAttackEffect(skillId, params) {
    switch (skillId) {
        case 'reduce_time':
            const reduction = params?.seconds || 3;
            timeLeft = Math.max(1, timeLeft - reduction);
            document.getElementById('chronoTimer').textContent = timeLeft;
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

function startAnswerShuffle(interval) {
    const answersGrid = document.getElementById('answersGrid');
    if (!answersGrid) return;
    
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
    const answersGrid = document.getElementById('answersGrid');
    if (!answersGrid) return;
    
    const answers = answersGrid.querySelectorAll('.answer-option');
    const reversed = Array.from(answers).reverse();
    reversed.forEach((btn, i) => {
        btn.style.order = i;
    });
}

function handleOpponentSkill(data) {
    const skillId = data.skillId;
    const params = data.params || {};
    
    if (data.blocked) {
        playBlockEffect();
        showAttackMessage('🛡️ {{ __("Attaque bloquée !") }}', 'blocked');
    } else {
        playAttackEffect();
        applyAttackEffect(skillId, params);
    }
}
</script>

@endsection
