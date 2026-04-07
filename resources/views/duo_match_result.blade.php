@extends('layouts.app')

@php
$playerWon    = $match_result['player_won'] ?? false;
$isDraw       = !$playerWon && ($match_result['player_rounds_won'] ?? 0) === ($match_result['opponent_rounds_won'] ?? 0);
$playerRoundsWon   = $match_result['player_rounds_won']   ?? 0;
$opponentRoundsWon = $match_result['opponent_rounds_won'] ?? 0;
$decidedBy    = $match_result['decided_by'] ?? 'rounds';

$globalStats  = $global_stats ?? $match_result['global_stats'] ?? [];
$totalPoints  = $globalStats['total_points'] ?? 0;
$correctCount = $globalStats['correct']      ?? 0;
$incorrectCount = $globalStats['incorrect']  ?? 0;
$totalAnswered  = $correctCount + $incorrectCount;

$opponentNameDisplay = $opponent_name ?? ($opponent->name ?? __('Adversaire'));
$coinsEarnedDisplay  = $coins_earned ?? 0;
$accuracyDisplay     = $accuracy ?? 0;

$resultClass = $playerWon ? 'victory' : ($isDraw ? 'draw' : 'defeat');
$resultIcon  = $playerWon ? '🏆'      : ($isDraw ? '🤝'   : '😔');
$resultTitle = $playerWon ? __('Victoire !') : ($isDraw ? __('Égalité') : __('Défaite'));
$resultColor = $playerWon ? '#11998e, #38ef7d' : ($isDraw ? '#667eea, #764ba2' : '#e74c3c, #c0392b');
@endphp

@section('title', $resultTitle . ' — StrategyBuzzer Duo')

