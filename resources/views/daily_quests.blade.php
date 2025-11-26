@extends('layouts.app')

@section('content')
<style>
    :root {
        --bg: #003DA5;
        --card-bg: #1E4B9E;
        --accent: #1E90FF;
        --accent-hover: #339CFF;
        --active-border: #FFD700;
        --inactive-bg: #0d2452;
    }

    body {
        background-color: var(--bg);
        color: #fff;
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }

    .daily-quests-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        text-align: center;
        margin-bottom: 30px;
    }

    .header h1 {
        font-size: clamp(2rem, 5vw, 3rem);
        margin: 0 0 10px 0;
    }

    .timer-section {
        text-align: center;
        margin-bottom: 40px;
        padding: 20px;
        background-color: var(--card-bg);
        border-radius: 15px;
        box-shadow: 0 4px 8px rgba(0,0,0,.3);
    }

    .timer-label {
        font-size: 1.2rem;
        color: #ddd;
        margin-bottom: 10px;
    }

    .timer-display {
        font-size: 2.5rem;
        font-weight: bold;
        color: var(--active-border);
    }

    .section-header {
        font-size: 1.8rem;
        margin-bottom: 20px;
        padding-left: 10px;
        border-left: 4px solid var(--active-border);
    }

    .quests-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .quest-card {
        background-color: var(--card-bg);
        border-radius: 12px;
        padding: 15px;
        box-shadow: 0 4px 8px rgba(0,0,0,.3);
        transition: transform .2s ease, box-shadow .2s ease;
        position: relative;
    }

    .quest-card.active {
        border: 3px solid var(--active-border);
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
    }

    .quest-card.active:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 16px rgba(255, 215, 0, 0.6);
    }

    .quest-card.inactive {
        background-color: var(--inactive-bg);
        opacity: 0.6;
    }

    .quest-card.inactive:hover {
        opacity: 0.8;
    }

    .quest-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .quest-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
    }

    .quest-emoji {
        font-size: 2rem;
    }

    .quest-card.inactive .quest-emoji {
        filter: grayscale(100%) brightness(0.5);
    }

    .quest-title {
        font-size: 1.1rem;
        font-weight: bold;
        color: #fff;
    }

    .quest-card.inactive .quest-title {
        color: #888;
    }

    .quest-reward {
        font-size: 1rem;
        color: var(--active-border);
        font-weight: bold;
        white-space: nowrap;
    }

    .quest-card.inactive .quest-reward {
        color: #555;
    }

    .quest-desc {
        font-size: 0.85rem;
        color: #ddd;
        margin-bottom: 10px;
    }

    .quest-card.inactive .quest-desc {
        color: #666;
    }

    .quest-progress {
        margin-top: 10px;
    }

    .progress-bar {
        background-color: rgba(0,0,0,0.3);
        border-radius: 10px;
        height: 18px;
        overflow: hidden;
        margin-bottom: 6px;
    }

    .progress-fill {
        background: linear-gradient(90deg, var(--accent), var(--accent-hover));
        height: 100%;
        transition: width .3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: bold;
    }

    .quest-card.inactive .progress-fill {
        background: linear-gradient(90deg, #333, #444);
    }

    .completed-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: #4CAF50;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: bold;
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

    .inactive-section-note {
        text-align: center;
        font-size: 1rem;
        color: #888;
        margin-bottom: 20px;
        font-style: italic;
    }

    @media (max-width: 768px) {
        .timer-display {
            font-size: 2rem;
        }
        .quest-emoji {
            font-size: 2.5rem;
        }
    }
</style>

<div class="daily-quests-container">
    <div class="header">
        <h1>☀️ {{ __('QUÊTES QUOTIDIENNES') }}</h1>
        <p style="font-size: 1.2rem; color: #ddd;">{{ __('Complétez 3 quêtes quotidiennes pour gagner des pièces') }} !</p>
    </div>

    <div class="timer-section">
        <div class="timer-label">⏰ {{ __('Temps restant avant réinitialisation') }}</div>
        <div class="timer-display" id="timer">
            <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
        </div>
    </div>

    @if($activeQuests->count() > 0)
        <h2 class="section-header">🎯 {{ __('Quêtes actives du jour') }}</h2>
        <div class="quests-grid">
            @foreach($activeQuests as $quest)
                <div class="quest-card active">
                    @if($quest->progress && $quest->progress->completed)
                        <div class="completed-badge">✓ {{ __('Complétée') }}</div>
                    @endif
                    
                    <div class="quest-header">
                        <div class="quest-title-row">
                            <span class="quest-emoji">{{ $quest->badge_emoji ?? '🎯' }}</span>
                            <span class="quest-title">{{ $quest->name }}</span>
                        </div>
                        <div class="quest-reward">💰 +{{ $quest->reward_coins ?? 10 }}</div>
                    </div>
                    
                    <div class="quest-desc">{{ $quest->condition ?? __('Quête quotidienne') }}</div>
                    
                    <div class="quest-progress">
                        @php
                            $currentValue = $quest->progress->current_value ?? 0;
                            $targetValue = $quest->detection_params['target_value'] ?? 1;
                            $percentage = $targetValue > 0 ? min(100, ($currentValue / $targetValue) * 100) : 0;
                        @endphp
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: {{ $percentage }}%">
                                {{ number_format($percentage, 0) }}%
                            </div>
                        </div>
                        <div style="text-align: center; font-size: 0.8rem; color: #ddd;">
                            {{ $currentValue }} / {{ $targetValue }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($inactiveQuests->count() > 0)
        <h2 class="section-header" style="border-left-color: #555;">📋 {{ __('Prochaines quêtes quotidiennes') }}</h2>
        <div class="inactive-section-note">{{ __('Ces quêtes seront disponibles lors des prochaines rotations') }}</div>
        <div class="quests-grid">
            @foreach($inactiveQuests as $quest)
                <div class="quest-card inactive">
                    <div class="quest-header">
                        <div class="quest-title-row">
                            <span class="quest-emoji">{{ $quest->badge_emoji ?? '📋' }}</span>
                            <span class="quest-title">{{ $quest->name }}</span>
                        </div>
                        <div class="quest-reward">💰 +{{ $quest->reward_coins ?? 10 }}</div>
                    </div>
                    
                    <div class="quest-desc">{{ $quest->condition ?? __('Quête quotidienne') }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div style="text-align: center;">
        <a href="{{ route('menu') }}" class="back-btn">← {{ __('Retour au Menu') }}</a>
    </div>
</div>

<script>
let timeRemaining = {{ $timeRemaining }};

function updateTimer() {
    if (timeRemaining <= 0) {
        location.reload();
        return;
    }
    
    const hours = Math.floor(timeRemaining / 3600);
    const minutes = Math.floor((timeRemaining % 3600) / 60);
    const seconds = timeRemaining % 60;
    
    document.getElementById('hours').textContent = String(hours).padStart(2, '0');
    document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
    document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
    
    timeRemaining--;
}

updateTimer();
setInterval(updateTimer, 1000);
</script>
@endsection
