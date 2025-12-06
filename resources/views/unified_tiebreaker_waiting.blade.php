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

    .waiting-container {
        max-width: 600px;
        width: 100%;
        text-align: center;
    }

    .title {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        margin-bottom: 20px;
    }

    .status-card {
        background: rgba(255,255,255,0.05);
        border: 3px solid rgba(78, 205, 196, 0.5);
        border-radius: 20px;
        padding: 40px;
        margin-bottom: 30px;
    }

    .player-status {
        margin-bottom: 30px;
    }

    .status-icon {
        font-size: 4rem;
        margin-bottom: 15px;
    }

    .status-icon.correct {
        color: #4CAF50;
    }

    .status-icon.incorrect {
        color: #f44336;
    }

    .status-text {
        font-size: 1.3rem;
        font-weight: 700;
    }

    .waiting-animation {
        margin: 30px 0;
    }

    .dots {
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .dot {
        width: 15px;
        height: 15px;
        background: #4ECDC4;
        border-radius: 50%;
        animation: bounce 1.4s ease-in-out infinite both;
    }

    .dot:nth-child(1) { animation-delay: -0.32s; }
    .dot:nth-child(2) { animation-delay: -0.16s; }
    .dot:nth-child(3) { animation-delay: 0s; }

    .opponent-status {
        font-size: 1.1rem;
        color: #B0B0B0;
        margin-top: 20px;
    }

    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }
</style>

<div class="waiting-container">
    <h1 class="title">
        @if(isset($sudden_death) && $sudden_death)
            💀 {{ __('SUDDEN DEATH') }}
        @else
            ❓ {{ __('QUESTION BONUS') }}
        @endif
    </h1>

    <div class="status-card">
        <div class="player-status">
            <div class="status-icon {{ $player_correct ? 'correct' : 'incorrect' }}">
                {{ $player_correct ? '✓' : '✗' }}
            </div>
            <div class="status-text">
                @if($player_correct)
                    {{ __('Bonne réponse !') }}
                @else
                    {{ __('Mauvaise réponse...') }}
                @endif
            </div>
        </div>

        <div class="waiting-animation">
            <div class="dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        </div>

        <div class="opponent-status">
            {{ __('En attente de la réponse de votre adversaire...') }}
        </div>
    </div>
</div>

<script>
const mode = "{{ $mode }}";
const matchId = "{{ $match_id ?? '' }}";
const isSuddenDeath = {{ isset($sudden_death) && $sudden_death ? 'true' : 'false' }};

if (typeof firebase !== 'undefined' && firebase.firestore && matchId) {
    const db = firebase.firestore();
    const opponentId = "{{ $gameState['opponent_id'] ?? '' }}";
    
    db.collection('matches').doc(matchId).onSnapshot(doc => {
        const data = doc.data();
        if (data) {
            let opponentAnswered = false;
            
            if (isSuddenDeath) {
                const questionNumber = {{ $gameState['sudden_death_question_number'] ?? 1 }};
                opponentAnswered = data[`sudden_death_answer_${opponentId}_q${questionNumber}`] !== undefined;
            } else {
                opponentAnswered = data[`tiebreaker_answer_${opponentId}`] !== undefined;
            }
            
            if (opponentAnswered) {
                window.location.href = "{{ route('game.match-result', ['mode' => $mode]) }}";
            }
        }
    });
}

setTimeout(() => {
    window.location.href = "{{ route('game.match-result', ['mode' => $mode]) }}";
}, 30000);
</script>
@endsection
