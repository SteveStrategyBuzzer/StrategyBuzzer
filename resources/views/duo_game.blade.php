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
        background: linear-gradient(135deg, rgba(15, 32, 39, 0.95) 0%, rgba(32, 58, 67, 0.95) 50%, rgba(44, 83, 100, 0.95) 100%);
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
        background: rgba(0, 0, 0, 0.85);
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
                        <div class="skill-circle {{ $skill['used'] ?? false ? 'used' : 'active' }}" 
                             data-skill-id="{{ $skill['id'] ?? '' }}"
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
    <div class="reveal-content">
        <div class="reveal-icon" id="revealIcon">✓</div>
        <div class="reveal-message" id="revealMessage">{{ __('Bonne réponse !') }}</div>
        <div class="reveal-answer" id="revealAnswer"></div>
        <div class="reveal-points" id="revealPoints"></div>
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
    phaseTimers: {
        intro: 3000,
        reveal: 3000,
        scoreboard: 2500
    },
    
    hideAllOverlays() {
        document.getElementById('introOverlay')?.classList.remove('active');
        document.getElementById('revealOverlay')?.classList.remove('active');
        document.getElementById('scoreboardOverlay')?.classList.remove('active');
        document.getElementById('tiebreakerChoiceOverlay')?.classList.remove('active');
        document.getElementById('matchEndOverlay')?.classList.remove('active');
    },
    
    showIntro(questionData) {
        currentPhase = 'intro';
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
        currentPhase = 'question';
        canBuzz = true;
        hasBuzzed = false;
        
        document.getElementById('questionHeader').classList.remove('waiting-for-question');
        document.getElementById('buzzContainer').style.display = 'flex';
        document.getElementById('answersGrid').style.display = 'none';
        document.getElementById('buzzButton').disabled = false;
        
        startBuzzTimer();
    },
    
    showReveal(isCorrect, correctAnswer, points = 0, wasTimeout = false) {
        currentPhase = 'reveal';
        canBuzz = false;
        
        const revealOverlay = document.getElementById('revealOverlay');
        const icon = document.getElementById('revealIcon');
        const message = document.getElementById('revealMessage');
        const answer = document.getElementById('revealAnswer');
        const pointsEl = document.getElementById('revealPoints');
        
        icon?.classList.remove('correct');
        message?.classList.remove('correct', 'incorrect', 'timeout');
        
        if (wasTimeout) {
            if (icon) icon.textContent = '⏱️';
            if (message) {
                message.textContent = '{{ __("Temps écoulé !") }}';
                message.classList.add('timeout');
            }
        } else if (isCorrect) {
            if (icon) {
                icon.textContent = '✓';
                icon.classList.add('correct');
            }
            if (message) {
                message.textContent = '{{ __("Bonne réponse !") }}';
                message.classList.add('correct');
            }
        } else {
            if (icon) icon.textContent = '✗';
            if (message) {
                message.textContent = '{{ __("Mauvaise réponse") }}';
                message.classList.add('incorrect');
            }
        }
        
        if (answer) {
            answer.textContent = correctAnswer || '';
            answer.style.display = correctAnswer ? 'block' : 'none';
        }
        
        if (pointsEl) {
            if (points > 0) {
                pointsEl.textContent = `+${points} {{ __("points") }}`;
                pointsEl.style.display = 'block';
            } else {
                pointsEl.style.display = 'none';
            }
        }
        
        revealOverlay?.classList.add('active');
        
        return new Promise(resolve => {
            setTimeout(() => {
                this.hideAllOverlays();
                resolve();
            }, this.phaseTimers.reveal);
        });
    },
    
    showScoreboard(playerScore, opponentScore, hasNextQuestion, questionNum, totalQuestions) {
        currentPhase = 'scoreboard';
        
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
            hasNextQuestion = false,
            questionNum = 1,
            totalQuestionsParam = totalQuestions
        } = options;
        
        isProcessingPhase = true;
        
        try {
            if (showRevealPhase && currentPhase !== 'reveal') {
                await this.showReveal(isCorrect, correctAnswer, points, wasTimeout);
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
    
    async onAnswerComplete(isCorrect, correctAnswer, points, playerScore, opponentScore, hasNextQuestion, questionNum, totalQuestionsParam, wasTimeout = false) {
        return this.finishPhase({
            showRevealPhase: true,
            isCorrect,
            correctAnswer,
            points,
            wasTimeout,
            playerScore,
            opponentScore,
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
        currentPhase = 'tiebreaker_choice';
        this.hideAllOverlays();
        
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
        currentPhase = 'match_end';
        this.hideAllOverlays();
        
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
    
    PhaseController.onAnswerComplete(
        false,
        currentQuestionData?.correct_answer || '',
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
            window.location.href = `/duo/result/${matchId}`;
        }
    });
}

function handleAnswerTimeout() {
    const buttons = document.querySelectorAll('.answer-option');
    buttons.forEach(btn => btn.classList.add('disabled'));
    
    PhaseController.onAnswerComplete(
        false,
        currentQuestionData?.correct_answer || '',
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
            window.location.href = `/duo/result/${matchId}`;
        }
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
                    correct_answer: state.tiebreaker_question.correct_answer || '',
                    correct_index: state.tiebreaker_question.correct_index ?? 0,
                    has_next_question: false
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
                PhaseController.showReveal(
                    result.is_correct || false,
                    result.correct_answer || state.correct_answer || '',
                    result.points || 0,
                    result.was_timeout || false
                );
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
                    correct_answer: state.current_question.correct_answer || '',
                    correct_index: state.current_question.correct_index ?? 0,
                    has_next_question: state.has_next_question ?? true
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
                    correct_answer: state.current_question.correct_answer || '',
                    correct_index: state.current_question.correct_index ?? 0,
                    has_next_question: state.has_next_question ?? true
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
            correct_answer: state.current_question.correct_answer || '',
            correct_index: state.current_question.correct_index ?? 0,
            has_next_question: state.has_next_question ?? true
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
                const answerText = btn.querySelector('.answer-text');
                if (answerText) {
                    answerText.textContent = questionData.answers[i];
                } else {
                    btn.innerHTML = `<span class="point-badge" id="badge${i}"></span><span class="answer-text">${questionData.answers[i]}</span>`;
                }
                btn.dataset.correct = (i === questionData.correct_index) ? 'true' : 'false';
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
    
    document.getElementById('buzzContainer').style.display = 'none';
    document.getElementById('answersGrid').style.display = 'grid';
    
    startAnswerTimer();
}

document.querySelectorAll('.answer-option').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.classList.contains('disabled')) return;
        
        const answerIndex = parseInt(this.dataset.index);
        const isCorrect = this.dataset.correct === 'true';
        
        if (answerTimerInterval) clearInterval(answerTimerInterval);
        
        document.querySelectorAll('.answer-option').forEach(b => {
            b.classList.add('disabled');
            if (b.dataset.correct === 'true') {
                b.classList.add('correct');
            }
        });
        
        if (!isCorrect) {
            this.classList.add('incorrect');
        }
        
        playSound(isCorrect ? 'correctSound' : 'incorrectSound');
        
        let points = 0;
        if (isCorrect) {
            if (answerTimeLeft > 3) points = 2;
            else if (answerTimeLeft >= 1) points = 1;
            else points = 0;
        } else {
            points = -2;
        }
        
        submitAnswer(answerIndex, isCorrect, points);
    });
});

