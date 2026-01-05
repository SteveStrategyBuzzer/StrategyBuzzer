@extends('layouts.app')

@section('content')
@php
// Validation défensive: s'assurer que $params est un tableau
if (!isset($params) || !is_array($params)) {
    $params = [];
}

$mode = $params['mode'] ?? 'solo';
$opponentType = $params['opponent_type'] ?? 'ai';
$opponentInfo = $params['opponent_info'] ?? [];
if (!is_array($opponentInfo)) {
    $opponentInfo = [];
}
$currentQuestion = $params['current'] ?? 1;
$totalQuestions = $params['nb_questions'] ?? 10;
$niveau = $params['niveau'] ?? 1;
$theme = $params['theme'] ?? 'Culture générale';
$themeDisplay = $theme === 'Culture générale' ? __('Général') : __($theme);
$subTheme = $params['sub_theme'] ?? '';
$playerScore = (int)($params['score'] ?? 0);
$opponentScore = (int)($params['opponent_score'] ?? 0);
$currentRound = $params['current_round'] ?? 1;
$playerRoundsWon = $params['player_rounds_won'] ?? 0;
$opponentRoundsWon = $params['opponent_rounds_won'] ?? 0;
$scoring = $params['scoring'] ?? [];
if (!is_array($scoring)) {
    $scoring = [];
}
$avatarName = $params['avatar'] ?? 'Aucun';
$avatarSkillsFull = $params['avatar_skills_full'] ?? ['rarity' => null, 'skills' => []];
if (!is_array($avatarSkillsFull)) {
    $avatarSkillsFull = ['rarity' => null, 'skills' => []];
}

$leagueTeamMode = $params['league_team_mode'] ?? 'classique';
$skillsFreeForAll = $params['skills_free_for_all'] ?? true;
$canUseSkills = $params['can_use_skills'] ?? true;
$isActivePlayer = $params['is_active_player'] ?? true;
$duelInfo = $params['duel_info'] ?? null;
$relayOrder = $params['relay_order'] ?? [];
$currentRelayIndex = $params['current_relay_index'] ?? 0;

$usedSkills = session('used_skills', []);
$skills = [];
if (!empty($avatarSkillsFull['skills']) && is_array($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skillData) {
        if (!is_array($skillData)) continue;
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

// Get player avatar from authenticated user's profile_settings (not session)
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
            // Prefer url over id, as url is the full path
            $selectedAvatar = $avatarData['url'] ?? $avatarData['id'] ?? 'default';
        } elseif (is_string($avatarData)) {
            $selectedAvatar = $avatarData;
        }
    }
}

