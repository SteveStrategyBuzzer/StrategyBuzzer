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
        padding: 20px;
        margin: 0;
    }

    .tiebreaker-container {
        max-width: 1200px;
        width: 100%;
        text-align: center;
    }

    .header-section {
        margin-bottom: 40px;
    }

    .title {
        font-size: 3rem;
        font-weight: 900;
        background: linear-gradient(135deg, #FFD700, #FFA500, #FF6347);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: 0 0 30px rgba(255, 215, 0, 0.5);
        margin-bottom: 15px;
        animation: pulse 2s ease-in-out infinite;
    }

    .subtitle {
        font-size: 1.3rem;
        color: #4ECDC4;
        font-weight: 600;
    }

    .timer-bar {
        width: 100%;
        height: 6px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        margin-top: 20px;
        overflow: hidden;
    }

    .timer-fill {
        height: 100%;
        background: linear-gradient(90deg, #FFD700, #FF6347);
        width: 100%;
        animation: timerCountdown 10s linear forwards;
    }

    .options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 30px;
        margin-bottom: 30px;
    }

    .option-card {
        background: rgba(255,255,255,0.05);
        border: 3px solid rgba(102, 126, 234, 0.3);
        border-radius: 20px;
        padding: 30px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .option-card:hover {
        transform: translateY(-5px);
        border-color: #667eea;
        box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
    }

    .option-card.selected {
        border-color: #FFD700;
        background: rgba(255, 215, 0, 0.1);
        box-shadow: 0 0 40px rgba(255, 215, 0, 0.5);
    }

    .option-icon {
        font-size: 4rem;
        margin-bottom: 15px;
        animation: float 3s ease-in-out infinite;
    }

    .option-title {
        font-size: 1.8rem;
        font-weight: 800;
        color: #FFD700;
        margin-bottom: 15px;
        text-transform: uppercase;
    }

    .option-description {
        font-size: 1rem;
        color: #B0B0B0;
        line-height: 1.6;
        margin-bottom: 20px;
    }

    .option-rules {
        background: rgba(0,0,0,0.3);
        border-radius: 12px;
        padding: 15px;
        margin-top: 15px;
        text-align: left;
    }

    .rule-item {
        font-size: 0.9rem;
        color: #FFFFFF;
        margin-bottom: 8px;
        padding-left: 20px;
        position: relative;
    }

    .rule-item:before {
        content: "▸";
        position: absolute;
        left: 0;
        color: #4ECDC4;
        font-weight: bold;
    }

    .vote-status {
        margin-top: 30px;
        padding: 20px;
        background: rgba(0,0,0,0.3);
        border-radius: 15px;
        display: none;
    }

    .vote-status.show {
        display: block;
    }

    .vote-info {
        font-size: 1.1rem;
        color: #4ECDC4;
        margin-bottom: 10px;
    }

    .selected-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: linear-gradient(135deg, #FFD700, #FFA500);
        color: #003DA5;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    @keyframes timerCountdown {
        from { width: 100%; }
        to { width: 0%; }
    }

    @media (max-width: 768px) {
        .title {
            font-size: 2rem;
        }

        .options-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .option-title {
            font-size: 1.4rem;
        }

        .option-icon {
            font-size: 3rem;
        }
    }
</style>

<div class="tiebreaker-container">
    <div class="header-section">
        <h1 class="title">⚔️ {{ __('JEU DÉCISIF') }} ⚔️</h1>
        <p class="subtitle">{{ __('Choisissez votre mode de départage') }}</p>
        
        @if(isset($params['is_multiplayer']) && $params['is_multiplayer'])
        <div class="timer-bar">
            <div class="timer-fill"></div>
        </div>
        @endif
    </div>

    <div class="options-grid">
        <!-- Option A: Question Bonus -->
        <div class="option-card" data-option="bonus" onclick="selectOption('bonus')">
            <div class="option-icon">❓</div>
            <div class="option-title">{{ __('Question Bonus') }}</div>
            <div class="option-description">
                {{ __('Une question décisive avec buzz et réponse') }}
            </div>
            <div class="option-rules">
                <div class="rule-item">{{ __('Un seul buzz → l'autre perd automatiquement') }}</div>
                <div class="rule-item">{{ __('Seul buzzeur échoue → double échec → Option B') }}</div>
                <div class="rule-item">{{ __('Double réussite → le plus rapide gagne 2pts, l'autre 1pt') }}</div>
                <div class="rule-item">{{ __('Une seule réussite → l'autre perd') }}</div>
            </div>
        </div>

        <!-- Option B: Efficacité -->
        <div class="option-card" data-option="efficiency" onclick="selectOption('efficiency')">
            <div class="option-icon">📊</div>
            <div class="option-title">{{ __('Efficacité Globale') }}</div>
            <div class="option-description">
                {{ __('Départage selon les performances du match') }}
            </div>
            <div class="option-rules">
                <div class="rule-item">{{ __('Comparaison de l'efficacité globale') }}</div>
                <div class="rule-item">{{ __('Si égalité → score de points gagnés') }}</div>
                <div class="rule-item">{{ __('Le meilleur profil l'emporte') }}</div>
            </div>
        </div>

        <!-- Option C: Sudden Death -->
        <div class="option-card" data-option="sudden_death" onclick="selectOption('sudden_death')">
            <div class="option-icon">💀</div>
            <div class="option-title">{{ __('Sudden Death') }}</div>
            <div class="option-description">
                {{ __('Questions jusqu'au premier échec') }}
            </div>
            <div class="option-rules">
                <div class="rule-item">{{ __('Pas de course de vitesse') }}</div>
                <div class="rule-item">{{ __('Première erreur → défaite immédiate') }}</div>
                <div class="rule-item">{{ __('Tension maximale garantie') }}</div>
            </div>
        </div>
    </div>

    <div class="vote-status" id="voteStatus">
        <div class="vote-info" id="voteInfo"></div>
    </div>
</div>

<script>
let selectedOption = null;
const isSolo = {{ isset($params['is_multiplayer']) && $params['is_multiplayer'] ? 'false' : 'true' }};
const gameMode = "{{ $params['game_mode'] ?? 'solo' }}";

function selectOption(option) {
    // Retirer la sélection précédente
    document.querySelectorAll('.option-card').forEach(card => {
        card.classList.remove('selected');
        const badge = card.querySelector('.selected-badge');
        if (badge) badge.remove();
    });

    // Ajouter la nouvelle sélection
    const card = document.querySelector(`[data-option="${option}"]`);
    card.classList.add('selected');
    
    const badge = document.createElement('div');
    badge.className = 'selected-badge';
    badge.textContent = '{{ __('SÉLECTIONNÉ') }}';
    card.appendChild(badge);

    selectedOption = option;

    if (isSolo) {
        // En solo, appliquer directement
        setTimeout(() => {
            applyTiebreaker(option);
        }, 500);
    } else {
        // En multijoueur, enregistrer le vote
        submitVote(option);
    }
}

function submitVote(option) {
    // TODO: Envoyer le vote via Firestore pour multijoueur
    console.log('Vote submitted:', option);
    
    document.getElementById('voteStatus').classList.add('show');
    document.getElementById('voteInfo').textContent = `Votre choix: ${getOptionName(option)}. En attente des autres joueurs...`;
}

function applyTiebreaker(mode) {
    // Rediriger vers le mode approprié
    let targetRoute = '';
    
    switch(mode) {
        case 'bonus':
            targetRoute = "{{ route('solo.tiebreaker-bonus') }}";
            break;
        case 'efficiency':
            targetRoute = "{{ route('solo.tiebreaker-efficiency') }}";
            break;
        case 'sudden_death':
            targetRoute = "{{ route('solo.tiebreaker-sudden-death') }}";
            break;
    }

    if (targetRoute) {
        window.location.href = targetRoute;
    }
}

function getOptionName(option) {
    const names = {
        'bonus': 'Question Bonus',
        'efficiency': 'Efficacité Globale',
        'sudden_death': 'Sudden Death'
    };
    return names[option] || option;
}

// Auto-sélection après 10 secondes en multijoueur
if (!isSolo) {
    setTimeout(() => {
        if (!selectedOption) {
            // Sélectionner aléatoirement
            const options = ['bonus', 'efficiency', 'sudden_death'];
            const randomOption = options[Math.floor(Math.random() * options.length)];
            selectOption(randomOption);
        }
    }, 10000);
}
</script>
@endsection
