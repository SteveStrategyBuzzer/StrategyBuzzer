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

$currentAvatar = $params['avatar'] ?? 'Aucun';
$skills = $currentAvatar !== 'Aucun' ? ($avatarSkills[$currentAvatar] ?? []) : [];

// Index de la bonne réponse (pour le skill)
$correctIndex = $params['question']['correct_index'] ?? -1;
@endphp

<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
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
    
    .answer-container {
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
    
    /* Header info */
    .answer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .answer-info {
        background: linear-gradient(135deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 8px 15px;
        border-radius: 15px;
        border: 2px solid rgba(78, 205, 196, 0.3);
        backdrop-filter: blur(10px);
        flex: 1;
        min-width: 150px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .answer-title {
        font-size: 0.85rem;
        color: #4ECDC4;
        margin-bottom: 3px;
        font-weight: 600;
    }
    
    .answer-value {
        font-size: 1.2rem;
        font-weight: bold;
    }
    
    .score-box {
        text-align: right;
    }
    
    /* Timer barre */
    .answer-timer {
        margin-bottom: 10px;
        position: relative;
        flex-shrink: 0;
    }
    
    .timer-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        font-size: 0.8rem;
    }
    
    .timer-bar-container {
        height: 8px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        border: 2px solid rgba(255,255,255,0.2);
    }
    
    .timer-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4 0%, #667eea 100%);
        transition: width 1s linear;
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.6);
    }
    
    .timer-bar.warning {
        background: linear-gradient(90deg, #FF6B6B 0%, #EE5A6F 100%);
        animation: timer-pulse 0.5s infinite;
    }
    
    @keyframes timer-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    
    /* Choix de réponses - Bulles stylisées */
    .answers-grid {
        display: grid;
        gap: 8px;
        margin-bottom: 10px;
        flex: 1;
        overflow-y: auto;
    }
    
    .answer-bubble {
        background: linear-gradient(145deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 15px;
        padding: 12px 18px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .answer-bubble::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s;
    }
    
    .answer-bubble:hover::before {
        left: 100%;
    }
    
    .answer-bubble:hover:not(.disabled) {
        transform: translateX(8px) scale(1.02);
        border-color: #4ECDC4;
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.25) 0%, rgba(102, 126, 234, 0.25) 100%);
        box-shadow: 0 10px 30px rgba(78, 205, 196, 0.4);
    }
    
    .answer-bubble:active:not(.disabled) {
        transform: translateX(4px) scale(0.98);
    }
    
    .answer-bubble.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .answer-number {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: bold;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
    }
    
    .answer-text {
        font-size: 0.95rem;
        font-weight: 500;
        flex: 1;
    }
    
    .answer-icon {
        font-size: 1.2rem;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .answer-bubble:hover .answer-icon {
        opacity: 1;
    }
    
    /* Style pour la bonne réponse illuminée */
    .answer-bubble.highlighted {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.6) 0%, rgba(102, 234, 126, 0.6) 100%) !important;
        border-color: #4ECDC4 !important;
        box-shadow: 0 0 30px rgba(78, 205, 196, 0.9), inset 0 0 20px rgba(78, 205, 196, 0.4) !important;
        animation: glow-pulse 1.5s infinite;
    }
    
    @keyframes glow-pulse {
        0%, 100% { box-shadow: 0 0 30px rgba(78, 205, 196, 0.9), inset 0 0 20px rgba(78, 205, 196, 0.4); }
        50% { box-shadow: 0 0 50px rgba(78, 205, 196, 1), inset 0 0 30px rgba(78, 205, 196, 0.6); }
    }
    
    /* Skills container */
    .skills-container {
        position: fixed;
        top: 50%;
        right: 20px;
        transform: translateY(-50%);
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 1000;
    }
    
    .skill-icon-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: 2px solid #FFD700;
        color: white;
        font-size: 1.4rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .skill-icon-circle:hover {
        transform: scale(1.15);
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.7);
    }
    
    .skill-icon-circle:active {
        transform: scale(0.95);
    }
    
    .skill-icon-circle.used {
        opacity: 0.4;
        cursor: not-allowed;
        filter: grayscale(100%);
        animation: none !important;
    }
    
    /* Animation de brillance pour les skills non utilisés */
    @keyframes skillShine {
        0%, 100% {
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.6),
                        0 0 20px rgba(255, 215, 0, 0.4),
                        inset 0 0 15px rgba(255, 255, 255, 0.2);
        }
        50% {
            box-shadow: 0 6px 30px rgba(255, 215, 0, 0.9),
                        0 0 40px rgba(255, 215, 0, 0.7),
                        inset 0 0 20px rgba(255, 255, 255, 0.4);
            border-color: #FFE55C;
        }
    }
    
    .skill-icon-circle:not(.used) {
        animation: skillShine 2s ease-in-out infinite;
    }
    
    /* Buzz info */
    .buzz-info {
        text-align: center;
        padding: 8px;
        background: rgba(78, 205, 196, 0.15);
        border-radius: 12px;
        margin-bottom: 5px;
        border: 2px solid rgba(78, 205, 196, 0.3);
        flex-shrink: 0;
    }
    
    .buzz-info-text {
        font-size: 0.8rem;
        color: #4ECDC4;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .answer-header {
            flex-direction: column;
            gap: 10px;
        }
        
        .answer-info {
            width: 100%;
        }
        
        .answer-value {
            font-size: 1.3rem;
        }
        
        .answer-text {
            font-size: 1rem;
        }
        
        .answer-number {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
        }
    }
    
    @media (max-width: 480px) {
        .answer-bubble {
            padding: 15px 18px;
        }
    }
    
    /* === RESPONSIVE POUR ORIENTATION === */
    
    /* Mobile Portrait */
    @media (max-width: 480px) and (orientation: portrait) {
        .answer-container {
            padding: 12px;
        }
        
        .answer-bubble {
            padding: 12px 16px;
            margin-bottom: 8px;
        }
        
        .answer-text {
            font-size: 0.95rem;
        }
    }
    
    /* Mobile Paysage */
    @media (max-height: 500px) and (orientation: landscape) {
        .answer-container {
            padding: 8px;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .answer-header {
            margin-bottom: 8px;
        }
        
        .answer-timer {
            margin-bottom: 8px;
        }
        
        .answer-bubble {
            padding: 10px 14px;
            margin-bottom: 6px;
        }
        
        .answer-text {
            font-size: 0.9rem;
        }
        
        .answer-number {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }
    }
    
    /* Tablettes Portrait */
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .answer-bubble {
            padding: 16px 20px;
        }
    }
    
    /* Tablettes Paysage */
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .answer-container {
            padding: 16px;
        }
    }
