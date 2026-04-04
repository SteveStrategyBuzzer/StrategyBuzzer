<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>StrategyBuzzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.png') }}">
    @stack('head')
    @yield('styles')
    <style>
    /* Brain overlay */
    #brainOverlay {
        position: fixed;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: rgba(15, 32, 39, 0.93);
        z-index: 9100;
        transition: opacity 0.3s ease;
    }
    #brainOverlay.hidden { display: none; }
    .brain-spin-img {
        width: 140px;
        height: 140px;
        animation: brainRotate 1.4s linear infinite, brainPulse 2.2s ease-in-out infinite;
    }
    .brain-msg {
        color: rgba(255,255,255,0.85);
        font-size: 1.2rem;
        margin-top: 20px;
        font-weight: 500;
        letter-spacing: 0.05em;
        text-align: center;
    }
    @keyframes brainRotate {
        0%   { transform: rotate(0deg) scale(1); }
        50%  { transform: rotate(180deg) scale(1.06); }
        100% { transform: rotate(360deg) scale(1); }
    }
    @keyframes brainPulse {
        0%, 100% { filter: drop-shadow(0 0 8px rgba(78,205,196,0.6)); }
        50%       { filter: drop-shadow(0 0 22px rgba(78,205,196,0.9)); }
    }

    /* Loading overlay */
    #loadingOverlay {
        position: fixed;
        inset: 0;
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        z-index: 9000;
        transition: opacity 0.3s ease;
    }
    #loadingOverlay.hidden { display: none; }
    #loadingOverlay .loading-content { text-align: center; }
    #loadingOverlay .loading-spinner {
        width: 70px;
        height: 70px;
        border: 4px solid rgba(78,205,196,0.3);
        border-top-color: #4ECDC4;
        border-radius: 50%;
        animation: grSpin 0.9s linear infinite;
        margin: 0 auto 18px;
    }
    #loadingOverlay .loading-text { color: #4ECDC4; font-size: 1.1rem; font-weight: 600; }
    @keyframes grSpin { to { transform: rotate(360deg); } }

    /* Voice mic button */
    #voiceMicButton {
        position: fixed;
        bottom: 200px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 2px solid rgba(78,205,196,0.5);
        background: rgba(15,32,39,0.9);
        color: white;
        font-size: 1.4rem;
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
        display: none;
        align-items: center;
        justify-content: center;
    }
    #voiceMicButton.active {
        background: linear-gradient(135deg, #2ECC71, #27AE60);
        border-color: #2ECC71;
        display: flex;
        animation: grPulseMic 1.5s infinite;
    }
    #voiceMicButton.muted {
        background: rgba(60,60,60,0.9);
        border-color: rgba(150,150,150,0.5);
        display: flex;
    }
    @keyframes grPulseMic {
        0%,100% { box-shadow: 0 0 10px rgba(46,204,113,0.5); }
        50%      { box-shadow: 0 0 20px rgba(46,204,113,0.8); }
    }

    /* Game header */
    #gameHeader {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(15,32,39,0.96);
        border-bottom: 1px solid rgba(78,205,196,0.3);
        padding: 8px 16px;
        position: sticky;
        top: 0;
        z-index: 500;
        min-height: 52px;
    }
    .gh-player { display: flex; align-items: center; gap: 8px; flex: 1; }
    .gh-left  { justify-content: flex-start; }
    .gh-right { justify-content: flex-end; flex-direction: row-reverse; }
    .gh-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); flex-shrink: 0; }
    .gh-name  { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.9); max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gh-score { font-size: 1.25rem; font-weight: 900; color: #4ECDC4; min-width: 26px; text-align: center; }
    .gh-center { text-align: center; font-size: 0.72rem; color: rgba(255,255,255,0.6); flex-shrink: 0; padding: 0 8px; }
    .gh-counter { display: block; font-weight: 700; color: rgba(255,255,255,0.85); font-size: 0.82rem; }
    .gh-round   { display: block; font-size: 0.68rem; margin-top: 1px; }

    @media (max-width: 375px) {
        #gameHeader { padding: 5px 8px; min-height: 44px; }
        .gh-avatar  { width: 26px; height: 26px; }
        .gh-name    { max-width: 52px; font-size: 0.72rem; }
        .gh-score   { font-size: 1.05rem; }
        .gh-center  { padding: 0 3px; font-size: 0.62rem; }
    }

    body { background: #0f2027; color: #fff; margin: 0; padding: 0; }
    </style>
</head>
<body>

<!-- Shared overlays — controlled by GameplayRuntime.js -->

<!-- Brain overlay: shows during INTRO/WAITING phases -->
<div id="brainOverlay" class="hidden">
    <img src="{{ asset('images/brain.png') }}" alt="" class="brain-spin-img" onerror="this.style.fontSize='4rem';this.outerHTML='<div style=\'font-size:4rem\'>🧠</div>'">
    <div class="brain-msg" id="brainMessage">{{ __('Préparation...') }}</div>
</div>

<!-- Loading Overlay: shown while connecting, hidden by GameplayRuntime on connect -->
<div id="loadingOverlay" class="hidden">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">{{ __('Connexion au serveur...') }}</div>
    </div>
</div>

<!-- Voice Mic Button (WebRTC — shown by VoiceChat init, hidden by default) -->
<button id="voiceMicButton" title="{{ __('Activer/désactiver le micro') }}">
    <span id="micIcon">🎤</span>
</button>

<!-- Game header — always rendered; hidden by CSS when no player data, updated live by GameplayRuntime -->
<header id="gameHeader" style="{{ empty($playerName) ? 'display:none;' : '' }}">
    <div class="gh-player gh-left">
        <img id="ghPlayerAvatar"
             src="{{ asset($playerAvatarPath ?? 'images/avatars/standard/default.png') }}"
             class="gh-avatar" alt="">
        <div class="gh-name" id="ghPlayerName">{{ $playerName ?? '' }}</div>
        <div class="gh-score" id="ghPlayerScore">{{ $playerScore ?? 0 }}</div>
    </div>
    <div class="gh-center">
        <span class="gh-counter" id="ghQuestionCounter">{{ ($currentQuestion ?? 1) }}/{{ $totalQuestions ?? 10 }}</span>
        <span class="gh-round" id="ghRound">{{ __('Manche') }} {{ $round ?? 1 }}</span>
    </div>
    <div class="gh-player gh-right">
        <img id="ghOpponentAvatar"
             src="{{ asset($opponentAvatarPath ?? 'images/avatars/standard/default.png') }}"
             class="gh-avatar" alt="">
        <div class="gh-name" id="ghOpponentName">{{ $opponentName ?? '' }}</div>
        <div class="gh-score" id="ghOpponentScore">{{ $opponentScore ?? 0 }}</div>
    </div>
</header>

<!-- Main game content (no container wrapper — game views are full-width) -->
@yield('content')

<!-- Musique d'ambiance StrategyBuzzer -->
<audio id="ambientMusic" preload="auto" loop></audio>

<!-- Toast Notification System -->
<div id="toastContainer" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;pointer-events:none;"></div>
<style>
.custom-toast {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    padding: 16px 28px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 0 20px rgba(255,215,0,0.2);
    border: 1px solid rgba(255,215,0,0.3);
    font-size: 16px;
    font-weight: 500;
    text-align: center;
    animation: toastSlideIn 0.4s ease-out, toastFadeOut 0.4s ease-in 2.6s forwards;
    pointer-events: auto;
    max-width: 90vw;
}
.custom-toast.success { border-color: rgba(76,217,100,0.5); box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 0 20px rgba(76,217,100,0.3); }
.custom-toast.error   { border-color: rgba(255,59,48,0.5);  box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 0 20px rgba(255,59,48,0.3); }
.custom-toast.warning { border-color: rgba(255,204,0,0.5);  box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 0 20px rgba(255,204,0,0.3); }
@keyframes toastSlideIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
@keyframes toastFadeOut  { from { opacity:1; } to { opacity:0; } }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Socket.IO — loaded once here, never duplicated in views -->
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<!-- Game server URL — injected once from layout so all gameplay views get port 3001 -->
<script>
window.GAME_SERVER_URL = window.location.protocol + '//' + window.location.hostname + ':3001';
</script>

<!-- Window variables set by each game view (must come before GameplayRuntime) -->
@yield('game-data')

<!-- GameplayRuntime: central connection, brain animation, score header, phase routing -->
<script src="{{ asset('js/GameplayRuntime.js') }}"></script>

<!-- Skill effects runtime -->
<script src="{{ asset('js/GameEffectsRuntime.js') }}"></script>

<!-- View-specific overlays (game-specific modals, sheets, etc.) -->
@yield('overlay')

<!-- View-specific scripts -->
@yield('scripts')

<script>
window.showToast = function(message, type, duration) {
    type = type || 'info';
    duration = duration || 3000;
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'custom-toast ' + type;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, duration);
};
</script>

<script>
// Système de musique d'ambiance globale StrategyBuzzer
(function() {
    const ambientMusic = document.getElementById('ambientMusic');
    if (!ambientMusic) return;
    const musicFiles = {
        'strategybuzzer': '/sounds/strategybuzzer_ambient.mp3',
        'fun_01': '/sounds/fun_01.mp3',
        'chill': '/sounds/chill.mp3',
        'punchy': '/sounds/punchy.mp3'
    };
    const gameplayPages = [
        '/solo/prepare','/solo/game','/solo/answer','/solo/next','/solo/victory','/solo/defeat',
        '/duo/game','/duo/answer','/league-individual/game','/league-individual/answer',
        '/league-team/game','/league-team/answer',
        '/game_preparation','/game_question','/game_answer','/game_result','/victory','/defeat'
    ];
    function isGameplayPage() {
        const p = window.location.pathname;
        return gameplayPages.some(page => p.includes(page));
    }
    const isResultPage = @json(isset($params) && isset($params['is_correct']));
    function isMusicEnabled() { const e = localStorage.getItem('ambient_music_enabled'); return e === null || e === 'true'; }
    function getSelectedMusic() { return localStorage.getItem('ambient_music_id') || 'strategybuzzer'; }
    function loadMusicSource() {
        const musicId = getSelectedMusic();
        const musicFile = musicFiles[musicId] || musicFiles['strategybuzzer'];
        if (ambientMusic.src !== window.location.origin + musicFile) {
            const wasPlaying = !ambientMusic.paused;
            const savedTime = parseFloat(localStorage.getItem('ambientMusicTime_' + musicId) || '0');
            if (ambientMusic.src !== window.location.origin + musicFile) ambientMusic.src = musicFile;
            ambientMusic.addEventListener('loadedmetadata', function onLoaded() {
                if (savedTime > 0 && savedTime < ambientMusic.duration) ambientMusic.currentTime = savedTime;
                if (wasPlaying && isMusicEnabled() && !isGameplayPage() && !isResultPage) ambientMusic.play().catch(function(){});
                ambientMusic.removeEventListener('loadedmetadata', onLoaded);
            }, { once: true });
        }
    }
    loadMusicSource();
    window.addEventListener('storage', function(e) {
        if (e.key === 'ambient_music_id') loadMusicSource();
        else if (e.key === 'ambient_music_enabled') {
            if (e.newValue === 'true' && !isGameplayPage() && !isResultPage) ambientMusic.play().catch(function(){});
            else ambientMusic.pause();
        }
    });
    function isMusicSessionStarted() { return localStorage.getItem('music_session_started') === 'true'; }
    if (isGameplayPage() || isResultPage) {
        ambientMusic.pause();
    } else {
        const savedTime = parseFloat(localStorage.getItem('ambientMusicTime_' + getSelectedMusic()) || '0');
        ambientMusic.addEventListener('canplay', function onCanPlay() {
            if (savedTime > 0) {
                if (!isNaN(ambientMusic.duration) && savedTime < ambientMusic.duration) ambientMusic.currentTime = savedTime;
                else if (isNaN(ambientMusic.duration) || ambientMusic.duration === Infinity) ambientMusic.currentTime = savedTime;
            }
            if (isMusicEnabled() && isMusicSessionStarted()) {
                ambientMusic.play().catch(function() {
                    document.addEventListener('click', function playOnClick() {
                        if (isMusicEnabled() && isMusicSessionStarted()) ambientMusic.play().catch(function(){});
                        document.removeEventListener('click', playOnClick);
                    }, { once: true });
                });
            }
            ambientMusic.removeEventListener('canplay', onCanPlay);
        });
        setInterval(function() {
            if (!ambientMusic.paused) {
                const musicId = getSelectedMusic();
                localStorage.setItem('ambientMusicTime_' + musicId, ambientMusic.currentTime.toString());
            }
        }, 250);
        const savePosition = function() {
            const musicId = getSelectedMusic();
            localStorage.setItem('ambientMusicTime_' + musicId, ambientMusic.currentTime.toString());
        };
        window.addEventListener('beforeunload', savePosition);
        window.addEventListener('pagehide', savePosition);
        document.addEventListener('visibilitychange', function() { if (document.hidden) savePosition(); });
    }
    window.toggleAmbientMusic = function(enabled) {
        localStorage.setItem('ambient_music_enabled', enabled.toString());
        if (enabled && isMusicSessionStarted() && !isGameplayPage() && !isResultPage) ambientMusic.play().catch(function(){});
        else ambientMusic.pause();
    };
    window.startAmbientMusicSession = function() {
        if (isMusicSessionStarted()) return;
        localStorage.setItem('music_session_started', 'true');
        if (isMusicEnabled() && !isGameplayPage() && !isResultPage) {
            ambientMusic.play().catch(function() {
                document.addEventListener('click', function playOnClick() {
                    if (isMusicEnabled()) ambientMusic.play().catch(function(){});
                    document.removeEventListener('click', playOnClick);
                }, { once: true });
            });
        }
    };
    window.changeAmbientMusic = function(musicId) {
        localStorage.setItem('ambient_music_id', musicId);
        loadMusicSource();
    };
})();
</script>

<!-- Détection automatique de la langue du navigateur -->
<script>
(function() {
    @auth
    const userLang = @json(auth()->user()->preferred_language ?? null);
    const supportedLanguages = @json(array_keys(config('languages.supported', ['fr' => []])));
    if (!userLang || userLang === 'fr') {
        const browserLang = (navigator.language || navigator.userLanguage || 'fr').split('-')[0].toLowerCase();
        if (supportedLanguages.includes(browserLang) && browserLang !== 'fr') {
            setTimeout(async function() {
                if (!window.customDialog) return;
                const confirmChange = await window.customDialog.confirm(
                    'Votre navigateur est en ' + browserLang + '. Voulez-vous utiliser StrategyBuzzer dans cette langue ?\n\nYour browser is in ' + browserLang + '. Do you want to use StrategyBuzzer in this language?',
                    { title: '🌐 {{ __("Langue") }}' }
                );
                if (confirmChange) {
                    const formData = new FormData();
                    formData.append('language', browserLang);
                    formData.append('_token', '{{ csrf_token() }}');
                    fetch('{{ route("profile.update") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body: formData
                    }).then(function(r) { return r.ok ? r.json() : Promise.reject(); }).then(function(d) { if (d.success) location.reload(); }).catch(function(){});
                }
            }, 500);
        }
    }
    @endauth
})();
</script>

@include('components.custom-dialog')
</body>
</html>
