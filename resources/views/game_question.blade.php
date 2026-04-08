@extends('layouts.app')

@section('content')
@php
// Mode de jeu (solo, duo, league_individual, master)
$mode = $params['mode'] ?? 'solo';
$isMultiplayer = in_array($mode, ['duo', 'league_individual', 'master']);
$isFirebaseMode = $isMultiplayer; // Alias pour compatibilité
$matchId = $params['match_id'] ?? null;
$roomCode = $params['room_code'] ?? null;
$lobbyCode = $params['lobby_code'] ?? null;
$totalQuestions = $params['nb_questions'] ?? $params['total_questions'] ?? 10;

// Récupérer la structure complète des skills depuis le contrôleur
$avatarSkillsFull = $params['avatar_skills_full'] ?? ['rarity' => null, 'skills' => []];
$currentAvatar = $params['avatar'] ?? 'Aucun';

// Récupérer les skills utilisés
$usedSkills = session('used_skills', []);

// Détection du Stratège
$isStratege = in_array(strtolower($currentAvatar), ['stratège', 'stratege']);
$teammateInfo = $avatarSkillsFull['teammate'] ?? null;

// Construire le tableau des skills pour l'affichage
$skills = [];

if ($isStratege) {
    // STRATÈGE : Afficher exactement 3 icônes : 💰 coin_bonus, 🏷️ avatar_discount, + skill du coéquipier
    $strategeSkillsToShow = ['coin_bonus', 'avatar_discount'];
    
    // Trouver les skills du Stratège (coin_bonus, avatar_discount)
    if (!empty($avatarSkillsFull['skills'])) {
        foreach ($avatarSkillsFull['skills'] as $skillData) {
            $skillId = $skillData['id'];
            
            // Ajouter coin_bonus et avatar_discount
            if (in_array($skillId, $strategeSkillsToShow)) {
                $skills[] = [
                    'id' => $skillId,
                    'icon' => $skillData['icon'],
                    'name' => $skillData['name'],
                    'description' => $skillData['description'],
                    'type' => $skillData['type'],
                    'trigger' => $skillData['trigger'],
                    'auto' => true,
                    'used' => false,
                    'uses_left' => -1,
                    'is_teammate_skill' => false,
                ];
            }
        }
        
        // Ajouter le skill du coéquipier (le 3ème skill)
        $teammateSkillAdded = false;
        if ($teammateInfo) {
            foreach ($avatarSkillsFull['skills'] as $skillData) {
                $skillId = $skillData['id'];
                
                // Si ce n'est pas un skill du Stratège, c'est le skill du coéquipier
                if (!in_array($skillId, ['coin_bonus', 'avatar_discount', 'create_team'])) {
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
                        'is_teammate_skill' => true,
                    ];
                    $teammateSkillAdded = true;
                    break; // Un seul skill du coéquipier
                }
            }
        }
        
        // Si aucun teammate skill n'est ajouté, afficher un placeholder
        // SEULEMENT si aucun avatar Rare n'est débloqué (👥)
        if (!$teammateSkillAdded) {
            $hasUnlockedRare = $avatarSkillsFull['has_unlocked_rare'] ?? false;
            $skills[] = [
                'id' => 'create_team',
                'icon' => '👥',
                'name' => 'Team',
                'description' => $hasUnlockedRare ? 'Sélectionnez un coéquipier' : 'Débloquez un Avatar Rare',
                'type' => 'team',
                'trigger' => 'passive',
                'auto' => true,
                'used' => false,
                'uses_left' => -1,
                'is_teammate_skill' => false,
                'is_placeholder' => true,
                'no_rare_unlocked' => !$hasUnlockedRare,
            ];
        }
    }
} else {
    // AUTRES AVATARS : Afficher tous leurs skills
    if (!empty($avatarSkillsFull['skills'])) {
        foreach ($avatarSkillsFull['skills'] as $skillData) {
            $skillId = $skillData['id'];
            $isUsed = in_array($skillId, $usedSkills);
            
            // Compter les utilisations pour les skills multi-usage
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
                'is_teammate_skill' => false,
            ];
        }
    }
}

// Prénoms pour le joueur (Solo uniquement)
$playerInfo = $params['player_info'] ?? [];
$opponentInfo = $params['opponent_info'] ?? [];
$niveau = $params['niveau'];
$playerLevel = $isMultiplayer ? ($playerInfo['level'] ?? $niveau) : $niveau;
$opponentLevel = $isMultiplayer ? ($opponentInfo['level'] ?? $niveau) : $niveau;
$opponentScore = $params['opponent_score'] ?? 0;

// Mode Duo/Multiplayer: utiliser les infos passées par le contrôleur
if ($isMultiplayer && !empty($playerInfo)) {
    $playerName = $playerInfo['name'] ?? 'Joueur';
    $playerAvatarPath = $playerInfo['avatar'] ?? asset('images/avatars/standard/standard1.png');
    if ($playerAvatarPath && !str_starts_with($playerAvatarPath, 'http') && !str_starts_with($playerAvatarPath, '/')) {
        $playerAvatarPath = '/' . $playerAvatarPath;
    }
} else {
    // Mode Solo: génération aléatoire
    $playerNames = ['Hugo', 'Léa', 'Lucas', 'Emma', 'Nathan', 'Chloé', 'Louis', 'Jade', 'Arthur', 'Inès', 'Raphaël', 'Camille', 'Gabriel', 'Zoé', 'Thomas', 'Alice'];
    $playerName = $playerNames[array_rand($playerNames)];
    
    // Avatar du joueur depuis session
    $selectedAvatar = session('selected_avatar', 'default');
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
}