@section('content')
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, {{ $resultColor }});
        min-height: 100vh;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 30px 16px 50px;
    }

    .result-card {
        background: rgba(255,255,255,0.97);
        border-radius: 28px;
        padding: 40px 36px;
        max-width: 600px;
        width: 100%;
        text-align: center;
        box-shadow: 0 30px 80px rgba(0,0,0,0.28);
        animation: slideUp .55s cubic-bezier(.34,1.56,.64,1) both;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(40px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .result-icon  { font-size: 4.5rem; margin-bottom: 12px; display: block; animation: {{ $playerWon ? 'bounce' : 'pulse' }} 1.2s ease-in-out infinite; }

    @keyframes bounce { 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-18px); } }
    @keyframes pulse  { 0%,100%{ transform:scale(1); }     50%{ transform:scale(1.12); } }

    .result-title {
        font-size: 2.6rem;
        font-weight: 900;
        background: linear-gradient(135deg, {{ $resultColor }});
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 8px;
    }

    .mode-badge {
        display: inline-block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 5px 18px;
        border-radius: 20px;
        font-size: .85rem;
        font-weight: 700;
        margin-bottom: 28px;
    }

    /* ── Opponent ───────────────────────────────── */
    .opponent-block {
        background: #f5f7fb;
        border-radius: 18px;
        padding: 20px 24px;
        margin-bottom: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 18px;
    }
    .opponent-block img {
        width: 70px; height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #667eea;
    }
    .opp-label { font-size: .85rem; color: #888; margin-bottom: 4px; }
    .opp-name  { font-size: 1.4rem; font-weight: 800; color: #222; }

    /* ── Rounds ─────────────────────────────────── */
    .rounds-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 22px;
        margin: 18px 0 22px;
    }
    .round-score {
        text-align: center;
    }
    .round-number {
        font-size: 3rem;
        font-weight: 900;
        line-height: 1;
    }
    .round-you  { color: #11998e; }
    .round-opp  { color: #e74c3c; }
    .round-vs   { font-size: 1.3rem; font-weight: 900; color: #aaa; }
    .round-label { font-size: .8rem; color: #888; margin-top: 4px; }

    /* ── Stats grid ──────────────────────────────── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 22px;
    }
    .stat-box {
        background: #f5f7fb;
        border-radius: 14px;
        padding: 14px 8px;
    }
    .stat-icon  { font-size: 1.6rem; margin-bottom: 4px; }
    .stat-value { font-size: 1.5rem; font-weight: 900; color: #222; }
    .stat-label { font-size: .75rem; color: #888; margin-top: 2px; }

    /* ── Rewards ──────────────────────────────────── */
    .rewards-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-radius: 18px;
        padding: 20px 24px;
        margin-bottom: 28px;
    }
    .rewards-title { font-size: 1rem; font-weight: 700; margin-bottom: 14px; opacity: .9; }
    .rewards-row   { display: flex; justify-content: center; gap: 36px; flex-wrap: wrap; }
    .reward-item   { text-align: center; }
    .reward-icon   { font-size: 1.8rem; }
    .reward-val    { font-size: 1.6rem; font-weight: 900; }
    .reward-lbl    { font-size: .75rem; opacity: .85; }

    /* ── Actions ──────────────────────────────────── */
    .action-row {
        display: flex;
        gap: 16px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .btn {
        padding: 16px 36px;
        font-size: 1rem;
        font-weight: 700;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: transform .2s, box-shadow .2s;
    }
    .btn:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,.18); }
    .btn-primary {
        background: linear-gradient(135deg, {{ $resultColor }});
        color: #fff;
    }
    .btn-secondary { background: #e8e8e8; color: #333; }

    @media (max-width: 520px) {
        .result-card { padding: 28px 20px; }
        .result-title { font-size: 2rem; }
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
        .rounds-row { gap: 14px; }
        .action-row { flex-direction: column; }
        .btn { width: 100%; text-align: center; }
        .opponent-block { flex-direction: column; text-align: center; }
    }
</style>

<div class="result-card">
    <span class="result-icon">{{ $resultIcon }}</span>
    <h1 class="result-title">{{ $resultTitle }}</h1>
    <span class="mode-badge">⚡ Duo</span>

    {{-- Opponent --}}
    <div class="opponent-block">
        <img src="{{ asset('images/avatars/standard/standard1.png') }}" alt="{{ $opponentNameDisplay }}" onerror="this.src='{{ asset('images/avatars/standard/standard1.png') }}'">
        <div>
            <div class="opp-label">{{ $playerWon ? __('Vous avez battu') : ($isDraw ? __('Match nul contre') : __('Vous avez perdu contre')) }}</div>
            <div class="opp-name">{{ $opponentNameDisplay }}</div>
        </div>
    </div>

    {{-- Rounds score --}}
    <div class="rounds-row">
        <div class="round-score">
            <div class="round-number round-you">{{ $playerRoundsWon }}</div>
            <div class="round-label">{{ __('Vos manches') }}</div>
        </div>
        <span class="round-vs">VS</span>
        <div class="round-score">
            <div class="round-number round-opp">{{ $opponentRoundsWon }}</div>
            <div class="round-label">{{ __('Ses manches') }}</div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-icon">🎯</div>
            <div class="stat-value">{{ $accuracyDisplay }}%</div>
            <div class="stat-label">{{ __('Précision') }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">✅</div>
            <div class="stat-value">{{ $correctCount }}</div>
            <div class="stat-label">{{ __('Correctes') }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon">⭐</div>
            <div class="stat-value">{{ $totalPoints }}</div>
            <div class="stat-label">{{ __('Points') }}</div>
        </div>
    </div>

    {{-- Rewards --}}
    @if($coinsEarnedDisplay > 0)
    <div class="rewards-box">
        <div class="rewards-title">🎁 {{ __('Récompenses') }}</div>
        <div class="rewards-row">
            <div class="reward-item">
                <div class="reward-icon">🪙</div>
                <div class="reward-val">+{{ $coinsEarnedDisplay }}</div>
                <div class="reward-lbl">{{ __('Pièces d\'Intelligence') }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Actions --}}
    <div class="action-row">
        <a href="{{ route('duo.lobby') }}" class="btn btn-primary">🔄 {{ __('Rejouer') }}</a>
        <a href="{{ route('menu') }}" class="btn btn-secondary">🏠 {{ __('Menu') }}</a>
    </div>
</div>

@if($playerWon)
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof confetti !== 'undefined') {
        confetti({ particleCount: 120, spread: 80, origin: { y: 0.55 } });
        setTimeout(function() {
            confetti({ particleCount: 60, spread: 60, origin: { x: 0.1, y: 0.6 } });
            confetti({ particleCount: 60, spread: 60, origin: { x: 0.9, y: 0.6 } });
        }, 800);
    }
});
</script>
@endif
@endsection
