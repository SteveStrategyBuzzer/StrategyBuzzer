@extends('layouts.app')

@section('content')
<style>
:root {
    --lg-gold:#FFD700;
    --lg-gold-2:#FFA928;
    --lg-violet:#c4b5fd;
    --lg-card-border:rgba(255,255,255,0.16);
}
* { box-sizing: border-box; }
body {
    background:
        radial-gradient(1100px 560px at 50% -8%, rgba(124,99,255,0.40), transparent 60%),
        radial-gradient(900px 500px at 110% 110%, rgba(0,180,255,0.18), transparent 55%),
        radial-gradient(700px 400px at -10% 90%, rgba(255,170,40,0.10), transparent 55%),
        linear-gradient(160deg, #05133b 0%, #0a2a6e 48%, #06204f 100%);
    color: #fff;
    text-align: center;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    padding: 24px 20px 56px;
}

/* ── BACK BUTTON ── */
.header-menu {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255,255,255,0.10);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.22);
    padding: 9px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    backdrop-filter: blur(8px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.25s ease;
    z-index: 20;
}
.header-menu:hover {
    background: rgba(255,255,255,0.20);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.35);
    color: #fff;
}

/* ── CONTAINER / HERO ── */
.ligue-container {
    max-width: 960px;
    width: 100%;
    margin-top: 56px;
    position: relative;
}
.ligue-title {
    font-size: clamp(2.6rem, 7vw, 4.6rem);
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin: 0 0 0.4rem;
    line-height: 1;
    background: linear-gradient(180deg, #ffffff 0%, #ffe9a8 42%, var(--lg-gold) 72%, var(--lg-gold-2) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 6px 22px rgba(0,0,0,0.55));
}
.ligue-title::after {
    content: "";
    display: block;
    width: 96px;
    height: 4px;
    margin: 0.7rem auto 0;
    border-radius: 999px;
    background: linear-gradient(90deg, transparent, var(--lg-gold), transparent);
    box-shadow: 0 0 18px rgba(255,215,0,0.6);
}
.ligue-subtitle {
    font-size: clamp(0.95rem, 2.4vw, 1.2rem);
    margin: 0.9rem 0 1.6rem;
    opacity: 0.85;
    letter-spacing: 0.04em;
}

/* ── RANK LADDER ── */
.rank-ladder {
    width: 100%;
    max-width: 760px;
    margin: 0 auto 2rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 18px;
    padding: 14px 18px 16px;
    backdrop-filter: blur(10px);
}
.rank-ladder-label {
    font-size: 0.72rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    opacity: 0.75;
    margin-bottom: 0.85rem;
}
.rank-ladder-track {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.rank-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.42rem 0.85rem;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    box-shadow: 0 4px 14px rgba(0,0,0,0.32);
    border: 1px solid rgba(255,255,255,0.25);
    white-space: nowrap;
}
.rank-arrow {
    opacity: 0.4;
    font-weight: 700;
    font-size: 0.85rem;
}
.chip-bronze   { background: linear-gradient(135deg,#cd7f32,#a35e22); color:#fff; }
.chip-argent   { background: linear-gradient(135deg,#e9e9e9,#b9b9b9); color:#2a2a2a; }
.chip-or       { background: linear-gradient(135deg,#ffe27a,#ffcb05); color:#3a2c00; }
.chip-platine  { background: linear-gradient(135deg,#f4f4f2,#cfd6d8); color:#2a2a2a; }
.chip-diamant  { background: linear-gradient(135deg,#b9f2ff,#00d4ff); color:#063248; }
.chip-legende  { background: linear-gradient(135deg,#c4b5fd,#7c3aed); color:#fff; box-shadow:0 0 18px rgba(124,58,237,0.6); }

/* ── MODES GRID ── */
.ligue-modes {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 1.5rem;
    margin-top: 0.5rem;
    align-items: start;
}

/* ── BASE CARD LOOK (shared) ── */
.ligue-mode-card, .team-section {
    position: relative;
    overflow: hidden;
    background: linear-gradient(160deg, rgba(255,255,255,0.13), rgba(255,255,255,0.035));
    border: 1px solid var(--lg-card-border);
    border-radius: 22px;
    backdrop-filter: blur(10px);
    box-shadow: 0 18px 40px rgba(0,0,0,0.35);
}

/* Top accent line */
.ligue-mode-card::before, .team-section::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--lg-gold), transparent);
    opacity: 0.85;
}
.team-section::before {
    background: linear-gradient(90deg, transparent, var(--lg-violet), transparent);
}

/* ── INDIVIDUAL CARD ── */
.ligue-mode-card {
    padding: 2.4rem 1.6rem 2rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    min-height: 100%;
    justify-content: center;
}
/* Shine sweep on hover */
.ligue-mode-card::after {
    content: "";
    position: absolute;
    top: -60%; left: -30%;
    width: 50%; height: 220%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,0.18), transparent);
    transform: rotate(20deg);
    transition: left 0.6s ease;
    pointer-events: none;
}
.ligue-mode-card:hover {
    transform: translateY(-8px);
    border-color: var(--lg-gold);
    box-shadow: 0 24px 54px rgba(0,0,0,0.45), 0 0 0 1px rgba(255,215,0,0.35);
}
.ligue-mode-card:hover::after { left: 130%; }

.mode-icon {
    width: 102px;
    height: 102px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.1rem;
    background: radial-gradient(circle at 32% 28%, rgba(255,255,255,0.28), rgba(255,255,255,0.03));
    border: 1px solid rgba(255,255,255,0.28);
    box-shadow: 0 10px 32px rgba(0,0,0,0.35), inset 0 0 26px rgba(255,215,0,0.18);
}
.mode-title {
    font-size: clamp(1.4rem, 3.5vw, 1.9rem);
    font-weight: 800;
    margin: 0;
    letter-spacing: 0.05em;
}
.mode-description {
    font-size: 0.95rem;
    opacity: 0.85;
    margin: 0;
    max-width: 22rem;
    line-height: 1.5;
}
.mode-badge {
    background: linear-gradient(135deg, rgba(255,215,0,0.18), rgba(255,169,40,0.1));
    border: 1px solid var(--lg-gold);
    color: var(--lg-gold);
    padding: 0.5rem 1.2rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
    margin-top: 0.4rem;
    letter-spacing: 0.04em;
}

/* ── TEAM SECTION ── */
.team-section {
    padding: 1.6rem 1.5rem;
    text-align: left;
}
.team-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.team-section-title {
    font-size: 1.45rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: 0.04em;
}
.team-action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.btn-create-team, .btn-join-team {
    padding: 0.55rem 1.05rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}
.btn-create-team {
    background: linear-gradient(135deg, var(--lg-gold), var(--lg-gold-2));
    color: #003DA5;
    box-shadow: 0 6px 16px rgba(255,180,0,0.3);
}
.btn-create-team:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(255,180,0,0.45); }
.btn-join-team {
    background: rgba(255,255,255,0.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.4);
    position: relative;
    display: flex;
    align-items: center;
    gap: 6px;
}
.btn-join-team:hover { background: rgba(255,255,255,0.22); transform: translateY(-2px); }
.invitation-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: #ff4444;
    border-radius: 9px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
    animation: badgePulse 2s infinite;
}
@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.12); }
}
.team-intro {
    font-size: 0.9rem;
    opacity: 0.85;
    margin-bottom: 1.1rem;
    text-align: left;
    line-height: 1.5;
}
.team-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.team-card {
    background: rgba(255,255,255,0.10);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    transition: all 0.2s ease;
}
.team-card:hover {
    background: rgba(255,255,255,0.18);
    transform: translateY(-2px);
    border-color: rgba(255,255,255,0.3);
}
.team-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.team-emblem {
    font-size: 2rem;
    width: 54px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2), rgba(0,0,0,0.25));
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 12px;
}
.team-details { text-align: left; }
.team-name {
    font-weight: 800;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.team-tag {
    background: rgba(255,255,255,0.2);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}
.team-meta {
    font-size: 0.85rem;
    opacity: 0.78;
    margin-top: 0.25rem;
}
.captain-badge {
    background: linear-gradient(135deg, var(--lg-gold), var(--lg-gold-2));
    color: #003DA5;
    padding: 0.12rem 0.45rem;
    border-radius: 5px;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.team-actions-row {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-left: auto;
    flex-wrap: wrap;
}
.team-efficiency { font-size: 0.85rem; font-weight: 700; color: #fff; }
.team-level {
    padding: 0.25rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border: 1px solid rgba(255,255,255,0.25);
}
.team-level.bronze   { background: linear-gradient(135deg,#cd7f32,#a35e22); color:#fff; }
.team-level.silver,
.team-level.argent   { background: linear-gradient(135deg,#e9e9e9,#b9b9b9); color:#2a2a2a; }
.team-level.gold,
.team-level.or       { background: linear-gradient(135deg,#ffe27a,#ffcb05); color:#3a2c00; }
.team-level.platinum,
.team-level.platine  { background: linear-gradient(135deg,#f4f4f2,#cfd6d8); color:#2a2a2a; }
.team-level.diamond,
.team-level.diamant  { background: linear-gradient(135deg,#b9f2ff,#00d4ff); color:#063248; }
.btn-team-action {
    padding: 0.5rem 1.1rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}
.btn-select {
    background: linear-gradient(135deg, #4CAF50, #2e9e53);
    color: #fff;
    box-shadow: 0 6px 16px rgba(76,175,80,0.3);
}
.btn-select:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(76,175,80,0.45); }
.empty-state {
    text-align: center;
    padding: 2.2rem 1rem;
    opacity: 0.85;
}
.empty-state-icon { font-size: 3rem; margin-bottom: 0.5rem; }
.pending-invitations {
    margin-top: 1.2rem;
    padding-top: 1.1rem;
    border-top: 1px solid rgba(255,255,255,0.18);
}
.pending-invitation-card {
    background: linear-gradient(135deg, rgba(255,215,0,0.16), rgba(255,215,0,0.06));
    border: 1px solid rgba(255,215,0,0.4);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
}
.invitation-info { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.invitation-actions { display: flex; gap: 0.5rem; }
.btn-accept {
    background: linear-gradient(135deg, #4CAF50, #2e9e53);
    color: #fff;
    padding: 0.45rem 0.9rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: transform 0.2s ease;
}
.btn-accept:hover { transform: translateY(-1px); }
.btn-decline {
    background: linear-gradient(135deg, #e0464f, #c1303a);
    color: #fff;
    padding: 0.45rem 0.9rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: transform 0.2s ease;
}
.btn-decline:hover { transform: translateY(-1px); }

/* ── KEYBOARD FOCUS ── */
.header-menu:focus-visible,
.ligue-mode-card:focus-visible,
.btn-create-team:focus-visible,
.btn-join-team:focus-visible,
.btn-select:focus-visible,
.btn-team-action:focus-visible,
.btn-accept:focus-visible,
.btn-decline:focus-visible {
    outline: 3px solid var(--lg-gold);
    outline-offset: 2px;
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .ligue-modes { grid-template-columns: 1fr; gap: 1.2rem; }
}
@media (max-width: 768px) {
    html, body { overflow-x: hidden; max-width: 100vw; }
    body { padding: 16px 12px 40px; }
    .ligue-container { margin-top: 48px; }
    .rank-ladder { padding: 12px 12px 14px; }
    .rank-chip { font-size: 0.72rem; padding: 0.36rem 0.7rem; }
    .ligue-mode-card { padding: 1.8rem 1.2rem; }
    .mode-icon { width: 84px; height: 84px; font-size: 2.6rem; }
    .team-section { padding: 1.2rem; }
    .team-section-header { flex-direction: column; align-items: flex-start; }
    .team-action-buttons { width: 100%; }
    .team-card { flex-direction: column; align-items: flex-start; }
    .team-info { width: 100%; }
    .team-actions-row { margin-left: 0; margin-top: 0.5rem; width: 100%; }
    .pending-invitation-card { flex-direction: column; align-items: flex-start; }
    .invitation-actions { width: 100%; justify-content: flex-end; }
    .header-menu { padding: 7px 13px; font-size: 0.85rem; right: 12px; top: 12px; }
}
</style>

{{-- ── SPLASH INTRO ── --}}
<div id="ligue-splash">
    <img src="{{ asset('images/ligue_hero.png') }}" alt="Ligue" id="ligue-splash-img">
    <div id="ligue-splash-overlay">
        <div id="ligue-splash-title">{{ __('LIGUE') }}</div>
        <div id="ligue-splash-sub">{{ __('Arène des Champions') }}</div>
    </div>
</div>

<style>
#ligue-splash {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    animation: splashExit 0.6s ease-in 2.6s forwards;
    pointer-events: all;
}
#ligue-splash-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 12%;
    animation: splashZoom 3.2s ease-out forwards;
}
#ligue-splash-overlay {
    position: relative;
    z-index: 2;
    text-align: center;
    animation: splashTextIn 0.8s ease-out 0.3s both;
}
#ligue-splash-title {
    font-size: clamp(3rem, 12vw, 7rem);
    font-weight: 900;
    letter-spacing: 0.15em;
    color: #fff;
    text-shadow: 0 0 40px rgba(196,181,253,0.9), 0 4px 20px rgba(0,0,0,0.8);
    text-transform: uppercase;
}
#ligue-splash-sub {
    font-size: clamp(0.9rem, 3vw, 1.4rem);
    font-weight: 500;
    letter-spacing: 0.3em;
    color: rgba(196,181,253,0.9);
    text-transform: uppercase;
    margin-top: 0.5rem;
    text-shadow: 0 2px 10px rgba(0,0,0,0.7);
}
@keyframes splashZoom {
    0%   { transform: scale(1.08); }
    100% { transform: scale(1); }
}
@keyframes splashTextIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes splashExit {
    0%   { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(-30px); pointer-events: none; }
}
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var splash = document.getElementById('ligue-splash');
        setTimeout(function () {
            splash.style.pointerEvents = 'none';
            setTimeout(function () {
                splash.style.display = 'none';
            }, 700);
        }, 2600);
    });
</script>

<a href="javascript:history.back()" class="header-menu">
    ← {{ __('Retour') }}
</a>

<div class="ligue-container">
    <h1 class="ligue-title">{{ __('LIGUE') }}</h1>
    <p class="ligue-subtitle">{{ __('Choisissez votre mode de compétition') }}</p>

    {{-- ── DIVISION LADDER ── --}}
    <div class="rank-ladder">
        <div class="rank-ladder-label">📊 {{ __('Système de divisions') }}</div>
        <div class="rank-ladder-track">
            <span class="rank-chip chip-bronze">🥉 {{ __('Bronze') }}</span>
            <span class="rank-arrow">→</span>
            <span class="rank-chip chip-argent">⚪ {{ __('Argent') }}</span>
            <span class="rank-arrow">→</span>
            <span class="rank-chip chip-or">🥇 {{ __('Or') }}</span>
            <span class="rank-arrow">→</span>
            <span class="rank-chip chip-platine">💠 {{ __('Platine') }}</span>
            <span class="rank-arrow">→</span>
            <span class="rank-chip chip-diamant">💎 {{ __('Diamant') }}</span>
            <span class="rank-arrow">→</span>
            <span class="rank-chip chip-legende">👑 {{ __('Légende') }}</span>
        </div>
    </div>

    <div class="ligue-modes">
        <a href="{{ route('league.individual.lobby') }}" class="ligue-mode-card">
            <div class="mode-icon">⚔️</div>
            <h2 class="mode-title">{{ __('INDIVIDUEL') }}</h2>
            <p class="mode-description">{{ __('Affrontez des adversaires en 1v1 et grimpez dans les divisions') }}</p>
            <div class="mode-badge">{{ __('Carrière Solo') }}</div>
        </a>

        <div class="team-section">
            <div class="team-section-header">
                <div class="team-section-title">
                    👥 {{ __('ÉQUIPE') }}
                </div>
                <div class="team-action-buttons">
                    <a href="{{ route('league.team.create') }}" class="btn-create-team">+ {{ __('Créer') }}</a>
                    <a href="{{ route('league.team.search') }}" class="btn-join-team">
                        {{ __('Rejoindre') }}
                        @if($pendingInvitations->count() > 0)
                            <span class="invitation-badge">{{ $pendingInvitations->count() }}</span>
                        @endif
                    </a>
                </div>
            </div>
            <p class="team-intro">
                {{ __('Choisissez l\'équipe avec laquelle vous souhaitez participer aux matchs 5v5.') }}
            </p>

            @if($userTeams->count() > 0)
                <div class="team-list">
                    @foreach($userTeams as $team)
                        @php
                            $totalEfficiency = 0;
                            $memberCount = $team->members->count();
                            foreach($team->members as $member) {
                                $stats = \App\Models\PlayerDuoStat::where('user_id', $member->id)->first();
                                if ($stats && ($stats->total_correct + $stats->total_wrong) > 0) {
                                    $totalEfficiency += ($stats->total_correct / ($stats->total_correct + $stats->total_wrong)) * 100;
                                }
                            }
                            $avgEfficiency = $memberCount > 0 ? round($totalEfficiency / $memberCount) : 0;
                            $teamLevel = ucfirst($team->division ?? 'bronze');
                        @endphp
                        <div class="team-card">
                            <div class="team-info">
                                <div class="team-emblem">{{ $team->emblem ?? '🛡️' }}</div>
                                <div class="team-details">
                                    <div class="team-name">
                                        {{ $team->name }}
                                        @if($team->captain_id === $user->id)
                                            <span class="captain-badge">{{ __('Capitaine') }}</span>
                                        @endif
                                    </div>
                                    <div class="team-meta">
                                        {{ $team->members->count() }} / 5 {{ __('joueurs') }}
                                    </div>
                                </div>
                            </div>
                            <div class="team-actions-row">
                                <span class="team-efficiency" title="{{ __('Efficacité moyenne') }}">🎯 {{ $avgEfficiency }}%</span>
                                <span class="team-level {{ strtolower($team->division ?? 'bronze') }}">{{ $teamLevel }}</span>
                                <a href="{{ route('league.team.management', ['teamId' => $team->id]) }}" class="btn-team-action btn-select">{{ __('Choisir') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <p>{{ __('Vous n\'appartenez à aucune équipe.') }}</p>
                    <p style="font-size: 0.9rem;">{{ __('Créez votre propre équipe ou rejoignez-en une existante !') }}</p>
                </div>
            @endif

            @if($pendingInvitations->count() > 0)
                <div class="pending-invitations">
                    <p style="font-weight: 600; margin-bottom: 0.5rem;">📩 {{ __('Invitations en attente') }}</p>
                    @foreach($pendingInvitations as $invitation)
                        <div class="pending-invitation-card">
                            <div class="invitation-info">
                                <span>{{ $invitation->team->emblem ?? '🛡️' }}</span>
                                <span><strong>{{ $invitation->team->name }}</strong> ({{ __('par') }} {{ $invitation->team->captain->name ?? __('Inconnu') }})</span>
                            </div>
                            <div class="invitation-actions">
                                <form action="{{ route('league.team.invitation.accept', $invitation->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-accept">{{ __('Accepter') }}</button>
                                </form>
                                <form action="{{ route('league.team.invitation.decline', $invitation->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-decline">{{ __('Refuser') }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
