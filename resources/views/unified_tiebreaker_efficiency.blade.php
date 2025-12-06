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

    .efficiency-container {
        max-width: 900px;
        width: 100%;
        text-align: center;
    }

    .header-section {
        margin-bottom: 40px;
    }

    .title {
        font-size: 2.5rem;
        font-weight: 900;
        background: linear-gradient(135deg, #4ECDC4, #44A08D);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 15px;
    }

    .subtitle {
        font-size: 1.2rem;
        color: #B0B0B0;
    }

    .comparison-container {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 60px;
        margin: 50px 0;
    }

    .player-column {
        text-align: center;
    }

    .player-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        margin: 0 auto 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
    }

    .player-avatar.winner {
        box-shadow: 0 0 30px rgba(255, 215, 0, 0.8);
        border: 4px solid #FFD700;
    }

    .player-name {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .efficiency-bar-container {
        width: 80px;
        height: 250px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
    }

    .efficiency-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, #4ECDC4, #44A08D);
        border-radius: 0 0 10px 10px;
        transition: height 2s ease-out;
    }

    .efficiency-bar.player {
        background: linear-gradient(to top, #667eea, #764ba2);
    }

    .efficiency-value {
        font-size: 2.5rem;
        font-weight: 900;
        margin-top: 20px;
    }

    .efficiency-value.winner {
        color: #FFD700;
    }

    .vs-section {
        font-size: 2rem;
        font-weight: 900;
        color: #FFD700;
        padding: 20px;
    }

    .result-banner {
        margin-top: 40px;
        padding: 30px;
        border-radius: 20px;
        font-size: 2rem;
        font-weight: 900;
    }

    .result-banner.victory {
        background: linear-gradient(135deg, rgba(76, 175, 80, 0.3), rgba(139, 195, 74, 0.3));
        border: 3px solid #4CAF50;
        color: #4CAF50;
    }

    .result-banner.defeat {
        background: linear-gradient(135deg, rgba(244, 67, 54, 0.3), rgba(229, 57, 53, 0.3));
        border: 3px solid #f44336;
        color: #f44336;
    }

    .result-banner.draw {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.3), rgba(255, 152, 0, 0.3));
        border: 3px solid #FFC107;
        color: #FFC107;
    }

    .continue-btn {
        margin-top: 40px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 18px 50px;
        border-radius: 30px;
        font-size: 1.3rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .continue-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
    }

    @media (max-width: 768px) {
        .title { font-size: 1.8rem; }
        .comparison-container { flex-direction: column; gap: 30px; }
        .efficiency-bar-container { width: 200px; height: 60px; }
        .efficiency-bar { left: 0; bottom: 0; top: 0; right: auto; width: 0; height: 100%; border-radius: 10px 0 0 10px; }
    }
</style>

<div class="efficiency-container">
    <div class="header-section">
        <h1 class="title">📊 {{ __('EFFICACITÉ GLOBALE') }}</h1>
        <p class="subtitle">{{ __('Comparaison des performances du match') }}</p>
    </div>

    <div class="comparison-container">
        <div class="player-column">
            <div class="player-avatar {{ $winner === 'player' ? 'winner' : '' }}">
                @if($player_avatar)
                    <img src="{{ asset('images/avatars/' . $player_avatar . '.png') }}" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.textContent='👤';">
                @else
                    👤
                @endif
            </div>
            <div class="player-name">{{ $player_name }}</div>
            <div class="efficiency-bar-container">
                <div class="efficiency-bar player" id="playerBar" style="height: 0%;"></div>
            </div>
            <div class="efficiency-value {{ $winner === 'player' ? 'winner' : '' }}" id="playerValue">0%</div>
        </div>

        <div class="vs-section">VS</div>

        <div class="player-column">
            <div class="player-avatar {{ $winner === 'opponent' ? 'winner' : '' }}">
                @if($opponent_avatar)
                    <img src="{{ asset('images/avatars/' . $opponent_avatar . '.png') }}" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.textContent='👤';">
                @else
                    👤
                @endif
            </div>
            <div class="player-name">{{ $opponent_name }}</div>
            <div class="efficiency-bar-container">
                <div class="efficiency-bar" id="opponentBar" style="height: 0%;"></div>
            </div>
            <div class="efficiency-value {{ $winner === 'opponent' ? 'winner' : '' }}" id="opponentValue">0%</div>
        </div>
    </div>

    <div class="result-banner {{ $winner === 'player' ? 'victory' : ($winner === 'opponent' ? 'defeat' : 'draw') }}">
        @if($winner === 'player')
            🏆 {{ __('VICTOIRE !') }}
        @elseif($winner === 'opponent')
            😔 {{ __('DÉFAITE') }}
        @else
            🤝 {{ __('ÉGALITÉ PARFAITE') }}
        @endif
    </div>

    <a href="{{ route('game.match-result', ['mode' => $mode]) }}" class="continue-btn">
        {{ __('Voir le résultat final') }} →
    </a>
</div>

<script>
const playerEfficiency = {{ $player_efficiency }};
const opponentEfficiency = {{ $opponent_efficiency }};

setTimeout(() => {
    document.getElementById('playerBar').style.height = playerEfficiency + '%';
    document.getElementById('opponentBar').style.height = opponentEfficiency + '%';
    
    animateValue('playerValue', 0, playerEfficiency, 2000);
    animateValue('opponentValue', 0, opponentEfficiency, 2000);
}, 500);

function animateValue(id, start, end, duration) {
    const element = document.getElementById(id);
    const range = end - start;
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const value = Math.round(start + range * progress);
        element.textContent = value + '%';
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    requestAnimationFrame(update);
}
</script>
@endsection
