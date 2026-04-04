@extends('layouts.game')

@section('game-data')
<script>
window.ROOM_ID           = @json($params['room_id'] ?? null);
window.JWT_TOKEN         = @json($params['jwt_token'] ?? null);
window.LOBBY_CODE        = @json($params['lobby_code'] ?? null);
window.CURRENT_USER_ID   = @json((string)(auth()->id() ?? ''));
window.NO_SOCKET_OVERLAY = true;
window.GR_HIDE_HEADER    = true;
</script>
@endsection

@push('head')
{{-- Préchargement des ressources critiques de la page question --}}
<link rel="prefetch" href="{{ asset('images/buzzer.png') }}" as="image">
<link rel="prefetch" href="{{ asset('sounds/buzzer_default_1.mp3') }}" as="audio">
<link rel="prefetch" href="{{ asset('sounds/no_buzz.mp3') }}" as="audio">
<link rel="preload" href="{{ asset('images/buzzer.png') }}" as="image">
@endpush

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

// Avatars non-avantageux en mode Solo (skills orientés multijoueur)
$soloDisadvantagedAvatars = [
    'defenseur' => 'Cet avatar ne sera pas nécessaire en mode Solo car il n\'y aura pas d\'attaque des joueurs adverses.',
    'comedienne' => 'Cet avatar ne vous sera pas avantageux en mode Solo car ses skills affectent les adversaires humains.',
];
$playerAvatarKey = strtolower($playerAvatar);
$showSoloWarning = ($mode === 'solo') && isset($soloDisadvantagedAvatars[$playerAvatarKey]);
$soloWarningMessage = $showSoloWarning ? $soloDisadvantagedAvatars[$playerAvatarKey] : '';

$playerId = auth()->id();
$opponentId = $params['opponent_id'] ?? null;
$matchId = $params['match_id'] ?? null;
$sessionId = $params['session_id'] ?? $matchId;
$isHost = $params['is_host'] ?? false;

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
$themeDisplay = $theme === 'Culture générale' ? __('Général') : __($theme);

$modeLabels = [
    'duo' => 'Duo',
    'league_individual' => 'Ligue',
    'league_team' => 'Ligue Équipe',
    'master' => 'Maître du Jeu',
];
$modeLabel = $modeLabels[$mode] ?? ucfirst($mode);
@endphp

<style>
body { 
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); 
    color: #fff; 
    min-height: 100vh;
    overflow: hidden;
    margin: 0;
}

.intro-container {
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
    animation: fadeInDown 0.8s ease;
}

.title-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 0 4px 15px rgba(0,0,0,0.5);
    margin-bottom: 10px;
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


/* Mobile portrait */
@media (max-width: 768px) and (orientation: portrait) {
    .intro-container {
        padding: 15px 10px;
        justify-content: flex-start;
        padding-top: 30px;
    }
    
    .title-section h1 {
        font-size: 1.5rem;
    }
    
    .theme-badge {
        font-size: 0.9rem;
        padding: 6px 16px;
    }
    
    .versus-section {
        flex-direction: row;
        gap: 15px;
        margin: 15px 0;
    }
    
    .versus-text {
        font-size: 1.5rem;
    }
    
    .player-card {
        min-width: 120px;
        max-width: 140px;
        padding: 15px 10px;
    }
    
    .player-avatar {
        width: 70px;
        height: 70px;
    }
    
    .player-name {
        font-size: 0.95rem;
    }
    
    .player-division {
        font-size: 0.75rem;
    }
    
}

/* Mobile landscape */
@media (max-width: 768px) and (orientation: landscape) {
    .versus-section {
        gap: 20px;
    }
    
    .versus-text {
        font-size: 1.8rem;
    }
    
    .player-card {
        min-width: 140px;
        padding: 15px;
    }
    
    .player-avatar {
        width: 60px;
        height: 60px;
    }
    
    .title-section h1 {
        font-size: 1.5rem;
    }
}

