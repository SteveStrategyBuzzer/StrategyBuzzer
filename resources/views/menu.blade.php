@extends('layouts.app')

@section('content')
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
    }

    .menu-title{
        grid-column: 1 / -1;
        font-size: clamp(2rem, 5vw, 3rem);
        margin: 0 0 8px 0;
        text-align: center;
    }

    .menu-link{
        display: block;
        width: 100%;
        text-align: center;
        padding: 16px 18px;
        background-color: var(--btn);
        color: #fff;
        text-decoration: none;
        font-size: 1.25rem;
        border-radius: 10px;
        box-shadow: 2px 2px 6px rgba(0,0,0,.3);
        transition: background-color .25s ease, transform .15s ease;
        user-select: none;
    }
    .menu-link:hover{ background-color: var(--btn-hover); transform: translateY(-1px); }
    .menu-link:active{ transform: translateY(0); }

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
            padding: 0;
            overflow: hidden;
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
            gap: 1.5vh;
            max-width: 100%;
            width: 100%;
            max-height: 100vh;
            padding: 2vh 12px;
            box-sizing: border-box;
        }

        .menu-title {
            font-size: clamp(1.5rem, 4vh, 1.8rem);
            margin-bottom: 1vh;
        }

        .menu-link {
            padding: clamp(8px, 1.5vh, 12px) 16px;
            font-size: clamp(0.9rem, 2vh, 1rem);
            width: 220px;
            max-width: 100%;
        }

        .brain {
            width: 54px;
            height: 54px;
        }
    }

    /* Mobile Paysage (orientation horizontale) */
    @media (max-height: 500px) and (orientation: landscape) {
        .menu-scene {
            padding: 12px;
            min-height: auto;
        }

        .menu-container {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            max-width: 100%;
        }

        .menu-title {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .menu-link {
            padding: 10px 12px;
            font-size: 1rem;
        }

        .brain {
            width: 44px;
            height: 44px;
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

    /* Tablettes Paysage */
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .menu-container {
            grid-template-columns: repeat(3, 1fr);
        }

        .brain {
            width: 64px;
            height: 64px;
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
           href="{{ \Illuminate\Support\Facades\Route::has('solo.index') ? route('solo.index') : url('/solo') }}">
            SOLO
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('duo') ? route('duo') : url('/duo') }}">
            DUO
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('master') ? route('master') : url('/master') }}">
            MAÎTRE DU JEU
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('quetes') ? route('quetes') : url('/quetes') }}">
            QUÊTES
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('ligue') ? route('ligue') : url('/ligue') }}">
            LIGUE
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('reglements') ? route('reglements') : url('/reglements') }}">
            RÈGLEMENTS
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('profile') ? route('profile') : url('/profile') }}">
            PROFIL
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('avatar') ? route('avatar') : url('/avatar') }}">
            AVATAR
        </a>

        <a class="menu-link"
           href="{{ \Illuminate\Support\Facades\Route::has('boutique') ? route('boutique') : url('/boutique') }}">
            BOUTIQUE
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

    function createBrain(x, y, isFirst = false){
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

        // Vitesse en diagonale (rebonds à 90°)
        const dir = [45, 135, 225, 315][Math.floor(Math.random()*4)] * Math.PI/180;
        const speed = rand(SPEED_MIN, SPEED_MAX);
        img.dataset.vx = Math.cos(dir) * speed;
        img.dataset.vy = Math.sin(dir) * speed;

        // Seul le premier cerveau peut être cliqué pour multiplier
        if (isFirst) {
            const multiply = (e) => {
                e.stopPropagation();
                e.preventDefault();
                createBrain(parseFloat(img.style.left)||0, parseFloat(img.style.top)||0, false);
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
