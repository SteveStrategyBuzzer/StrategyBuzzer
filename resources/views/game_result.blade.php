@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        overflow-y: auto;
        overflow-x: hidden;
        margin: 0;
    }
    
    .result-container {
        max-width: 800px;
        width: 100%;
        text-align: center;
        padding: 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        max-height: 100vh;
    }
    
    .result-icon {
        font-size: 80px;
        margin-bottom: 10px;
        animation: scaleIn 0.5s ease-out;
        flex-shrink: 0;
    }
    
    .result-title {
        font-size: 2rem;
        font-weight: 900;
        margin-bottom: 15px;
        animation: slideDown 0.6s ease-out;
        text-transform: uppercase;
        letter-spacing: 2px;
        flex-shrink: 0;
    }
    
    .result-correct .result-title {
        color: #2ECC71;
        text-shadow: 0 0 30px rgba(46, 204, 113, 0.8);
    }
    
    .result-incorrect .result-title {
        color: #E74C3C;
        text-shadow: 0 0 30px rgba(231, 76, 60, 0.8);
    }
    
    /* Round Details */
    .round-details {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 12px;
        padding: 8px;
        background: rgba(0,0,0,0.3);
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }
    
    .round-player, .round-opponent {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    
    .round-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .round-label {
        font-size: 0.85rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .points-gained {
        background: linear-gradient(135deg, #2ECC71, #27AE60);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .points-lost {
        background: linear-gradient(135deg, #E74C3C, #C0392B);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .points-neutral {
        background: rgba(255,255,255,0.1);
        color: #95a5a6;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .speed-indicator {
        font-size: 0.75rem;
        color: #95a5a6;
        padding: 2px 8px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
    }
    
    .speed-indicator.first {
        color: #f39c12;
        background: rgba(243, 156, 18, 0.2);
    }
    
    /* Score Battle Display */
    .score-battle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        animation: fadeIn 0.8s ease-out;
        flex-shrink: 0;
    }
    
    .score-player, .score-opponent {
        flex: 1;
        max-width: 150px;
        padding: 15px;
        border-radius: 15px;
        position: relative;
        backdrop-filter: blur(10px);
    }
    
    .score-player {
        background: linear-gradient(145deg, rgba(46, 204, 113, 0.2) 0%, rgba(39, 174, 96, 0.2) 100%);
        border: 3px solid #2ECC71;
        box-shadow: 0 10px 40px rgba(46, 204, 113, 0.3);
    }
    
    .score-opponent {
        background: linear-gradient(145deg, rgba(231, 76, 60, 0.2) 0%, rgba(192, 57, 43, 0.2) 100%);
        border: 3px solid #E74C3C;
        box-shadow: 0 10px 40px rgba(231, 76, 60, 0.3);
    }
    
    .score-label {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .score-number {
        font-size: 2.5rem;
        font-weight: 900;
        line-height: 1;
    }
    
    .score-player .score-number {
        color: #2ECC71;
    }
    
    .score-opponent .score-number {
        color: #E74C3C;
    }
    
    .vs-divider {
        font-size: 1.2rem;
        font-weight: bold;
        color: #4ECDC4;
        background: rgba(78, 205, 196, 0.2);
        padding: 10px;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #4ECDC4;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.5);
    }
    
    /* Answers display */
    .result-answers {
        background: rgba(0,0,0,0.4);
        padding: 15px;
        border-radius: 15px;
        margin-bottom: 15px;
        animation: fadeIn 1s ease-out;
        border: 2px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
    }
    
    .answer-display {
        padding: 10px 15px;
        border-radius: 12px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        backdrop-filter: blur(5px);
    }
    
    .answer-display:last-child {
        margin-bottom: 0;
    }
    
    .answer-correct {
        background: rgba(46, 204, 113, 0.25);
        border: 2px solid #2ECC71;
        box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
    }
    
    .answer-incorrect {
        background: rgba(231, 76, 60, 0.25);
        border: 2px solid #E74C3C;
        box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
    }
    
    .answer-label {
        opacity: 0.9;
        font-size: 0.95rem;
        font-weight: 600;
        flex-shrink: 0;
    }
    
    .answer-text {
        flex: 1;
        text-align: left;
        font-weight: 500;
    }
    
    .answer-icon {
        font-size: 1.8rem;
    }
    
    /* Informations de progression */
    .progress-info {
        background: rgba(0,0,0,0.3);
        border: 2px solid rgba(78, 205, 196, 0.3);
        border-radius: 10px;
        padding: 8px;
        margin-top: 10px;
        backdrop-filter: blur(10px);
    }
    
    .info-row {
        display: flex;
        gap: 6px;
        margin-bottom: 6px;
    }
    
    .info-row:last-child {
        margin-bottom: 0;
    }
    
    .info-item {
        background: rgba(78, 205, 196, 0.1);
        border: 1px solid rgba(78, 205, 196, 0.3);
        border-radius: 6px;
        padding: 4px 8px;
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .info-item.wide {
        flex: 1;
    }
    
    .info-label {
        font-size: 0.65rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .info-value {
        font-size: 0.75rem;
        color: white;
        font-weight: bold;
    }
    
    /* Timer next question et boutons */
    .result-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    
    .btn-action {
        flex: 1;
        padding: 12px 20px;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .btn-menu {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    .btn-menu:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }
    
    .btn-go {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.4);
    }
    
    .btn-go:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(78, 205, 196, 0.6);
    }
    
    .next-question-timer {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 15px;
        border-radius: 15px;
        font-size: 1rem;
        border: 2px solid rgba(78, 205, 196, 0.3);
        animation: fadeIn 1.2s ease-out;
    }
    
    .timer-count {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        display: inline-block;
        margin: 0 5px;
        animation: pulse 1s infinite;
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0) rotate(-180deg);
            opacity: 0;
        }
        to {
            transform: scale(1) rotate(0deg);
            opacity: 1;
        }
    }
    
    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.15);
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .result-title {
            font-size: 2rem;
        }
        
        .score-battle {
            gap: 15px;
        }
        
        .score-number {
            font-size: 2.5rem;
        }
        
        .vs-divider {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }
        
        .answer-text {
            font-size: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .score-battle {
            flex-direction: column;
            gap: 20px;
        }
        
        .score-player, .score-opponent {
            max-width: 100%;
            width: 100%;
        }
        
        .vs-divider {
            transform: rotate(90deg);
        }
    }
    
    /* === RESPONSIVE POUR ORIENTATION === */
    
    /* Mobile Portrait */
    @media (max-width: 480px) and (orientation: portrait) {
        .result-container {
            padding: 16px;
        }
        
        .result-title {
            font-size: 1.8rem;
        }
        
        .result-icon {
            font-size: 4rem;
        }
        
        .score-number {
            font-size: 2rem;
        }
        
        .round-details {
            padding: 12px;
        }
        
        .answer-text {
            font-size: 0.95rem;
        }
    }
    
    /* Mobile Paysage */
    @media (max-height: 500px) and (orientation: landscape) {
        .result-container {
            padding: 10px;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .result-correct, .result-incorrect {
            padding: 12px;
            margin-bottom: 12px;
        }
        
        .result-title {
            font-size: 1.5rem;
        }
        
        .result-icon {
            font-size: 3rem;
        }
        
        .score-battle {
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .score-number {
            font-size: 2rem;
        }
        
        .vs-divider {
            width: 45px;
            height: 45px;
            font-size: 1rem;
        }
        
        .round-details {
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .answer-text {
            font-size: 0.9rem;
        }
        
        .next-button {
            padding: 12px 24px;
        }
    }
    
    /* Tablettes Portrait */
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .result-title {
            font-size: 2.2rem;
        }
        
        .score-number {
            font-size: 2.8rem;
        }
    }
    
    /* Tablettes Paysage */
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .result-container {
            padding: 18px;
        }
        
        .score-battle {
            gap: 20px;
        }
    }
</style>

<div class="result-container">
    @if($params['is_correct'])
        <div class="result-correct">
            <div class="result-icon">✅</div>
            <h1 class="result-title">Bonne réponse !</h1>
        </div>
    @else
        <div class="result-incorrect">
            <div class="result-icon">❌</div>
            <h1 class="result-title">Mauvaise réponse</h1>
        </div>
    @endif
    
    <!-- Détails du round -->
    @if(isset($params['player_points']))
    <div class="round-details">
        <div class="round-player">
            <div class="round-info">
                <span class="round-label">🎮 Vous</span>
                @if($params['player_points'] > 0)
                    <span class="points-gained">+{{ $params['player_points'] }}</span>
                @elseif($params['player_points'] < 0)
                    <span class="points-lost">{{ $params['player_points'] }}</span>
                @else
                    <span class="points-neutral">0</span>
                @endif
            </div>
            @if(isset($params['opponent_faster']) && $params['opponent_faster'])
                <div class="speed-indicator">⏱️ 2ème</div>
            @elseif($params['player_points'] != 0 && !isset($params['is_timeout']))
                <div class="speed-indicator first">⚡ 1er</div>
            @endif
        </div>
        
        <div class="round-opponent">
            <div class="round-info">
                <span class="round-label">🤖 IA</span>
                @if(isset($params['opponent_buzzed']) && !$params['opponent_buzzed'])
                    <span class="points-neutral">Pas buzzé</span>
                @elseif(isset($params['opponent_points']))
                    @if($params['opponent_points'] > 0)
                        <span class="points-gained">+{{ $params['opponent_points'] }}</span>
                    @elseif($params['opponent_points'] < 0)
                        <span class="points-lost">{{ $params['opponent_points'] }}</span>
                    @else
                        <span class="points-neutral">0</span>
                    @endif
                @endif
            </div>
            @if(isset($params['opponent_faster']) && $params['opponent_faster'] && isset($params['opponent_buzzed']) && $params['opponent_buzzed'])
                <div class="speed-indicator first">⚡ 1er</div>
            @elseif(isset($params['opponent_buzzed']) && $params['opponent_buzzed'] && $params['opponent_points'] != 0 && !$params['opponent_faster'])
                <div class="speed-indicator">⏱️ 2ème</div>
            @endif
        </div>
    </div>
    @endif
    
    <!-- Score Battle -->
    <div class="score-battle">
        <div class="score-player">
            <div class="score-label">🎮 Votre Score</div>
            <div class="score-number">{{ $params['score'] }}</div>
        </div>
        
        <div class="vs-divider">VS</div>
        
        <div class="score-opponent">
            <div class="score-label">🤖 Adversaire</div>
            <div class="score-number">{{ $params['opponent_score'] ?? 0 }}</div>
        </div>
    </div>
    
    <!-- Answers -->
    <div class="result-answers">
        @php
            $question = $params['question'];
            $userAnswerIndex = $params['answer_index'];
            $correctIndex = $question['correct_index'];
            $isTimeout = $params['is_timeout'] ?? false;
        @endphp
        
        @if(!$params['is_correct'])
            <!-- Afficher la réponse incorrecte du joueur ou le timeout -->
            <div class="answer-display answer-incorrect">
                <span class="answer-label">Votre réponse:</span>
                <span class="answer-text">
                    @if($isTimeout)
                        ⏰ Temps écoulé - Pas de buzz
                    @else
                        {{ $question['answers'][$userAnswerIndex] }}
                    @endif
                </span>
                <span class="answer-icon">❌</span>
            </div>
        @endif
        
        <!-- Afficher la bonne réponse -->
        <div class="answer-display answer-correct">
            <span class="answer-label">Bonne réponse:</span>
            <span class="answer-text">{{ $question['answers'][$correctIndex] }}</span>
            <span class="answer-icon">✅</span>
        </div>
    </div>
    
    <!-- Informations de progression simplifiées -->
    <div class="progress-info">
        <div class="info-row">
            <div class="info-item">
                <span class="info-label">⚔️ Score:</span>
                <span class="info-value">{{ $params['player_rounds_won'] ?? 0 }}-{{ $params['opponent_rounds_won'] ?? 0 }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">❤️ Vies:</span>
                <span class="info-value">{{ $params['vies_restantes'] ?? config('game.life_max', 3) }}</span>
            </div>
        </div>
        
        <div class="info-row">
            <div class="info-item wide">
                <span class="info-label">📈 Progression:</span>
                <span class="info-value">{{ $params['current_question'] ?? 1 }}/{{ $params['total_questions'] ?? 30 }}</span>
            </div>
        </div>
    </div>
    
    <!-- Actions: Boutons et Timer -->
    <div class="result-actions">
        <div class="action-buttons">
            <a href="{{ route('solo.index') }}" class="btn-action btn-menu">
                ← Solo
            </a>
            <button onclick="goToNextQuestion()" class="btn-action btn-go">
                🚀 GO
            </button>
        </div>
        
        <div class="next-question-timer">
            Prochaine question dans <span class="timer-count" id="countdown">15</span> secondes...
        </div>
    </div>
</div>

<!-- Musique d'ambiance du gameplay (continue depuis game_answer) -->
<audio id="gameplayAmbient" preload="auto" loop>
    <source src="{{ asset('sounds/gameplay_ambient.mp3') }}" type="audio/mpeg">
</audio>

<script>
// Vérifier si la musique de gameplay est activée
function isGameplayMusicEnabled() {
    const enabled = localStorage.getItem('gameplay_music_enabled');
    return enabled === 'true';
}

// Continuer la musique d'ambiance du gameplay SEULEMENT si activée
const gameplayAmbient = document.getElementById('gameplayAmbient');
gameplayAmbient.volume = 0.5; // -6 dB ≈ 50% de volume

if (isGameplayMusicEnabled()) {
    const savedTime = parseFloat(localStorage.getItem('gameplayMusicTime') || '0');
    gameplayAmbient.addEventListener('loadedmetadata', function() {
        if (savedTime > 0 && savedTime < gameplayAmbient.duration) {
            gameplayAmbient.currentTime = savedTime;
        }
        
        gameplayAmbient.play().catch(e => {
            console.log('Gameplay music autoplay blocked:', e);
            document.addEventListener('click', function playGameplayMusic() {
                gameplayAmbient.play().catch(err => console.log('Audio play failed:', err));
                document.removeEventListener('click', playGameplayMusic);
            }, { once: true });
        });
    });

    setInterval(() => {
        if (!gameplayAmbient.paused) {
            localStorage.setItem('gameplayMusicTime', gameplayAmbient.currentTime.toString());
        }
    }, 1000);

    window.addEventListener('beforeunload', () => {
        localStorage.setItem('gameplayMusicTime', gameplayAmbient.currentTime.toString());
    });
}

// Compte à rebours de 15 secondes
let countdown = 15;
const countdownElement = document.getElementById('countdown');

const interval = setInterval(() => {
    countdown--;
    if (countdown > 0) {
        countdownElement.textContent = countdown;
    } else {
        clearInterval(interval);
        // Rediriger vers la prochaine question
        goToNextQuestion();
    }
}, 1000);

function goToNextQuestion() {
    clearInterval(interval);
    window.location.href = "{{ route('solo.next') }}";
}
</script>
@endsection
