@extends('layouts.app')

@section('content')
<div class="guide-container" style="--mode-color: {{ $modeData['color'] }};">
    <div class="brain-bg" id="brain-stage">
        <img src="{{ asset('images/brain.png') }}" alt="" class="floating-brain" id="main-brain">
    </div>

    <a href="{{ route('guide.index') }}" class="back-button-fixed">← {{ __('Guide') }}</a>

    <h1 class="guide-title">{{ $modeData['icon'] }} {{ __($modeData['name']) }}</h1>

    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="regles">🎮 {{ __('Règles du Jeu') }}</button>
            <button class="tab-btn" data-tab="points">🏆 {{ __('Système de Points') }}</button>
            <button class="tab-btn" data-tab="outils">🛠️ {{ __('Outils Disponibles') }}</button>
        </div>

        <div class="tab-content active" id="tab-regles">
            @include('guide.content.' . $mode . '_regles')
        </div>

        <div class="tab-content" id="tab-points">
            @include('guide.content.' . $mode . '_points')
        </div>

        <div class="tab-content" id="tab-outils">
            @include('guide.content.' . $mode . '_outils')
        </div>
    </div>

    <div class="other-modes">
        <p class="other-modes-title">{{ __('Autres modes') }}</p>
        <div class="other-modes-grid">
            @foreach($allModes as $modeKey => $modeInfo)
                @if($modeKey !== $mode)
                <a href="{{ route('guide.show', $modeKey) }}" class="mini-mode-card" style="--card-color: {{ $modeInfo['color'] }};">
                    <span>{{ $modeInfo['icon'] }}</span>
                </a>
                @endif
            @endforeach
        </div>
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
    opacity: 0.06;
    left: 50%;
    transform: translateX(-50%);
    animation: brainFloat 8s linear infinite;
}

@keyframes brainFloat {
    0% { top: -30%; opacity: 0; }
    10% { opacity: 0.06; }
    90% { opacity: 0.06; }
    100% { top: 110%; opacity: 0; }
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
    font-size: 2.2rem;
    margin-bottom: 30px;
    text-shadow: 0 4px 15px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}

.tabs-container {
    max-width: 900px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.tabs-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    justify-content: center;
}

.tab-btn {
    background: rgba(255,255,255,0.15);
    border: 2px solid rgba(255,255,255,0.3);
    color: white;
    padding: 12px 24px;
    border-radius: 30px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    background: rgba(255,255,255,0.25);
}

.tab-btn.active {
    background: var(--mode-color);
    border-color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

.tab-content {
    display: none;
    background: rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 30px;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.2);
    color: white;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.tab-content h3 {
    color: white;
    font-size: 1.4rem;
    margin-bottom: 15px;
    border-bottom: 2px solid var(--mode-color);
    padding-bottom: 10px;
}

.tab-content ul {
    list-style: none;
    padding: 0;
}

.tab-content li {
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.tab-content li:last-child {
    border-bottom: none;
}

.tab-content li::before {
    content: '▸';
    color: var(--mode-color);
    font-weight: bold;
}

.other-modes {
    margin-top: 40px;
    text-align: center;
    position: relative;
    z-index: 1;
}

.other-modes-title {
    color: rgba(255,255,255,0.7);
    margin-bottom: 15px;
    font-size: 0.95rem;
}

.other-modes-grid {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.mini-mode-card {
    width: 50px;
    height: 50px;
    background: var(--card-color);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

.mini-mode-card:hover {
    transform: scale(1.15);
}

@media (max-width: 600px) {
    .guide-title { font-size: 1.6rem; }
    .tabs-nav { flex-direction: column; align-items: stretch; }
    .tab-btn { text-align: center; }
    .tab-content { padding: 20px 15px; }
    .floating-brain { width: 100vw; }
}

@media (orientation: landscape) and (max-height: 500px) {
    .guide-container { padding-top: 60px; }
    .guide-title { font-size: 1.4rem; margin-bottom: 15px; }
    .tab-content { padding: 15px; }
}
</style>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});

(function() {
    const brain = document.getElementById('main-brain');
    if (!brain) return;
    
    brain.addEventListener('animationiteration', () => {
        brain.style.animation = 'none';
        brain.offsetHeight;
        setTimeout(() => {
            brain.style.animation = 'brainFloat 8s linear infinite';
        }, 1000);
    });
})();
</script>
@endsection