</style>

<div class="answer-container">
    <!-- Header -->
    <div class="answer-header">
        <div class="answer-info">
            @php
                $selectedAvatar = session('selected_avatar', 'default');
                $avatarPath = asset("images/avatars/{$selectedAvatar}.png");
            @endphp
            <img src="{{ $avatarPath }}" alt="Player" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #4ECDC4;">
            <div>
                <div class="answer-title">📝 Réponses Q: {{ $params['current_question'] }}</div>
                <div class="answer-value">{{ $params['total_questions'] }} questions</div>
            </div>
        </div>
        
        <div class="answer-info score-box">
            <div class="answer-title">🎯 Score</div>
            <div class="answer-value">{{ $params['score'] }} / {{ $params['current_question'] - 1 }}</div>
        </div>
    </div>
    
    <!-- Timer -->
    <div class="answer-timer">
        <div class="timer-label">
            <span>⏱️ Temps pour répondre</span>
            <span id="timerText">{{ $params['answer_time'] }}s</span>
        </div>
        <div class="timer-bar-container">
            <div class="timer-bar" id="timerBar"></div>
        </div>
    </div>
    
    <!-- Choix de réponses -->
    <form id="answerForm" method="POST" action="{{ route('solo.answer') }}">
        @csrf
        <input type="hidden" name="answer_index" id="answerIndex">
        
        <div class="answers-grid">
            @php
                $question = $params['question'];
                $isTrueFalse = $question['type'] === 'true_false';
            @endphp
            
            @foreach($question['answers'] as $index => $answer)
                @if($isTrueFalse && $answer === null)
                    @continue
                @endif
                
                <div class="answer-bubble" onclick="selectAnswer({{ $index }})" data-index="{{ $index }}">
                    <div class="answer-number">{{ $index + 1 }}</div>
                    <div class="answer-text">{{ $answer }}</div>
                    <div class="answer-icon">👉</div>
                </div>
            @endforeach
        </div>
    </form>
    
    <!-- Buzz info -->
    @if(isset($params['player_buzzed']) && !$params['player_buzzed'])
        <div class="buzz-info" style="background: rgba(255, 107, 107, 0.15); border-color: rgba(255, 107, 107, 0.3);">
            <div class="buzz-info-text" style="color: #FF6B6B;">
                ⚠️ Pas buzzé - Vous pouvez quand même répondre (0 point)
            </div>
        </div>
    @elseif(isset($params['buzz_time']))
        <div class="buzz-info">
            <div class="buzz-info-text">
                Vous avez buzzé en {{ $params['buzz_time'] }}s 💚
            </div>
        </div>
    @endif
</div>

<!-- Skills de l'avatar stratégique -->
@if(count($skills) > 0)
<div class="skills-container">
    @php
        $usedSkills = $params['used_skills'] ?? [];
    @endphp
    @foreach($skills as $skill)
        @php
            $isUsed = in_array($skill['name'], $usedSkills);
        @endphp
        <div class="skill-icon-circle {{ $isUsed ? 'used' : '' }}" 
             id="skill-{{ $loop->index }}" 
             onclick="activateSkill('{{ $skill['name'] }}', {{ $loop->index }})" 
             title="{{ $skill['name'] }}"
             data-skill-name="{{ $skill['name'] }}">
            {{ $skill['icon'] }}
        </div>
    @endforeach