// Avatar stratégique - les avatars sont dans public/images/avatars/
$strategicAvatarPath = '';
if ($currentAvatar !== 'Aucun') {
    $strategicAvatarSlug = strtolower($currentAvatar);
    $strategicAvatarSlug = str_replace(['é', 'è', 'ê'], 'e', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(['à', 'â'], 'a', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(' ', '-', $strategicAvatarSlug);
    $strategicAvatarPath = asset("images/avatars/{$strategicAvatarSlug}.png");
}

// Déterminer l'avatar et le nom de l'adversaire
if ($isMultiplayer && !empty($opponentInfo)) {
    // Mode Duo/Multiplayer: utiliser les infos de l'adversaire réel
    $opponentName = $opponentInfo['name'] ?? 'Adversaire';
    $opponentAvatar = $opponentInfo['avatar'] ?? asset('images/avatars/standard/standard1.png');
    if ($opponentAvatar && !str_starts_with($opponentAvatar, 'http') && !str_starts_with($opponentAvatar, '/')) {
        $opponentAvatar = '/' . $opponentAvatar;
    }
    $opponentDescription = '';
} elseif ($opponentInfo['is_boss'] ?? false) {
    $opponentName = $opponentInfo['name'];
    $opponentAvatar = asset("images/avatars/bosses/{$opponentInfo['avatar']}.png");
    $opponentDescription = '';
} else {
    $opponentName = $opponentInfo['name'] ?? 'Adversaire';
    $opponentAge = $opponentInfo['age'] ?? 8;
    $nextBoss = $opponentInfo['next_boss'] ?? 'Le Stratège';
    $opponentAvatar = asset("images/avatars/students/{$opponentInfo['avatar']}.png");
    $opponentDescription = __('Votre adversaire') . " {$opponentName} {$opponentAge} " . __('ans élève du') . " {$nextBoss}";
}
@endphp

<style>
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
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
        padding-bottom: env(safe-area-inset-bottom, 10px);
        box-sizing: border-box;
        overflow-x: hidden;
    }
    
    .game-container {
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
        position: relative;
        z-index: 10;
        min-height: calc(100vh - 20px);
        min-height: calc(100dvh - 20px);
        padding-bottom: 20px;
    }
    
    /* Question en haut */
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
    
    /* Layout 3 colonnes */
    .game-layout {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 30px;
        align-items: start;
        justify-items: center;
        margin: 20px 0;
    }
    
    /* COLONNE GAUCHE - Joueur + Adversaire */
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
        border: 3px solid #4ECDC4;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.5);
        object-fit: cover;
    }
    
    .player-name {
        font-size: 1rem;
        font-weight: 600;
        color: #4ECDC4;
    }
    
    .player-level {
        font-size: 0.85rem;
        color: #4ECDC4;
        opacity: 0.8;
    }
    
    .player-score {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
    }
    
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
        border: 3px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        object-fit: cover;
    }
    
    .opponent-avatar-empty {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 107, 107, 0.1);
        font-size: 2.5rem;
        font-weight: 900;
        color: #FF6B6B;
    }
    
    .opponent-name {
        font-size: 1rem;
        font-weight: 600;
        color: #FF6B6B;
    }
    
    .opponent-level {
        font-size: 0.85rem;
        color: #FF6B6B;
        opacity: 0.8;
    }
    
    .opponent-score {
        font-size: 2rem;
        font-weight: 900;
        color: #FF6B6B;
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.8);
    }
    
    /* COLONNE CENTRE - Chronomètre */
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
    
    /* COLONNE DROITE - Avatar stratégique + Skills */
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
    }
    
    .skill-circle.active {
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.2);
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
        animation: golden-pulse 2s ease-in-out infinite;
        cursor: pointer;
    }
    
    .skill-circle.active:hover {
        transform: scale(1.15);
        box-shadow: 0 0 40px rgba(255, 215, 0, 1);
    }
    
    .skill-circle.active:active {
        transform: scale(0.95);
    }
    
    @keyframes golden-pulse {
        0%, 100% {
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
        }
        50% {
            box-shadow: 0 0 35px rgba(255, 215, 0, 0.9);
        }
    }
    
    .skill-circle.empty {
        opacity: 0.3;
        cursor: not-allowed;
    }
    
    .skill-circle.passive {
        opacity: 0.7;
        border-color: rgba(150, 150, 150, 0.5);
        background: rgba(100, 100, 100, 0.3);
        box-shadow: 0 0 10px rgba(150, 150, 150, 0.3);
        animation: none;
        cursor: help;
    }
    
    .skill-circle.used {
        opacity: 0.4;
        border-color: rgba(255, 255, 255, 0.2);
        background: rgba(100, 100, 100, 0.2);
        box-shadow: none;
        animation: none;
        cursor: not-allowed;
    }
    
    .skill-circle.disabled:not(.used):not(.passive) {
        cursor: help;
    }
    
    /* BOUTON BUZZER CENTRÉ EN BAS */
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
    
    /* Buzzer States */
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
    
    /* Messages et résultats */
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
    
    /* Responsive Tablette */
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
    
    /* Responsive Mobile */
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
        
        .player-level, .opponent-level {
            font-size: 0.75rem;
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
    
    /* Landscape mode pour mobile */
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
</style>

@if($isMultiplayer)
<button id="voiceMicButton" class="voice-mic-button" title="{{ __('Activer/désactiver le micro') }}">
    <span id="micIcon">🎤</span>
</button>
<style>
    .voice-mic-button {
        position: fixed;
        bottom: 200px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 2px solid rgba(78, 205, 196, 0.5);
        background: rgba(15, 32, 39, 0.9);
        color: white;
        font-size: 1.4rem;
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .voice-mic-button:hover {
        background: rgba(78, 205, 196, 0.3);
        transform: scale(1.1);
    }
    .voice-mic-button.active {
        background: linear-gradient(135deg, #2ECC71, #27AE60);
        border-color: #2ECC71;
        animation: pulse-mic 1.5s infinite;
    }
    .voice-mic-button.muted {
        background: rgba(60, 60, 60, 0.9);
        border-color: rgba(150, 150, 150, 0.5);
    }
    @keyframes pulse-mic {
        0%, 100% { box-shadow: 0 0 10px rgba(46, 204, 113, 0.5); }
        50% { box-shadow: 0 0 20px rgba(46, 204, 113, 0.8); }
    }
    @media (max-width: 768px) {
        .voice-mic-button {
            width: 45px;
            height: 45px;
            font-size: 1.2rem;
            bottom: 180px;
            right: 15px;
        }
    }
</style>
@endif

<div class="game-container">
    <!-- Question en haut -->
    <div class="question-header">
        <div class="question-text">{{ $params['question']['text'] }}</div>
    </div>
    
    <!-- Layout 3 colonnes -->
    <div class="game-layout">
        <!-- COLONNE GAUCHE : Joueur + Adversaire -->
        <div class="left-column">
            <!-- Joueur -->
            <div class="player-circle">
                <img src="{{ $playerAvatarPath }}" alt="Avatar joueur" class="player-avatar">
                <div class="player-name">{{ $playerName }}</div>
                <div class="player-level">{{ __('Niveau') }} {{ $playerLevel }}</div>
                <div class="player-score" id="playerScore">{{ $params['score'] }}</div>
            </div>
            
            <!-- Adversaire -->
            <div class="opponent-circle">
                <!-- Avatar avec photo (boss ou élève) -->
                <img src="{{ $opponentAvatar }}" alt="Avatar {{ $opponentName }}" class="opponent-avatar">
                <div class="opponent-name">{{ $opponentName }}</div>
                @if(!empty($opponentDescription))
                    <div style="font-size: 0.8rem; text-align: center; opacity: 0.9; margin-top: 5px;">
                        {{ $opponentDescription }}
                    </div>
                @endif
                <div class="opponent-level">{{ __('Niveau') }} {{ $opponentLevel }}</div>
                <div class="opponent-score" id="opponentScore">{{ $opponentScore }}</div>
            </div>
        </div>
        
        <!-- COLONNE CENTRE : Chronomètre -->
        <div class="center-column">
            <div class="chrono-circle">
                <div class="chrono-time" id="chronoTimer">8</div>
            </div>
        </div>
        
        <!-- COLONNE DROITE : Avatar stratégique + Skills -->
        <div class="right-column">
            <!-- Avatar stratégique -->
            @if($currentAvatar !== 'Aucun' && $strategicAvatarPath)
                <div class="strategic-avatar-circle">
                    <img src="{{ $strategicAvatarPath }}" alt="Avatar stratégique" class="strategic-avatar-image">
                </div>
            @else
                <div class="strategic-avatar-circle empty"></div>
            @endif
            
            <!-- Cercles de skills (jusqu'à 4 pour Stratège + coéquipier) -->
            <div class="skills-container">
                @php
                    $maxSkills = min(count($skills), 4);
                @endphp
                @for($i = 0; $i < $maxSkills; $i++)
                    @if(isset($skills[$i]))
                        @php
                            $skill = $skills[$i];
                            $skillId = $skill['id'];
                            $skillTrigger = $skill['trigger'];
                            $isAuto = $skill['auto'];
                            $isUsed = $skill['used'];
                            
                            // Skills qui s'activent sur la page question (manuels seulement)
                            $isQuestionSkill = in_array($skillTrigger, ['question']);
                            
                            // Désactiver si déjà utilisé ou si c'est un skill passif/auto
                            $isDisabled = $isUsed || $isAuto;
                            
                            // Cas spécial: bonus_question disponible seulement après Q10
                            if ($skillId === 'bonus_question' && $params['current_question'] < 10) {
                                $isDisabled = true;
                            }
                            
                            // Style: skills passifs ont une apparence différente
                            $passiveClass = $isAuto ? 'passive' : '';
                            $disabledClass = $isDisabled ? 'disabled' : '';
                            $usedClass = $isUsed ? 'used' : '';
                            $activeClass = (!$isAuto && !$isUsed) ? 'active' : '';
                        @endphp
                        <div class="skill-circle {{ $activeClass }} {{ $passiveClass }} {{ $disabledClass }} {{ $usedClass }}" 
                             data-skill-id="{{ $skillId }}"
                             data-skill-index="{{ $i }}" 
                             data-skill-type="{{ $skill['type'] }}"
                             data-skill-trigger="{{ $skillTrigger }}"
                             data-skill-auto="{{ $isAuto ? 'true' : 'false' }}"
                             data-skill-used="{{ $isUsed ? 'true' : 'false' }}"
                             data-uses-left="{{ $skill['uses_left'] }}"
                             title="{{ $skill['name'] }}: {{ $skill['description'] }}">
                            {{ $skill['icon'] }}
                        </div>
                    @endif
                @endfor
                @for($j = $maxSkills; $j < 3; $j++)
                    <div class="skill-circle empty"></div>
                @endfor
            </div>
        </div>
    </div>
    
    <!-- Bouton Buzzer centré en bas - prêt dès le début -->
    <div class="buzz-container-bottom buzzer-ready" id="buzzContainer">
        <button id="buzzButton" class="buzz-button">
            <img src="{{ asset('images/buzzer.png') }}" alt="Strategy Buzzer">
        </button>
    </div>
</div>

<!-- Audio pour le buzzer (dynamique selon le choix utilisateur) -->
<audio id="buzzerSound" preload="auto">
    <source id="buzzerSource" src="{{ asset('sounds/buzzer_default_1.mp3') }}" type="audio/mpeg">
</audio>

<!-- Audio pour "sans buzzer" (fin du chrono) -->
<audio id="noBuzzSound" preload="auto">
    <source src="{{ asset('sounds/fin_chrono.mp3') }}" type="audio/mpeg">
</audio>

<!-- Audio de fond "grenouille" pendant le chrono -->
<audio id="chronoBackgroundSound" preload="auto">
    <source src="{{ asset('sounds/grenouille.mp3') }}" type="audio/mpeg">
</audio>

<!-- Musique d'ambiance du gameplay (joue en boucle pendant toute la question à -6 dB) -->
<audio id="gameplayAmbient" preload="auto" loop>
    <source src="{{ asset('sounds/gameplay_ambient.mp3') }}" type="audio/mpeg">
</audio>

<!-- Sons d'épée pour les skills de combat -->
<audio id="swordShieldSound" preload="auto">
    <source src="{{ asset('sounds/sword_shield.wav') }}" type="audio/wav">
</audio>
<audio id="swordSwishSound" preload="auto">
    <source src="{{ asset('sounds/sword_swish.wav') }}" type="audio/wav">
</audio>

<!-- Overlay bouclier pour l'effet défensif -->
<div id="shieldOverlay" class="shield-overlay" style="display: none;">
    <div class="shield-effect">
        <div class="shield-icon">🛡️</div>
        <div class="shield-glow"></div>
    </div>
</div>

<!-- Animation Bouclier Défenseur - Fullscreen Defense (quand on est attaqué) -->
<div id="shieldDefenseFlash" class="shield-defense-flash"></div>
<div id="shieldDefenseOverlay" class="shield-defense-overlay">
    <img id="shieldDefenseIcon" class="shield-defense-icon" src="{{ asset('images/shield_medieval.png') }}" alt="Shield">
</div>

<style>
.shield-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.shield-effect {
    position: relative;
    animation: shieldAppear 0.5s ease-out;
}

.shield-icon {
    font-size: 120px;
    filter: drop-shadow(0 0 30px rgba(78, 205, 196, 0.8));
    animation: shieldPulse 1s ease-in-out infinite;
}

.shield-glow {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(78, 205, 196, 0.4) 0%, transparent 70%);
    border-radius: 50%;
    animation: shieldGlowPulse 1.5s ease-in-out infinite;
}

/* Bouclier derrière les réponses */
.answers-shielded {
    position: relative;
}

.answers-shielded::before {
    content: '';
    position: absolute;
    top: -20px;
    left: -20px;
    right: -20px;
    bottom: -20px;
    background: radial-gradient(ellipse at center, rgba(78, 205, 196, 0.15) 0%, transparent 70%);
    border: 3px solid rgba(78, 205, 196, 0.4);
    border-radius: 20px;
    animation: shieldBorder 2s ease-in-out infinite;
    pointer-events: none;
    z-index: -1;
}

@keyframes shieldAppear {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}

@keyframes shieldPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes shieldGlowPulse {
    0%, 100% { opacity: 0.5; transform: translate(-50%, -50%) scale(1); }
    50% { opacity: 1; transform: translate(-50%, -50%) scale(1.3); }
}

@keyframes shieldBorder {
    0%, 100% { border-color: rgba(78, 205, 196, 0.4); box-shadow: 0 0 20px rgba(78, 205, 196, 0.2); }
    50% { border-color: rgba(78, 205, 196, 0.8); box-shadow: 0 0 40px rgba(78, 205, 196, 0.5); }
}

/* Animation d'attaque bloquée */
.shield-block-effect {
    animation: shieldBlock 0.5s ease-out;
}

@keyframes shieldBlock {
    0% { transform: scale(1); }
    25% { transform: scale(1.15) rotate(-5deg); }
    50% { transform: scale(1.1) rotate(5deg); }
    75% { transform: scale(1.05) rotate(-2deg); }
    100% { transform: scale(1) rotate(0deg); }
}

/* Animation Bouclier Défenseur - Fullscreen DERRIÈRE tous les éléments */
.shield-defense-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 5;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.shield-defense-icon {
    width: 120px;
    height: auto;
    opacity: 0;
    transform: scale(0.1) translateY(200vh);
    filter: drop-shadow(0 0 50px rgba(70, 130, 180, 1));
}

.shield-defense-icon.animate {
    animation: shieldDefenseRush 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

@keyframes shieldDefenseRush {
    0% {
        opacity: 0;
        transform: scale(0.1) translateY(200vh);
        filter: drop-shadow(0 0 20px rgba(70, 130, 180, 0.5));
    }
    20% {
        opacity: 1;
        transform: scale(2) translateY(0);
        filter: drop-shadow(0 0 40px rgba(70, 130, 180, 0.8));
    }
    40% {
        transform: scale(8) translateY(0);
        filter: drop-shadow(0 0 80px rgba(70, 130, 180, 1));
    }
    60% {
        transform: scale(15) translateY(0) rotate(-5deg);
        filter: drop-shadow(0 0 100px rgba(70, 130, 180, 1));
    }
    80% {
        opacity: 1;
        transform: scale(25) translateY(0) rotate(3deg);
        filter: drop-shadow(0 0 150px rgba(255, 255, 255, 0.8));
    }
    100% {
        opacity: 0;
        transform: scale(40) translateY(0);
        filter: drop-shadow(0 0 200px rgba(255, 255, 255, 0));
    }
}

/* Flash lumineux derrière le bouclier */
.shield-defense-flash {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 4;
    pointer-events: none;
    background: radial-gradient(circle at center, rgba(78, 205, 196, 0.3) 0%, transparent 70%);
    opacity: 0;
}

.shield-defense-flash.animate {
    animation: shieldFlash 1.2s ease-out forwards;
}

@keyframes shieldFlash {
    0% { opacity: 0; }
    30% { opacity: 0.6; }
    60% { opacity: 0.8; }
    100% { opacity: 0; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buzzButton = document.getElementById('buzzButton');
    const buzzerSound = document.getElementById('buzzerSound');
    const buzzerSource = document.getElementById('buzzerSource');
    const chronoTimer = document.getElementById('chronoTimer');
    let timeLeft = 8;
    let timerInterval;
    let buzzed = false;
    let buzzerDuration = 1500;
    let noBuzzDuration = 3500;
    let grenouilleStartDelay = 0; // Délai avant de démarrer grenouille
    
    // Charger le buzzer sélectionné depuis localStorage
    const selectedBuzzer = localStorage.getItem('selectedBuzzer') || 'buzzer_default_1';
    buzzerSource.src = `/sounds/${selectedBuzzer}.mp3`;
    buzzerSound.load();
    
    // Détecter la durée du son buzzer : délai de 100ms APRÈS la fin du son
    buzzerSound.addEventListener('loadedmetadata', function() {
        buzzerDuration = Math.floor(buzzerSound.duration * 1000) + 100;
    });
    
    // Détecter la durée du son no_buzz : délai de 100ms APRÈS la fin du son
    const noBuzzSound = document.getElementById('noBuzzSound');
    noBuzzSound.addEventListener('loadedmetadata', function() {
        noBuzzDuration = Math.floor(noBuzzSound.duration * 1000) + 100;
    });
    
    // Démarrer grenouille quand il reste 3 secondes au chrono (5 secondes après le début si timeLeft=8)
    const chronoBackgroundSound = document.getElementById('chronoBackgroundSound');
    // Calculer immédiatement le délai (5 secondes pour un chrono de 8s)
    grenouilleStartDelay = (timeLeft - 3) * 1000; // 5000ms si timeLeft = 8
    console.log(`Grenouille: démarre dans ${grenouilleStartDelay}ms (quand il reste 3s au chrono)`);
    
    chronoBackgroundSound.addEventListener('loadedmetadata', function() {
        const grenouilleLength = chronoBackgroundSound.duration; // durée en secondes
        console.log(`Grenouille: fichier chargé, durée ${grenouilleLength}s`);
    });
    
    // Vérifier si la musique de gameplay est activée (paramètre séparé de l'ambiance navigation)
    function isGameplayMusicEnabled() {
        const enabled = localStorage.getItem('gameplay_music_enabled');
        return enabled === null || enabled === 'true'; // Activé par défaut
    }
    
    // Démarrer la musique d'ambiance du gameplay à -6 dB (volume 0.5) SEULEMENT si activée
    const gameplayAmbient = document.getElementById('gameplayAmbient');
    gameplayAmbient.volume = 0.5; // -6 dB ≈ 50% de volume
    
    if (isGameplayMusicEnabled()) {
        // Restaurer la position depuis localStorage si disponible
        const savedTime = parseFloat(localStorage.getItem('gameplayMusicTime') || '0');
        gameplayAmbient.addEventListener('loadedmetadata', function() {
            if (savedTime > 0 && savedTime < gameplayAmbient.duration) {
                gameplayAmbient.currentTime = savedTime;
            }
            
            gameplayAmbient.play().catch(e => {
                console.log('Gameplay ambient music autoplay blocked:', e);
                // Si bloqué, jouer au premier clic
                document.addEventListener('click', function playGameplayMusic() {
                    gameplayAmbient.play().catch(err => console.log('Audio play failed:', err));
                    document.removeEventListener('click', playGameplayMusic);
                }, { once: true });
            });
        });
        
        // Sauvegarder la position toutes les secondes SEULEMENT si musique activée
        setInterval(() => {
            if (!gameplayAmbient.paused) {
                localStorage.setItem('gameplayMusicTime', gameplayAmbient.currentTime.toString());
            }
        }, 1000);
        
        // Sauvegarder avant de quitter la page
        window.addEventListener('beforeunload', () => {
            localStorage.setItem('gameplayMusicTime', gameplayAmbient.currentTime.toString());
        });
    } else {
        console.log('Musique de gameplay désactivée');
    }
    
    // Démarrer le chronomètre
    function startTimer() {
        // Activer le buzzer (le rendre cliquable)
        const buzzContainer = document.getElementById('buzzContainer');
        const buzzButton = document.getElementById('buzzButton');
        if (buzzContainer) {
            buzzContainer.classList.remove('buzzer-waiting', 'buzzer-hidden');
            buzzContainer.classList.add('buzzer-ready');
        }
        if (buzzButton) {
            buzzButton.disabled = false;
        }
        
        // Démarrer le son grenouille avec un délai pour qu'il se termine à la fin du chrono
        setTimeout(() => {
            if (!buzzed) { // Ne jouer que si pas déjà buzzé
                const chronoBackgroundSound = document.getElementById('chronoBackgroundSound');
                chronoBackgroundSound.currentTime = 0;
                chronoBackgroundSound.play().catch(e => console.log('Audio play failed:', e));
            }
        }, grenouilleStartDelay);
        
        timerInterval = setInterval(() => {
            timeLeft--;
            chronoTimer.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                const chronoBackgroundSound = document.getElementById('chronoBackgroundSound');
                chronoBackgroundSound.pause(); // Arrêter le son grenouille
                if (!buzzed) {
                    handleNoBuzz();
                }
            }
        }, 1000);
    }
    
    // Gestion du buzz
    buzzButton.addEventListener('click', function() {
        if (buzzed) return;
        
        buzzed = true;
        clearInterval(timerInterval);
        
        // Arrêter le son grenouille
        const chronoBackgroundSound = document.getElementById('chronoBackgroundSound');
        chronoBackgroundSound.pause();
        
        // Jouer le son buzzer
        buzzerSound.currentTime = 0;
        buzzerSound.play();
        
        // Désactiver le bouton et cacher le buzzer
        buzzButton.disabled = true;
        const buzzContainer = document.getElementById('buzzContainer');
        if (buzzContainer) {
            buzzContainer.classList.remove('buzzer-ready', 'buzzer-waiting');
            buzzContainer.classList.add('buzzer-hidden');
        }
        
        // Envoyer requête POST à /game/{mode}/buzz après le son
        setTimeout(async () => {
            const buzzTime = 8 - timeLeft;
            
            try {
                @php
                    // Solo utilise des routes HTTP, Duo/League utilisent Socket.IO
                    $buzzRoute = match($mode) {
                        'solo' => route('solo.buzz'),
                        default => '/game/' . $mode . '/buzz'
                    };
                    $answerRoute = match($mode) {
                        'solo' => route('solo.answer'),
                        'duo' => route('duo.answer', ['match' => $matchId ?? 0]),
                        default => '/game/' . $mode . '/answer'
                    };
                @endphp
                const response = await fetch('{{ $buzzRoute }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ buzz_time: buzzTime })
                });
                
                const data = await response.json();
                
                // Sync Firebase APRÈS confirmation serveur
                @if($isFirebaseMode)
                if (data.success && typeof FirebaseGameSync !== 'undefined' && FirebaseGameSync.isReady) {
                    await FirebaseGameSync.sendBuzz(buzzTime);
                }
                @endif
                
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = '{{ $answerRoute }}?buzz_time=' + buzzTime + '&buzz_winner=player';
                }
            } catch (error) {
                console.error('Buzz error:', error);
                if (window.customDialog) window.customDialog.alert('{{ __("Erreur lors du buzz. Veuillez réessayer.") }}');
                buzzed = false;
                buzzButton.disabled = false;
                const buzzContainer = document.getElementById('buzzContainer');
                if (buzzContainer) {
                    buzzContainer.classList.remove('buzzer-hidden', 'buzzer-waiting');
                    buzzContainer.classList.add('buzzer-ready');
                }
            }
        }, buzzerDuration);
    });
    
    // Pas de buzz - redirection vers la page timeout
    function handleNoBuzz() {
        // Jouer le son "sans buzzer"
        const noBuzzSound = document.getElementById('noBuzzSound');
        noBuzzSound.currentTime = 0;
        noBuzzSound.play().catch(e => console.log('Audio play failed:', e));
        
        // Rediriger après que le son se soit joué complètement
        // Note: La page Timeout gère automatiquement le skill Plume si disponible
        @php
            $timeoutRoute = match($mode) {
                'solo' => route('solo.timeout') . '?no_buzz=1',
                'duo' => route('duo.answer', ['match' => $matchId ?? 0]) . '?no_buzz=1',
                default => '/game/' . $mode . '/timeout?no_buzz=1'
            };
        @endphp
        setTimeout(() => {
            window.location.href = '{{ $timeoutRoute }}';
        }, noBuzzDuration);
    }
    
    // Démarrer le jeu
    startTimer();
    
    // Gestion des skills (click sur les cercles actifs)
    document.querySelectorAll('.skill-circle.active').forEach(skill => {
        skill.addEventListener('click', function() {
            const skillId = this.getAttribute('data-skill-id');
            const skillTrigger = this.getAttribute('data-skill-trigger');
            activateSkill(skillId, skillTrigger, this);
        });
    });
    
    function activateSkill(skillId, skillTrigger, skillElement) {
        if (!skillElement || !skillId) {
            console.log('Skill element or ID not found');
            return;
        }
        
        // Vérifier si le skill est désactivé ou déjà utilisé
        if (skillElement.classList.contains('disabled') || skillElement.classList.contains('used')) {
            const usesLeft = skillElement.getAttribute('data-uses-left');
            if (usesLeft === '0') {
                showSkillMessage('⚪ Skill déjà utilisé', 'error');
            }
            return;
        }
        
        // Skills Historien: sur la page Question, afficher seulement le titre (pas de consommation)
        if (skillId === 'answer_without_buzz') {
            showSkillMessage('🪶 {{ __("Savoir sans temps") }}', 'info', 2000);
            return;
        }
        if (skillId === 'history_corrects') {
            showSkillMessage('📜 {{ __("L\'histoire corrige") }}', 'info', 2000);
            return;
        }
        
        // Skills qui redirigent vers une autre page
        if (skillId === 'bonus_question') {
            const bonusRoute = '{{ $mode }}' === 'solo' 
                ? '/solo/bonus-question' 
                : '/game/{{ $mode }}/question?bonus=1';
            window.location.href = bonusRoute;
            return;
        }
        
        // Appeler l'API pour activer le skill
        const skillRoute = '{{ $mode }}' === 'solo' 
            ? '/solo/use-skill' 
            : '/game/{{ $mode }}/use-skill';
        
        fetch(skillRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ skill_id: skillId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                handleSkillEffect(data.result, skillElement);
                // Griser le skill
                skillElement.classList.add('used', 'disabled');
                skillElement.textContent = '⚪';
            } else {
                showSkillMessage(data.error || 'Erreur', 'error');
            }
        })
        .catch(error => {
            console.error('Skill error:', error);
            showSkillMessage('Erreur de connexion', 'error');
        });
    }
    
    function handleSkillEffect(result, skillElement) {
        const answers = document.querySelectorAll('.answer-option, .answer-btn');
        
        switch(result.effect) {
            case 'highlight':
                // Mathématicien: Illuminer la bonne réponse
                if (result.illuminate_index >= 0 && answers[result.illuminate_index]) {
                    answers[result.illuminate_index].classList.add('skill-highlight');
                    answers[result.illuminate_index].style.boxShadow = '0 0 20px #FFD700, 0 0 40px #FFD700';
                    showSkillMessage('🔢 Réponse avec chiffre illuminée!', 'success');
                } else {
                    showSkillMessage('🔢 Aucun chiffre dans la bonne réponse', 'warning');
                }
                break;
                
            case 'acidify':
                // Scientifique: Acidifier une mauvaise réponse
                if (result.acidify_index >= 0 && answers[result.acidify_index]) {
                    answers[result.acidify_index].classList.add('skill-acidified');
                    answers[result.acidify_index].style.background = 'linear-gradient(135deg, #8B0000, #FF4500)';
                    answers[result.acidify_index].style.opacity = '0.7';
                    showSkillMessage('⚗️ Mauvaise réponse acidifiée!', 'success');
                }
                break;
                
            case 'popular':
                // Explorateur: Montrer la réponse populaire
                if (result.popular_index >= 0 && answers[result.popular_index]) {
                    answers[result.popular_index].classList.add('skill-popular');
                    answers[result.popular_index].style.border = '3px solid #00CED1';
                    showSkillMessage('🧭 Réponse la plus choisie par l\'adversaire', 'info');
                }
                break;
                
            case 'hint':
                // Historien: Afficher l'indice
                showSkillMessage('🪶 ' + result.hint, 'info', 5000);
                break;
                
            case 'time_bonus':
                // Ajouter du temps au chrono
                const extraSeconds = result.extra_seconds || 2;
                timeLeft += extraSeconds;
                document.getElementById('chronoTimer').textContent = timeLeft;
                showSkillMessage('⏰ +' + extraSeconds + ' secondes!', 'success');
                break;
                
            case 'ai_suggest':
                // IA Junior: Suggestion (80% correct)
                if (result.suggestion_index >= 0 && answers[result.suggestion_index]) {
                    answers[result.suggestion_index].classList.add('skill-ai-suggest');
                    answers[result.suggestion_index].style.boxShadow = '0 0 25px #00FF00, 0 0 50px #00FF00';
                    showSkillMessage('💡 Suggestion IA (80% fiable)', 'info');
                }
                break;
                
            case 'eliminate':
                // IA Junior: Éliminer 2 réponses
                if (result.eliminated_indices && result.eliminated_indices.length > 0) {
                    result.eliminated_indices.forEach(idx => {
                        if (answers[idx]) {
                            answers[idx].classList.add('skill-eliminated');
                            answers[idx].style.opacity = '0.3';
                            answers[idx].style.pointerEvents = 'none';
                            answers[idx].style.textDecoration = 'line-through';
                        }
                    });
                    showSkillMessage('❌ 2 mauvaises réponses éliminées!', 'success');
                }
                break;
                
            case 'preview':
                // Visionnaire: Afficher les questions futures
                if (result.preview && result.preview.length > 0) {
                    let previewHtml = '<div class="skill-preview-modal">';
                    previewHtml += '<h3>👁️ Questions à venir</h3>';
                    result.preview.forEach((q, i) => {
                        previewHtml += '<div class="preview-item">' + (i+1) + '. ' + q.text.substring(0, 80) + '...</div>';
                    });
                    previewHtml += '</div>';
                    showSkillModal(previewHtml);
                }
                break;
                
            case 'lock_correct':
                // Visionnaire: Verrouiller sur la bonne réponse
                if (result.lock_index >= 0) {
                    answers.forEach((ans, idx) => {
                        if (idx !== result.lock_index) {
                            ans.style.opacity = '0.3';
                            ans.style.pointerEvents = 'none';
                        } else {
                            ans.style.boxShadow = '0 0 30px #00FF00';
                        }
                    });
                    showSkillMessage('🎯 Seule la bonne réponse est cliquable!', 'success');
                } else {
                    showSkillMessage('🎯 ' + result.message, 'warning');
                }
                break;
                
            case 'shield_ready':
                showSkillMessage('🛡️ Bouclier activé!', 'success');
                break;
                
            case 'passive_active':
                // Skills passifs - juste afficher un message
                showSkillMessage('✨ ' + (result.message || 'Skill passif actif'), 'info');
                break;
                
            case 'team_mode':
                // Stratège: Afficher le modal de sélection d'avatar rare
                if (result.available_avatars && result.available_avatars.length > 0) {
                    let teamHtml = '<div class="skill-team-modal">';
                    teamHtml += '<h3>🤝 Mode Équipe</h3>';
                    teamHtml += '<p>Choisissez un Avatar rare comme coéquipier :</p>';
                    result.available_avatars.forEach(avatar => {
                        teamHtml += '<div class="team-avatar-option" data-avatar="' + avatar.name + '">';
                        teamHtml += '<span class="avatar-icon">' + avatar.icon + '</span>';
                        teamHtml += '<span class="avatar-name">' + avatar.name + '</span>';
                        if (avatar.skills && avatar.skills.length > 0) {
                            teamHtml += '<span class="avatar-skill">' + avatar.skills[0].icon + ' ' + avatar.skills[0].name + '</span>';
                        }
                        teamHtml += '</div>';
                    });
                    teamHtml += '</div>';
                    showSkillModal(teamHtml);
                } else {
                    showSkillMessage('🤝 Aucun avatar rare disponible', 'warning');
                }
                break;
                
            case 'buzz_rewind':
                // Sprinteur: Recul du temps de buzz
                showSkillMessage('⏱️ Temps de buzz recalé à 0.5s du plus rapide!', 'success');
                break;
                
            case 'cancel_error':
                // Magicienne: Annulation d'erreur activée
                showSkillMessage('✨ Annulation d\'erreur prête!', 'success');
                break;
                
            case 'redirect':
                // Skills qui nécessitent une redirection
                if (result.redirect_to) {
                    window.location.href = result.redirect_to;
                }
                break;
                
            case 'no_question':
                showSkillMessage('⚠️ ' + (result.message || 'Question non disponible'), 'error');
                break;
                
            case 'fake_score':
                // Comédien: Score trompeur affiché
                showSkillMessage('🎭 ' + result.message, 'success');
                // Optionnel: mettre à jour l'affichage du score visuellement
                if (result.fake_score !== undefined) {
                    const playerScoreEl = document.querySelector('.player-score');
                    if (playerScoreEl) {
                        playerScoreEl.setAttribute('data-real-score', result.real_score);
                        playerScoreEl.textContent = result.fake_score;
                        playerScoreEl.classList.add('fake-score-active');
                    }
                }
                break;
                
            case 'replay_answer':
                // IA Junior: Possibilité de rejouer
                showSkillMessage('🔁 ' + result.message, 'success');
                // Activer le bouton de replay si présent
                const replayBtn = document.querySelector('.replay-button');
                if (replayBtn) {
                    replayBtn.style.display = 'block';
                    replayBtn.classList.add('replay-available');
                }
                break;
                
            case 'shuffle':
                // Challenger: Les réponses se déplacent toutes les secondes
                if (document.body.classList.contains('shuffle-immunity-active')) {
                    // Attaque bloquée par le bouclier - juste l'effet visuel et sonore, pas de message
                    playShieldBlockEffect();
                    break;
                }
                // Attaque réussie - son d'épée dans le vent
                showSkillMessage('🔀 {{ __("Réponses en mouvement!") }}', 'warning');
                playSwordSwishSound();
                startAnswerShuffle(result.interval || 1000);
                break;
                
            case 'counter_challenger':
                // Visionnaire: Immunité contre le Challenger - Bouclier activé
                showSkillMessage('🏰 ' + result.message, 'success');
                // Marquer l'immunité active
                document.body.classList.add('shuffle-immunity-active');
                // Afficher le bouclier derrière les réponses
                activateShieldBehindAnswers();
                break;
                
            default:
                console.log('Unknown skill effect:', result.effect);
                if (result.message) {
                    showSkillMessage(result.message, 'info');
                }
        }
    }
    
    function showSkillMessage(message, type, duration = 3000) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'skill-message skill-message-' + type;
        msgDiv.innerHTML = message;
        msgDiv.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 10px; font-weight: bold; z-index: 9999; animation: fadeInOut ' + (duration/1000) + 's ease-in-out;';
        
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
    
    function showSkillModal(html) {
        const modal = document.createElement('div');
        modal.className = 'skill-modal-overlay';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 9999;';
        
        const content = document.createElement('div');
        content.style.cssText = 'background: #1a1a2e; padding: 30px; border-radius: 20px; max-width: 500px; color: white;';
        content.innerHTML = html + '<button onclick="this.parentElement.parentElement.remove()" style="margin-top: 20px; padding: 10px 30px; border: none; border-radius: 10px; background: #4ECDC4; color: white; cursor: pointer;">Fermer</button>';
        
        modal.appendChild(content);
        document.body.appendChild(modal);
    }
    
    // ===== FONCTIONS SONS ET EFFETS DE COMBAT =====
    
    // Jouer le son d'épée qui frappe le bouclier (attaque bloquée)
    function playShieldBlockEffect() {
        const swordShieldSound = document.getElementById('swordShieldSound');
        if (swordShieldSound) {
            swordShieldSound.currentTime = 0;
            swordShieldSound.volume = 0.7;
            swordShieldSound.play().catch(e => console.log('Shield sound failed:', e));
        }
        
        // Afficher l'overlay du bouclier avec effet de blocage
        const shieldOverlay = document.getElementById('shieldOverlay');
        if (shieldOverlay) {
            shieldOverlay.style.display = 'flex';
            const shieldEffect = shieldOverlay.querySelector('.shield-effect');
            if (shieldEffect) {
                shieldEffect.classList.add('shield-block-effect');
            }
            
            // Masquer après 1.5 secondes
            setTimeout(() => {
                shieldOverlay.style.display = 'none';
                if (shieldEffect) {
                    shieldEffect.classList.remove('shield-block-effect');
                }
            }, 1500);
        }
    }
    
    // Jouer le son d'épée dans le vent (attaque réussie)
    function playSwordSwishSound() {
        const swordSwishSound = document.getElementById('swordSwishSound');
        if (swordSwishSound) {
            swordSwishSound.currentTime = 0;
            swordSwishSound.volume = 0.6;
            swordSwishSound.play().catch(e => console.log('Swish sound failed:', e));
        }
    }
    
    // Activer le bouclier visuel derrière les réponses
    function activateShieldBehindAnswers() {
        const answersContainer = document.querySelector('.answers-grid, .answers-container, .game-answers');
        if (answersContainer) {
            answersContainer.classList.add('answers-shielded');
        }
        
        // Afficher brièvement l'overlay du bouclier
        const shieldOverlay = document.getElementById('shieldOverlay');
        if (shieldOverlay) {
            shieldOverlay.style.display = 'flex';
            setTimeout(() => {
                shieldOverlay.style.display = 'none';
            }, 2000);
        }
    }
    
    // Animation Bouclier Défenseur - Fullscreen (quand on est attaqué et le bouclier bloque)
    window.triggerShieldDefense = function() {
        const flash = document.getElementById('shieldDefenseFlash');
        const overlay = document.getElementById('shieldDefenseOverlay');
        const icon = document.getElementById('shieldDefenseIcon');
        const sound = document.getElementById('swordShieldSound');
        
        if (!flash || !overlay || !icon) {
            console.log('Shield defense elements not found');
            return;
        }
        
        // Jouer le son épée/bouclier - VOLUME MAXIMUM
        if (sound) {
            sound.currentTime = 0;
            sound.volume = 1.0;
            sound.play().catch(e => console.log('Shield sound failed:', e));
        }
        
        // Déclencher les animations
        flash.classList.add('animate');
        icon.classList.add('animate');
        
        // Nettoyer après l'animation (1.2s)
        setTimeout(() => {
            flash.classList.remove('animate');
            icon.classList.remove('animate');
        }, 1300);
    };
    
    // ===== SHUFFLE DES RÉPONSES =====
    
    // Shuffle des réponses (Challenger skill)
    let shuffleInterval = null;
    
    function startAnswerShuffle(interval) {
        // Arrêter tout shuffle précédent
        if (shuffleInterval) {
            clearInterval(shuffleInterval);
        }
        
        const answersContainer = document.querySelector('.answers-grid, .answers-container, .game-answers');
        if (!answersContainer) {
            console.log('No answers container found for shuffle');
            return;
        }
        
        // Ajouter une classe pour l'effet visuel
        answersContainer.classList.add('shuffle-active');
        
        // Démarrer le shuffle toutes les X ms
        shuffleInterval = setInterval(() => {
            if (document.body.classList.contains('shuffle-immunity-active')) {
                stopAnswerShuffle();
                return;
            }
            shuffleAnswerPositions(answersContainer);
        }, interval);
        
        // Arrêter le shuffle après 8 secondes max (ou quand la question est répondue)
        setTimeout(() => {
            stopAnswerShuffle();
        }, 8000);
    }
    
    function shuffleAnswerPositions(container) {
        const answers = Array.from(container.querySelectorAll('.answer-btn, .answer-option, [data-answer-index]'));
        if (answers.length < 2) return;
        
        // Choisir 2 réponses aléatoires à échanger
        const idx1 = Math.floor(Math.random() * answers.length);
        let idx2 = Math.floor(Math.random() * answers.length);
        while (idx2 === idx1) {
            idx2 = Math.floor(Math.random() * answers.length);
        }
        
        const answer1 = answers[idx1];
        const answer2 = answers[idx2];
        
        // Animation de déplacement
        answer1.style.transition = 'transform 0.3s ease-in-out, opacity 0.15s';
        answer2.style.transition = 'transform 0.3s ease-in-out, opacity 0.15s';
        
        // Flash d'avertissement
        answer1.style.opacity = '0.5';
        answer2.style.opacity = '0.5';
        answer1.style.transform = 'scale(0.95)';
        answer2.style.transform = 'scale(0.95)';
        
        setTimeout(() => {
            // Échanger les positions dans le DOM
            const parent = answer1.parentNode;
            const next1 = answer1.nextSibling;
            const next2 = answer2.nextSibling;
            
            if (next2 === answer1) {
                parent.insertBefore(answer1, answer2);
            } else if (next1 === answer2) {
                parent.insertBefore(answer2, answer1);
            } else {
                parent.insertBefore(answer2, next1);
                parent.insertBefore(answer1, next2);
            }
            
            // Restaurer l'apparence
            answer1.style.opacity = '1';
            answer2.style.opacity = '1';
            answer1.style.transform = 'scale(1)';
            answer2.style.transform = 'scale(1)';
        }, 150);
    }
    
    function stopAnswerShuffle() {
        if (shuffleInterval) {
            clearInterval(shuffleInterval);
            shuffleInterval = null;
        }
        const container = document.querySelector('.answers-grid, .answers-container, .game-answers');
        if (container) {
            container.classList.remove('shuffle-active');
        }
    }
});
</script>

