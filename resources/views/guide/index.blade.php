@extends('layouts.app')

@section('content')
<div class="guide-container">
    <div class="brain-bg" id="brain-stage">
        <img src="{{ asset('images/brain.png') }}" alt="" class="floating-brain" id="main-brain">
    </div>

    <a href="{{ route('menu') }}" class="back-button-fixed">← {{ __('Menu') }}</a>

    <h1 class="guide-title">📜 {{ __('GUIDE DU JOUEUR') }}</h1>
    <p class="guide-subtitle">{{ __('Choisissez un mode pour voir les règles et astuces') }}</p>

    <div class="modes-grid">
        <a href="{{ route('guide.show', 'solo') }}" class="mode-card" style="--mode-color: #4CAF50;">
            <span class="mode-icon">🎮</span>
            <span class="mode-name">{{ __('SOLO') }}</span>
            <span class="mode-desc">{{ __('Affrontez 90 adversaires IA') }}</span>
        </a>

        <a href="{{ route('guide.show', 'duo') }}" class="mode-card" style="--mode-color: #2196F3;">
            <span class="mode-icon">👥</span>
            <span class="mode-name">{{ __('DUO') }}</span>
            <span class="mode-desc">{{ __('Défiez un ami en 1v1') }}</span>
        </a>

        <a href="{{ route('guide.show', 'ligue-individuelle') }}" class="mode-card" style="--mode-color: #FF9800;">
            <span class="mode-icon">🏆</span>
            <span class="mode-name">{{ __('LIGUE INDIVIDUELLE') }}</span>
            <span class="mode-desc">{{ __('Grimpez les divisions') }}</span>
        </a>

        <a href="{{ route('guide.show', 'ligue-equipe') }}" class="mode-card" style="--mode-color: #9C27B0;">
            <span class="mode-icon">⚔️</span>
            <span class="mode-name">{{ __('LIGUE ÉQUIPE') }}</span>
            <span class="mode-desc">{{ __('Combattez en équipe de 5') }}</span>
        </a>

        <a href="{{ route('guide.show', 'master') }}" class="mode-card" style="--mode-color: #F44336;">
            <span class="mode-icon">👑</span>
            <span class="mode-name">{{ __('MAÎTRE DU JEU') }}</span>
            <span class="mode-desc">{{ __('Créez et animez vos quiz') }}</span>
        </a>

        <a href="{{ route('guide.show', 'avatars') }}" class="mode-card" style="--mode-color: #00BCD4;">
            <span class="mode-icon">🦸</span>
            <span class="mode-name">{{ __('AVATARS') }}</span>
            <span class="mode-desc">{{ __('Skills et stratégies') }}</span>
        </a>
    </div>
</div>

<style>
.guide-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #1a237e 0%, #0d47a1 50%, #01579b 100%);
    padding: 20px;
    padding-top: 80px;
    position: relative;
    overflow: hidden;
}

.brain-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}

.floating-brain {
    position: absolute;
    width: 80vw;
    max-width: 600px;
    opacity: 0.08;
    left: 50%;
    transform: translateX(-50%);
    animation: brainFloat 8s linear infinite;
}

@keyframes brainFloat {
    0% {
        top: -30%;
        opacity: 0;
    }
    10% {
        opacity: 0.08;
    }
    90% {
        opacity: 0.08;
    }
    100% {
        top: 110%;
        opacity: 0;
    }
}

.back-button-fixed {
    position: fixed;
    top: 20px;
    left: 20px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: bold;
    z-index: 100;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.back-button-fixed:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateX(-5px);
}

.guide-title {
    text-align: center;
    color: white;
    font-size: 2.5rem;
    margin-bottom: 10px;
    text-shadow: 0 4px 15px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}

.guide-subtitle {
    text-align: center;
    color: rgba(255,255,255,0.8);
    font-size: 1.1rem;
    margin-bottom: 40px;
    position: relative;
    z-index: 1;
}

.modes-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.mode-card {
    background: linear-gradient(135deg, var(--mode-color) 0%, color-mix(in srgb, var(--mode-color) 70%, black) 100%);
    border-radius: 20px;
    padding: 30px 20px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    border: 3px solid rgba(255,255,255,0.2);
}

.mode-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 40px rgba(0,0,0,0.4);
    border-color: rgba(255,255,255,0.5);
}

.mode-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.mode-name {
    color: white;
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 8px;
    text-shadow: 0 2px 5px rgba(0,0,0,0.3);
}

.mode-desc {
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
}

@media (max-width: 600px) {
    .modes-grid {
        grid-template-columns: 1fr;
        padding: 0 10px;
    }
    
    .guide-title {
        font-size: 1.8rem;
    }
    
    .mode-card {
        padding: 25px 15px;
    }
    
    .mode-icon {
        font-size: 2.5rem;
    }
    
    .mode-name {
        font-size: 1.1rem;
    }
    
    .floating-brain {
        width: 100vw;
    }
}

@media (orientation: landscape) and (max-height: 500px) {
    .guide-container {
        padding-top: 60px;
    }
    
    .modes-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }
    
    .mode-card {
        padding: 20px 15px;
    }
    
    .mode-icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }
}
</style>

<script>
(function() {
    const brain = document.getElementById('main-brain');
    if (!brain) return;
    
    function resetAnimation() {
        brain.style.animation = 'none';
        brain.offsetHeight;
        setTimeout(() => {
            brain.style.animation = 'brainFloat 8s linear infinite';
        }, 1000);
    }
    
    brain.addEventListener('animationiteration', () => {
        setTimeout(resetAnimation, 0);
    });
})();
</script>
@endsection
