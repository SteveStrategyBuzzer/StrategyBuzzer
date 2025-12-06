@extends('layouts.app')

@section('content')
@php
$mode = $params['mode'] ?? 'duo';
$theme = $params['theme'] ?? 'Culture générale';
$nbQuestions = $params['nb_questions'] ?? 10;
$playerName = $params['player_name'] ?? 'Joueur 1';
$playerAvatar = $params['player_avatar'] ?? 'default';
$opponentName = $params['opponent_name'] ?? 'Joueur 2';
$opponentAvatar = $params['opponent_avatar'] ?? 'default';
$playerDivision = $params['player_division'] ?? 'Bronze';
$opponentDivision = $params['opponent_division'] ?? 'Bronze';
$redirectUrl = $params['redirect_url'] ?? route('game.question', ['mode' => $mode]);

$themeIcons = [
    'Culture générale' => '🧠',
    'Géographie' => '🌐',
    'Histoire' => '📜',
    'Art' => '🎨',
    'Cinéma' => '🎬',
    'Sport' => '🏅',
    'Cuisine' => '🍳',
    'Animaux' => '🦁',
    'Sciences' => '🔬',
];
$themeIcon = $themeIcons[$theme] ?? '❓';
@endphp

<style>
body { 
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); 
    color: #fff; 
    min-height: 100vh;
    overflow: hidden;
    margin: 0;
}

.resume-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
    text-align: center;
}

.title-section {
    margin-bottom: 30px;
}

.title-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 0 4px 15px rgba(0,0,0,0.5);
    margin-bottom: 10px;
    animation: fadeInDown 0.8s ease;
}

.theme-badge {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 10px 25px;
    border-radius: 30px;
    font-size: 1.2rem;
    animation: fadeIn 1s ease 0.3s both;
}

.versus-section {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 40px;
    margin: 30px 0;
    animation: fadeIn 1s ease 0.5s both;
}

.player-card {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 24px;
    padding: 30px;
    min-width: 200px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.player-card.left {
    border-color: rgba(40, 167, 69, 0.5);
    box-shadow: 0 0 30px rgba(40, 167, 69, 0.2);
}

.player-card.right {
    border-color: rgba(255, 107, 107, 0.5);
    box-shadow: 0 0 30px rgba(255, 107, 107, 0.2);
}

.player-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255,255,255,0.3);
    margin-bottom: 15px;
}

.player-card.left .player-avatar {
    border-color: #28a745;
}

.player-card.right .player-avatar {
    border-color: #ff6b6b;
}

.player-name {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 8px;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.player-division {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    background: rgba(255,255,255,0.1);
    padding: 5px 15px;
    border-radius: 20px;
}

.versus-text {
    font-size: 3rem;
    font-weight: 900;
    color: #ffd700;
    text-shadow: 0 0 20px rgba(255,215,0,0.5);
    animation: pulse 1.5s ease infinite;
}

.countdown-section {
    margin-top: 40px;
    animation: fadeIn 1s ease 1s both;
}

.countdown-text {
    font-size: 1.5rem;
    margin-bottom: 15px;
    color: rgba(255,255,255,0.8);
}

.countdown-number {
    font-size: 6rem;
    font-weight: 900;
    color: #ffd700;
    text-shadow: 0 0 30px rgba(255,215,0,0.6);
    animation: countdownPulse 1s ease infinite;
}

.info-row {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-top: 30px;
    animation: fadeIn 1s ease 0.7s both;
}

.info-badge {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    padding: 12px 24px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-badge .icon {
    font-size: 1.3rem;
}

.info-badge .text {
    font-size: 1rem;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes countdownPulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.15); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

@media (max-width: 768px) {
    .versus-section {
        flex-direction: column;
        gap: 20px;
    }
    
    .versus-text {
        font-size: 2rem;
    }
    
    .player-card {
        min-width: 160px;
        padding: 20px;
    }
    
    .player-avatar {
        width: 90px;
        height: 90px;
    }
    
    .player-name {
        font-size: 1.1rem;
    }
    
    .countdown-number {
        font-size: 4rem;
    }
    
    .title-section h1 {
        font-size: 1.8rem;
    }
}
</style>

<div class="resume-container">
    <div class="title-section">
        <h1>🎮 {{ __('Ladies and Gentlemen') }} 🎮</h1>
        <div class="theme-badge">
            <span>{{ $themeIcon }}</span> {{ $theme }}
        </div>
    </div>
    
    <div class="versus-section">
        <div class="player-card left">
            @if(str_contains($playerAvatar, '/'))
                <img src="{{ asset($playerAvatar) }}" alt="{{ $playerName }}" class="player-avatar">
            @else
                <img src="{{ asset("images/avatars/standard/{$playerAvatar}.png") }}" alt="{{ $playerName }}" class="player-avatar">
            @endif
            <div class="player-name">{{ $playerName }}</div>
            <div class="player-division">{{ $playerDivision }}</div>
        </div>
        
        <div class="versus-text">VS</div>
        
        <div class="player-card right">
            @if(str_contains($opponentAvatar, '/'))
                <img src="{{ asset($opponentAvatar) }}" alt="{{ $opponentName }}" class="player-avatar">
            @else
                <img src="{{ asset("images/avatars/standard/{$opponentAvatar}.png") }}" alt="{{ $opponentName }}" class="player-avatar">
            @endif
            <div class="player-name">{{ $opponentName }}</div>
            <div class="player-division">{{ $opponentDivision }}</div>
        </div>
    </div>
    
    <div class="info-row">
        <div class="info-badge">
            <span class="icon">📝</span>
            <span class="text">{{ $nbQuestions }} {{ __('questions') }}</span>
        </div>
        <div class="info-badge">
            <span class="icon">🏆</span>
            <span class="text">{{ __('Best of 3') }}</span>
        </div>
    </div>
    
    <div class="countdown-section">
        <div class="countdown-text">{{ __('La partie commence dans') }}...</div>
        <div class="countdown-number" id="countdown">5</div>
    </div>
</div>

<script>
(function() {
    let count = 5;
    let redirected = false;
    const countdownEl = document.getElementById('countdown');
    const redirectUrl = @json($redirectUrl);
    
    if (!countdownEl || !redirectUrl) {
        console.error('Countdown or redirect URL missing');
        return;
    }

    const interval = setInterval(() => {
        count--;
        if (count > 0) {
            countdownEl.textContent = count;
        } else {
            countdownEl.textContent = '🚀';
            clearInterval(interval);
            
            if (!redirected) {
                redirected = true;
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 500);
            }
        }
    }, 1000);
    
    window.addEventListener('beforeunload', () => {
        clearInterval(interval);
    });
})();
</script>
@endsection