// Normalize avatar path: handle full paths, category/slug format, and simple names
// Use strpos for PHP 7.x compatibility
if (strpos($selectedAvatar, 'http://') === 0 || strpos($selectedAvatar, 'https://') === 0 || strpos($selectedAvatar, '//') === 0) {
    // Full URL or protocol-relative URL - use directly
    $playerAvatarPath = $selectedAvatar;
} elseif (strpos($selectedAvatar, 'images/') === 0) {
    // Already a proper relative path (e.g., images/avatars/portraits/1.png)
    // Ensure it has .png extension (avoid double extension)
    if (substr($selectedAvatar, -4) !== '.png') {
        $selectedAvatar .= '.png';
    }
    $playerAvatarPath = asset($selectedAvatar);
} elseif (strpos($selectedAvatar, '/') !== false && substr($selectedAvatar, -4) !== '.png') {
    // Category/slug format like "animal/lynx" without extension - needs images/avatars/ prefix and .png suffix
    $playerAvatarPath = asset("images/avatars/{$selectedAvatar}.png");
} elseif (strpos($selectedAvatar, '/') !== false) {
    // Already has a slash and extension - use as relative path
    $playerAvatarPath = asset($selectedAvatar);
} elseif (substr($selectedAvatar, -4) === '.png') {
    // Simple name with .png extension (like "1.png") - strip extension and add folder
    $baseName = preg_replace('/\.png$/', '', $selectedAvatar);
    $playerAvatarPath = asset("images/avatars/standard/{$baseName}.png");
} else {
    // Simple name like "default" - use standard folder
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
    // Pour les adversaires humains, vérifier si l'avatar est déjà un chemin complet ou une URL
    // Use strpos for PHP 7.x compatibility (str_starts_with is PHP 8+)
    $rawOpponentAvatar = $opponentInfo['avatar'] ?? 'default';
    if (strpos($rawOpponentAvatar, 'http://') === 0 || strpos($rawOpponentAvatar, 'https://') === 0 || strpos($rawOpponentAvatar, '//') === 0) {
        // C'est une URL complète ou protocol-relative, l'utiliser directement
        $opponentAvatar = $rawOpponentAvatar;
    } elseif (strpos($rawOpponentAvatar, 'images/') === 0) {
        // L'avatar est déjà un chemin relatif complet (ex: images/avatars/portraits/2.png)
        $opponentAvatar = asset($rawOpponentAvatar);
    } elseif (strpos($rawOpponentAvatar, '/') !== false && strpos($rawOpponentAvatar, '.png') === false) {
        // Category/slug format like "animal/lynx" - needs images/avatars/ prefix and .png suffix
        $opponentAvatar = asset("images/avatars/{$rawOpponentAvatar}.png");
    } elseif (strpos($rawOpponentAvatar, '/') !== false) {
        // Already has a slash and extension - use as relative path
        $opponentAvatar = asset($rawOpponentAvatar);
    } else {
        // L'avatar est juste un nom (ex: "default")
        $opponentAvatar = asset("images/avatars/standard/{$rawOpponentAvatar}.png");
    }
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
    
    /* Hide question header initially for multiplayer modes until question is received */
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
    
    /* Skill utilisable maintenant - scintillement doré */
    .skill-circle.usable-now:not(.used):not(.locked) {
        animation: skill-shimmer 1.5s ease-in-out infinite;
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.25);
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
    
    /* Skill disponible mais pas pour cette phase - doré sans scintillement */
    .skill-circle.available:not(.used):not(.locked):not(.usable-now) {
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.15);
        box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
    }
    
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
        0% { transform: scale(0.5); opacity: 0; }
        50% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(1); opacity: 0; }
    }
    
    /* Attack shake effect on game container */
    .attack-shake {
        animation: shake-effect 0.5s ease-out;
    }
    
    @keyframes shake-effect {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    /* Block shield effect */
    .block-shield {
        animation: shield-pulse 0.6s ease-out;
    }
    
    @keyframes shield-pulse {
        0% { box-shadow: 0 0 0 0 rgba(78, 205, 196, 0.7); }
        50% { box-shadow: 0 0 30px 15px rgba(78, 205, 196, 0.4); }
        100% { box-shadow: 0 0 0 0 rgba(78, 205, 196, 0); }
    }
    
    /* Fake score styling */
    .fake-score {
        color: #ff6b6b !important;
        animation: fake-score-pulse 1.5s infinite;
    }
    
    @keyframes fake-score-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    /* Inverted answers grid */
    .inverted-answers {
        display: flex !important;
        flex-direction: column;
    }
    
    /* Shuffle animation */
    .shuffle-animation {
        animation: shuffle-wiggle 0.3s ease-out;
    }
    
    @keyframes shuffle-wiggle {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px) rotate(-2deg); }
        75% { transform: translateX(10px) rotate(2deg); }
    }
    
    .shuffling-answers .answer-option {
        transition: order 0.2s ease-out;
    }
    
    /* Time reduced effect */
    .time-reduced {
        color: #ff4444 !important;
        animation: time-flash 0.5s ease-out;
    }
    
    @keyframes time-flash {
        0%, 50% { background: rgba(255, 68, 68, 0.3); }
        100% { background: transparent; }
    }
    
    /* Opponent selected answer indicator */
    .opponent-selected {
        position: relative;
    }
    
    .opponent-selected::after {
        content: '👁️';
        position: absolute;
        top: -10px;
        right: -10px;
        font-size: 1.2rem;
        animation: eye-bounce 0.5s ease-out;
    }
    
    @keyframes eye-bounce {
        0% { transform: scale(0); }
        50% { transform: scale(1.3); }
        100% { transform: scale(1); }
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
    
    .answer-option {
        position: relative;
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
    
    .answer-timer-container {
        display: flex;
        justify-content: center;
        margin: 15px 0;
    }
    
    .answer-timer-circle {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        border: 4px solid #4ECDC4;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: border-color 0.3s, transform 0.2s;
    }
    
    .answer-timer-circle.warning {
        border-color: #F39C12;
    }
    
    .answer-timer-circle.danger {
        border-color: #E74C3C;
        animation: timer-pulse 0.5s infinite;
    }
    
    @keyframes timer-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }
    
    .answer-timer-value {
        font-size: 2rem;
        font-weight: bold;
        color: #fff;
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
    
    .buzz-button img {
        width: 180px;
        height: 180px;
        filter: drop-shadow(0 10px 30px rgba(78, 205, 196, 0.6));
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
        display: none; /* Caché pour uniformité avec Solo - sync invisible en arrière-plan */
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
        .skill-bar { gap: 5px; }
        .skill-icon { font-size: 1.2rem; }
    }
    
    @media (max-height: 700px) and (orientation: portrait) {
        .buzz-button img { width: 110px; height: 110px; }
        .game-container { padding-bottom: 120px; }
        .buzz-container-bottom { bottom: 8px; }
        .chrono-circle { width: 80px; height: 80px; }
        .chrono-time { font-size: 1.8rem; }
    }
    
    /* Phase Overlay Styles */
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
    
    /* Intro Phase */
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
    
    /* Reveal Phase */
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
    
    /* Enhanced Reveal Overlay Styles (Unified Design) */
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
    
    .reveal-stat-row.correct {
        background: rgba(46, 204, 113, 0.15);
    }
    
    .reveal-stat-row.wrong {
        background: rgba(231, 76, 60, 0.15);
    }
    
    .reveal-stat-row.no-answer {
        background: rgba(241, 196, 15, 0.15);
    }
    
    .reveal-stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.7);
    }
    
    .reveal-stat-value {
        font-size: 0.85rem;
        font-weight: 700;
        color: #4ECDC4;
    }
    
    .answer-icon {
        font-size: 1.2rem;
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    
    .answer-bubble:hover .answer-icon {
        opacity: 1;
    }
    
    /* Answer Phase Overlay Styles */
    .answer-overlay {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .answer-overlay.active { display: flex !important; }
    .answer-container {
        width: 100%;
        max-width: 600px;
        padding: 20px;
    }
    .answer-header { margin-bottom: 20px; }
    .answer-info { display: flex; }
    .answer-timer {
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        padding: 10px 15px;
        margin-bottom: 20px;
    }
    .answer-overlay .timer-label {
        display: flex;
        justify-content: space-between;
        color: white;
        margin-bottom: 8px;
    }
    .answer-overlay .timer-bar-container {
        background: rgba(255,255,255,0.2);
        border-radius: 5px;
        height: 8px;
        overflow: hidden;
    }
    .answer-overlay .timer-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4, #44a08d);
        border-radius: 5px;
        transition: width 0.3s linear;
    }
    .answer-overlay .timer-bar.warning {
        background: linear-gradient(90deg, #e74c3c, #c0392b);
    }
    #answerGridOverlay {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 20px;
    }
    .answer-bubble {
        background: rgba(255,255,255,0.1);
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .answer-bubble:hover {
        background: rgba(78, 205, 196, 0.3);
        border-color: #4ECDC4;
        transform: scale(1.02);
    }
    .answer-number {
        width: 30px;
        height: 30px;
        background: #4ECDC4;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #1a1a2e;
        margin-bottom: 8px;
    }
    .answer-bubble .answer-text {
        color: white;
        text-align: center;
        font-size: 0.95rem;
    }
    .buzz-info {
        text-align: center;
        padding: 15px;
        background: rgba(78, 205, 196, 0.2);
        border-radius: 10px;
    }
    .buzz-info.not-buzzed {
        background: rgba(255, 215, 0, 0.2);
    }
    .buzz-info-text {
        color: white;
        font-size: 1.1rem;
    }
    
    /* Scoreboard Phase */
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
        border: 3px solid #FF6B6B;
        box-shadow: 0 0 20px rgba(255, 107, 107, 0.5);
    }
    
    .scoreboard-name {
        font-size: 1rem;
        font-weight: 600;
    }
    
    .scoreboard-name.player { color: #4ECDC4; }
    .scoreboard-name.opponent { color: #FF6B6B; }
    
    .scoreboard-score {
        font-size: 3rem;
        font-weight: 900;
    }
    
    .scoreboard-score.player {
        color: #4ECDC4;
        text-shadow: 0 0 30px rgba(78, 205, 196, 0.8);
    }
    
    .scoreboard-score.opponent {
        color: #FF6B6B;
        text-shadow: 0 0 30px rgba(255, 107, 107, 0.8);
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
    
    @media (max-width: 480px) {
        .intro-question-number { font-size: 2rem; }
        .intro-theme { font-size: 1.1rem; }
        .reveal-icon { font-size: 3.5rem; }
        .reveal-message { font-size: 1.5rem; }
        .scoreboard-players { gap: 20px; }
        .scoreboard-avatar { width: 60px; height: 60px; }
        .scoreboard-score { font-size: 2.5rem; }
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
    
    <div class="question-header{{ $isFirebaseMode ? ' waiting-for-question' : '' }}" id="questionHeader">
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
            {{ $themeDisplay }} @if($subTheme)- {{ $subTheme }}@endif | {{ __('Question') }} {{ $currentQuestion }}/{{ $totalQuestions }}
        </div>
        
        <div class="question-text" id="questionText">
            @if($isFirebaseMode)
                {{ __('Chargement...') }}
            @else
                {{ $params['question_text'] ?? __('Chargement de la question...') }}
            @endif
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
                            $skillLocked = ($mode === 'league_team' && $leagueTeamMode === 'relais' && !$isActivePlayer);
                            $isDisabled = $isUsed || $isAuto || $skillLocked;
                        @endphp
                        <div class="skill-circle {{ $isUsed ? 'used' : 'active' }} {{ $isAuto ? 'auto' : 'clickable' }} {{ $skillLocked ? 'locked' : '' }}" 
                             data-skill-id="{{ $skill['id'] }}"
                             data-skill-type="{{ $skill['type'] ?? 'personal' }}"
                             data-skill-trigger="{{ $skill['trigger'] }}"
                             data-affects-opponent="{{ ($skill['affects_opponent'] ?? false) ? 'true' : 'false' }}"
                             data-locked="{{ $skillLocked ? 'true' : 'false' }}"
                             title="{{ $skillLocked ? __('Ce n\'est pas votre tour') : $skill['name'] . ': ' . $skill['description'] }}">
                            {{ $skillLocked ? '🔒' : $skill['icon'] }}
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
    
    <div class="buzz-container-bottom" id="buzzContainer">
        <button id="buzzButton" class="buzz-button">
            <img src="{{ asset('images/buzzer.png') }}" alt="Strategy Buzzer">
        </button>
    </div>
</div>

<div class="waiting-overlay" id="waitingOverlay">
    <div class="spinner"></div>
    <div class="waiting-text" id="waitingText">{{ __('En attente de l\'adversaire...') }}</div>
</div>

<!-- Phase Overlays -->
<div class="phase-overlay intro-overlay" id="introOverlay">
    <div class="intro-content">
        <div class="intro-question-number" id="introQuestionNumber">{{ __('Question') }} 1/10</div>
        <div class="intro-theme" id="introTheme">{{ $params['theme'] ?? __('Culture générale') }}</div>
        <div class="intro-subtheme" id="introSubtheme">{{ $params['sub_theme'] ?? '' }}</div>
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
    </div>
</div>

<div class="phase-overlay scoreboard-overlay" id="scoreboardOverlay">
    <div class="scoreboard-content">
        <div class="scoreboard-title">{{ __('Scores') }}</div>
        <div class="scoreboard-players">
            <div class="scoreboard-player">
                <img src="{{ auth()->user()->avatar ?? asset('images/default-avatar.png') }}" alt="{{ __('Joueur') }}" class="scoreboard-avatar player">
                <div class="scoreboard-name player" id="scoreboardPlayerName">{{ auth()->user()->name ?? __('Vous') }}</div>
                <div class="scoreboard-score player" id="scoreboardPlayerScore">0</div>
            </div>
            <div class="scoreboard-vs">VS</div>
            <div class="scoreboard-player">
                <img src="{{ $params['opponent_info']['avatar'] ?? asset('images/default-avatar.png') }}" alt="{{ __('Adversaire') }}" class="scoreboard-avatar opponent">
                <div class="scoreboard-name opponent" id="scoreboardOpponentName">{{ $params['opponent_info']['name'] ?? __('Adversaire') }}</div>
                <div class="scoreboard-score opponent" id="scoreboardOpponentScore">0</div>
            </div>
        </div>
        <div class="scoreboard-progress" id="scoreboardProgress">{{ __('Question suivante...') }}</div>
    </div>
</div>

<!-- Answer Phase Overlay (like Solo mode) -->
<div class="phase-overlay answer-overlay" id="answerOverlay">
    <div class="answer-container">
        <div class="answer-header">
            <div class="answer-info" style="text-align: center; width: 100%; flex-direction: row; align-items: center; justify-content: space-between; gap: 15px;">
                <div class="answer-question-num" id="answerQuestionNum" style="font-size: 1.7rem; font-weight: 700; flex: 1; text-align: left;">Question #1</div>
                <div class="answer-points-display" id="answerPointsDisplay" style="font-size: 2.5rem; font-weight: 900; color: #4ECDC4; text-shadow: 0 0 20px rgba(78, 205, 196, 0.5);">+2</div>
                <div class="answer-score-display" id="answerScoreDisplay" style="font-size: 1.7rem; font-weight: 700; flex: 1; text-align: right;">Score 0/0</div>
            </div>
        </div>
        <div class="answer-timer">
            <div class="timer-label">
                <span>⏱️ {{ __('Temps pour répondre') }}</span>
                <span id="answerTimerText">10s</span>
            </div>
            <div class="timer-bar-container">
                <div class="timer-bar" id="answerTimerBar"></div>
            </div>
        </div>
        <div class="answers-grid" id="answerGridOverlay">
            <div class="answer-bubble" data-index="0" id="answerBubble0">
                <div class="answer-number">1</div>
                <div class="answer-text" id="answerBubbleText0"></div>
                <div class="answer-icon">👉</div>
            </div>
            <div class="answer-bubble" data-index="1" id="answerBubble1">
                <div class="answer-number">2</div>
                <div class="answer-text" id="answerBubbleText1"></div>
                <div class="answer-icon">👉</div>
            </div>
            <div class="answer-bubble" data-index="2" id="answerBubble2">
                <div class="answer-number">3</div>
                <div class="answer-text" id="answerBubbleText2"></div>
                <div class="answer-icon">👉</div>
            </div>
            <div class="answer-bubble" data-index="3" id="answerBubble3">
                <div class="answer-number">4</div>
                <div class="answer-text" id="answerBubbleText3"></div>
                <div class="answer-icon">👉</div>
            </div>
        </div>
        <div class="buzz-info" id="buzzInfoMessage">
            <div class="buzz-info-text" id="buzzInfoText">{{ __('Vous avez buzzé !') }} 💚</div>
        </div>
    </div>
</div>

@if($isFirebaseMode)
    <div class="firebase-status disconnected" id="firebaseStatus">
        {{ __('Connexion...') }}
    </div>
@endif

<div class="attack-overlay" id="attackOverlay">
    <div class="attack-icon" id="attackIcon">⚔️</div>
</div>

@if($mode === 'league_team')
<div class="league-team-mode-banner" id="leagueTeamBanner">
    @if($leagueTeamMode === 'classique')
        <div class="mode-indicator classique">
            <span class="mode-icon">🏆</span>
            <span class="mode-text">{{ __('Mode Classique - Skills libres pour tous') }}</span>
        </div>
    @elseif($leagueTeamMode === 'bataille')
        <div class="mode-indicator bataille">
            <span class="mode-icon">⚔️</span>
            @if($duelInfo)
                <span class="mode-text">{{ __('Duel #') }}{{ $duelInfo['rank'] ?? 1 }} : {{ __('Vous vs') }} {{ $duelInfo['opponent_name'] ?? __('Adversaire') }}</span>
            @else
                <span class="mode-text">{{ __('Mode Bataille de Niveaux') }}</span>
            @endif
        </div>
    @elseif($leagueTeamMode === 'relais')
        <div class="mode-indicator relais {{ $isActivePlayer ? 'your-turn' : 'waiting' }}">
            <span class="mode-icon">{{ $isActivePlayer ? '🎯' : '⏳' }}</span>
            @if($isActivePlayer)
                <span class="mode-text">{{ __('C\'est votre tour !') }}</span>
            @else
                <span class="mode-text">{{ __('En attente de votre tour...') }} ({{ $currentRelayIndex + 1 }}/{{ count($relayOrder) }})</span>
            @endif
        </div>
    @endif
</div>

<style>
.league-team-mode-banner {
    position: fixed;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1000;
    padding: 8px 20px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.mode-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mode-indicator.classique {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.8), rgba(118, 75, 162, 0.8));
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.mode-indicator.bataille {
    background: linear-gradient(135deg, rgba(244, 67, 54, 0.8), rgba(211, 47, 47, 0.8));
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.mode-indicator.relais {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.8), rgba(56, 142, 60, 0.8));
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.mode-indicator.relais.your-turn {
    animation: turn-pulse 1.5s ease-in-out infinite;
}

.mode-indicator.relais.waiting {
    opacity: 0.7;
}

@keyframes turn-pulse {
    0%, 100% { box-shadow: 0 0 10px rgba(76, 175, 80, 0.5); }
    50% { box-shadow: 0 0 25px rgba(76, 175, 80, 0.9); }
}

.mode-icon {
    font-size: 1.1rem;
}
</style>
@endif

@if($mode === 'duo')
<!-- Contrôles communication Duo - CACHÉS pendant le gameplay (question/réponse)
     Les boutons sont uniquement visibles sur la page de transition (game_result)
     Le VoiceChat reste actif en arrière-plan pour permettre la communication -->
<div id="duoCommFloating" class="duo-comm-floating" style="display: none !important;">
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

const sessionId = '{{ $params['session_id'] ?? $params['lobby_code'] ?? $matchId ?? '' }}';
const currentPlayerId = {{ auth()->id() }};
const opponentId = {{ $params['opponent_info']['user_id'] ?? 0 }};
const gameMode = '{{ $mode ?? 'duo' }}';

function normalizeMatchIdForWebRTC(matchId) {
    if (typeof matchId === 'number' && matchId > 0) {
        return matchId;
    }
    const matchIdStr = String(matchId);
    const numericId = parseInt(matchIdStr.replace(/[^0-9]/g, ''), 10) || 0;
    if (numericId === 0) {
        let crc = 0xFFFFFFFF;
        for (let i = 0; i < matchIdStr.length; i++) {
            crc ^= matchIdStr.charCodeAt(i);
            for (let j = 0; j < 8; j++) {
                crc = (crc >>> 1) ^ (crc & 1 ? 0xEDB88320 : 0);
            }
        }
        return ((crc ^ 0xFFFFFFFF) >>> 0) & 0x7FFFFFFF;
    }
    return numericId;
}

const normalizedSessionId = normalizeMatchIdForWebRTC(sessionId);
const firestoreGameId = `${gameMode}-match-${normalizedSessionId}`;
console.log('[WebRTC] Using Firestore path: games/' + firestoreGameId);

let voiceChat = null;
let firebaseAuthReady = false;

function initVoiceChat() {
    if (!firebaseAuthReady || voiceChat) return;
    
    voiceChat = new GameVoiceChat();
    window.duoVoiceChat = voiceChat;
    
    if (sessionId && opponentId) {
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
        return `games/${firestoreGameId}/webrtc`;
    }
    
    getPresencePath() {
        return `games/${firestoreGameId}/voice_presence`;
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
<audio id="timerTickSound" preload="auto">
    <source src="{{ asset('sounds/tic_tac.mp3') }}" type="audio/mpeg">
</audio>

<script src="{{ asset('js/GameplayEngine.js') }}"></script>
@if(!$isFirebaseMode)
<script src="{{ asset('js/LocalProvider.js') }}"></script>
@else
<script src="{{ asset('js/FirestoreProvider.js') }}"></script>
@endif

<script>
const gameConfig = {
    mode: '{{ $mode }}',
    opponentType: '{{ $opponentType }}',
    isFirebaseMode: {{ $isFirebaseMode ? 'true' : 'false' }},
    matchId: '{{ $matchId ?? '' }}',
    roomCode: '{{ $roomCode ?? '' }}',
    sessionId: '{{ $params['session_id'] ?? $matchId ?? $roomCode ?? '' }}',
    isHost: {{ ($params['is_host'] ?? false) ? 'true' : 'false' }},
    playerId: '{{ auth()->id() }}',
    opponentId: '{{ $params['opponent_info']['user_id'] ?? $params['opponent_id'] ?? '' }}',
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
        fetchQuestion: '/game/{{ $mode }}/fetch-question',
    },
    initialQuestion: {
        question_number: {{ $currentQuestion }},
        total_questions: {{ $totalQuestions }},
        question_text: {!! json_encode($params['question_text'] ?? '') !!},
        theme: {!! json_encode($params['theme'] ?? 'Culture générale') !!},
        sub_theme: {!! json_encode($params['sub_theme'] ?? '') !!},
        chrono_time: {{ $params['chrono_time'] ?? 8 }},
        correct_index: {{ collect($params['answers'] ?? [])->search(function($a) { return is_array($a) && ($a['is_correct'] ?? false); }) ?: 0 }},
        answers: {!! json_encode(collect($params['answers'] ?? [])->map(function($a, $i) {
            return ['text' => is_array($a) ? ($a['text'] ?? $a) : $a, 'is_correct' => is_array($a) ? ($a['is_correct'] ?? false) : false];
        })->values()) !!}
    }
};

let timeLeft = 8;
let timerInterval;
let buzzed = false;
let answersShown = false;
let playerBuzzTime = null;

const buzzerSound = document.getElementById('buzzerSound');
const waitingOverlay = document.getElementById('waitingOverlay');
const answersGrid = document.getElementById('answersGrid');

const selectedBuzzer = localStorage.getItem('selectedBuzzer') || 'buzzer_default_1';
document.getElementById('buzzerSource').src = `/sounds/${selectedBuzzer}.mp3`;
buzzerSound.load();

// PhaseController - Manages game phase transitions
const PhaseController = {
    currentPhase: 'intro',
    phases: ['intro', 'question', 'buzz', 'answer', 'reveal', 'scoreboard'],
    phaseTimers: {
        intro: 9000,
        answer: 10000,
        reveal: 15000,
        scoreboard: 2500
    },
    isMultiplayer: gameConfig.isFirebaseMode,
    isHost: gameConfig.isHost,
    
    currentQuestionData: null,
    lastAnswerResult: null,
    
    init() {
        console.log('[PhaseController] Initializing, multiplayer:', this.isMultiplayer);
        this.currentPhase = 'intro';
        window.PhaseController = this;
    },
    
    setPhase(phase, phaseData = {}) {
        if (!this.phases.includes(phase)) {
            console.warn('[PhaseController] Invalid phase:', phase);
            return;
        }
        
        const previousPhase = this.currentPhase;
        this.currentPhase = phase;
        console.log('[PhaseController] Phase transition:', previousPhase, '->', phase);
        
        // Always hide overlays first when transitioning
        this.hideAllOverlays();
        
        // Publish phase to Firebase for multiplayer sync (host only)
        if (this.isMultiplayer && this.isHost && typeof window.handleFirebasePhase === 'function') {
            // Pass phase data for guests to properly render overlays
            window.handleFirebasePhase(phase, phaseData).catch(err => {
                console.error('[PhaseController] Firebase phase publish error:', err);
            });
        }
        
        // Ensure overlays are hidden locally after a short delay (safety measure)
        // This prevents overlays from being stuck if something interrupts the normal flow
        if (previousPhase !== phase && ['question', 'buzz'].includes(phase)) {
            setTimeout(() => this.hideAllOverlays(), 100);
        }
        
        return phase;
    },
    
    hideAllOverlays() {
        document.getElementById('introOverlay')?.classList.remove('active');
        document.getElementById('answerOverlay')?.classList.remove('active');
        document.getElementById('revealOverlay')?.classList.remove('active');
        document.getElementById('scoreboardOverlay')?.classList.remove('active');
        
        // Stop any running answer timer
        if (window.answerTimerInterval) {
            clearInterval(window.answerTimerInterval);
            window.answerTimerInterval = null;
        }
    },
    
    showIntro(questionData) {
        this.setPhase('intro', { questionData });
        this.currentQuestionData = questionData;
        
        const introOverlay = document.getElementById('introOverlay');
        const questionNum = document.getElementById('introQuestionNumber');
        const theme = document.getElementById('introTheme');
        const subtheme = document.getElementById('introSubtheme');
        
        if (questionNum) {
            questionNum.textContent = `{{ __('Question') }} ${questionData.question_number}/${questionData.total_questions}`;
        }
        if (theme) {
            theme.textContent = questionData.theme || '{{ __("Culture générale") }}';
        }
        if (subtheme) {
            subtheme.textContent = questionData.sub_theme || '';
            subtheme.style.display = questionData.sub_theme ? 'block' : 'none';
        }
        
        introOverlay?.classList.add('active');
        
        return new Promise(resolve => {
            // For Q1 in multiplayer: use synchronized introEndTimestamp from Firebase
            // This ensures all players see the intro end at the exact same moment
            let introDuration = this.phaseTimers.intro;
            
            if (this.isMultiplayer && questionData.question_number === 1 && window.introEndTimestamp) {
                const now = Date.now();
                const remaining = window.introEndTimestamp - now;
                
                console.log('[PhaseController] Synchronized intro timing:', {
                    introEndTimestamp: window.introEndTimestamp,
                    now: now,
                    remainingMs: remaining
                });
                
                if (remaining <= 0) {
                    // Intro already finished (late load), skip immediately
                    console.log('[PhaseController] Intro already finished, skipping');
                    this.hideAllOverlays();
                    resolve();
                    return;
                }
                
                introDuration = remaining;
            }
            
            // For Q2+ or solo mode: use standard 3-second intro
            if (questionData.question_number > 1) {
                introDuration = 3000;
            }
            
            console.log('[PhaseController] Intro duration:', introDuration, 'ms for Q', questionData.question_number);
            
            setTimeout(() => {
                this.hideAllOverlays();
                resolve();
            }, introDuration);
        });
    },
    
    startQuestion() {
        this.setPhase('question');
        
        const buzzContainer = document.getElementById('buzzContainer');
        const answersGrid = document.getElementById('answersGrid');
        
        if (buzzContainer) buzzContainer.style.display = 'flex';
        if (answersGrid) answersGrid.style.display = 'none';
        
        if (typeof GameplayEngine !== 'undefined') {
            GameplayEngine.startTimer();
        }
    },
    
    onBuzz() {
        // Transition directly to answer phase with the new overlay (like Solo mode)
        this.showAnswerPhase({ playerBuzzed: true, potentialPoints: 2 });
    },
    
    showAnswerPhase(options = {}) {
        const { playerBuzzed = buzzed, potentialPoints = 2, answers: optionAnswers = null, questionNumber = null } = options;
        console.log('[PhaseController] showAnswerPhase called', { playerBuzzed, potentialPoints });
        
        // Stop buzz timer
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        // Get current question data (prefer currentQuestionData, fallback to GameFlowController, then gameConfig)
        const currentQ = this.currentQuestionData || 
                         (typeof GameFlowController !== 'undefined' ? GameFlowController.currentQuestionData : null) || 
                         gameConfig.initialQuestion || {};
        
        // Pass answer phase data with question info for Firebase guests
        this.setPhase('answer', { 
            playerBuzzed, 
            potentialPoints,
            answers: currentQ.answers || [],
            questionNumber: currentQ.question_number || gameConfig.currentQuestion
        });
        
        const buzzContainer = document.getElementById('buzzContainer');
        const answerGrid = document.getElementById('answersGrid');
        if (buzzContainer) buzzContainer.style.display = 'none';
        if (answerGrid) answerGrid.style.display = 'none';
        
        const answerOverlay = document.getElementById('answerOverlay');
        const answerTimerBar = document.getElementById('answerTimerBar');
        const answerTimerText = document.getElementById('answerTimerText');
        
        const qNum = questionNumber || currentQ.question_number || gameConfig.currentQuestion || 1;
        const pScore = parseInt(document.getElementById('playerScore')?.textContent || '0');
        const oScore = parseInt(document.getElementById('opponentScore')?.textContent || '0');
        
        document.getElementById('answerQuestionNum').textContent = `Question #${qNum}`;
        document.getElementById('answerScoreDisplay').textContent = `Score ${pScore}/${oScore}`;
        
        const pointsDisplay = document.getElementById('answerPointsDisplay');
        const displayPoints = playerBuzzed ? potentialPoints : 0;
        pointsDisplay.textContent = `+${displayPoints}`;
        pointsDisplay.style.color = displayPoints === 0 ? '#FFD700' : '#4ECDC4';
        
        const buzzInfoMessage = document.getElementById('buzzInfoMessage');
        const buzzInfoText = document.getElementById('buzzInfoText');
        if (!playerBuzzed) {
            buzzInfoMessage?.classList.add('not-buzzed');
            if (buzzInfoText) buzzInfoText.innerHTML = "⚠️ {{ __('Pas buzzé - Vous pouvez répondre (0 point)') }}";
        } else {
            buzzInfoMessage?.classList.remove('not-buzzed');
            if (buzzInfoText) buzzInfoText.innerHTML = "{{ __('Vous avez buzzé !') }} 💚";
        }
        
        // Use answers from options (for Firebase guests), or from current question data
        const answers = optionAnswers || currentQ.answers || [];
        answers.forEach((answer, i) => {
            const textEl = document.getElementById(`answerBubbleText${i}`);
            if (textEl) {
                const answerText = typeof answer === 'object' ? (answer?.text || '') : (answer || '');
                textEl.textContent = answerText;
            }
        });
        
        if (answerTimerBar) answerTimerBar.style.width = '100%';
        if (answerTimerText) answerTimerText.textContent = '10s';
        
        answerOverlay?.classList.add('active');
        
        let answerTimeLeft = 10;
        if (window.answerTimerInterval) clearInterval(window.answerTimerInterval);
        
        window.answerTimerInterval = setInterval(() => {
            answerTimeLeft--;
            if (answerTimerBar) answerTimerBar.style.width = (answerTimeLeft / 10 * 100) + '%';
            if (answerTimerText) answerTimerText.textContent = answerTimeLeft + 's';
            
            if (answerTimeLeft <= 3 && answerTimerBar) answerTimerBar.classList.add('warning');
            
            if (answerTimeLeft <= 0) {
                clearInterval(window.answerTimerInterval);
                window.answerTimerInterval = null;
                if (typeof submitAnswer === 'function') {
                    submitAnswer(-1, true);
                }
            }
        }, 1000);
        
        document.querySelectorAll('#answerGridOverlay .answer-bubble').forEach(bubble => {
            bubble.onclick = function() {
                const index = parseInt(this.getAttribute('data-index'));
                if (typeof submitAnswer === 'function') {
                    submitAnswer(index, false);
                }
            };
        });
    },
    
    showReveal(isCorrect, correctAnswer, points = 0, wasTimeout = false, options = {}) {
        const {
            playerScore = 0,
            opponentScore = 0,
            userAnswer = null,
            questionNum = 1,
            totalQuestions = 10
        } = options;
        
        this.setPhase('reveal', { isCorrect, correctAnswer, points, wasTimeout, ...options });
        
        this.lastAnswerResult = { isCorrect, correctAnswer, points, wasTimeout, ...options };
        
        const revealOverlay = document.getElementById('revealOverlay');
        const icon = document.getElementById('revealIcon');
        const message = document.getElementById('revealMessage');
        
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
        
        const playerPointsEl = document.getElementById('revealPlayerPoints');
        if (playerPointsEl) {
            playerPointsEl.textContent = `+${points}`;
            playerPointsEl.className = 'reveal-points-badge ' + (points > 0 ? 'points-gained' : 'points-neutral');
        }
        
        const opponentPointsEl = document.getElementById('revealOpponentPoints');
        if (opponentPointsEl) {
            opponentPointsEl.textContent = '+0';
            opponentPointsEl.className = 'reveal-points-badge points-neutral';
        }
        
        const revealScorePlayer = document.getElementById('revealScorePlayer');
        if (revealScorePlayer) revealScorePlayer.textContent = playerScore;
        
        const revealScoreOpponent = document.getElementById('revealScoreOpponent');
        if (revealScoreOpponent) revealScoreOpponent.textContent = opponentScore;
        
        const userAnswerRow = document.getElementById('revealUserAnswerRow');
        const userAnswerEl = document.getElementById('revealUserAnswer');
        const userAnswerIcon = document.getElementById('revealUserAnswerIcon');
        
        if (userAnswerEl) {
            if (wasTimeout) {
                userAnswerEl.textContent = '{{ __("Pas de réponse") }}';
            } else if (userAnswer) {
                userAnswerEl.textContent = userAnswer;
            } else {
                userAnswerEl.textContent = '—';
            }
        }
        
        if (userAnswerRow) {
            userAnswerRow.classList.toggle('was-correct', isCorrect);
        }
        if (userAnswerIcon) {
            userAnswerIcon.textContent = isCorrect ? '✅' : '❌';
        }
        
        const correctAnswerEl = document.getElementById('revealCorrectAnswer');
        if (correctAnswerEl) correctAnswerEl.textContent = correctAnswer || '—';
        
        const statMatchScore = document.getElementById('revealStatMatchScore');
        if (statMatchScore) statMatchScore.textContent = playerScore;
        
        const statQuestion = document.getElementById('revealStatQuestion');
        if (statQuestion) statQuestion.textContent = `${questionNum}/${totalQuestions}`;
        
        if (!gameConfig.isFirebaseMode) {
            const vsHeader = document.getElementById('revealVsHeader');
            const roundDetails = document.getElementById('revealRoundDetails');
            const scoreBattle = document.getElementById('revealScoreBattle');
            
            if (vsHeader) vsHeader.style.display = 'none';
            if (roundDetails) roundDetails.style.display = 'none';
            if (scoreBattle) scoreBattle.style.display = 'none';
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
        
        scoreboardOverlay?.classList.add('active');
        
        return new Promise(resolve => {
            setTimeout(() => {
                this.hideAllOverlays();
                resolve();
            }, this.phaseTimers.scoreboard);
        });
    },
    
    async nextQuestion(questionData) {
        await this.showIntro(questionData);
        this.startQuestion();
    },
    
    async onAnswerComplete(isCorrect, correctAnswer, points, playerScore, opponentScore, hasNextQuestion, questionNum, totalQuestions, wasTimeout = false, userAnswer = null) {
        await this.showReveal(isCorrect, correctAnswer, points, wasTimeout, {
            playerScore,
            opponentScore,
            userAnswer,
            questionNum,
            totalQuestions
        });
        
        if (!hasNextQuestion) {
            this.hideAllOverlays();
        }
        
        return { proceed: hasNextQuestion };
    },
    
    receivePhase(phase, data = {}) {
        if (!this.isHost) {
            console.log('[PhaseController] Received phase from host:', phase);
            
            switch(phase) {
                case 'intro':
                    if (data.questionData) {
                        this.showIntro(data.questionData);
                    }
                    break;
                case 'question':
                    this.startQuestion();
                    break;
                case 'answer':
                    this.showAnswerPhase(data);
                    break;
                case 'reveal':
                    this.showReveal(data.isCorrect, data.correctAnswer, data.points, data.wasTimeout, {
                        playerScore: data.playerScore || 0,
                        opponentScore: data.opponentScore || 0,
                        userAnswer: data.userAnswer || null,
                        questionNum: data.questionNum || 1,
                        totalQuestions: data.totalQuestions || 10
                    });
                    break;
                case 'scoreboard':
                    this.showScoreboard(data.playerScore, data.opponentScore, data.hasNextQuestion, data.questionNum, data.totalQuestions);
                    break;
            }
        }
    }
};

window.PhaseController = PhaseController;

document.addEventListener('DOMContentLoaded', async function() {
    // Initialize PhaseController
    PhaseController.init();
    
    // Show intro phase for first question - SKIP for first question in ALL modes
    // Solo: Players already saw intro in game_intro.blade.php with "Ladies and Gentlemen" countdown
    // Multiplayer: Players enter directly after lobby countdown without intro overlay
    // Only show intro overlay for subsequent questions (question 2+)
    const initialQ = gameConfig.initialQuestion;
    const isFirstQuestion = (initialQ?.question_number || gameConfig.currentQuestion) === 1;
    const skipIntroForFirstQuestion = isFirstQuestion;
    
    if (initialQ && initialQ.question_text && !skipIntroForFirstQuestion) {
        await PhaseController.showIntro({
            question_number: initialQ.question_number || gameConfig.currentQuestion,
            total_questions: initialQ.total_questions || gameConfig.totalQuestions,
            theme: initialQ.theme || '{{ $params["theme"] ?? __("Culture générale") }}',
            sub_theme: initialQ.sub_theme || ''
        });
    }
    
    if (gameConfig.isFirebaseMode) {
        GameplayEngine.init({
            config: {
                timerDuration: 8,
                csrfToken: gameConfig.csrfToken,
                routes: gameConfig.routes,
                sounds: {
                    buzz: document.getElementById('buzzerSound'),
                    correct: document.getElementById('correctSound'),
                    incorrect: document.getElementById('incorrectSound'),
                    timer: document.getElementById('timerTickSound'),
                    timerEnd: document.getElementById('noBuzzSound')
                }
            },
            state: {
                mode: gameConfig.mode,
                isHost: gameConfig.isHost,
                playerId: gameConfig.playerId,
                sessionId: gameConfig.sessionId,
                currentQuestion: gameConfig.currentQuestion,
                totalQuestions: gameConfig.totalQuestions
            },
            provider: null,
            initialQuestion: gameConfig.initialQuestion
        });
        
        // Phase transition to question happens after intro promise resolves (already awaited above)
        // Only set phase once per question - intro overlay auto-hides and we're ready for question
        try {
            PhaseController.setPhase('question');
        } catch (e) {
            console.error('[PhaseController] Error transitioning to question phase:', e);
        }
    } else {
        LocalProvider.init({
            csrfToken: gameConfig.csrfToken,
            routes: {
                fetchQuestion: gameConfig.routes.fetchQuestion,
                buzz: gameConfig.routes.buzz,
                answer: gameConfig.routes.answer
            },
            niveau: {{ $niveau }}
        });
        
        GameplayEngine.init({
            config: {
                timerDuration: 8,
                csrfToken: gameConfig.csrfToken,
                routes: gameConfig.routes,
                sounds: {
                    buzz: document.getElementById('buzzerSound'),
                    correct: document.getElementById('correctSound'),
                    incorrect: document.getElementById('incorrectSound'),
                    timer: document.getElementById('timerTickSound'),
                    timerEnd: document.getElementById('noBuzzSound')
                }
            },
            state: {
                mode: gameConfig.mode,
                isHost: gameConfig.isHost,
                playerId: gameConfig.playerId,
                sessionId: gameConfig.sessionId,
                currentQuestion: gameConfig.currentQuestion,
                totalQuestions: gameConfig.totalQuestions
            },
            provider: LocalProvider
        });
        
        // Phase transition to question after intro promise resolves (already awaited above)
        try {
            PhaseController.setPhase('question');
        } catch (e) {
            console.error('[PhaseController] Error transitioning to question phase:', e);
        }
        GameplayEngine.startTimer();
    }
});

function startTimer() {
    if (typeof GameplayEngine !== 'undefined' && GameplayEngine.startTimer) {
        GameplayEngine.startTimer();
        return;
    }
    timerInterval = setInterval(() => {
        timeLeft--;
        const chronoTimer = document.getElementById('chronoTimer');
        if (chronoTimer) chronoTimer.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            if (!buzzed) {
                handleTimeout();
            }
        }
    }, 1000);
}

function showAnswers() {
    if (answersShown) return;
    answersShown = true;
    
    // Use GameplayEngine if available (for answer timer and point badges)
    if (typeof GameplayEngine !== 'undefined' && GameplayEngine.showAnswers) {
        GameplayEngine.showAnswers();
        return;
    }
    
    // Fallback for non-GameplayEngine flow
    if (typeof PhaseController !== 'undefined') {
        PhaseController.onBuzz();
    }
    
    const buzzContainer = document.getElementById('buzzContainer');
    const grid = document.getElementById('answersGrid');
    
    if (buzzContainer) buzzContainer.style.display = 'none';
    if (grid) grid.style.display = 'grid';
    
    const answerButtons = grid?.querySelectorAll('.answer-option') || [];
    answerButtons.forEach((btn, index) => {
        btn.addEventListener('click', () => handleAnswerClick(btn, index));
    });
}

function handleAnswerClick(button, index) {
    const isCorrect = button.dataset.correct === 'true';
    const grid = document.getElementById('answersGrid');
    
    grid?.querySelectorAll('.answer-option').forEach(btn => {
        btn.classList.add('disabled');
        if (btn.dataset.correct === 'true') {
            btn.classList.add('correct');
        }
    });
    
    if (!isCorrect) {
        button.classList.add('incorrect');
    }
    
    const sound = isCorrect ? document.getElementById('correctSound') : document.getElementById('incorrectSound');
    if (sound) {
        sound.currentTime = 0;
        sound.play().catch(e => console.log('Sound error:', e));
    }
    
    submitAnswer(index, isCorrect);
}

async function submitAnswer(answerIndex, isCorrect) {
    if (gameConfig.isFirebaseMode) {
        showWaitingOverlay('{{ __("En attente du résultat...") }}');
    }
    
    try {
        const response = await fetch(gameConfig.routes.answer, {
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
        });
        
        const data = await response.json();
        hideWaitingOverlay();
        
        document.getElementById('playerScore').textContent = data.player_score;
        const playerScore = data.player_score || 0;
        const opponentScore = data.opponent?.opponent_score || 0;
        
        if (typeof GameplayEngine !== 'undefined') {
            GameplayEngine.updateScores(playerScore, opponentScore);
        }
        
        if (window.handleFirebaseScore) {
            await window.handleFirebaseScore(playerScore);
        }
        
        if (data.opponent) {
            document.getElementById('opponentScore').textContent = opponentScore;
        }
        
        if (window.handleFirebaseReady) {
            await window.handleFirebaseReady();
        }
        
        // Get correct answer text for reveal
        const currentQ = gameConfig.initialQuestion || {};
        const correctAnswer = currentQ?.answers?.[data.correct_index]?.text || '';
        const userAnswer = currentQ?.answers?.[answerIndex]?.text || '';
        const points = data.was_correct ? (data.points_earned || 10) : 0;
        const questionNum = currentQ?.question_number || gameConfig.currentQuestion;
        const totalQuestions = currentQ?.total_questions || gameConfig.totalQuestions;
        
        // Use PhaseController for proper reveal/scoreboard overlay handling
        if (typeof PhaseController !== 'undefined') {
            await PhaseController.onAnswerComplete(
                data.was_correct,
                correctAnswer,
                points,
                playerScore,
                opponentScore,
                data.has_next_question,
                questionNum,
                totalQuestions,
                false,
                userAnswer
            );
            
            // Navigate after overlays complete - no extra delay
            if (data.has_next_question) {
                if (gameConfig.isFirebaseMode && window.GameFlowController) {
                    GameFlowController.advanceToNextQuestion();
                } else {
                    window.location.reload();
                }
            } else {
                // End of game - redirect immediately (overlays already hidden by PhaseController)
                window.location.href = gameConfig.routes.roundResult;
            }
        } else {
            // Fallback without PhaseController - brief delay for visual feedback
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
            }, 1500);
        }
    } catch (error) {
        console.error('Answer error:', error);
        hideWaitingOverlay();
    }
}

const GameFlowController = {
    isHost: {{ ($params['is_host'] ?? false) ? 'true' : 'false' }},
    lastQuestionNumber: {{ $currentQuestion }},
    
    async advanceToNextQuestion() {
        const nextQ = this.lastQuestionNumber + 1;
        console.log('[GameFlow] Advancing to question', nextQ, 'isHost:', this.isHost);
        
        if (this.isHost) {
            showWaitingOverlay('{{ __("En attente des joueurs...") }}');
            
            await this.waitForFirebaseReady();
            
            const expectedPlayers = gameConfig.mode === 'master' ? 3 : 2;
            await this.waitForAllPlayersReady(expectedPlayers, 10000);
            
            showWaitingOverlay('{{ __("Chargement de la question...") }}');
            
            if (window.handleFirebaseFetchQuestion) {
                const result = await window.handleFirebaseFetchQuestion(nextQ);
                if (result && result.success && result.questionData) {
                    this.lastQuestionNumber = nextQ;
                    this.displayQuestion({
                        question_number: result.questionData.question_number,
                        total_questions: result.questionData.total_questions || gameConfig.totalQuestions,
                        question_text: result.questionData.question_text,
                        answers: result.questionData.answers.map((a, i) => ({
                            text: a.text || a,
                            is_correct: a.is_correct !== undefined ? a.is_correct : (i === result.questionData.correct_index)
                        })),
                        theme: result.questionData.theme,
                        sub_theme: result.questionData.sub_theme
                    });
                } else if (result?.redirect_url) {
                    window.location.href = result.redirect_url;
                } else {
                    console.error('[GameFlow] Failed to fetch question via provider:', result);
                    const fallbackData = await this.fetchQuestion(nextQ);
                    if (fallbackData && fallbackData.success) {
                        this.lastQuestionNumber = nextQ;
                        const correctIdx = fallbackData.question.answers.findIndex(a => a.is_correct);
                        this.displayQuestion({
                            question_number: fallbackData.question_number,
                            total_questions: fallbackData.total_questions,
                            question_text: fallbackData.question.question_text,
                            answers: fallbackData.question.answers.map((a, i) => ({
                                text: a.text || a,
                                is_correct: a.is_correct || (i === correctIdx)
                            })),
                            theme: fallbackData.question.theme,
                            sub_theme: fallbackData.question.sub_theme
                        });
                    } else {
                        window.location.reload();
                    }
                }
            } else {
                const questionData = await this.fetchQuestion(nextQ);
                if (questionData && questionData.success) {
                    await this.publishQuestionToFirestore(nextQ, questionData);
                    this.lastQuestionNumber = nextQ;
                } else {
                    console.error('[GameFlow] Failed to fetch question');
                    window.location.reload();
                }
            }
        } else {
            showWaitingOverlay('{{ __("En attente de la question...") }}');
        }
    },
    
    async waitForFirebaseReady(maxWait = 5000) {
        if (window.MultiplayerFirestoreProvider && window.MultiplayerFirestoreProvider.db) {
            return true;
        }
        if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
            return true;
        }
        
        const startTime = Date.now();
        while (Date.now() - startTime < maxWait) {
            if (window.MultiplayerFirestoreProvider && window.MultiplayerFirestoreProvider.db) {
                return true;
            }
            if (typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
                return true;
            }
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        console.warn('[GameFlow] Firebase not ready after', maxWait, 'ms');
        return false;
    },
    
    async waitForAllPlayersReady(expectedPlayers, maxWait = 30000) {
        return new Promise((resolve) => {
            if (!window.MultiplayerFirestoreProvider || !window.MultiplayerFirestoreProvider.db) {
                console.warn('[GameFlow] No provider, skipping RE-SYNC wait');
                resolve(true);
                return;
            }
            
            let resolved = false;
            let unsubscribe = null;
            
            const timeout = setTimeout(() => {
                if (!resolved) {
                    resolved = true;
                    console.warn('[GameFlow] Timeout waiting for all players ready');
                    if (unsubscribe) unsubscribe();
                    resolve(true);
                }
            }, maxWait);
            
            unsubscribe = window.MultiplayerFirestoreProvider.listenForAllReady(expectedPlayers, () => {
                if (!resolved) {
                    resolved = true;
                    clearTimeout(timeout);
                    console.log('[GameFlow] All players ready, proceeding');
                    resolve(true);
                }
            });
        });
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
        // OPTION C: Backend publishes directly to Firebase, no client-side publish needed
        // This method now just displays the question locally
        // Both host and guest receive via listenForQuestions callback
        console.log('[GameFlow] OPTION C: Skipping client publish, backend handles Firebase');
        this.displayQuestion(questionData);
    },
    
    async displayQuestion(questionData) {
        hideWaitingOverlay();
        
        this.resetGameState();
        
        gameConfig.currentQuestion = questionData.question_number;
        this.lastQuestionNumber = questionData.question_number;
        
        // Store current question data for answer handling
        this.currentQuestionData = questionData;
        
        // Show intro phase for all questions (intro overlay must complete before gameplay starts)
        // Always await showIntro with try/catch to ensure proper phase sequencing
        if (typeof PhaseController !== 'undefined') {
            try {
                await PhaseController.showIntro(questionData);
            } catch (e) {
                console.error('[GameFlow] showIntro error:', e);
            }
        }
        
        if (typeof GameplayEngine !== 'undefined' && GameplayEngine.startQuestion) {
            GameplayEngine.startQuestion(questionData);
            // Set phase to question only once after intro completes
            if (typeof PhaseController !== 'undefined') {
                try {
                    PhaseController.setPhase('question');
                } catch (e) {
                    console.error('[GameFlow] setPhase error:', e);
                }
            }
            return;
        }
        
        const themeDisplay = questionData.theme === 'Culture générale' ? '{{ __("Général") }}' : questionData.theme;
        
        // Show question header now that we have data
        document.getElementById('questionHeader')?.classList.remove('waiting-for-question');
        
        document.getElementById('questionText').textContent = questionData.question_text;
        document.getElementById('questionNumber').textContent = 
            `${themeDisplay}${questionData.sub_theme ? ' - ' + questionData.sub_theme : ''} | {{ __("Question") }} ${questionData.question_number}/${questionData.total_questions}`;
        
        const grid = document.getElementById('answersGrid');
        grid.innerHTML = '';
        grid.style.display = 'none';
        
        const correctIndex = questionData.answers.findIndex(a => a.is_correct);
        
        questionData.answers.forEach((answer, idx) => {
            const btn = document.createElement('button');
            btn.className = 'answer-option';
            btn.dataset.index = idx;
            btn.textContent = answer.text;
            btn.addEventListener('click', async function() {
                if (btn.classList.contains('disabled')) return;
                
                grid.querySelectorAll('.answer-option').forEach(b => b.classList.add('disabled'));
                
                try {
                    const response = await fetch(gameConfig.routes.answer, {
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
                    });
                    
                    const data = await response.json();
                    const isCorrect = data.is_correct || data.was_correct;
                    const correctAnswer = questionData.answers[data.correct_index]?.text || '';
                    const userAnswer = btn.textContent || questionData.answers[idx]?.text || '';
                    const points = isCorrect ? (data.points_earned || 10) : 0;
                    const playerScore = data.player_score || 0;
                    const opponentScore = data.opponent?.opponent_score || 0;
                    
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
                    
                    document.getElementById('playerScore').textContent = playerScore;
                    if (data.opponent) {
                        document.getElementById('opponentScore').textContent = opponentScore;
                    }
                    
                    if (typeof GameplayEngine !== 'undefined') {
                        GameplayEngine.updateScores(playerScore, opponentScore);
                    }
                    
                    // Use PhaseController for reveal and scoreboard phases
                    if (typeof PhaseController !== 'undefined') {
                        await PhaseController.onAnswerComplete(
                            isCorrect,
                            correctAnswer,
                            points,
                            playerScore,
                            opponentScore,
                            data.has_next_question,
                            questionData.question_number,
                            questionData.total_questions,
                            false,
                            userAnswer
                        );
                        
                        if (data.has_next_question) {
                            GameFlowController.advanceToNextQuestion();
                        } else {
                            window.location.href = gameConfig.routes.roundResult;
                        }
                    } else {
                        // Fallback without PhaseController
                        setTimeout(() => {
                            if (data.has_next_question) {
                                GameFlowController.advanceToNextQuestion();
                            } else {
                                window.location.href = gameConfig.routes.roundResult;
                            }
                        }, 2000);
                    }
                } catch(e) {
                    console.error('Answer error:', e);
                }
            });
            grid.appendChild(btn);
        });
        
        // Phase already set after showIntro completes - no duplicate setPhase needed here
        // (intro handles phase transition timing)
        
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
        
        if (buzzContainer) buzzContainer.style.display = 'flex';
        if (buzzButton) {
            buzzButton.disabled = false;
            buzzButton.style.opacity = '1';
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
        console.log('[GameFlow] Question received from Firestore:', questionNumber, questionData);
        if (questionNumber > this.lastQuestionNumber) {
            this.lastQuestionNumber = questionNumber;
            this.displayQuestion({
                question_number: questionNumber,
                question_text: questionData.question_text,
                answers: questionData.answers.map((a, i) => ({
                    text: a.text || a,
                    is_correct: a.is_correct !== undefined ? a.is_correct : (i === questionData.correct_index)
                })),
                theme: questionData.theme || '{{ $theme }}',
                sub_theme: questionData.sub_theme || '',
                total_questions: questionData.total_questions || gameConfig.totalQuestions
            });
        }
    }
};

window.GameFlowController = GameFlowController;

async function handleTimeout() {
    document.getElementById('noBuzzSound').play().catch(e => console.log('Sound error:', e));
    
    // Buzz timer expired - show Answer phase with 0 points (player can still answer but for 0 pts)
    if (typeof PhaseController !== 'undefined') {
        PhaseController.showAnswerPhase({ playerBuzzed: false, potentialPoints: 0 });
        return; // Let the answer phase timer handle the rest
    }
    
    // Fallback: Submit timeout answer directly if PhaseController not available
    const currentQ = GameFlowController.currentQuestionData || gameConfig.initialQuestion;
    const correctAnswer = currentQ?.answers?.find(a => a.is_correct)?.text || '';
    
    try {
        const response = await fetch(gameConfig.routes.answer, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': gameConfig.csrfToken
            },
            body: JSON.stringify({
                answer_id: -1,
                is_correct: false,
                buzz_time: 0
            })
        });
        
        const data = await response.json();
        const playerScore = data.player_score || 0;
        const opponentScore = data.opponent?.opponent_score || 0;
        
        document.getElementById('playerScore').textContent = playerScore;
        if (data.opponent) {
            document.getElementById('opponentScore').textContent = opponentScore;
        }
        
        if (typeof GameplayEngine !== 'undefined') {
            GameplayEngine.updateScores(playerScore, opponentScore);
        }
        
        if (window.handleFirebaseScore) {
            await window.handleFirebaseScore(playerScore);
        }
        
        // Use PhaseController fallback to properly chain reveal → next question
        if (typeof PhaseController !== 'undefined') {
            await PhaseController.onAnswerComplete(
                false,
                correctAnswer,
                0,
                playerScore,
                opponentScore,
                data.has_next_question,
                currentQ?.question_number || gameConfig.currentQuestion,
                currentQ?.total_questions || gameConfig.totalQuestions,
                true,  // wasTimeout = true
                null   // userAnswer = null (no answer was given)
            );
            
            if (data.has_next_question) {
                if (gameConfig.isFirebaseMode && window.GameFlowController) {
                    GameFlowController.advanceToNextQuestion();
                } else {
                    window.location.reload();
                }
            } else {
                window.location.href = gameConfig.routes.roundResult;
            }
        } else {
            // Fallback without PhaseController
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
        }
    } catch (error) {
        console.error('Timeout answer error:', error);
        // Still show reveal on error
        if (typeof PhaseController !== 'undefined') {
            await PhaseController.showReveal(false, correctAnswer, 0, true);
        }
    }
}

function showWaitingOverlay(text) {
    document.getElementById('waitingText').textContent = text;
    waitingOverlay.classList.add('active');
}

function hideWaitingOverlay() {
    waitingOverlay.classList.remove('active');
}

@if($isFirebaseMode)
(async function() {
    const script = document.createElement('script');
    script.src = '/js/MultiplayerFirestoreProvider.js';
    document.head.appendChild(script);
    await new Promise(r => script.onload = r);
    
    const [{ initializeApp }, { getAuth, signInAnonymously }, firestoreModule] = await Promise.all([
        import('https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js'),
        import('https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js'),
        import('https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js')
    ]);
    
    const { getFirestore, doc, onSnapshot, updateDoc, setDoc, serverTimestamp, arrayUnion, getDoc } = firestoreModule;
    
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
    
    try {
        const userCredential = await signInAnonymously(auth);
        const firebaseUid = userCredential.user.uid;
        console.log('[Firebase] Anonymous auth successful, UID:', firebaseUid);
        
        const statusEl = document.getElementById('firebaseStatus');
        statusEl.textContent = '{{ __("Connecté") }}';
        statusEl.classList.remove('disconnected');
        statusEl.classList.add('connected');
        
        const sessionId = gameConfig.sessionId || gameConfig.matchId || gameConfig.roomCode;
        
        if (sessionId && window.MultiplayerFirestoreProvider) {
            const providerInit = await window.MultiplayerFirestoreProvider.init({
                sessionId: sessionId,
                playerId: firebaseUid,
                laravelUserId: gameConfig.playerId,
                isHost: gameConfig.isHost,
                mode: gameConfig.mode,
                csrfToken: gameConfig.csrfToken,
                routes: gameConfig.routes,
                db: db,
                doc: doc,
                onSnapshot: onSnapshot,
                updateDoc: updateDoc,
                setDoc: setDoc,
                serverTimestamp: serverTimestamp,
                arrayUnion: arrayUnion,
                getDoc: getDoc
            });
            
            if (providerInit) {
                console.log('[Firebase] MultiplayerFirestoreProvider initialized');
                
                if (typeof GameplayEngine !== 'undefined') {
                    GameplayEngine.setProvider(window.MultiplayerFirestoreProvider);
                    console.log('[Firebase] MultiplayerFirestoreProvider set for GameplayEngine');
                    
                    // ARCHITECTURE FIX: Both host and guest wait for question from Firebase
                    // The backend publishes questions directly to Firebase via DuoFirestoreService
                    // This ensures perfect synchronization - no client-side publishing needed
                    console.log('[Firebase] Waiting for question from Firebase (backend publishes)...');
                    showWaitingOverlay('{{ __("En attente de la question...") }}');
                }
                
                window.MultiplayerFirestoreProvider.listenForQuestions((questionData, questionNumber) => {
                    console.log('[Firebase] Question received from backend:', questionNumber, 'lastQuestionNumber:', GameFlowController.lastQuestionNumber);
                    
                    // SYNC FIX: Both host and guest receive questions the same way from Firebase
                    // Backend publishes via DuoFirestoreService, ensuring perfect synchronization
                    const isFirstQuestion = questionNumber === 1 && !window._receivedFirstQuestion;
                    
                    if (questionNumber > GameFlowController.lastQuestionNumber || isFirstQuestion) {
                        if (isFirstQuestion) {
                            window._receivedFirstQuestion = true;
                        }
                        hideWaitingOverlay();
                        console.log('[Firebase] Both players received question', questionNumber, 'from backend');
                        
                        // Start question directly - no client-side phase publishing needed
                        if (typeof GameplayEngine !== 'undefined' && GameplayEngine.startQuestion) {
                            GameplayEngine.startQuestion(questionData);
                        }
                        GameFlowController.onQuestionDataReceived(questionData, questionNumber);
                    }
                });
                
                window.MultiplayerFirestoreProvider.listenForBuzz((buzzedPlayerId, buzzTime) => {
                    console.log('[Firebase] Opponent buzzed via provider');
                    if (typeof GameplayEngine !== 'undefined' && GameplayEngine.receiveBuzz) {
                        GameplayEngine.receiveBuzz(buzzedPlayerId, buzzTime);
                    }
                    if (!answersShown) {
                        hideWaitingOverlay();
                        showAnswers();
                    }
                });
                
                window.MultiplayerFirestoreProvider.listenForScores((opponentScore) => {
                    document.getElementById('opponentScore').textContent = opponentScore;
                    if (typeof GameplayEngine !== 'undefined') {
                        GameplayEngine.updateScores(null, opponentScore);
                    }
                });
                
                window.handleFirebaseBuzz = async function(buzzTime) {
                    return await window.MultiplayerFirestoreProvider.publishBuzz(buzzTime);
                };
                
                window.handleFirebaseScore = async function(score) {
                    return await window.MultiplayerFirestoreProvider.updateScore(score);
                };
                
                window.handleFirebaseReady = async function() {
                    return await window.MultiplayerFirestoreProvider.markPlayerReady();
                };
                
                window.handleFirebaseFetchQuestion = async function(questionNumber) {
                    // OPTION C: Use fetchQuestion which triggers backend to publish to Firebase
                    // Backend handles Firebase publishing, client just waits for listener callback
                    return await window.MultiplayerFirestoreProvider.fetchQuestion(questionNumber);
                };
                
                // Phase sync handler for multiplayer
                window.handleFirebasePhase = async function(phase, data = {}) {
                    return await window.MultiplayerFirestoreProvider.publishPhase(phase, data);
                };
                
                // Listen for phase changes from host
                window.MultiplayerFirestoreProvider.listenForPhases((phase, data) => {
                    console.log('[Firebase] Phase received from host:', phase);
                    
                    // Connect to GameplayEngine for phase sync (guest waits for 'question' phase)
                    if (typeof GameplayEngine !== 'undefined' && GameplayEngine.onPhaseChange) {
                        GameplayEngine.onPhaseChange(phase, data);
                    }
                    
                    if (typeof PhaseController !== 'undefined') {
                        PhaseController.receivePhase(phase, data);
                    }
                });
                
                // Initialize skill listeners for multiplayer attack/defense synchronization
                window.MultiplayerFirestoreProvider.listenForSkills((skillId, skillData, fromPlayerId) => {
                    console.log('[Firebase] Skill received from opponent:', skillId);
                    if (typeof GameplayEngine !== 'undefined' && GameplayEngine.receiveSkill) {
                        GameplayEngine.receiveSkill(skillId, skillData, fromPlayerId);
                    }
                });
                
                // Listen for opponent's answer choice (for see_opponent_choice skill)
                window.MultiplayerFirestoreProvider.listenForOpponentChoice((answerIndex, playerId) => {
                    console.log('[Firebase] Opponent choice received:', answerIndex);
                    if (typeof GameplayEngine !== 'undefined' && GameplayEngine.showOpponentChoice) {
                        GameplayEngine.showOpponentChoice(answerIndex);
                    }
                });
                
                // Handler to publish answer choice for see_opponent_choice skill
                window.handleFirebaseAnswerChoice = async function(answerIndex) {
                    return await window.MultiplayerFirestoreProvider.publishAnswerChoice(answerIndex);
                };
                
                console.log('[Firebase] Skill listeners initialized');
            }
        }
        
        if (gameConfig.matchId && !window.MultiplayerFirestoreProvider) {
            const firestoreGameId = gameConfig.mode + '-match-' + gameConfig.matchId;
            const matchRef = doc(db, 'games', firestoreGameId);
            
            console.log('[Firebase] Legacy listener for:', firestoreGameId);
            
            onSnapshot(matchRef, (snapshot) => {
                if (snapshot.exists()) {
                    const data = snapshot.data();
                    handleFirebaseUpdate(data);
                }
            }, (error) => {
                console.error('[Firebase] Listener error:', error);
            });
        }
    } catch (e) {
        console.error('[Firebase] Auth error:', e);
    }
})();

let lastQuestionPublishedAt = 0;

function handleFirebaseUpdate(data) {
    const isPlayer1 = gameConfig.playerId === (data.player1Id || '').toString();
    const isPlayer2 = gameConfig.playerId === (data.player2Id || '').toString();
    
    const questionPublishedAt = data.questionPublishedAt?.toMillis?.() || data.questionPublishedAt || 0;
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

function activateRevealSkill(skillIndex) {
    const skillItem = document.getElementById(`revealSkill${skillIndex}`);
    const skillBtn = document.getElementById(`revealSkillBtn${skillIndex}`);
    
    if (!skillItem || skillItem.classList.contains('used')) return;
    
    const skillEl = skillItem.querySelector('.reveal-skill-icon');
    const skillId = skillItem.dataset?.skillIndex ?? skillIndex;
    
    skillItem.classList.add('used');
    if (skillBtn) skillBtn.disabled = true;
    
    console.log('[RevealSkill] Activating skill:', skillIndex);
    
    fetch('/game/{{ $mode }}/use-skill', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': gameConfig.csrfToken
        },
        body: JSON.stringify({ skill_id: skillId, from_reveal: true })
    }).then(response => response.json()).then(data => {
        console.log('[RevealSkill] Skill activated:', data);
    }).catch(err => {
        console.error('[RevealSkill] Error:', err);
    });
}

window.activateRevealSkill = activateRevealSkill;

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
            if (this.classList.contains('locked') || this.dataset.locked === 'true') {
                console.log('[Skill] Skill locked - not your turn');
                return;
            }
            
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
            if (this.classList.contains('locked') || this.dataset.locked === 'true') {
                console.log('[Skill] Skill locked - not your turn');
                return;
            }
            
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