@if($isFirebaseMode && $mode !== 'duo')
{{-- Firebase sync for non-Duo multiplayer modes (League, Master) --}}
<script src="/js/firebase-game-sync.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function() {
    const isFirebaseMode = {{ $isFirebaseMode ? 'true' : 'false' }};
    const matchId = '{{ $matchId ?? $roomCode ?? "" }}';
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
                    console.log('[QuestionPage] Firebase ready');
                    const statusEl = document.getElementById('firebaseStatus');
                    if (statusEl) {
                        statusEl.classList.remove('disconnected');
                        statusEl.classList.add('connected');
                        statusEl.textContent = 'En ligne';
                    }
                },
                onBuzz: (buzzWinnerRole, buzzTime, data, isOpponentBuzz) => {
                    console.log('[QuestionPage] Buzz detected:', buzzWinnerRole, buzzTime);
                    if (isOpponentBuzz) {
                        window.location.href = '{{ route("game.answers", ["mode" => $mode]) }}?buzz_time=' + buzzTime + '&buzz_winner=opponent';
                    }
                },
                onPhaseChange: (phase, data) => {
                    console.log('[QuestionPage] Phase changed to:', phase);
                    if (phase === 'answering' && data.buzzWinnerLaravelId !== laravelUserId) {
                        window.location.href = '{{ route("game.answers", ["mode" => $mode]) }}';
                    }
                }
            }
        });
    } catch (error) {
        console.error('[QuestionPage] Firebase init error:', error);
    }
});
</script>
@endif

