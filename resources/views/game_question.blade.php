@extends('layouts.app')

@section('content')
@php
// Mapping des skills pour chaque avatar stratégique
$avatarSkills = [
    'Mathématicien' => ['🔢'],
    'Scientifique' => ['⚗️'],
    'Explorateur' => ['🧭'],
    'Défenseur' => ['🛡️'],
    'Comédienne' => ['🎯', '🌀'],
    'Magicienne' => ['✨', '💫'],
    'Challenger' => ['🔄', '⏳'],
    'Historien' => ['🪶', '⏰'],
    'IA Junior' => ['💡', '❌', '🔁'],
    'Stratège' => ['🧠', '🤝', '💰'],
    'Sprinteur' => ['⏱️', '🕒', '🔋'],
    'Visionnaire' => ['👁️', '🏰', '🎯'],
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

// Avatar stratégique
$strategicAvatarPath = '';
if ($currentAvatar !== 'Aucun') {
    $strategicAvatarSlug = strtolower(str_replace(' ', '-', $currentAvatar));
    $strategicAvatarPath = asset("images/avatars/{$strategicAvatarSlug}.png");
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
    
    /* Question en haut */
    .question-header {
        background: rgba(78, 205, 196, 0.1);
        padding: 20px;
        border-radius: 20px;
        text-align: center;
        border: 2px solid rgba(78, 205, 196, 0.3);
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
    
    /* Avatar joueur (gauche) */
    .player-avatar-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .player-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid #4ECDC4;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.5);
        object-fit: cover;
    }
    
    .player-label {
        font-size: 0.9rem;
        color: #4ECDC4;
        font-weight: 600;
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
    
    /* Avatar stratégique (droite) avec skills */
    .strategic-avatar-container {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .strategic-avatar-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
    }
    
    .strategic-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid #FFD700;
        box-shadow: 0 8px 30px rgba(255, 215, 0, 0.5);
        object-fit: cover;
        position: absolute;
        top: 10px;
        left: 10px;
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
        position: absolute;
        top: 10px;
        left: 10px;
    }
    
    .skill-icon {
        position: absolute;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        border: 2px solid #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    
    /* Positionnement des skills en cercle */
    .skill-icon:nth-child(2) { top: 0; left: 50%; transform: translateX(-50%); }
    .skill-icon:nth-child(3) { top: 20%; right: 5%; }
    .skill-icon:nth-child(4) { bottom: 20%; right: 5%; }
    .skill-icon:nth-child(5) { bottom: 0; left: 50%; transform: translateX(-50%); }
    .skill-icon:nth-child(6) { bottom: 20%; left: 5%; }
    .skill-icon:nth-child(7) { top: 20%; left: 5%; }
    
    .strategic-label {
        font-size: 0.9rem;
        color: #FFD700;
        font-weight: 600;
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
    
    /* Scores en haut */
    .score-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 30px;
        background: rgba(0,0,0,0.3);
        border-radius: 15px;
        margin-bottom: 10px;
    }
    
    .score-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .score-label {
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    .score-value {
        font-size: 1.8rem;
        font-weight: 900;
    }
    
    .player-score { color: #4ECDC4; }
    .opponent-score { color: #FF6B6B; }
    
    .vs-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 1.2rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .chrono-section {
            gap: 20px;
        }
        
        .player-avatar, .strategic-avatar, .strategic-placeholder {
            width: 70px;
            height: 70px;
        }
        
        .strategic-avatar-wrapper {
            width: 90px;
            height: 90px;
        }
        
        .chrono-circle {
            width: 120px;
            height: 120px;
        }
        
        .chrono-time {
            font-size: 2.8rem;
        }
        
        .skill-icon {
            width: 26px;
            height: 26px;
            font-size: 0.9rem;
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
        
        .player-avatar, .strategic-avatar, .strategic-placeholder {
            width: 60px;
            height: 60px;
        }
        
        .strategic-avatar-wrapper {
            width: 80px;
            height: 80px;
        }
        
        .chrono-circle {
            width: 100px;
            height: 100px;
        }
        
        .chrono-time {
            font-size: 2.2rem;
        }
        
        .skill-icon {
            width: 22px;
            height: 22px;
            font-size: 0.75rem;
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
        
        .score-header {
            padding: 10px 15px;
        }
        
        .score-value {
            font-size: 1.4rem;
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
        
        .player-avatar, .strategic-avatar, .strategic-placeholder {
            width: 60px;
            height: 60px;
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
        
        .skill-icon {
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
        }
    }
</style>

<div class="game-container">
    <!-- Scores en haut -->
    <div class="score-header">
        <div class="score-item">
            <span class="score-label">🎮 Vous</span>
            <span class="score-value player-score">{{ $params['score'] }}</span>
        </div>
        
        <div class="vs-badge">VS</div>
        
        <div class="score-item">
            @php
                $niveau = $params['niveau'];
                $bossInfo = (new App\Http\Controllers\SoloController())->getBossForLevel($niveau);
            @endphp
            <span class="score-value opponent-score">{{ $params['current_question'] - 1 - $params['score'] }}</span>
            <span class="score-label">
                @if($bossInfo)
                    🏆 {{ $bossInfo['name'] }}
                @else
                    📚 Élève {{ $niveau }}
                @endif
            </span>
        </div>
    </div>
    
    <!-- Question en haut -->
    <div class="question-header">
        <div class="question-number">
            Question {{ $params['current_question'] }} / {{ $params['total_questions'] }}
        </div>
        <div class="question-text">{{ $params['question']['text'] }}</div>
    </div>
    
    <!-- Section centrale : Avatar + Chronomètre + Avatar stratégique -->
    <div class="chrono-section">
        <!-- Avatar joueur (gauche) -->
        <div class="player-avatar-container">
            <img src="{{ $playerAvatarPath }}" alt="Player" class="player-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
            <div class="player-label">Vous</div>
        </div>
        
        <!-- Chronomètre -->
        <div class="chrono-container" id="chronoContainer">
            <div class="chrono-circle">
                <div class="chrono-time" id="chronoTime">{{ $params['chrono_time'] }}</div>
            </div>
            <div class="chrono-label">⏱️ Secondes</div>
        </div>
        
        <!-- Avatar stratégique (droite) -->
        <div class="strategic-avatar-container">
            <div class="strategic-avatar-wrapper">
                @if($currentAvatar !== 'Aucun' && !empty($strategicAvatarPath))
                    <img src="{{ $strategicAvatarPath }}" alt="{{ $currentAvatar }}" class="strategic-avatar" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                    
                    @foreach($skills as $index => $skill)
                        <div class="skill-icon">{{ $skill }}</div>
                    @endforeach
                @else
                    <div class="strategic-placeholder">⚔️</div>
                @endif
            </div>
            <div class="strategic-label">{{ $currentAvatar !== 'Aucun' ? $currentAvatar : 'Aucun avatar' }}</div>
        </div>
    </div>
    
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
</script>
@endsection
