@extends('layouts.app')

@section('content')
<style>
    :root {
        --bg: #003DA5;
        --card-bg: #1E4B9E;
        --accent: #1E90FF;
        --accent-hover: #339CFF;
        --bronze: #CD7F32;
        --silver: #C0C0C0;
        --gold: #FFD700;
        --locked: #555;
    }

    body {
        background-color: var(--bg);
        color: #fff;
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }

    .badges-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        text-align: center;
        margin-bottom: 40px;
    }

    .header h1 {
        font-size: clamp(2rem, 5vw, 3rem);
        margin: 0 0 10px 0;
    }

    .tier-section {
        margin-bottom: 50px;
    }

    .tier-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        margin-bottom: 25px;
    }

    .tier-icon {
        font-size: 2.5rem;
    }

    .tier-title {
        font-size: 2rem;
        margin: 0;
    }

    .tier-bronze .tier-title { color: var(--bronze); }
    .tier-silver .tier-title { color: var(--silver); }
    .tier-gold .tier-title { color: var(--gold); }

    .badges-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    .badge-card {
        background-color: var(--card-bg);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 8px rgba(0,0,0,.3);
        transition: transform .2s ease;
        position: relative;
    }

    .badge-card:hover {
        transform: translateY(-2px);
    }

    .badge-card.locked {
        background-color: var(--locked);
        opacity: 0.6;
    }

    .badge-card.unlocked {
        border: 2px solid var(--gold);
        animation: glow 2s ease-in-out infinite;
    }

    @keyframes glow {
        0%, 100% {
            box-shadow: 0 4px 8px rgba(0,0,0,.3);
        }
        50% {
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.6);
        }
    }

    .badge-icon {
        font-size: 3rem;
        margin-bottom: 10px;
    }

    .badge-card.locked .badge-icon {
        filter: grayscale(100%) brightness(0.3);
    }

    .badge-title {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 8px;
    }

    .badge-card.locked .badge-title {
        color: #888;
    }

    .badge-desc {
        font-size: 0.9rem;
        color: #ddd;
        margin-bottom: 12px;
    }

    .badge-card.locked .badge-desc {
        color: #666;
    }

    .badge-reward {
        color: var(--gold);
        font-weight: bold;
        font-size: 1.1rem;
    }

    .badge-card.locked .badge-reward {
        color: #777;
    }

    .back-btn {
        display: inline-block;
        margin-top: 30px;
        padding: 12px 24px;
        background-color: var(--accent);
        color: #fff;
        text-decoration: none;
        border-radius: 10px;
        transition: background-color .25s ease;
    }

    .back-btn:hover {
        background-color: var(--accent-hover);
    }

    .locked-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(0,0,0,.4);
        border-radius: 12px;
        font-size: 2rem;
        color: #888;
    }
</style>

<div class="badges-container">
    <div class="header">
        <h1>🏆 BADGES</h1>
        <p style="font-size: 1.2rem; color: #ddd;">Débloquez tous les badges en complétant des quêtes !</p>
    </div>

    <div class="tier-section tier-bronze">
        <div class="tier-header">
            <span class="tier-icon">🥉</span>
            <h2 class="tier-title">BRONZE</h2>
            <span class="tier-icon">🥉</span>
        </div>
        <div class="badges-grid">
            @foreach($bronzeQuests as $quest)
                <div class="badge-card {{ $quest->progress && $quest->progress->completed ? 'unlocked' : 'locked' }}">
                    <div class="badge-icon">
                        @if($quest->progress && $quest->progress->completed)
                            🏅
                        @else
                            🔒
                        @endif
                    </div>
                    <div class="badge-title">
                        @if($quest->progress && $quest->progress->completed)
                            {{ $quest->name }}
                        @else
                            ???
                        @endif
                    </div>
                    <div class="badge-desc">
                        @if($quest->progress && $quest->progress->completed)
                            {{ $quest->description }}
                        @else
                            Badge verrouillé
                        @endif
                    </div>
                    <div class="badge-reward">
                        @if($quest->progress && $quest->progress->completed)
                            🧠 {{ $quest->reward_pieces }} pièces
                        @else
                            🧠 ??
                        @endif
                    </div>
                    @if(!$quest->progress || !$quest->progress->completed)
                        <div class="locked-overlay">🔒</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="tier-section tier-silver">
        <div class="tier-header">
            <span class="tier-icon">🥈</span>
            <h2 class="tier-title">ARGENT</h2>
            <span class="tier-icon">🥈</span>
        </div>
        <div class="badges-grid">
            @foreach($silverQuests as $quest)
                <div class="badge-card {{ $quest->progress && $quest->progress->completed ? 'unlocked' : 'locked' }}">
                    <div class="badge-icon">
                        @if($quest->progress && $quest->progress->completed)
                            🎖️
                        @else
                            🔒
                        @endif
                    </div>
                    <div class="badge-title">
                        @if($quest->progress && $quest->progress->completed)
                            {{ $quest->name }}
                        @else
                            ???
                        @endif
                    </div>
                    <div class="badge-desc">
                        @if($quest->progress && $quest->progress->completed)
                            {{ $quest->description }}
                        @else
                            Badge verrouillé
                        @endif
                    </div>
                    <div class="badge-reward">
                        @if($quest->progress && $quest->progress->completed)
                            🧠 {{ $quest->reward_pieces }} pièces
                        @else
                            🧠 ??
                        @endif
                    </div>
                    @if(!$quest->progress || !$quest->progress->completed)
                        <div class="locked-overlay">🔒</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="tier-section tier-gold">
        <div class="tier-header">
            <span class="tier-icon">🥇</span>
            <h2 class="tier-title">OR</h2>
            <span class="tier-icon">🥇</span>
        </div>
        <div class="badges-grid">
            @foreach($goldQuests as $quest)
                <div class="badge-card {{ $quest->progress && $quest->progress->completed ? 'unlocked' : 'locked' }}">
                    <div class="badge-icon">
                        @if($quest->progress && $quest->progress->completed)
                            👑
                        @else
                            🔒
                        @endif
                    </div>
                    <div class="badge-title">
                        @if($quest->progress && $quest->progress->completed)
                            {{ $quest->name }}
                        @else
                            ???
                        @endif
                    </div>
                    <div class="badge-desc">
                        @if($quest->progress && $quest->progress->completed)
                            {{ $quest->description }}
                        @else
                            Badge verrouillé
                        @endif
                    </div>
                    <div class="badge-reward">
                        @if($quest->progress && $quest->progress->completed)
                            🧠 {{ $quest->reward_pieces }} pièces
                        @else
                            🧠 ??
                        @endif
                    </div>
                    @if(!$quest->progress || !$quest->progress->completed)
                        <div class="locked-overlay">🔒</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ route('menu') }}" class="back-btn">← Retour au Menu</a>
    </div>
</div>
@endsection
