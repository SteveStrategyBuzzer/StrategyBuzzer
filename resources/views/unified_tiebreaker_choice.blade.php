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

    .players-display {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 40px;
        margin: 30px 0;
    }

    .player-card {
        background: rgba(255,255,255,0.05);
        border-radius: 15px;
        padding: 20px;
        min-width: 150px;
    }

    .player-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        margin: 0 auto 10px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
    }

    .player-name {
        font-weight: 700;
        font-size: 1.1rem;
        color: #fff;
    }

    .player-score {
        color: #4ECDC4;
        font-size: 1.5rem;
        font-weight: 800;
    }

    .vs-badge {
        font-size: 2rem;
        font-weight: 900;
        color: #FFD700;
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
        animation: timerCountdown 15s linear forwards;
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

    .option-card.disabled {
        opacity: 0.5;
        pointer-events: none;
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

    .host-indicator {
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: white;
        padding: 10px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: inline-block;
    }

    .waiting-indicator {
        background: rgba(255, 193, 7, 0.2);
        border: 2px solid #FFC107;
        color: #FFC107;
        padding: 15px 25px;
        border-radius: 10px;
        margin-top: 20px;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.02); opacity: 0.9; }
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
        .title { font-size: 2rem; }
        .options-grid { grid-template-columns: 1fr; gap: 20px; }
        .option-title { font-size: 1.4rem; }
        .option-icon { font-size: 3rem; }
        .players-display { flex-direction: column; gap: 20px; }
    }
</style>

<div class="tiebreaker-container">
    <div class="header-section">
        <h1 class="title">⚔️ {{ __('JEU DÉCISIF') }} ⚔️</h1>
        <p class="subtitle">{{ __('Égalité parfaite ! Choisissez votre mode de départage') }}</p>
        
        @if($is_multiplayer)
        <div class="players-display">
            <div class="player-card">
                <div class="player-avatar">
                    @if($player_avatar)
                        <img src="{{ asset('images/avatars/' . $player_avatar . '.png') }}" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.textContent='👤';">
                    @else
                        👤
                    @endif
                </div>
                <div class="player-name">{{ $player_name }}</div>
                <div class="player-score">{{ $player_score }} pts</div>
            </div>
            <div class="vs-badge">VS</div>
            <div class="player-card">
                <div class="player-avatar">
                    @if($opponent_avatar)
                        <img src="{{ asset('images/avatars/' . $opponent_avatar . '.png') }}" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.textContent='👤';">
                    @else
                        👤
                    @endif
                </div>
                <div class="player-name">{{ $opponent_name }}</div>
                <div class="player-score">{{ $opponent_score }} pts</div>
            </div>
        </div>
        
        @if($is_host)
        <div class="host-indicator">👑 {{ __('Vous choisissez le mode de départage') }}</div>
        @else
        <div class="waiting-indicator">⏳ {{ __('L\'hôte choisit le mode de départage...') }}</div>
        @endif
        
        <div class="timer-bar">
            <div class="timer-fill" id="timerFill"></div>
        </div>
        @endif
    </div>

    @if(!$is_multiplayer || $is_host)
    <div class="options-grid">
        <div class="option-card" data-option="bonus" onclick="selectOption('bonus')">
            <div class="option-icon">❓</div>
            <div class="option-title">{{ __('Question Bonus') }}</div>
            <div class="option-description">
                {{ __('Une question décisive avec buzz et réponse') }}
            </div>
            <div class="option-rules">
                <div class="rule-item">{{ __('Le plus rapide à buzzer et répondre correctement gagne') }}</div>
                <div class="rule-item">{{ __('Erreur = défaite immédiate') }}</div>
                <div class="rule-item">{{ __('Double réussite = le plus rapide l\'emporte') }}</div>
            </div>
        </div>

        <div class="option-card" data-option="efficiency" onclick="selectOption('efficiency')">
            <div class="option-icon">📊</div>
            <div class="option-title">{{ __('Efficacité Globale') }}</div>
            <div class="option-description">
                {{ __('Départage selon les performances du match') }}
            </div>
            <div class="option-rules">
                <div class="rule-item">{{ __('Comparaison de l\'efficacité globale') }}</div>
                <div class="rule-item">{{ __('Précision + vitesse moyenne') }}</div>
                <div class="rule-item">{{ __('Le meilleur profil l\'emporte') }}</div>
            </div>
        </div>

        <div class="option-card" data-option="sudden_death" onclick="selectOption('sudden_death')">
            <div class="option-icon">💀</div>
            <div class="option-title">{{ __('Sudden Death') }}</div>
            <div class="option-description">
                {{ __('Questions jusqu\'au premier échec') }}
            </div>
            <div class="option-rules">
                <div class="rule-item">{{ __('Chacun répond à la même question') }}</div>
                <div class="rule-item">{{ __('Première erreur = défaite immédiate') }}</div>
                <div class="rule-item">{{ __('Continue jusqu\'à ce qu\'un joueur se trompe') }}</div>
            </div>
        </div>
    </div>
    @endif

    <div class="vote-status" id="voteStatus">
        <div class="vote-info" id="voteInfo"></div>
    </div>