function submitAnswer(answerIndex, isCorrect, points) {
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
            correct_answer: ['A', 'B', 'C', 'D'][currentQuestionData?.correct_index || 0] || 'A',
            answer_index: answerIndex,
            is_correct: isCorrect,
            points: points
        })
    })
    .then(response => response.json())
    .then(data => {
        const playerScore = data.player_score || data.gameState?.score || parseInt(document.getElementById('playerScore').textContent) + (isCorrect ? points : 0);
        const opponentScore = data.opponent_score || data.gameState?.opponent_score || parseInt(document.getElementById('opponentScore').textContent);
        
        document.getElementById('playerScore').textContent = playerScore;
        document.getElementById('opponentScore').textContent = opponentScore;
        
        const hasNextQuestion = data.hasMoreQuestions ?? data.has_next_question ?? true;
        const questionNum = currentQuestionData?.question_number || 1;
        
        PhaseController.onAnswerComplete(
            isCorrect,
            currentQuestionData?.answers?.[currentQuestionData.correct_index] || '',
            isCorrect ? points : 0,
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
                        window.location.href = `/duo/result/${matchId}`;
                    } else {
                        resetForNextQuestion();
                        loadGameState();
                    }
                } else {
                    window.location.href = `/duo/result/${matchId}`;
                }
            }
        });
    })
    .catch(err => {
        console.error('Answer submission error:', err);
    });
}