</div>
@endif

<audio id="tickSound" preload="auto" loop>
    <source src="{{ asset('sounds/tick.mp3') }}" type="audio/mpeg">
</audio>

<audio id="timeoutSound" preload="auto">
    <source src="{{ asset('sounds/timeout.mp3') }}" type="audio/mpeg">
</audio>

<script>
let timeLeft = {{ $params['answer_time'] }};
let totalTime = {{ $params['answer_time'] }};
let answered = false;

// Animation de la barre de temps
const timerBar = document.getElementById('timerBar');
timerBar.style.width = '100%';

// Démarrer le son tic-tac en boucle dès le début
const tickSound = document.getElementById('tickSound');
tickSound.currentTime = 0;
tickSound.play().catch(e => console.log('Audio play failed:', e));

const timerInterval = setInterval(() => {
    timeLeft--;
    const percentage = (timeLeft / totalTime) * 100;
    timerBar.style.width = percentage + '%';
    document.getElementById('timerText').textContent = timeLeft + 's';
    
    // Changement de couleur à 3 secondes
    if (timeLeft <= 3) {
        timerBar.classList.add('warning');
    }
    
    // Temps écoulé
    if (timeLeft <= 0) {
        clearInterval(timerInterval);
        tickSound.pause(); // Arrêter le son tic-tac
        if (!answered) {
            handleTimeout();
        }
    }
}, 1000);

function selectAnswer(index) {
    if (answered) return;
    answered = true;
    
    clearInterval(timerInterval);
    
    // Arrêter le son tic-tac
    const tickSound = document.getElementById('tickSound');
    tickSound.pause();
    
    // Marquer la réponse choisie
    document.getElementById('answerIndex').value = index;
    
    // Désactiver tous les boutons
    document.querySelectorAll('.answer-bubble').forEach(bubble => {
        bubble.classList.add('disabled');
    });
    
    // Soumettre le formulaire
    setTimeout(() => {
        document.getElementById('answerForm').submit();
    }, 200);
}

function handleTimeout() {
    if (answered) return;
    answered = true;
    
    // Jouer son de timeout
    const timeoutSound = document.getElementById('timeoutSound');
    timeoutSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver tous les boutons
    document.querySelectorAll('.answer-bubble').forEach(bubble => {
        bubble.classList.add('disabled');
    });
    
    // Rediriger vers les stats (timeout = échec)
    setTimeout(() => {
        window.location.href = "{{ route('solo.stat') }}";
    }, 2000);
}

function activateSkill(skillName, skillIndex) {
    const skillButton = document.getElementById('skill-' + skillIndex);
    
    // Vérifier si le skill a déjà été utilisé (classe "used" présente)
    if (skillButton.classList.contains('used')) {
        return;
    }
    
    console.log('Skill activé:', skillName);
    
    // Marquer le skill comme utilisé visuellement immédiatement
    skillButton.classList.add('used');
    
    // Enregistrer l'utilisation du skill dans la session via l'API
    fetch("{{ route('solo.use-skill') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
        },
        body: JSON.stringify({ skill_name: skillName })
    }).then(response => response.json())
      .then(data => {
          console.log('Skill enregistré:', data);
      })
      .catch(error => {
          console.error('Erreur lors de l\'enregistrement du skill:', error);
      });
    
    // Implémenter l'effet du skill selon le nom
    if (skillName === 'Calcul Rapide') {
        // Illuminer la bonne réponse en utilisant data-index pour gérer les questions Vrai/Faux
        const correctIndex = {{ $correctIndex }};
        const correctBubble = document.querySelector(`.answer-bubble[data-index="${correctIndex}"]`);
        
        if (correctBubble) {
            correctBubble.classList.add('highlighted');
            
            // Afficher un message de confirmation
            const skillMessage = document.createElement('div');
            skillMessage.textContent = '✨ Bonne réponse révélée !';
            skillMessage.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
                color: white;
                padding: 15px 30px;
                border-radius: 25px;
                font-weight: bold;
                box-shadow: 0 10px 30px rgba(78, 205, 196, 0.5);
                z-index: 10000;
                animation: slideDown 0.5s ease-out;
            `;
            document.body.appendChild(skillMessage);
            
            // Supprimer le message après 3 secondes
            setTimeout(() => {
                skillMessage.style.animation = 'slideUp 0.5s ease-out';
                setTimeout(() => skillMessage.remove(), 500);
            }, 3000);
        }
    }
    // Autres skills à implémenter plus tard...
}
</script>
@endsection
