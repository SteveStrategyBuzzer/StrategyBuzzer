@extends('layouts.app')

@section('content')
@php
// Récupérer la structure complète des skills depuis le contrôleur
$avatarSkillsFull = $params['avatar_skills_full'] ?? ['rarity' => null, 'skills' => []];
$currentAvatar = $params['avatar'] ?? 'Aucun';

// Récupérer les skills utilisés
$usedSkills = session('used_skills', []);

// Construire le tableau des skills pour l'affichage
$skills = [];
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
        ];
    }
}

// Prénoms pour le joueur
$playerNames = ['Hugo', 'Léa', 'Lucas', 'Emma', 'Nathan', 'Chloé', 'Louis', 'Jade', 'Arthur', 'Inès', 'Raphaël', 'Camille', 'Gabriel', 'Zoé', 'Thomas', 'Alice'];
$playerName = $playerNames[array_rand($playerNames)];

// Avatar du joueur
$selectedAvatar = session('selected_avatar', 'default');
if (strpos($selectedAvatar, '/') !== false || strpos($selectedAvatar, 'images/') === 0) {
    $playerAvatarPath = asset($selectedAvatar);
} else {
    $playerAvatarPath = asset("images/avatars/standard/{$selectedAvatar}.png");
}

// Avatar stratégique - les avatars sont dans public/images/avatars/
$strategicAvatarPath = '';
if ($currentAvatar !== 'Aucun') {
    // Enlever les accents et normaliser
    $strategicAvatarSlug = strtolower($currentAvatar);
    $strategicAvatarSlug = str_replace(['é', 'è', 'ê'], 'e', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(['à', 'â'], 'a', $strategicAvatarSlug);
    $strategicAvatarSlug = str_replace(' ', '-', $strategicAvatarSlug);
    $strategicAvatarPath = asset("images/avatars/{$strategicAvatarSlug}.png");
}

// Info de l'adversaire - récupéré depuis les params
$niveau = $params['niveau'];
$opponentInfo = $params['opponent_info'] ?? [];
$opponentScore = $params['opponent_score'] ?? 0;

// Déterminer l'avatar et le nom de l'adversaire
if ($opponentInfo['is_boss'] ?? false) {
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
    body {
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
        margin: 0;
        overflow-x: hidden;
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
    }
    
    /* Classe 'disabled' pour logique JS uniquement - PAS de style grisé visuel */
    .skill-circle.disabled {
        /* Pas de style visuel - le bouton reste normal */
        /* La classe sert uniquement à déclencher le popup JS */
    }
    
    /* BOUTON BUZZER CENTRÉ EN BAS */
    .buzz-container-bottom {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 100;
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
            bottom: 20px;
        }
    }
</style>

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
                <div class="player-level">{{ __('Niveau') }} {{ $niveau }}</div>
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
                <div class="opponent-level">{{ __('Niveau') }} {{ $niveau }}</div>
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
            
            <!-- 3 cercles de skills -->
            <div class="skills-container">
                @for($i = 0; $i < 3; $i++)
                    @if(isset($skills[$i]))
                        @php
                            $skill = $skills[$i];
                            $skillId = $skill['id'];
                            $skillTrigger = $skill['trigger'];
                            $isAuto = $skill['auto'];
                            $isUsed = $skill['used'];
                            
                            // Skills qui s'activent sur la page question
                            $isQuestionSkill = in_array($skillTrigger, ['question']);
                            
                            // Désactiver si déjà utilisé ou si c'est un skill passif/auto
                            $isDisabled = $isUsed || $isAuto;
                            
                            // Cas spécial: bonus_question disponible seulement après Q10
                            if ($skillId === 'bonus_question' && $params['current_question'] < 10) {
                                $isDisabled = true;
                            }
                            
                            $disabledClass = $isDisabled ? 'disabled' : '';
                            $usedClass = $isUsed ? 'used' : '';
                        @endphp
                        <div class="skill-circle active {{ $disabledClass }} {{ $usedClass }}" 
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
                    @else
                        <div class="skill-circle empty"></div>
                    @endif
                @endfor
            </div>
        </div>
    </div>
    
    <!-- Bouton Buzzer centré en bas -->
    <div class="buzz-container-bottom">
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
        
        // Désactiver le bouton
        buzzButton.disabled = true;
        buzzButton.style.opacity = '0.5';
        
        // Envoyer requête POST à /solo/buzz après le son
        setTimeout(() => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('solo.buzz') }}';
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }, buzzerDuration);
    });
    
    // Pas de buzz - redirection vers /solo/timeout
    function handleNoBuzz() {
        // Jouer le son "sans buzzer"
        const noBuzzSound = document.getElementById('noBuzzSound');
        noBuzzSound.currentTime = 0;
        noBuzzSound.play().catch(e => console.log('Audio play failed:', e));
        
        // Rediriger après que le son se soit joué complètement
        setTimeout(() => {
            window.location.href = '{{ route('solo.timeout') }}';
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
        
        // Skills qui redirigent vers une autre page
        if (skillId === 'bonus_question') {
            window.location.href = '{{ route('solo.bonus-question') }}';
            return;
        }
        
        // Appeler l'API pour activer le skill
        fetch('{{ route('solo.use-skill') }}', {
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
                
            default:
                console.log('Unknown skill effect:', result.effect);
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
});
</script>

@endsection
