@extends('layouts.app')

@section('content')
@php
// Mapping des skills pour chaque avatar stratégique
$avatarSkills = [
    'Mathématicien' => [['icon' => '🔢', 'name' => 'Calcul Rapide']],
    'Scientifique' => [['icon' => '⚗️', 'name' => 'Analyse']],
    'Explorateur' => [['icon' => '🧭', 'name' => 'Navigation']],
    'Défenseur' => [['icon' => '🛡️', 'name' => 'Protection']],
    'Comédienne' => [['icon' => '🎯', 'name' => 'Précision'], ['icon' => '🌀', 'name' => 'Confusion']],
    'Magicienne' => [['icon' => '✨', 'name' => 'Magie'], ['icon' => '💫', 'name' => 'Étoile']],
    'Challenger' => [['icon' => '🔄', 'name' => 'Rotation'], ['icon' => '⏳', 'name' => 'Temps']],
    'Historien' => [['icon' => '🪶', 'name' => 'Histoire'], ['icon' => '⏰', 'name' => 'Chrono']],
    'IA Junior' => [['icon' => '💡', 'name' => 'Idée'], ['icon' => '❌', 'name' => 'Annulation'], ['icon' => '🔁', 'name' => 'Répétition']],
    'Stratège' => [['icon' => '🧠', 'name' => 'Intelligence'], ['icon' => '🤝', 'name' => 'Alliance'], ['icon' => '💰', 'name' => 'Richesse']],
    'Sprinteur' => [['icon' => '⏱️', 'name' => 'Sprint'], ['icon' => '🕒', 'name' => 'Heure'], ['icon' => '🔋', 'name' => 'Énergie']],
    'Visionnaire' => [['icon' => '👁️', 'name' => 'Vision'], ['icon' => '🏰', 'name' => 'Château'], ['icon' => '🎯', 'name' => 'Cible']],
];

// Prénoms pour le joueur
$playerNames = ['Hugo', 'Léa', 'Lucas', 'Emma', 'Nathan', 'Chloé', 'Louis', 'Jade', 'Arthur', 'Inès', 'Raphaël', 'Camille', 'Gabriel', 'Zoé', 'Thomas', 'Alice'];
$playerName = $playerNames[array_rand($playerNames)];

// Récupérer les noms d'adversaires depuis la configuration
$opponents = config('opponents');
$regularOpponents = $opponents['regular_opponents'] ?? [];
$bossOpponents = $opponents['boss_opponents'] ?? [];

$currentAvatar = $params['avatar'] ?? 'Aucun';
$skills = $currentAvatar !== 'Aucun' ? ($avatarSkills[$currentAvatar] ?? []) : [];

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

// Info de l'adversaire
$niveau = $params['niveau'];
$bossInfo = (new App\Http\Controllers\SoloController())->getBossForLevel($niveau);
$opponentScore = $params['current_question'] - 1 - $params['score'];

