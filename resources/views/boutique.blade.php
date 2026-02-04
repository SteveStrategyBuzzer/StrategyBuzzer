@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\Auth;
    $user = Auth::user();
    $coins = $user ? ($user->coins ?? 0) : 0;
    $masterPurchased = $user && ($user->master_purchased ?? false);
    $duoPurchased = $user && ($user->duo_purchased ?? false);
    $leaguePurchased = $user && ($user->league_purchased ?? false);
    $allModesPurchased = $masterPurchased && $duoPurchased && $leaguePurchased;
@endphp

<style>
    :root {
        --bg: #0b1020;
        --card: #111735;
        --btn: #2c4bff;
        --btn-hover: #4466ff;
        --ink: #ecf0ff;
        --muted: #9fb6ff;
        --line: rgba(255,255,255,.08);
        --shadow: 0 10px 24px rgba(0,0,0,.12);
    }

    * { box-sizing: border-box; }
    
    .boutique-scene {
        position: relative;
        min-height: 100vh;
        width: 100%;
        background: linear-gradient(135deg, #0b1020 0%, #15224c 100%);
        color: var(--ink);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 24px;
    }

    .boutique-header {
        width: 100%;
        max-width: 1000px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .coins-pill {
        background: linear-gradient(135deg, #1a2344, #15224c);
        border: 1px solid var(--line);
        padding: 12px 18px;
        border-radius: 999px;
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #fbbf24;
    }

    .coins-pill img {
        width: 28px;
        height: 28px;
        min-width: 28px;
        min-height: 28px;
        object-fit: cover;
        object-position: center;
        clip-path: circle(50%);
        flex-shrink: 0;
    }

    .menu-btn {
        background: white;
        color: #0b1020;
        padding: 12px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        transition: all 0.3s ease;
    }
    .menu-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,255,255,0.3);
    }

    .boutique-title {
        width: 100%;
        text-align: center;
        font-size: clamp(2rem, 5vw, 3rem);
        margin-bottom: 32px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }

    .boutique-grid {
        width: 100%;
        max-width: 1000px;
        display: grid;
        gap: 20px;
        align-items: stretch;
    }

    .category-card {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: 24px;
        text-decoration: none;
        color: var(--ink);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: var(--shadow);
        min-height: 140px;
    }

    .category-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 32px rgba(0,0,0,0.25);
        border-color: var(--btn);
    }

    .category-icon {
        font-size: 2.5rem;
        margin-bottom: 12px;
    }

    .category-name {
        font-size: 1.2rem;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .category-desc {
        font-size: 0.9rem;
        color: var(--muted);
        line-height: 1.4;
    }

    .category-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: white;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .category-card.premium {
        background: linear-gradient(135deg, #1a1a4a, #2d1b69);
        border-color: #6366f1;
    }

    .category-card.unlocked {
        position: relative;
    }

    /* === PAYSAGE (Landscape) - Grille 4 colonnes === */
    @media (orientation: landscape) {
        .boutique-grid {
            grid-template-columns: repeat(4, 1fr);
        }
        
        .category-card {
            min-height: 160px;
            padding: 20px;
        }

        .category-icon {
            font-size: 2.2rem;
        }

        .category-name {
            font-size: 1.1rem;
        }

        .category-desc {
            font-size: 0.85rem;
        }
    }

    /* === PORTRAIT - Grille 2 colonnes === */
    @media (orientation: portrait) {
        .boutique-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .category-card {
            min-height: 130px;
            padding: 18px;
        }

        .category-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .category-name {
            font-size: 1rem;
        }

        .category-desc {
            font-size: 0.8rem;
        }
    }

    /* === Mobile Portrait (petits écrans) === */
    @media (max-width: 480px) and (orientation: portrait) {
        .boutique-scene {
            padding: 16px 12px;
        }

        .boutique-header {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .coins-pill {
            justify-content: center;
        }

        .menu-btn {
            text-align: center;
        }

        .boutique-title {
            font-size: 1.6rem;
            margin-bottom: 20px;
        }

        .boutique-grid {
            gap: 12px;
        }

        .category-card {
            min-height: 110px;
            padding: 14px;
        }

        .category-icon {
            font-size: 1.8rem;
            margin-bottom: 6px;
        }

        .category-name {
            font-size: 0.95rem;
        }

        .category-desc {
            font-size: 0.75rem;
        }
    }

    /* === Mobile Paysage (écrans courts) === */
    @media (max-height: 500px) and (orientation: landscape) {
        .boutique-scene {
            padding: 12px;
        }

        .boutique-header {
            margin-bottom: 12px;
        }

        .boutique-title {
            font-size: 1.4rem;
            margin-bottom: 16px;
        }

        .boutique-grid {
            gap: 10px;
        }

        .category-card {
            min-height: 100px;
            padding: 12px;
        }

        .category-icon {
            font-size: 1.6rem;
            margin-bottom: 4px;
        }

        .category-name {
            font-size: 0.9rem;
        }

        .category-desc {
            font-size: 0.7rem;
        }
    }

    /* === Tablettes Portrait === */
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .boutique-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .category-card {
            min-height: 150px;
        }
    }

    /* === Tablettes Paysage === */
    @media (min-width: 600px) and (max-width: 1024px) and (orientation: landscape) {
        .boutique-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    /* === Grands écrans === */
    @media (min-width: 1200px) {
        .boutique-grid {
            grid-template-columns: repeat(4, 1fr);
            max-width: 1100px;
        }

        .category-card {
            min-height: 180px;
        }
    }
</style>

<div class="boutique-scene">
    <div class="boutique-header">
        <div class="coins-pill">
            <img src="{{ asset('images/coin-intelligence.png') }}" alt="Intelligence">
            <b>{{ number_format($coins) }}</b>
        </div>
        <div class="coins-pill">
            <img src="{{ asset('images/skill_coin.png') }}" alt="Compétence">
            <b>{{ number_format($competenceCoins ?? 0) }}</b>
        </div>
        <a href="{{ route('menu') }}" class="menu-btn">{{ __('Menu') }}</a>
    </div>

    <h1 class="boutique-title">🛒 {{ __('Boutique') }}</h1>

    <div class="boutique-grid">
        <!-- Packs d'avatars -->
        <a href="{{ route('boutique.category', 'packs') }}" class="category-card">
            <div class="category-icon">🎨</div>
            <div class="category-name">{{ __("Packs d'avatars") }}</div>
            <div class="category-desc">{{ __('Nouveaux visuels pour votre profil') }}</div>
        </a>

        <!-- Musiques d'Ambiance -->
        <a href="{{ route('boutique.category', 'musiques') }}" class="category-card">
            <div class="category-icon">🎵</div>
            <div class="category-name">{{ __("Musiques d'Ambiance") }}</div>
            <div class="category-desc">{{ __('Ambiances musicales exclusives') }}</div>
        </a>

        <!-- Sons de Buzzers -->
        <a href="{{ route('boutique.category', 'buzzers') }}" class="category-card">
            <div class="category-icon">🔊</div>
            <div class="category-name">{{ __('Sons de Buzzers') }}</div>
            <div class="category-desc">{{ __('Personnalisez votre buzz') }}</div>
        </a>

        <!-- Avatars Stratégiques -->
        <a href="{{ route('boutique.category', 'strategiques') }}" class="category-card premium">
            <div class="category-icon">🛡️</div>
            <div class="category-name">{{ __('Avatars Stratégiques') }}</div>
            <div class="category-desc">{{ __('Pouvoirs spéciaux en jeu') }}</div>
        </a>

        <!-- Modes de Jeux -->
        <a href="{{ route('boutique.category', 'master') }}" class="category-card premium {{ $allModesPurchased ? 'unlocked' : '' }}">
            @if($allModesPurchased)
                <div class="category-badge">✓ {{ __('Tous débloqués') }}</div>
            @endif
            <div class="category-icon">🎮</div>
            <div class="category-name">{{ __('Modes de jeux') }}</div>
            <div class="category-desc">{{ __('Duo, Ligue et Maître du Jeu') }}</div>
        </a>

        <!-- Pièces d'Intelligence -->
        <a href="{{ route('boutique.category', 'coins_intelligence') }}" class="category-card">
            <div class="category-icon"><img src="{{ asset('images/coin-intelligence.png') }}" alt="{{ __("Pièce d'Intelligence") }}" style="width:48px;height:48px;object-fit:cover;object-position:center;clip-path:circle(50%);"></div>
            <div class="category-name">{{ __("Pièces d'Intelligence") }}</div>
            <div class="category-desc">{{ __('Gain dans Duo et Ligue') }}</div>
        </a>

        <!-- Pièces de Compétence -->
        <a href="{{ route('boutique.category', 'coins_competence') }}" class="category-card">
            <div class="category-icon"><img src="{{ asset('images/skill_coin.png') }}" alt="{{ __('Pièce de Compétence') }}" style="width:48px;height:48px;object-fit:cover;object-position:center;clip-path:circle(50%);"></div>
            <div class="category-name">{{ __('Pièces de Compétence') }}</div>
            <div class="category-desc">{{ __('Gain dans Solo') }}</div>
        </a>

        <!-- Vies -->
        <a href="{{ route('boutique.category', 'vies') }}" class="category-card">
            <div class="category-icon">❤️</div>
            <div class="category-name">{{ __('Vies') }}</div>
            <div class="category-desc">{{ __('Continuez à jouer') }}</div>
        </a>
    </div>
</div>
@endsection