</div>

<form id="tiebreakerForm" action="{{ route('game.tiebreaker-select', ['mode' => $mode]) }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="choice" id="choiceInput">
</form>

<script>
const i18n = {
    selected: "{{ __('SÉLECTIONNÉ') }}",
    yourChoice: "{{ __('Votre choix') }}",
    redirecting: "{{ __('Redirection en cours...') }}",
    questionBonus: "{{ __('Question Bonus') }}",
    globalEfficiency: "{{ __('Efficacité Globale') }}",
    suddenDeath: "{{ __('Sudden Death') }}"
};

let selectedOption = null;
const isMultiplayer = {{ $is_multiplayer ? 'true' : 'false' }};
const isHost = {{ $is_host ? 'true' : 'false' }};
const mode = "{{ $mode }}";
const matchId = "{{ $match_id ?? '' }}";

function selectOption(option) {
    if (selectedOption) return;
    
    document.querySelectorAll('.option-card').forEach(card => {
        card.classList.remove('selected');
        card.classList.add('disabled');
        const badge = card.querySelector('.selected-badge');
        if (badge) badge.remove();
    });

    const card = document.querySelector(`[data-option="${option}"]`);
    card.classList.add('selected');
    card.classList.remove('disabled');
    
    const badge = document.createElement('div');
    badge.className = 'selected-badge';
    badge.textContent = '✓ ' + i18n.selected;
    card.appendChild(badge);

    selectedOption = option;

    document.getElementById('voteStatus').classList.add('show');
    document.getElementById('voteInfo').textContent = `${i18n.yourChoice}: ${getOptionName(option)}. ${i18n.redirecting}`;

    if (isMultiplayer && matchId) {
        syncChoiceToFirebase(option);
    }

    setTimeout(() => {
        document.getElementById('choiceInput').value = option;
        document.getElementById('tiebreakerForm').submit();
    }, 800);
}

function syncChoiceToFirebase(option) {
    if (typeof firebase !== 'undefined' && firebase.firestore) {
        const db = firebase.firestore();
        db.collection('matches').doc(matchId).update({
            tiebreaker_choice: option,
            tiebreaker_chosen_at: firebase.firestore.FieldValue.serverTimestamp()
        }).catch(err => console.error('Firebase sync error:', err));
    }
}

function getOptionName(option) {
    const names = {
        'bonus': i18n.questionBonus,
        'efficiency': i18n.globalEfficiency,
        'sudden_death': i18n.suddenDeath
    };
    return names[option] || option;
}

@if($is_multiplayer && !$is_host)
if (typeof firebase !== 'undefined' && firebase.firestore && matchId) {
    const db = firebase.firestore();
    db.collection('matches').doc(matchId).onSnapshot(doc => {
        const data = doc.data();
        if (data && data.tiebreaker_choice) {
            window.location.href = "{{ route('game.tiebreaker-select', ['mode' => $mode]) }}".replace('{{ $mode }}', mode) + 
                '?choice=' + data.tiebreaker_choice + '&_token={{ csrf_token() }}';
        }
    });
}
@endif

@if(!$is_multiplayer)
setTimeout(() => {
    if (!selectedOption) {
        selectOption('bonus');
    }
}, 15000);
@endif
</script>
@endsection