/* Popup avertissement avatar non-avantageux en Solo */
.solo-warning-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.solo-warning-popup {
    background: linear-gradient(145deg, #2d1f3d, #1a1a2e);
    border: 2px solid #f39c12;
    border-radius: 20px;
    padding: 30px;
    max-width: 400px;
    margin: 20px;
    position: relative;
    box-shadow: 0 0 40px rgba(243, 156, 18, 0.3);
    animation: scaleIn 0.3s ease;
}

.solo-warning-close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 1.8rem;
    cursor: pointer;
    color: rgba(255,255,255,0.6);
    transition: color 0.2s, transform 0.2s;
    background: none;
    border: none;
}

.solo-warning-close:hover {
    color: #fff;
    transform: scale(1.2);
}

.solo-warning-icon {
    font-size: 3rem;
    text-align: center;
    margin-bottom: 15px;
}

.solo-warning-title {
    font-size: 1.3rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 15px;
    color: #f39c12;
}

.solo-warning-message {
    font-size: 1rem;
    line-height: 1.5;
    text-align: center;
    color: rgba(255,255,255,0.85);
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
</style>

<div class="intro-container">
    <div class="title-section">
        <h1>🎮 {{ __('Ladies and Gentlemen') }} 🎮</h1>
        <div class="theme-badge">
            <span>{{ $themeIcon }}</span> {{ $themeDisplay }}
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
    
{{-- countdown-section removed: brain overlay from layouts.game shows during INTRO phase --}}
</div>

@if($showSoloWarning)
<div class="solo-warning-overlay" id="soloWarningOverlay">
    <div class="solo-warning-popup">
        <button class="solo-warning-close" onclick="closeSoloWarning()">&times;</button>
        <div class="solo-warning-icon">⚠️</div>
        <div class="solo-warning-title">{{ __('Avertissement Avatar') }}</div>
        <div class="solo-warning-message">{{ __($soloWarningMessage) }}</div>
    </div>
</div>
@endif

<audio id="readyAudio" preload="auto">
    <source src="{{ asset('sounds/ready_announcement.mp3') }}" type="audio/mpeg">
</audio>

{{-- socket.io and DuoSocketClient loaded by layouts.game --}}

<script>
// Fonction pour fermer le popup d'avertissement
function closeSoloWarning() {
    const overlay = document.getElementById('soloWarningOverlay');
    if (overlay) {
        overlay.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => overlay.remove(), 300);
    }
}

(function() {
    const redirectUrl = @json($redirectUrl);
    const mode = @json($mode);
    const matchId = @json($params['match_id'] ?? null);

    let redirected = false;
    let questionPrefetched = false;

    async function prefetchFirstQuestion() {
        if (questionPrefetched || !matchId) return;
        try {
            const response = await fetch('/game/' + mode + '/fetch-question', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ match_id: matchId, question_number: 1 })
            });
            if (response.ok) {
                questionPrefetched = true;
                console.log('[Intro] First question prefetched');
            }
        } catch (err) {
            console.warn('[Intro] Question prefetch failed:', err.message);
        }
    }

    function navigateToQuestion() {
        if (!redirected && redirectUrl) {
            redirected = true;
            window.location.href = redirectUrl;
        }
    }

    // Navigation driven exclusively by server Socket.IO events — no local timer authority
    if (typeof DuoSocketClient !== 'undefined') {
        DuoSocketClient.on('phase_changed', function(data) {
            if (data && data.phase === 'QUESTION_ACTIVE') navigateToQuestion();
        });
        // state event: { state: GameState } — used for reconnect hydration
        DuoSocketClient.on('state', function(payload) {
            var phase = payload && (payload.state ? payload.state.phase : payload.phase);
            if (phase === 'QUESTION_ACTIVE') navigateToQuestion();
        });
        DuoSocketClient.on('question_published', function() {
            navigateToQuestion();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        prefetchFirstQuestion();
        const audio = document.getElementById('readyAudio');
        if (audio) {
            audio.volume = 1.0;
            audio.play().catch(() => {});
        }
    });
})();
</script>
@endsection
