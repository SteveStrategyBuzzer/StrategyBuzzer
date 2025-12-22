@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\Auth;
    use App\Models\ProfileStat;
    use App\Models\TeamInvitation;
    use App\Models\UserQuestProgress;
    use App\Models\Quest;
    use App\Models\PlayerMessage;
    use App\Models\DuoMatch;
    
    $user = Auth::user();
    
    // Compteurs de notifications
    $duoNotifications = 0;
    $ligueNotifications = 0;
    $questsNotifications = 0;
    $dailyQuestsNotifications = 0;
    
    if ($user) {
        // Duo: Invitations en attente (user est player2 et status 'waiting')
        $duoInvitations = DuoMatch::where('player2_id', $user->id)
            ->where('status', 'waiting')
            ->count();
        
        // Duo: Messages non lus
        $duoMessages = PlayerMessage::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
        
        // Total notifications Duo = invitations + messages non lus
        $duoNotifications = $duoInvitations + $duoMessages;
        
        // Ligue: Invitations d'équipe en attente
        $ligueNotifications = TeamInvitation::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
        
        // Quêtes: Complétées mais non réclamées (hors quotidiennes)
        $questsNotifications = UserQuestProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('rewarded', false)
            ->whereHas('quest', function($q) {
                $q->where('rarity', '!=', 'Quotidiennes');
            })
            ->count();
        
        // Quêtes quotidiennes: Complétées mais non réclamées
        $dailyQuestsNotifications = UserQuestProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('rewarded', false)
            ->whereHas('quest', function($q) {
                $q->where('rarity', 'Quotidiennes');
            })
            ->count();
    }
    
    // Vérifier si le profil a été complété (enregistré au moins une fois)
    $profileComplete = $user && ($user->profile_completed ?? false);
    
    // Solo : accessible SEULEMENT si profil complet
    $soloUnlocked = $profileComplete;
    
    // Duo : toujours accessible pour les invitations
    // - Avant niveau 11 : Peut jouer mais stats NON comptabilisées
    // - À partir niveau 11 (après boss niveau 10) : Accès complet avec stats
    $profileSettings = $user && $user->profile_settings ? $user->profile_settings : [];
    $choixNiveau = is_array($profileSettings) ? ($profileSettings['choix_niveau'] ?? 1) : 1;
    $duoFullUnlocked = $choixNiveau >= 11;    // Après boss niveau 10
    $duoUnlocked = true;                       // Duo toujours accessible pour invitations
    
    // Ligue : acheté OU 25 matchs Duo joués (victoires + défaites)
    $profileStats = $user ? ProfileStat::where('user_id', $user->id)->first() : null;
    $duoMatches = $profileStats ? (($profileStats->duo_victoires ?? 0) + ($profileStats->duo_defaites ?? 0)) : 0;
    $leaguePurchased = $user && ($user->league_purchased ?? false);
    $ligueUnlocked = $leaguePurchased || $duoMatches >= 25;
    
    // Maître du Jeu : verrouillé SEULEMENT si acheté mais profil incomplet
    $masterPurchased = $user && ($user->master_purchased ?? false);
    $masterUnlocked = !$masterPurchased || ($masterPurchased && $profileComplete);
    
    // Tous les autres sont toujours accessibles
    $avatarsUnlocked = true;
    $questesUnlocked = true;
    $badgesUnlocked = true;
    $boutiqueUnlocked = true;
    $reglementsUnlocked = true;
@endphp