function resetGameplayState() {
    hasBuzzed = false;
    canBuzz = false;
    
    if (timerInterval) clearInterval(timerInterval);
    if (answerTimerInterval) clearInterval(answerTimerInterval);
    
    document.getElementById('buzzButton').disabled = true;
    document.getElementById('playerBuzzIndicator').classList.remove('buzzed');
    document.getElementById('opponentBuzzIndicator').classList.remove('buzzed');
    
    document.querySelectorAll('.answer-option').forEach(btn => {
        btn.classList.remove('correct', 'incorrect', 'disabled');
        btn.disabled = false;
    });
    
    document.getElementById('answersGrid').style.display = 'none';
    document.getElementById('buzzContainer').style.display = 'flex';
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
            has_next_question: (data.questionIndex + 1) < (data.totalQuestions || totalQuestions)
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
        
        if (isMyAnswer) {
            document.getElementById('playerScore').textContent = data.totalScore || 0;
            PhaseController.showReveal(isCorrect, data.correctAnswer?.toString() || '', points, wasTimeout);
        } else {
            document.getElementById('opponentScore').textContent = data.totalScore || 0;
            if (currentPhase !== 'reveal') {
                currentPhase = 'reveal';
                PhaseController.showReveal(isCorrect, data.correctAnswer?.toString() || '', points, wasTimeout);
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
    console.log('[DuoGame] Handling server phase:', normalizedPhase);
    
    switch (normalizedPhase) {
        case 'INTRO':
            if (currentPhase !== 'intro' && currentQuestionData) {
                resetGameplayState();
                PhaseController.showIntro(currentQuestionData).then(() => {
                    PhaseController.startQuestion();
                });
            }
            break;
            
        case 'QUESTION_ACTIVE':
        case 'QUESTION':
            if (currentPhase !== 'question' && currentPhase !== 'answer') {
                resetGameplayState();
                PhaseController.hideAllOverlays();
                PhaseController.startQuestion();
            }
            break;
            
        case 'ANSWER_SELECTION':
            break;
            
        case 'REVEAL':
            break;
            
        case 'ROUND_SCOREBOARD':
        case 'SCOREBOARD':
            break;
            
        case 'TIEBREAKER_CHOICE':
            if (currentPhase !== 'tiebreaker_choice') {
                PhaseController.showTiebreakerChoice();
            }
            break;
            
        case 'TIEBREAKER_QUESTION':
            if (currentPhase !== 'question' && currentPhase !== 'answer') {
                resetGameplayState();
                currentPhase = 'waiting';
                PhaseController.hideAllOverlays();
                PhaseController.startQuestion();
            }
            break;
            
        case 'MATCH_END':
            break;
            
        default:
            console.log('[DuoGame] Unknown phase:', normalizedPhase);
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
});
</script>

@endsection