@if($mode === 'duo')
{{-- Socket.IO integration for Duo mode --}}
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', async function() {
    const GAME_SERVER_URL = window.location.origin;
    const ROOM_ID = '{{ $matchId ?? "" }}';
    const LOBBY_CODE = '{{ $lobbyCode ?? "" }}';
    const JWT_TOKEN = '{{ $params["jwt_token"] ?? session("game_state.jwt_token") ?? "" }}';
    const PLAYER_ID = '{{ auth()->id() }}';
    const PLAYER_NAME = '{{ $playerName ?? "Joueur" }}';
    const IS_HOST = {{ ($params['is_host'] ?? false) ? 'true' : 'false' }};
    
    if (!ROOM_ID && !LOBBY_CODE) {
        console.warn('[Duo] Missing room and lobby code');
        return;
    }
    
    // Reference to buzz button
    const buzzButton = document.getElementById('buzzButton');
    
    async function initializeSocket() {
        // DuoSocketClient is a singleton object, not a class
        DuoSocketClient.onConnect = () => {
            console.log('[Duo] Socket connected');
            DuoSocketClient.joinRoom(ROOM_ID, LOBBY_CODE, {
                playerId: PLAYER_ID,
                playerName: PLAYER_NAME,
                isHost: IS_HOST
            });
        };
        
        DuoSocketClient.onDisconnect = (reason) => {
            console.log('[Duo] Disconnected:', reason);
        };
        
        DuoSocketClient.onError = (error) => {
            console.error('[Duo] Error:', error);
        };
        
        DuoSocketClient.onBuzzWinner = (data) => {
            console.log('[Duo] Buzz winner:', data);
            const isMyBuzz = String(data.playerId) === String(PLAYER_ID);
            window.location.href = '{{ route("game.answers", ["mode" => "duo"]) }}?buzz_time=' + data.buzzTime + '&buzz_winner=' + (isMyBuzz ? 'player' : 'opponent');
        };
        
        DuoSocketClient.onBuzzResult = DuoSocketClient.onBuzzWinner;
        
        DuoSocketClient.onPhaseChanged = (data) => {
            console.log('[Duo] Phase changed:', data);
            const phase = data?.phase || data;
            if (phase === 'answering' || phase === 'ANSWER_SELECTION') {
                window.location.href = '{{ route("game.answers", ["mode" => "duo"]) }}';
            } else if (phase === 'REVEAL' || phase === 'reveal') {
                // Handle reveal phase
            } else if (phase === 'ROUND_SCOREBOARD' || phase === 'round_scoreboard') {
                window.location.href = '{{ route("duo.result") }}';
            } else if (phase === 'MATCH_END' || phase === 'match_end') {
                window.location.href = '{{ route("duo.summary") }}';
            }
        };
        
        DuoSocketClient.onScoreUpdate = (scores) => {
            console.log('[Duo] Score update:', scores);
            const myScore = scores[PLAYER_ID] ?? 0;
            const opponentScore = Object.entries(scores).find(([id]) => String(id) !== String(PLAYER_ID))?.[1] ?? 0;
            const playerScoreEl = document.getElementById('playerScore');
            const opponentScoreEl = document.getElementById('opponentScore');
            if (playerScoreEl) playerScoreEl.textContent = myScore;
            if (opponentScoreEl) opponentScoreEl.textContent = opponentScore;
        };
        
        DuoSocketClient.onGameState = (state) => {
            console.log('[Duo] Game state:', state);
        };
        
        DuoSocketClient.onQuestionPublished = (questionData) => {
            console.log('[Duo] Question published:', questionData);
        };
        
        DuoSocketClient.onAnswerRevealed = (revealData) => {
            console.log('[Duo] Answer revealed:', revealData);
        };
        
        DuoSocketClient.onSkillUsed = (skillData) => {
            console.log('[Duo] Skill used:', skillData);
        };
        
        DuoSocketClient.onMatchEnded = (matchData) => {
            console.log('[Duo] Match ended:', matchData);
            window.location.href = '{{ route("duo.summary") }}';
        };
        
        try {
            await DuoSocketClient.connect(GAME_SERVER_URL, JWT_TOKEN);
        } catch (error) {
            console.error('[Duo] Connection error:', error);
        }
    }
    
    // Override buzz button for Socket.IO
    if (buzzButton) {
        buzzButton.addEventListener('click', function(e) {
            e.stopImmediatePropagation();
            if (DuoSocketClient.isConnected()) {
                DuoSocketClient.buzz(Date.now());
            }
        }, true);
    }
    
    initializeSocket();
    
    window.addEventListener('beforeunload', () => {
        if (DuoSocketClient.isConnected()) {
            DuoSocketClient.disconnect();
        }
    });
});
</script>
@endif