<style>
    :root{
        --bg:#003DA5;
        --btn:#1E90FF;
        --btn-hover:#339CFF;
    }

    /* Scène globale : tout reste dans le cadre */
    .menu-scene{
        position: relative;
        overflow: hidden;            /* empêche de sortir de l’écran */
        min-height: 100vh;
        width: 100%;
        background-color: var(--bg);
        color: #fff;
        display: grid;
        place-items: center;
        padding: 24px;
        box-sizing: border-box;
    }

    /* Calque des cerveaux (sous les onglets) */
    .brain-stage{
        position: absolute;
        inset: 0;
        z-index: 1;                  /* SOUS les boutons */
        pointer-events: none;        /* clics ignorés sauf sur l’image */
    }

    /* Conteneur des onglets */
    .menu-container{
        position: relative;
        z-index: 2;                  /* AU-DESSUS des cerveaux */
        width: 100%;
        max-width: 900px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 14px;
        align-items: center;
        justify-items: center;
        pointer-events: none;        /* Laisse passer les clics aux cerveaux */
    }

    .menu-title{
        grid-column: 1 / -1;
        font-size: clamp(2rem, 5vw, 3rem);
        margin: 0 0 8px 0;
        text-align: center;
        pointer-events: auto;        /* Titre capturable */
    }

    .menu-link{
        display: inline-block;
        min-width: 13ch;
        max-width: 100%;
        text-align: center;
        padding: 16px 1ch;
        background-color: var(--btn);
        color: #fff;
        text-decoration: none;
        font-size: 1.25rem;
        border-radius: 10px;
        box-shadow: 2px 2px 6px rgba(0,0,0,.3);
        transition: background-color .25s ease, transform .15s ease;
        user-select: none;
        box-sizing: content-box;
        pointer-events: auto;        /* Boutons cliquables */
    }
    .menu-link:hover{ background-color: var(--btn-hover); transform: translateY(-1px); }
    .menu-link:active{ transform: translateY(0); }
    
    /* Badge de notification */
    .menu-link {
        position: relative;
    }
    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #dc3545;
        color: #fff;
        font-size: 12px;
        font-weight: bold;
        min-width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* Onglets désactivés (verrouillés) */
    .menu-link.disabled{
        background-color: #333;
        color: #666;
        cursor: not-allowed;
        pointer-events: none;
        opacity: 0.5;
    }
    .menu-link.disabled:hover{
        background-color: #333;
        transform: none;
    }

    /* Cerveau (image) — x2 (96px) */
    .brain{
        position: absolute;
        width: 90px;
        height: 90px;
        pointer-events: auto;        /* cliquable */
        user-select: none;
        will-change: transform, left, top;
        filter: drop-shadow(0 2px 2px rgba(0,0,0,.25));
    }

    /* === RESPONSIVE POUR ORIENTATION === */

    /* Mobile Portrait (320px - 480px) */
    @media (max-width: 480px) and (orientation: portrait) {
        body {
            position: fixed;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        .menu-scene {
            padding: 1vh 8px;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            height: 100vh;
            max-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        .menu-container {
            grid-template-columns: 1fr;
            gap: 1vh;
            max-width: 100%;
            width: 100%;
            padding: 1vh 8px;
            box-sizing: border-box;
        }

        .menu-title {
            font-size: clamp(1.3rem, 3.5vh, 1.6rem);
            margin-bottom: 0.5vh;
        }

        .menu-link {
            padding: clamp(10px, 1.8vh, 14px) 1ch;
            font-size: clamp(0.85rem, 1.8vh, 1rem);
            min-width: 13ch;
            max-width: 95%;
            margin: 0 auto;
            box-sizing: content-box;
        }

        .brain {
            width: 48px;
            height: 48px;
        }
    }

    /* Mobile Paysage (orientation horizontale) - Grille 3×3 + Quêtes Quotidiennes centré */
    @media (max-height: 500px) and (orientation: landscape) {
        .menu-scene {
            padding: 4px;
            min-height: auto;
            width: 100vw;
            height: 100vh;
            box-sizing: border-box;
        }

        .menu-container {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            grid-template-rows: auto repeat(4, 1fr);
            gap: 4px;
            align-items: center;
            justify-items: center;
            padding-top: 25px;
            max-width: 100%;
            width: 100%;
        }

        .menu-title {
            grid-column: 1 / -1;
            grid-row: 1;
            font-size: 1.1rem;
            margin-bottom: 3px;
        }

        /* Grille 3×3 - Ligne 1 */
        .menu-link:nth-of-type(1) { grid-column: 1; grid-row: 2; }
        .menu-link:nth-of-type(2) { grid-column: 2; grid-row: 2; }
        .menu-link:nth-of-type(3) { grid-column: 3; grid-row: 2; }

        /* Grille 3×3 - Ligne 2 */
        .menu-link:nth-of-type(4) { grid-column: 1; grid-row: 3; }
        .menu-link:nth-of-type(5) { grid-column: 2; grid-row: 3; }
        .menu-link:nth-of-type(6) { grid-column: 3; grid-row: 3; }

        /* Grille 3×3 - Ligne 3 */
        .menu-link:nth-of-type(7) { grid-column: 1; grid-row: 4; }
        .menu-link:nth-of-type(8) { grid-column: 2; grid-row: 4; }
        .menu-link:nth-of-type(9) { grid-column: 3; grid-row: 4; }

        /* Quêtes Quotidiennes - Centré en dessous */
        .menu-link:nth-of-type(10) { grid-column: 1 / -1; grid-row: 5; }

        .menu-link {
            padding: 6px 4px;
            font-size: 0.75rem;
            width: auto;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .brain {
            width: 30px;
            height: 30px;
        }
    }

    /* Tablettes Portrait */
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .menu-container {
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .menu-link {
            font-size: 1.15rem;
        }

        .brain {
            width: 64px;
            height: 64px;
        }
    }

    /* Tablettes Paysage - Grille 3×3 + Quêtes Quotidiennes centré */
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .menu-scene {
            padding: 12px;
        }

        .menu-container {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            grid-template-rows: auto repeat(4, 1fr);
            gap: 10px;
            align-items: center;
            padding-top: 40px;
        }

        .menu-title {
            grid-column: 1 / -1;
            grid-row: 1;
            margin-bottom: 10px;
        }

        /* Grille 3×3 - Ligne 1 */
        .menu-link:nth-of-type(1) { grid-column: 1; grid-row: 2; }
        .menu-link:nth-of-type(2) { grid-column: 2; grid-row: 2; }
        .menu-link:nth-of-type(3) { grid-column: 3; grid-row: 2; }

        /* Grille 3×3 - Ligne 2 */
        .menu-link:nth-of-type(4) { grid-column: 1; grid-row: 3; }
        .menu-link:nth-of-type(5) { grid-column: 2; grid-row: 3; }
        .menu-link:nth-of-type(6) { grid-column: 3; grid-row: 3; }

        /* Grille 3×3 - Ligne 3 */
        .menu-link:nth-of-type(7) { grid-column: 1; grid-row: 4; }
        .menu-link:nth-of-type(8) { grid-column: 2; grid-row: 4; }
        .menu-link:nth-of-type(9) { grid-column: 3; grid-row: 4; }

        /* Quêtes Quotidiennes - Centré en dessous */
        .menu-link:nth-of-type(10) { grid-column: 1 / -1; grid-row: 5; }

        .menu-link {
            font-size: 0.95rem;
            width: 15ch;
            box-sizing: content-box;
        }

        .brain {
            width: 50px;
            height: 50px;
        }
    }
</style>

<div class="menu-scene">
    <!-- Calque pour les cerveaux animés -->
    <div class="brain-stage" id="brain-stage"></div>

    <!-- Boutons du menu -->
    <div class="menu-container">
        <h1 class="menu-title">{{ __('Menu') }}</h1>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('profile') ? route('profile') : url('/profile') }}">
            {{ __('PROFIL') }}
        </a>

        <a class="menu-link {{ $soloUnlocked ? '' : 'disabled' }}"
           href="{{ $soloUnlocked ? (\Illuminate\Support\Facades\Route::has('solo.index') ? route('solo.index') : url('/solo')) : 'javascript:void(0)' }}">
            {{ __('SOLO') }} {{ !$soloUnlocked ? '🔒' : '' }}
        </a>

        <a class="menu-link {{ $duoUnlocked ? '' : 'disabled' }}"
           href="{{ $duoUnlocked ? route('duo.splash') : 'javascript:void(0)' }}">
            {{ __('DUO') }} {{ !$duoUnlocked ? '🔒' : '' }}
            <span class="notification-badge" id="duo-badge" style="{{ $duoNotifications > 0 ? '' : 'display:none;' }}">{{ $duoNotifications }}</span>
        </a>

        <a class="menu-link {{ $ligueUnlocked ? '' : 'disabled' }}"
           href="{{ $ligueUnlocked ? route('ligue') : 'javascript:void(0)' }}">
            {{ __('LIGUE') }} {{ !$ligueUnlocked ? '🔒' : '' }}
            <span class="notification-badge" id="ligue-badge" style="{{ $ligueNotifications > 0 ? '' : 'display:none;' }}">{{ $ligueNotifications }}</span>
        </a>

        <a class="menu-link {{ $masterUnlocked ? '' : 'disabled' }}"
           href="{{ $masterPurchased && $profileComplete ? url('/master') : (route('boutique') . '?tab=master') }}">
            {{ __('MAÎTRE DU JEU') }} {{ ($masterPurchased && !$profileComplete) ? '🔒' : '' }}
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('avatar') ? route('avatar') : url('/avatar') }}">
            {{ __('AVATARS') }}
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('quests.index') ? route('quests.index') : url('/quests') }}">
            {{ __('QUÊTES') }}
            <span class="notification-badge" id="quests-badge" style="{{ $questsNotifications > 0 ? '' : 'display:none;' }}">{{ $questsNotifications }}</span>
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('boutique') ? route('boutique') : url('/boutique') }}">
            {{ __('BOUTIQUE') }}
        </a>

        <a class="menu-link"
           href="{{ route('guide.index') }}">
            {{ __('GUIDE DU JOUEUR') }}
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('quetes-quotidiennes') ? route('quetes-quotidiennes') : url('/quetes-quotidiennes') }}">
            {{ __('QUÊTES QUOTIDIENNES') }}
            <span class="notification-badge" id="daily-badge" style="{{ $dailyQuestsNotifications > 0 ? '' : 'display:none;' }}">{{ $dailyQuestsNotifications }}</span>
        </a>
    </div>
