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

// Prénoms pour les adversaires selon leur niveau
$opponentNames = [
    1 => 'Lucas', 2 => 'Emma', 3 => 'Nathan', 4 => 'Léa', 5 => 'Hugo',
    6 => 'Chloé', 7 => 'Louis', 8 => 'Jade', 9 => 'Arthur'
];

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
    $opponentName = $opponentNames[$niveau] ?? 'Élève';
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
        align-items: center;
        justify-content: center;
        gap: 40px;
        margin: 20px 0;
    }
    
    /* Avatar joueur (gauche) avec score */
    .player-avatar-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    .player-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid #4ECDC4;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.5);
        object-fit: cover;
    }
    
    .player-score-display {
        font-size: 2.2rem;
        font-weight: 900;
        color: #4ECDC4;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
    }
    
    .player-name {
        font-size: 0.9rem;
        color: #4ECDC4;
        font-weight: 600;
        opacity: 0.9;
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
    
    /* Avatar adversaire (droite) avec score */
    .opponent-avatar-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    .opponent-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
        object-fit: cover;
    }
    
    .opponent-score-display {
        font-size: 2.2rem;
        font-weight: 900;
        color: #FF6B6B;
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.8);
    }
    
    .opponent-name {
        font-size: 0.9rem;
        color: #FF6B6B;
        font-weight: 600;
        opacity: 0.9;
    }
    
    /* Avatar stratégique ET boutons de skills */
    .strategic-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        margin-top: 20px;
    }
    
    .strategic-avatar-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .strategic-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid #FFD700;
        box-shadow: 0 8px 30px rgba(255, 215, 0, 0.5);
        object-fit: cover;
    }
    
    .strategic-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px dashed rgba(255,255,255,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        opacity: 0.5;
    }
    
    .strategic-label {
        font-size: 0.9rem;
        color: #FFD700;
        font-weight: 600;
        text-align: center;
    }
    
    /* Boutons de skills empilés */
    .skills-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }
    
    .skill-button {
        width: 140px;
        padding: 10px 15px;
        border-radius: 25px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: 2px solid #FFD700;
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .skill-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }
    
    .skill-button:active {
        transform: translateY(0);
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.4);
    }
    
    .skill-icon {
        font-size: 1.2rem;
    }
    
    /* Buzzer redessiné */
    .buzz-container {
        text-align: center;
        margin-top: 20px;
    }
    
    .buzz-button {
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: linear-gradient(145deg, #e63946 0%, #a4161a 50%, #660708 100%);
        border: none;
        color: white;
        font-weight: 900;
        cursor: pointer;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s ease;
        box-shadow: 
            0 20px 50px rgba(230, 57, 70, 0.6),
            inset 0 -12px 30px rgba(0,0,0,0.4),
            inset 0 4px 15px rgba(255,255,255,0.2),
            0 0 0 8px #8B0000;
        position: relative;
        text-transform: uppercase;
    }
    
    .buzz-button::before {
        content: '';
        position: absolute;
        top: 18px;
        left: 50%;
        transform: translateX(-50%);
        width: 65%;
        height: 25%;
        background: radial-gradient(ellipse at center, rgba(255,255,255,0.35) 0%, transparent 70%);
        border-radius: 50%;
    }
    
    .buzz-button:hover:not(:disabled) {
        transform: translateY(-8px) scale(1.03);
        box-shadow: 
            0 25px 70px rgba(230, 57, 70, 0.8),
            inset 0 -12px 30px rgba(0,0,0,0.4),
            inset 0 4px 15px rgba(255,255,255,0.3),
            0 0 0 8px #8B0000;
    }
    
    .buzz-button:active:not(:disabled) {
        transform: translateY(4px) scale(0.97);
        box-shadow: 
            0 8px 25px rgba(230, 57, 70, 0.6),
            inset 0 -6px 15px rgba(0,0,0,0.5),
            0 0 0 8px #8B0000;
    }
    
    .buzz-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: linear-gradient(145deg, #95a5a6 0%, #7f8c8d 50%, #5a5a5a 100%);
    }
    
    .buzz-top {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 3px;
        opacity: 0.9;
        color: rgba(255,255,255,0.8);
    }
    
    .buzz-main {
        font-size: 2.8rem;
        font-weight: 900;
        letter-spacing: 4px;
        background: linear-gradient(180deg, #FFD700 0%, #FFA500 50%, #FF8C00 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: 0 2px 10px rgba(255, 215, 0, 0.5);
    }
    
    .buzz-bottom {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 2px;
        opacity: 0.9;
        color: rgba(255,255,255,0.8);
    }
    
    
    /* Responsive */
    @media (max-width: 768px) {
        .chrono-section {
            gap: 20px;
        }
        
        .player-avatar, .opponent-avatar, .strategic-avatar {
            width: 70px;
            height: 70px;
        }
        
        .player-score-display, .opponent-score-display {
            font-size: 1.8rem;
        }
        
        .chrono-circle {
            width: 120px;
            height: 120px;
        }
        
        .chrono-time {
            font-size: 2.8rem;
        }
        
        .skill-button {
            width: 120px;
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        .buzz-button {
            width: 160px;
            height: 160px;
        }
        
        .buzz-main {
            font-size: 2.2rem;
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
        
        .buzz-button {
            width: 140px;
            height: 140px;
        }
        
        .buzz-main {
            font-size: 1.8rem;
        }
        
        .buzz-top, .buzz-bottom {
            font-size: 0.55rem;
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
        
        .buzz-button {
            width: 120px;
            height: 120px;
        }
        
        .buzz-main {
            font-size: 1.6rem;
        }
        
        .skill-button {
            width: 100px;
            padding: 6px 8px;
            font-size: 0.75rem;
        }
        
        .strategic-section {
            gap: 10px;
            margin-top: 10px;
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
    
    <!-- Section centrale : Avatar joueur + Chronomètre + Avatar adversaire -->
    <div class="chrono-section">
        <!-- Avatar joueur (gauche) avec score -->
        <div class="player-avatar-container">
            <img src="{{ $playerAvatarPath }}" alt="Player" class="player-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
            <div class="player-score-display">{{ $params['score'] }}</div>
            <div class="player-name">Vous - Niv {{ $niveau }}</div>
        </div>
        
        <!-- Chronomètre -->
        <div class="chrono-container" id="chronoContainer">
            <div class="chrono-circle">
                <div class="chrono-time" id="chronoTime">{{ $params['chrono_time'] }}</div>
            </div>
            <div class="chrono-label">⏱️ Secondes</div>
        </div>
        
        <!-- Avatar adversaire (droite) avec score -->
        <div class="opponent-avatar-container">
            <img src="{{ $opponentAvatar }}" alt="Opponent" class="opponent-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
            <div class="opponent-score-display">{{ $opponentScore }}</div>
            <div class="opponent-name">{{ $opponentName }} - Niv {{ $niveau }}</div>
        </div>
    </div>
    
    <!-- Avatar stratégique avec boutons de skills -->
    @if($currentAvatar !== 'Aucun')
    <div class="strategic-section">
        <div class="strategic-avatar-wrapper">
            @if(!empty($strategicAvatarPath))
                <img src="{{ $strategicAvatarPath }}" alt="{{ $currentAvatar }}" class="strategic-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
            @else
                <div class="strategic-placeholder">⚔️</div>
            @endif
            <div class="strategic-label">{{ $currentAvatar }}</div>
        </div>
        
        @if(count($skills) > 0)
        <div class="skills-buttons">
            @foreach($skills as $skill)
                <button type="button" class="skill-button" onclick="activateSkill('{{ $skill['name'] }}')">
                    <span class="skill-icon">{{ $skill['icon'] }}</span>
                    <span>{{ $skill['name'] }}</span>
                </button>
            @endforeach
        </div>
        @endif
    </div>
    @endif
    
    <!-- Buzzer redessiné -->
    <div class="buzz-container">
        <form id="buzzForm" method="POST" action="{{ route('solo.buzz') }}">
            @csrf
            <button type="button" id="buzzButton" class="buzz-button" onclick="handleBuzz()">
                <span class="buzz-top">STRATEGY</span>
                <span class="buzz-main">BUZZ</span>
                <span class="buzz-bottom">BUZZER</span>
            </button>
        </form>
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
    const buzzSound = document.getElementById('buzzSound');
    buzzSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver le bouton
    document.getElementById('buzzButton').disabled = true;
    
    // Soumettre le formulaire après un court délai pour entendre le son
    setTimeout(() => {
        document.getElementById('buzzForm').submit();
    }, 300);
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
    // Fonctionnalité des skills à implémenter plus tard
    alert('Skill "' + skillName + '" activé ! (Fonctionnalité à venir)');
}
</script>
@endsection
