@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        color: #fff;
        min-height: 100vh;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        overflow: hidden;
        margin: 0;
    }
    
    .game-container {
        max-width: 900px;
        width: 100%;
        margin: 0 auto;
        padding: 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        max-height: 100vh;
    }
    
    /* Header avec VS adversaire */
    .game-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .player-info, .opponent-info {
        background: rgba(0,0,0,0.4);
        padding: 10px 15px;
        border-radius: 15px;
        backdrop-filter: blur(10px);
        border: 2px solid rgba(255,255,255,0.1);
        flex: 1;
        min-width: 150px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .player-info {
        border-left: 4px solid #4ECDC4;
    }
    
    .opponent-info {
        border-right: 4px solid #FF6B6B;
    }
    
    .vs-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 1.2rem;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.6);
        min-width: 60px;
    }
    
    .score-display {
        font-size: 1.8rem;
        font-weight: bold;
        color: #4ECDC4;
    }
    
    .opponent-score {
        color: #FF6B6B;
    }
    
    .player-label, .opponent-label {
        font-size: 0.85rem;
        opacity: 0.8;
        margin-bottom: 5px;
    }
    
    /* Question bubble */
    .question-bubble {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.15) 0%, rgba(102, 126, 234, 0.15) 100%);
        padding: 15px 20px;
        border-radius: 20px;
        margin-bottom: 15px;
        border: 2px solid rgba(78, 205, 196, 0.3);
        box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .question-bubble::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        background: linear-gradient(45deg, #4ECDC4, #667eea, #764ba2);
        border-radius: 25px;
        opacity: 0.1;
        z-index: -1;
    }
    
    .question-number {
        font-size: 0.9rem;
        color: #4ECDC4;
        margin-bottom: 15px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .question-text {
        font-size: 1.2rem;
        font-weight: 600;
        line-height: 1.4;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    
    /* Chronomètre énergétique */
    .chrono-container {
        margin-bottom: 15px;
        position: relative;
        flex-shrink: 0;
    }
    
    .chrono-circle {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        position: relative;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.6);
        animation: pulse-glow 2s ease-in-out infinite;
    }
    
    @keyframes pulse-glow {
        0%, 100% {
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.6);
        }
        50% {
            box-shadow: 0 10px 60px rgba(102, 126, 234, 0.9);
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
        font-size: 2.2rem;
        font-weight: bold;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 20px rgba(0,0,0,0.5);
    }
    
    .chrono-label {
        font-size: 0.85rem;
        opacity: 0.9;
        text-align: center;
    }
    
    .chrono-warning {
        animation: danger-pulse 0.5s infinite !important;
    }
    
    .chrono-warning .chrono-circle {
        background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    }
    
    @keyframes danger-pulse {
        0%, 100% { 
            transform: scale(1);
            box-shadow: 0 10px 40px rgba(255, 107, 107, 0.8);
        }
        50% { 
            transform: scale(1.08);
            box-shadow: 0 15px 60px rgba(255, 107, 107, 1);
        }
    }
    
    /* Bouton BUZZ réaliste avec Strategy Buzzer */
    .buzz-container {
        text-align: center;
        flex-shrink: 0;
    }
    
    .buzz-button {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: linear-gradient(145deg, #FF6B6B 0%, #C0392B 100%);
        border: 6px solid #8B0000;
        color: white;
        font-size: 1.4rem;
        font-weight: 900;
        cursor: pointer;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        transition: all 0.2s ease;
        box-shadow: 
            0 15px 40px rgba(255, 107, 107, 0.6),
            inset 0 -8px 20px rgba(0,0,0,0.3),
            inset 0 3px 10px rgba(255,255,255,0.2);
        position: relative;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }
    
    .buzz-button::before {
        content: '';
        position: absolute;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        width: 70%;
        height: 30%;
        background: radial-gradient(ellipse at center, rgba(255,255,255,0.3) 0%, transparent 70%);
        border-radius: 50%;
    }
    
    .buzz-button:hover:not(:disabled) {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 
            0 20px 60px rgba(255, 107, 107, 0.8),
            inset 0 -8px 20px rgba(0,0,0,0.3),
            inset 0 3px 10px rgba(255,255,255,0.3);
    }
    
    .buzz-button:active:not(:disabled) {
        transform: translateY(3px) scale(0.98);
        box-shadow: 
            0 5px 20px rgba(255, 107, 107, 0.6),
            inset 0 -3px 10px rgba(0,0,0,0.4);
    }
    
    .buzz-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: linear-gradient(145deg, #95a5a6 0%, #7f8c8d 100%);
        border-color: #5a5a5a;
    }
    
    .buzz-icon {
        font-size: 2.5rem;
        display: block;
        animation: ring 2s ease-in-out infinite;
    }
    
    .buzz-text {
        font-size: 1rem;
        margin-top: -3px;
    }
    
    .buzz-brand {
        font-size: 0.6rem;
        font-weight: 600;
        opacity: 0.9;
        letter-spacing: 0.8px;
    }
    
    @keyframes ring {
        0%, 100% { transform: rotate(0deg); }
        10% { transform: rotate(-15deg); }
        20% { transform: rotate(15deg); }
        30% { transform: rotate(-15deg); }
        40% { transform: rotate(15deg); }
        50% { transform: rotate(0deg); }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .question-text {
            font-size: 1.2rem;
        }
        
        .chrono-circle {
            width: 110px;
            height: 110px;
        }
        
        .chrono-time {
            font-size: 2.5rem;
        }
        
        .buzz-button {
            width: 200px;
            height: 200px;
            font-size: 1.5rem;
        }
        
        .buzz-icon {
            font-size: 3rem;
        }
        
        .buzz-text {
            font-size: 1rem;
        }
        
        .score-display {
            font-size: 1.4rem;
        }
    }
    
    @media (max-width: 480px) {
        .game-header {
            flex-direction: column;
        }
        
        .player-info, .opponent-info {
            width: 100%;
        }
        
        .question-bubble {
            padding: 20px;
        }
        
        .buzz-button {
            width: 180px;
            height: 180px;
        }
    }
    
    /* === RESPONSIVE POUR ORIENTATION === */
    
    /* Mobile Portrait */
    @media (max-width: 480px) and (orientation: portrait) {
        .game-container {
            padding: 12px;
        }
        
        .question-bubble {
            padding: 16px;
            margin: 12px 0;
        }
        
        .question-text {
            font-size: 1.1rem;
        }
        
        .buzz-button {
            width: 160px;
            height: 160px;
        }
        
        .buzz-icon {
            font-size: 2.5rem;
        }
    }
    
    /* Mobile Paysage */
    @media (max-height: 500px) and (orientation: landscape) {
        .game-container {
            padding: 8px;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .game-header {
            margin-bottom: 8px;
        }
        
        .question-bubble {
            padding: 12px;
            margin: 8px 0;
        }
        
        .question-text {
            font-size: 1rem;
        }
        
        .chrono-container {
            margin: 8px 0;
        }
        
        .chrono-circle {
            width: 90px;
            height: 90px;
        }
        
        .chrono-time {
            font-size: 2rem;
        }
        
        .buzz-button {
            width: 140px;
            height: 140px;
        }
        
        .buzz-icon {
            font-size: 2.2rem;
        }
        
        .buzz-text {
            font-size: 0.9rem;
        }
        
        .buzz-brand {
            font-size: 0.55rem;
        }
    }
    
    /* Tablettes Portrait */
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .buzz-button {
            width: 220px;
            height: 220px;
        }
    }
    
    /* Tablettes Paysage */
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .game-container {
            padding: 16px;
        }
        
        .buzz-button {
            width: 200px;
            height: 200px;
        }
    }
</style>

<div class="game-container">
    <!-- Header avec VS adversaire -->
    <div class="game-header">
        <div class="player-info">
            <div class="player-avatar-small">
                @php
                    $selectedAvatar = session('selected_avatar', 'default');
                    $avatarPath = asset("images/avatars/{$selectedAvatar}.png");
                @endphp
                <img src="{{ $avatarPath }}" alt="Player" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #4ECDC4;">
            </div>
            <div>
                <div class="player-label">🎮 Vous</div>
                <div class="score-display">{{ $params['score'] }}</div>
            </div>
        </div>
        
        <div class="vs-badge">VS</div>
        
        <div class="opponent-info">
            <div class="opponent-label">
                @php
                    $niveau = $params['niveau'];
                    $bossInfo = (new App\Http\Controllers\SoloController())->getBossForLevel($niveau);
                @endphp
                @if($bossInfo)
                    🏆 {{ $bossInfo['name'] }}
                @else
                    📚 Élève Niveau {{ $niveau }}
                @endif
            </div>
            <div class="score-display opponent-score">{{ $params['current_question'] - 1 - $params['score'] }}</div>
        </div>
    </div>
    
    <!-- Question bubble -->
    <div class="question-bubble">
        <div class="question-number">
            Question {{ $params['current_question'] }} / {{ $params['total_questions'] }}
            @if(!$bossInfo)
                • Niveau {{ $niveau }} - Élève {{ $niveau % 10 }}/10
            @endif
        </div>
        <div class="question-text">{{ $params['question']['text'] }}</div>
    </div>
    
    <!-- Chronomètre énergétique -->
    <div class="chrono-container" id="chronoContainer">
        <div class="chrono-circle">
            <div class="chrono-time" id="chronoTime">{{ $params['chrono_time'] }}</div>
        </div>
        <div class="chrono-label">⏱️ Secondes pour buzzer</div>
    </div>
    
    <!-- Bouton BUZZ réaliste -->
    <div class="buzz-container">
        <form id="buzzForm" method="POST" action="{{ route('solo.buzz') }}">
            @csrf
            <button type="button" id="buzzButton" class="buzz-button" onclick="handleBuzz()">
                <span class="buzz-icon">🔔</span>
                <span class="buzz-text">BUZZ</span>
                <span class="buzz-brand">Strategy Buzzer</span>
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
    buzzButton.style.background = 'linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%)';
    
    // Message d'échec
    document.getElementById('chronoTime').textContent = '0';
    
    // Rediriger vers écran de résultat (timeout) après 2 secondes
    setTimeout(() => {
        window.location.href = "{{ route('solo.timeout') }}";
    }, 2000);
}
</script>
@endsection
