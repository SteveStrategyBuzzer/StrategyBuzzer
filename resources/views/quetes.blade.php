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
    }

    body {
        background-color: var(--bg);
        color: #fff;
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }

    .quests-container {
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

    .intelligence-counter {
        display: inline-block;
        background-color: rgba(255, 215, 0, 0.2);
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 1.5rem;
        margin-bottom: 20px;
    }

    .intelligence-icon {
        color: #FFD700;
        margin-right: 8px;
    }

    .section {
        margin-bottom: 40px;
    }

    .section-title {
        font-size: 1.8rem;
        margin-bottom: 20px;
        color: #FFD700;
    }

    .quests-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .quest-card {
        background-color: var(--card-bg);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 8px rgba(0,0,0,.3);
        transition: transform .2s ease;
    }

    .quest-card:hover {
        transform: translateY(-2px);
    }

    .quest-card.completed {
        border: 2px solid var(--gold);
        background: linear-gradient(135deg, var(--card-bg) 0%, rgba(255, 215, 0, 0.1) 100%);
    }

    .quest-title {
        font-size: 1.3rem;
        margin-bottom: 10px;
        font-weight: bold;
    }

    .quest-desc {
        font-size: 0.95rem;
        color: #ddd;
        margin-bottom: 15px;
    }

    .quest-tier {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .tier-bronze { background-color: var(--bronze); }
    .tier-silver { background-color: var(--silver); color: #333; }
    .tier-gold { background-color: var(--gold); color: #333; }

    .quest-progress {
        margin-top: 10px;
    }

    .progress-bar {
        width: 100%;
        height: 20px;
        background-color: rgba(0,0,0,.3);
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #1E90FF, #339CFF);
        transition: width .3s ease;
    }

    .progress-text {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: bold;
        color: #fff;
        text-shadow: 1px 1px 2px rgba(0,0,0,.5);
    }

    .quest-reward {
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .reward-amount {
        color: var(--gold);
        font-weight: bold;
        font-size: 1.2rem;
    }

    .claim-btn {
        background-color: var(--gold);
        color: #000;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: transform .2s ease;
    }

    .claim-btn:hover {
        transform: scale(1.05);
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

    .empty-message {
        text-align: center;
        font-size: 1.2rem;
        color: #aaa;
        padding: 40px;
    }
</style>

<div class="quests-container">
    <div class="header">
        <h1>🎯 QUÊTES</h1>
        <div class="intelligence-counter">
            <span class="intelligence-icon">🧠</span>
            <span>{{ $totalIntelligencePieces }} Pièces d'Intelligence</span>
        </div>
    </div>

    <audio id="questCompleteSound" preload="auto">
    <source src="{{ asset('sounds/quest_complete.mp3') }}" type="audio/mpeg">
</audio>

@if(session('success'))
        <div style="background-color: rgba(0,255,0,0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            {{ session('success') }}
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const audio = document.getElementById('questCompleteSound');
                if (audio) {
                    audio.play().catch(e => console.log('Audio blocked:', e));
                }
            });
        </script>
    @endif

    <div class="section">
        <h2 class="section-title">✅ Quêtes Complétées (à réclamer)</h2>
        @if($completedQuests->count() > 0)
            <div class="quests-grid">
                @foreach($completedQuests as $progress)
                    <div class="quest-card completed">
                        <div class="quest-tier tier-{{ $progress->quest->tier }}">
                            {{ strtoupper($progress->quest->tier) }}
                        </div>
                        <div class="quest-title">{{ $progress->quest->name }}</div>
                        <div class="quest-desc">{{ $progress->quest->description }}</div>
                        <div class="quest-reward">
                            <span class="reward-amount">🧠 +{{ $progress->quest->reward_pieces }} pièces</span>
                            <form action="{{ route('quetes.claim', $progress->quest->id) }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="claim-btn">RÉCLAMER</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-message">Aucune quête complétée pour le moment</div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">📋 Quêtes en Cours</h2>
        @if($activeQuests->count() > 0)
            <div class="quests-grid">
                @foreach($activeQuests as $progress)
                    @php
                        $percentage = $progress->quest->target_value > 0 ? min(100, ($progress->current_value / $progress->quest->target_value) * 100) : 0;
                    @endphp
                    <div class="quest-card">
                        <div class="quest-tier tier-{{ $progress->quest->tier }}">
                            {{ strtoupper($progress->quest->tier) }}
                        </div>
                        <div class="quest-title">{{ $progress->quest->name }}</div>
                        <div class="quest-desc">{{ $progress->quest->description }}</div>
                        <div class="quest-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: {{ $percentage }}%"></div>
                                <div class="progress-text">{{ $progress->current_value }} / {{ $progress->quest->target_value }}</div>
                            </div>
                        </div>
                        <div class="quest-reward">
                            <span class="reward-amount">🧠 +{{ $progress->quest->reward_pieces }} pièces</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-message">Aucune quête active pour le moment</div>
        @endif
    </div>

    <div style="text-align: center;">
        <a href="{{ route('menu') }}" class="back-btn">← Retour au Menu</a>
    </div>
</div>
@endsection