</div>

<script>
(() => {
    const stage = document.getElementById('brain-stage');
    const BRAIN_SRC = @json(asset('images/brain.png'));  // public/images/brain.png attendu
    const MAX_BRAINS = 25;
    const SPEED_MIN = 90, SPEED_MAX = 160;
    const BRAINS = [];

    function getBrainSize() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        
        if (width <= 480 && window.matchMedia('(orientation: portrait)').matches) return 54;
        if (height <= 500 && window.matchMedia('(orientation: landscape)').matches) return 44;
        if (width >= 481 && width <= 900 && window.matchMedia('(orientation: portrait)').matches) return 64;
        if (width >= 481 && width <= 1024 && window.matchMedia('(orientation: landscape)').matches) return 64;
        return 90;
    }

    function stageSize(){
        const r = stage.getBoundingClientRect();
        return { w: r.width, h: r.height };
    }
    const rand = (a,b)=> Math.random()*(b-a)+a;

    function createBrain(x, y, isFirst = false, parentAngle = null){
        if (BRAINS.length >= MAX_BRAINS) return;

        const img = new Image();
        img.className = 'brain';
        img.src = BRAIN_SRC;
        img.alt = 'cerveau';
        
        const size = getBrainSize();
        img.width = size;
        img.height = size;

        const { w, h } = stageSize();
        const startX = (typeof x === 'number') ? x : rand(0, Math.max(0, w - size));
        const startY = (typeof y === 'number') ? y : rand(0, Math.max(0, h - size));
        img.style.left = startX + 'px';
        img.style.top  = startY + 'px';

        // Calcul de l'angle de direction
        let dir;
        if (parentAngle !== null) {
            // Duplicata : angle opposé + 35°
            dir = (parentAngle + 180 + 35) * Math.PI / 180;
        } else {
            // Premier cerveau : angle aléatoire
            dir = [45, 135, 225, 315][Math.floor(Math.random()*4)] * Math.PI/180;
        }
        
        const speed = rand(SPEED_MIN, SPEED_MAX);
        img.dataset.vx = Math.cos(dir) * speed;
        img.dataset.vy = Math.sin(dir) * speed;
        
        // Stocke l'angle en degrés pour les duplicatas
        img.dataset.angle = (dir * 180 / Math.PI) % 360;

        // Seul le premier cerveau est cliquable
        if (isFirst) {
            const multiply = (e) => {
                e.stopPropagation();
                e.preventDefault();
                const currentAngle = parseFloat(img.dataset.angle) || 0;
                createBrain(parseFloat(img.style.left)||0, parseFloat(img.style.top)||0, false, currentAngle);
            };
            
            img.addEventListener('click', multiply);
            img.addEventListener('touchstart', multiply, { passive: false });
            img.addEventListener('touchend', (e) => e.preventDefault(), { passive: false });
            img.style.pointerEvents = 'auto';
        } else {
            img.style.pointerEvents = 'none';
        }

        stage.appendChild(img);
        BRAINS.push(img);
    }

    let last = performance.now();
    function tick(now){
        const dt = Math.min(0.032, (now - last)/1000);
        last = now;
        const { w, h } = stageSize();

        for (const img of BRAINS){
            let x = parseFloat(img.style.left) || 0;
            let y = parseFloat(img.style.top)  || 0;
            let vx = parseFloat(img.dataset.vx) || 100;
            let vy = parseFloat(img.dataset.vy) || 100;

            x += vx * dt;
            y += vy * dt;

            const size = getBrainSize();

            // rebonds avec la taille correcte
            if (x <= 0){ x = 0; vx = Math.abs(vx); }
            else if (x + size >= w){ x = w - size; vx = -Math.abs(vx); }

            if (y <= 0){ y = 0; vy = Math.abs(vy); }
            else if (y + size >= h){ y = h - size; vy = -Math.abs(vy); }

            img.style.left = x + 'px';
            img.style.top  = y + 'px';
            img.dataset.vx = vx;
            img.dataset.vy = vy;
            
            // Met à jour l'angle après rebond
            const currentAngle = Math.atan2(vy, vx) * 180 / Math.PI;
            img.dataset.angle = (currentAngle + 360) % 360;
        }
        requestAnimationFrame(tick);
    }

    createBrain(undefined, undefined, true);  // Premier cerveau cliquable
    requestAnimationFrame(tick);

    // Ajuste les positions et tailles si la fenêtre/orientation change
    window.addEventListener('resize', () => {
        const { w, h } = stageSize();
        const size = getBrainSize();
        
        for (const img of BRAINS){
            img.width = size;
            img.height = size;
            
            let x = Math.min(parseFloat(img.style.left)||0, Math.max(0, w - size));
            let y = Math.min(parseFloat(img.style.top)||0, Math.max(0, h - size));
            img.style.left = x + 'px';
            img.style.top  = y + 'px';
        }
    });
})();

// === Démarrage automatique de la musique d'ambiance dès l'arrivée au menu ===
(function() {
  function tryStartMusic() {
    if (window.startAmbientMusicSession) {
      window.startAmbientMusicSession();
    } else {
      setTimeout(tryStartMusic, 100);
    }
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tryStartMusic);
  } else {
    tryStartMusic();
  }
})();

// === Polling des notifications en temps réel ===
(function() {
    const POLL_INTERVAL = 10000;
    
    function updateBadge(id, count) {
        const badge = document.getElementById(id);
        if (!badge) return;
        
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }
    
    async function pollNotifications() {
        try {
            const response = await fetch('/api/notifications', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            updateBadge('duo-badge', data.duo || 0);
            updateBadge('ligue-badge', data.ligue || 0);
            updateBadge('quests-badge', data.quests || 0);
            updateBadge('daily-badge', data.daily || 0);
        } catch (e) {
            console.log('Notification poll error:', e);
        }
    }
    
    setInterval(pollNotifications, POLL_INTERVAL);
    setTimeout(pollNotifications, 2000);
})();
</script>
@endsection
