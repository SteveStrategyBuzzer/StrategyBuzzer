@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\Auth;
    $user = Auth::user();
    
    // Vérifier si le profil a été complété (enregistré au moins une fois)
    $profileComplete = $user && ($user->profile_completed ?? false);
    
    // Solo : accessible SEULEMENT si profil complet
    $soloUnlocked = $profileComplete;
    
    // Duo : 20 matchs Solo joués (victoires + défaites)
    $soloMatches = $user ? (($user->solo_defeats ?? 0) + ($user->solo_victories ?? 0)) : 0;
    $duoUnlocked = $soloMatches >= 20;
    
    // Ligue : 100 matchs Duo joués (victoires + défaites)
    $duoMatches = $user ? (($user->duo_defeats ?? 0) + ($user->duo_victories ?? 0)) : 0;
    $ligueUnlocked = $duoMatches >= 100;
    
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

    /* Mobile Paysage (orientation horizontale) - Disposition 3 colonnes (3-4-3) */
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

        /* Colonne gauche - 3 onglets */
        .menu-link:nth-of-type(1) { grid-column: 1; grid-row: 2; }
        .menu-link:nth-of-type(2) { grid-column: 1; grid-row: 3; }
        .menu-link:nth-of-type(3) { grid-column: 1; grid-row: 4; }

        /* Colonne centre - 4 onglets (2 en haut alignés, 2 en bas alignés) */
        .menu-link:nth-of-type(4) { grid-column: 2; grid-row: 2; }
        .menu-link:nth-of-type(5) { grid-column: 2; grid-row: 3; }
        .menu-link:nth-of-type(6) { grid-column: 2; grid-row: 4; }
        .menu-link:nth-of-type(7) { grid-column: 2; grid-row: 5; }

        /* Colonne droite - 3 onglets */
        .menu-link:nth-of-type(8) { grid-column: 3; grid-row: 2; }
        .menu-link:nth-of-type(9) { grid-column: 3; grid-row: 3; }
        .menu-link:nth-of-type(10) { grid-column: 3; grid-row: 4; }

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

    /* Tablettes Paysage - Disposition 3 colonnes (3-4-3) */
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

        /* Colonne gauche - 3 onglets */
        .menu-link:nth-of-type(1) { grid-column: 1; grid-row: 2; }
        .menu-link:nth-of-type(2) { grid-column: 1; grid-row: 3; }
        .menu-link:nth-of-type(3) { grid-column: 1; grid-row: 4; }

        /* Colonne centre - 4 onglets centrés verticalement */
        .menu-link:nth-of-type(4) { grid-column: 2; grid-row: 2; }
        .menu-link:nth-of-type(5) { grid-column: 2; grid-row: 3; }
        .menu-link:nth-of-type(6) { grid-column: 2; grid-row: 4; }
        .menu-link:nth-of-type(7) { grid-column: 2; grid-row: 5; }

        /* Colonne droite - 3 onglets */
        .menu-link:nth-of-type(8) { grid-column: 3; grid-row: 2; }
        .menu-link:nth-of-type(9) { grid-column: 3; grid-row: 3; }
        .menu-link:nth-of-type(10) { grid-column: 3; grid-row: 4; }

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
        <h1 class="menu-title">Menu</h1>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('profile') ? route('profile') : url('/profile') }}">
            PROFIL
        </a>

        <a class="menu-link {{ $soloUnlocked ? '' : 'disabled' }}"
           href="{{ $soloUnlocked ? (\Illuminate\Support\Facades\Route::has('solo.index') ? route('solo.index') : url('/solo')) : 'javascript:void(0)' }}">
            SOLO {{ !$soloUnlocked ? '🔒' : '' }}
        </a>

        <a class="menu-link {{ $duoUnlocked ? '' : 'disabled' }}"
           href="{{ $duoUnlocked ? (\Illuminate\Support\Facades\Route::has('duo') ? route('duo') : url('/duo')) : 'javascript:void(0)' }}">
            DUO {{ !$duoUnlocked ? '🔒' : '' }}
        </a>

        <a class="menu-link {{ $ligueUnlocked ? '' : 'disabled' }}"
           href="{{ $ligueUnlocked ? (\Illuminate\Support\Facades\Route::has('ligue') ? route('ligue') : url('/ligue')) : 'javascript:void(0)' }}">
            LIGUE {{ !$ligueUnlocked ? '🔒' : '' }}
        </a>

        <a class="menu-link {{ $masterUnlocked ? '' : 'disabled' }}"
           href="{{ $masterPurchased && $profileComplete ? url('/master') : (route('boutique') . '?tab=master') }}">
            MAÎTRE DU JEU {{ ($masterPurchased && !$profileComplete) ? '🔒' : '' }}
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('avatar') ? route('avatar') : url('/avatar') }}">
            AVATARS
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('quetes') ? route('quetes') : url('/quetes') }}">
            QUÊTES
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('badges') ? route('badges') : url('/badges') }}">
            BADGES
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('boutique') ? route('boutique') : url('/boutique') }}">
            BOUTIQUE
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('reglements') ? route('reglements') : url('/reglements') }}">
            RÈGLEMENTS
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
</script>
@endsection