@if($isMultiplayer)
{{-- VoiceChat WebRTC - Firebase Signaling --}}
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

const app = initializeApp(firebaseConfig, 'voice-chat-game-question');
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
    const VOICE_LOBBY_CODE = '{{ $lobbyCode ?? "" }}';
    const CURRENT_PLAYER_ID = {{ auth()->id() ?? 0 }};
    const GAME_MODE = '{{ $mode ?? "duo" }}';

    const micButton = document.getElementById('voiceMicButton');
    const micIcon = document.getElementById('micIcon');

    function updateMicButtonState(active) {
        isMicActive = active;
        if (!micButton) return;
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

    async function toggleMicrophone() {
        if (!voiceChat) {
            console.log('[VoiceChat] Voice chat not initialized');
            return;
        }
        try {
            const newState = await voiceChat.toggleMicrophone();
            updateMicButtonState(newState);
            console.log('[VoiceChat] Mic toggled:', newState ? 'ON' : 'OFF');
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
                mode: GAME_MODE,
                db: window.voiceChatDb,
                onConnectionChange: (state) => {
                    console.log('[VoiceChat] State:', state);
                    if (state.muted !== undefined) {
                        updateMicButtonState(!state.muted);
                    }
                },
                onError: (error) => console.error('[VoiceChat] Error:', error)
            });

            await voiceChat.initialize();
            console.log('[VoiceChat] Initialized on game_question page');

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
})();
</script>
@endif

@endsection