if ($bossInfo) {
    $opponentName = $bossInfo['name'];
    $opponentAvatar = asset("images/avatars/boss/{$bossInfo['slug']}.png");
} else {
    // Utiliser le nom depuis la configuration selon le niveau
    $opponentName = $regularOpponents[$niveau] ?? 'Adversaire';
    $opponentAvatar = asset("images/avatars/opponent/default.png");
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
        max-width: 1000px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    /* Question tout en haut */
    .question-header {
        background: rgba(78, 205, 196, 0.1);
        padding: 20px;
        border-radius: 20px;
        text-align: center;
        border: 2px solid rgba(78, 205, 196, 0.3);
        margin-bottom: 20px;
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
    
    /* Section centrale avec chronomètre et avatars */
    .chrono-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px;
        margin: 20px 0;
    }
    
    .chrono-row {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px;
    }
    
    .buzz-container-inline {
        text-align: center;
    }
    
    
    .player-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    
    .player-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 3px solid #4ECDC4;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.5);
        object-fit: cover;
    }
    
    .player-score-display {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
    }
    
    .opponent-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    
    .opponent-info {
        font-size: 0.85rem;
        color: #FF6B6B;
        font-weight: 600;
        text-align: center;
    }
    
    /* Chronomètre central */
    .chrono-container {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .chrono-circle {
        width: 200px;
        height: 200px;
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
        font-size: 4.5rem;
        font-weight: 900;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 20px rgba(0,0,0,0.5);
    }
    
    .chrono-label {
        font-size: 0.85rem;
        opacity: 0.9;
        text-align: center;
        color: #4ECDC4;
    }
    
    .chrono-warning .chrono-circle {
        background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
        animation: danger-pulse 0.5s infinite !important;
    }
    
    @keyframes danger-pulse {
        0%, 100% { 
            transform: scale(1);
            box-shadow: 0 15px 50px rgba(255, 107, 107, 0.8);
        }
        50% { 
            transform: scale(1.08);
            box-shadow: 0 20px 70px rgba(255, 107, 107, 1);
        }
    }
    
    .opponent-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        border: 3px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        object-fit: cover;
    }
    
    .opponent-score-display {
        font-size: 1.8rem;
        font-weight: 900;
        color: #FF6B6B;
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.8);
    }
    
    /* Colonne droite : avatar stratégique + skills */
    .right-column {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        justify-content: flex-start;
    }
    
    .strategic-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    
    .strategic-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 3px solid #FFD700;
        box-shadow: 0 8px 30px rgba(255, 215, 0, 0.5);
        object-fit: cover;
    }
    
    .strategic-placeholder {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 3px dashed rgba(255,255,255,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        opacity: 0.5;
    }
    
    /* Skills icônes seulement */
    .skills-icons {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: center;
    }
    
    .skill-icon-circle {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: 2px solid #FFD700;
        color: white;
        font-size: 1.3rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .skill-icon-circle:hover {
        transform: translateY(-3px) scale(1.1);
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
    }
    
    .skill-icon-circle:active {
        transform: translateY(0) scale(0.95);
    }
    
    /* Buzzer avec image */
    .buzz-container {
        text-align: center;
        margin-top: 20px;
    }
    
    .buzz-button {
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        transition: all 0.2s ease;
        display: block;
        margin: 0 auto;
    }
    
    .buzz-button img {
        width: 200px;
        height: 200px;
        display: block;
        transition: all 0.2s ease;
        filter: drop-shadow(0 20px 40px rgba(230, 57, 70, 0.6));
    }
    
    .buzz-button:hover:not(:disabled) img {
        transform: translateY(-8px) scale(1.05);
        filter: drop-shadow(0 25px 50px rgba(230, 57, 70, 0.8));
    }
    
    .buzz-button:active:not(:disabled) img {
        transform: translateY(4px) scale(0.95);
        filter: drop-shadow(0 10px 20px rgba(230, 57, 70, 0.6));
    }
    
    .buzz-button:disabled img {
        opacity: 0.5;
        cursor: not-allowed;
        filter: grayscale(100%);
    }
    
    /* Zone desktop cachée par défaut (visible uniquement en desktop/paysage) */
    .strategic-desktop-zone {
        display: none;
    }
    
    /* DESKTOP / PAYSAGE : Disposition en grille selon schéma */
    @media (min-width: 768px) and (orientation: landscape), 
           (min-width: 1024px) {
        .game-container {
            max-width: 1400px;
            display: grid;
            grid-template-columns: 1fr 300px;
            grid-template-rows: auto 1fr;
            gap: 20px;
            align-items: start;
        }
        
        /* Question en haut à gauche */
        .question-header {
            grid-column: 1;
            grid-row: 1;
            margin-bottom: 0;
        }
        
        /* Avatar stratégique + skills en haut à droite */
        .strategic-desktop-zone {
            grid-column: 2;
            grid-row: 1 / 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: rgba(78, 205, 196, 0.05);
            border-radius: 20px;
            border: 2px solid rgba(78, 205, 196, 0.2);
        }
        
        .strategic-desktop-zone .strategic-section {
            margin-bottom: 10px;
        }
        
        .strategic-desktop-zone .skills-icons {
            margin-top: 10px;
        }
        
        /* Section basse : joueur + adversaire + chrono + buzzer en ligne horizontale */
        .chrono-section {
            grid-column: 1;
            grid-row: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .chrono-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-around;
            gap: 40px;
            width: 100%;
        }
        
        .right-column {
            display: none; /* Caché en mode desktop car déplacé dans strategic-desktop-zone */
        }
        
        /* Buzzer dans la ligne horizontale */
        .buzz-container-inline {
            margin-top: 0;
            flex-shrink: 0;
        }
        
        .buzz-button img {
            width: 180px;
            height: 180px;
        }
        
        .chrono-circle {
            width: 160px;
            height: 160px;
        }
        
        .chrono-time {
            font-size: 3.5rem;
        }
        
        .player-avatar {
            width: 90px;
            height: 90px;
        }
        
        .opponent-avatar {
            width: 85px;
            height: 85px;
        }
        
        .strategic-avatar {
            width: 100px;
            height: 100px;
        }
        
        .skill-icon-circle {
            width: 50px;
            height: 50px;
            font-size: 1.4rem;
        }
    }
    
    /* Responsive Mobile */
    @media (max-width: 768px) {
        .chrono-section {
            gap: 20px;
        }
        
        .player-avatar {
            width: 60px;
            height: 60px;
        }
        
        .opponent-avatar {
            width: 55px;
            height: 55px;
        }
        
        .strategic-avatar {
            width: 70px;
            height: 70px;
        }
        
        .player-score-display, .opponent-score-display {
            font-size: 1.6rem;
        }
        
        .chrono-circle {
            width: 120px;
            height: 120px;
        }
        
        .chrono-time {
            font-size: 2.8rem;
        }
        
        .skill-icon-circle {
            width: 38px;
            height: 38px;
            font-size: 1.1rem;
        }
        
        .buzz-button img {
            width: 160px;
            height: 160px;
        }
    }
    
    @media (max-width: 480px) and (orientation: portrait) {
        .chrono-section {
            gap: 15px;
        }
        
        .question-text {
            font-size: 1.1rem;
        }
        
        .player-avatar, .opponent-avatar, .strategic-avatar {
            width: 60px;
            height: 60px;
        }
        
        .player-score-display, .opponent-score-display {
            font-size: 1.5rem;
        }
        
        .player-name, .opponent-name {
            font-size: 0.75rem;
        }
        
        .chrono-circle {
            width: 100px;
            height: 100px;
        }
        
        .chrono-time {
            font-size: 2.2rem;
        }
        
        .skill-button {
            width: 110px;
            padding: 7px 10px;
            font-size: 0.8rem;
        }
        
        .buzz-button img {
            width: 140px;
            height: 140px;
        }
    }
    
    @media (max-height: 600px) and (orientation: landscape) {
        .game-container {
            gap: 10px;
        }
        
        .question-header {
            padding: 12px;
        }
        
        .question-text {
            font-size: 1rem;
        }
        
        .chrono-section {
            gap: 20px;
            margin: 10px 0;
        }
        
        .player-avatar, .opponent-avatar, .strategic-avatar {
            width: 60px;
            height: 60px;
        }
        
        .player-score-display, .opponent-score-display {
            font-size: 1.4rem;
        }
        
        .player-name, .opponent-name {
            font-size: 0.7rem;
        }
        
        .chrono-circle {
            width: 90px;
            height: 90px;
        }
        
        .chrono-time {
            font-size: 2rem;
        }
        
        .buzz-button img {
            width: 120px;
            height: 120px;
        }
        
        .skill-icon-circle {
            width: 32px;
            height: 32px;
            font-size: 1rem;
        }
    }
</style>

<div class="game-container">
    <!-- Question TOUT EN HAUT -->
    <div class="question-header">
        <div class="question-number">
            Question {{ $params['current_question'] }} / {{ $params['total_questions'] }}
        </div>
        <div class="question-text">{{ $params['question']['text'] }}</div>
    </div>
    
    <!-- Zone Desktop/Paysage : Avatar stratégique + Skills (visible uniquement en desktop/paysage) -->
    <div class="strategic-desktop-zone">
        @if($currentAvatar !== 'Aucun')
            <div class="strategic-section">
                @if(!empty($strategicAvatarPath))
                    <img src="{{ $strategicAvatarPath }}" alt="{{ $currentAvatar }}" class="strategic-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                @else
                    <div class="strategic-placeholder">⚔️</div>
                @endif
                <div class="player-name" style="color: #FFD700; font-size: 0.9rem; margin-top: 10px;">{{ $currentAvatar }}</div>
            </div>
            
            @if(count($skills) > 0)
            <div class="skills-icons">
                @foreach($skills as $skill)
                    <div class="skill-icon-circle" onclick="activateSkill('{{ $skill['name'] }}')">
                        {{ $skill['icon'] }}
                    </div>
                @endforeach
            </div>
            @endif
        @else
            <!-- Pas d'avatar stratégique : afficher l'adversaire -->
            <div class="strategic-section">
                @if($bossInfo)
                    <img src="{{ $opponentAvatar }}" alt="{{ $opponentName }}" class="strategic-avatar" style="border-color: #FF6B6B;" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                    <div class="player-score-display" style="color: #FF6B6B; font-size: 1.8rem; margin-top: 10px;">{{ $opponentScore }}</div>
                    <div class="opponent-info" style="color: #FF6B6B; font-size: 0.9rem;">{{ $opponentName }} Niv {{ $niveau }}</div>
                @else
                    <div style="text-align: center;">
                        <div style="color: #FF6B6B; font-size: 1.2rem; font-weight: 700; margin-bottom: 8px;">
                            {{ $opponentName }}<br>
                            <span style="font-size: 1rem; opacity: 0.9;">Niv {{ $niveau }}</span>
                        </div>
                        <div class="player-score-display" style="color: #FF6B6B;">{{ $opponentScore }}</div>
                    </div>
                @endif
            </div>
        @endif
    </div>
    
    <!-- Section centrale : Joueur | Adversaire | Chrono | Buzzer (alignés en mobile vertical, desktop horizontal) -->
    <div class="chrono-section">
        <div class="chrono-row">
            <!-- Joueur -->
            <div class="player-section">
                <img src="{{ $playerAvatarPath }}" alt="Player" class="player-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                <div class="player-score-display">{{ $params['score'] }}</div>
                <div class="player-name" style="color: #4ECDC4;">{{ $playerName }} Niv {{ $niveau }}</div>
            </div>
            
            <!-- Adversaire (toujours affiché) -->
            <div class="opponent-section">
                @if($bossInfo)
                    <!-- BOSS : Afficher avec cercle et image -->
                    <img src="{{ $opponentAvatar }}" alt="{{ $opponentName }}" class="opponent-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                    <div class="opponent-score-display">{{ $opponentScore }}</div>
                    <div class="opponent-info">{{ $opponentName }} Niv {{ $niveau }}</div>
                @else
                    <!-- ADVERSAIRE NORMAL : Juste prénom + niveau + score (SANS cercle) -->
                    <div style="text-align: center;">
                        <div style="color: #FF6B6B; font-size: 1.1rem; font-weight: 700; margin-bottom: 5px;">
                            {{ $opponentName }}<br>
                            <span style="font-size: 0.9rem; opacity: 0.9;">Niv {{ $niveau }}</span>
                        </div>
                        <div class="opponent-score-display">{{ $opponentScore }}</div>
                    </div>
                @endif
            </div>
            
            <!-- Chronomètre -->
            <div class="chrono-container" id="chronoContainer">
                <div class="chrono-circle">
                    <div class="chrono-time" id="chronoTime">{{ $params['chrono_time'] }}</div>
                </div>
                <div class="chrono-label">⏱️ Secondes</div>
            </div>
            
            <!-- Buzzer -->
            <div class="buzz-container-inline">
                <form id="buzzForm" method="POST" action="{{ route('solo.buzz') }}">
                    @csrf
                    <button type="button" id="buzzButton" class="buzz-button" onclick="handleBuzz()">
                        <img src="{{ asset('images/buzzer.png') }}" alt="Strategy Buzz Buzzer">
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Avatar stratégique + Skills (mobile uniquement, masqué en desktop) -->
        <div class="right-column">
            @if($currentAvatar !== 'Aucun')
                <div class="strategic-section">
                    @if(!empty($strategicAvatarPath))
                        <img src="{{ $strategicAvatarPath }}" alt="{{ $currentAvatar }}" class="strategic-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                    @else
                        <div class="strategic-placeholder">⚔️</div>
                    @endif
                    <div class="player-name" style="color: #FFD700; font-size: 0.8rem;">{{ $currentAvatar }}</div>
                </div>
                
                @if(count($skills) > 0)
                <div class="skills-icons">
                    @foreach($skills as $skill)
                        <div class="skill-icon-circle" onclick="activateSkill('{{ $skill['name'] }}')">
                            {{ $skill['icon'] }}
                        </div>
                    @endforeach
                </div>
                @endif
            @endif
        </div>
    </div>
</div>

<audio id="buzzSound" preload="auto">
    <source src="{{ asset('sounds/buzz.mp3') }}" type="audio/mpeg">
    <source src="{{ asset('sounds/buzz.wav') }}" type="audio/wav">
</audio>

<audio id="failSound" preload="auto">
    <source src="{{ asset('sounds/fail.mp3') }}" type="audio/mpeg">
    <source src="{{ asset('sounds/fail.wav') }}" type="audio/wav">
</audio>

<script>
let timeLeft = {{ $params['chrono_time'] }};
let chronoInterval;
let buzzed = false;
let buzzSoundDuration = 1000; // Durée par défaut en millisecondes

// Détecter la durée du son de buzzer automatiquement
const buzzSound = document.getElementById('buzzSound');
buzzSound.addEventListener('loadedmetadata', function() {
    // Convertir la durée en millisecondes et ajouter 100ms de marge
    buzzSoundDuration = Math.ceil(buzzSound.duration * 1000) + 100;
    console.log('Durée du buzzer détectée:', buzzSoundDuration + 'ms');
});

// Démarrer le chronomètre
chronoInterval = setInterval(() => {
    timeLeft--;
    document.getElementById('chronoTime').textContent = timeLeft;
    
    // Avertissement visuel à 3 secondes
    if (timeLeft <= 3 && timeLeft > 0) {
        document.getElementById('chronoContainer').classList.add('chrono-warning');
    }
    
    // Temps écoulé
    if (timeLeft <= 0) {
        clearInterval(chronoInterval);
        handleTimeout();
    }
}, 1000);

function handleBuzz() {
    if (buzzed) return;
    
    buzzed = true;
    clearInterval(chronoInterval);
    
    // Jouer le son de buzz
    buzzSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver le bouton
    document.getElementById('buzzButton').disabled = true;
    
    // Soumettre le formulaire après que le son soit complètement fini
    setTimeout(() => {
        document.getElementById('buzzForm').submit();
    }, buzzSoundDuration);
}

function handleTimeout() {
    if (buzzed) return;
    
    buzzed = true;
    
    // Jouer le son d'échec
    const failSound = document.getElementById('failSound');
    failSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver le bouton
    const buzzButton = document.getElementById('buzzButton');
    buzzButton.disabled = true;
    buzzButton.style.background = 'linear-gradient(135deg, #95a5a6 0%, #7f8c8d 50%, #5a5a5a 100%)';
    
    // Message d'échec
    document.getElementById('chronoTime').textContent = '0';
    
    // Rediriger vers écran de résultat (timeout) après 2 secondes
    setTimeout(() => {
        window.location.href = "{{ route('solo.timeout') }}";
    }, 2000);
}

function activateSkill(skillName) {
    console.log('Skill activé:', skillName);
    // Fonctionnalité des skills implémentée dans game_answer.blade.php
    // Pas de popup, le skill est activé directement
}
</script>
@endsection
