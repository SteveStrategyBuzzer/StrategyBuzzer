@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\Route as R;

    $displayName  = $user ? ($user->display_name ?? $user->name ?? 'Joueur') : 'Joueur';
    $avatarUrl    = null;
    if ($user && $user->profile_settings) {
        $settings  = is_array($user->profile_settings) ? $user->profile_settings : json_decode($user->profile_settings, true);
        $avatarUrl = $settings['avatar']['url'] ?? null;
    }

    $victories      = $playerStats->victories ?? 0;
    $level          = $playerStats->level ?? $victories;
    $winRate        = $playerStats->win_rate ?? 0;
    $totalMatches   = $playerStats->total_matches ?? 0;

    $xpMilestone    = max(50, (int)(ceil(($level + 1) / 50) * 50));
    $xpPercent      = $xpMilestone > 0 ? min(100, round(($level / $xpMilestone) * 100)) : 0;

    $intelligencePieces = $user ? ($user->intelligence_pieces ?? 0) : 0;
    $competenceCoins    = $user ? ($user->competence_coins ?? 0) : 0;

    $seasonName    = $season ? $season->name : null;
    $seasonDays    = $season ? $season->daysRemaining() : 0;

    $challengeTitle    = null;
    $challengeProgress = 0;
    $challengeGoal     = 3;
    $challengeReward   = 150;
    if ($dailyChallenge) {
        $challengeTitle    = $dailyChallenge->quest->name ?? null;
        $challengeReward   = $dailyChallenge->quest->reward_coins ?? 150;
        $challengeGoal     = $dailyChallenge->quest->goal ?? 3;
        $prog              = $dailyChallenge->progress ?? [];
        if (is_array($prog)) {
            $challengeProgress = $prog['count'] ?? $prog['current'] ?? array_sum($prog) ?? 0;
        }
    }
    $challengePercent = $challengeGoal > 0 ? min(100, round(($challengeProgress / $challengeGoal) * 100)) : 0;
@endphp

<style>
:root {
    --sb-bg:        #060b20;
    --sb-sidebar:   #0b1230;
    --sb-card:      #0e1535;
    --sb-card2:     #0c1128;
    --sb-border:    rgba(100,140,255,0.12);
    --sb-gold:      #e8c43a;
    --sb-gold2:     #f5a623;
    --sb-blue:      #1a73e8;
    --sb-green:     #22c55e;
    --sb-purple:    #7c3aed;
    --sb-orange:    #f97316;
    --sb-text:      #ffffff;
    --sb-muted:     #8ba0d4;
    --sb-accent:    #3a7bd5;
    --sidebar-w:    92px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body { background: var(--sb-bg); }

.sb-layout {
    display: flex;
    min-height: 100vh;
    background: var(--sb-bg);
    color: var(--sb-text);
    font-family: 'Segoe UI', system-ui, sans-serif;
}

/* ── SIDEBAR ──────────────────────────────────── */
.sb-sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: var(--sb-sidebar);
    border-right: 1px solid var(--sb-border);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 0 12px;
    position: sticky;
    top: 0;
    flex-shrink: 0;
}

.sb-sidebar-user {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 0 8px 14px;
    border-bottom: 1px solid var(--sb-border);
    width: 100%;
}

.sb-avatar-wrap {
    position: relative;
    width: 52px;
    height: 52px;
}

.sb-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--sb-gold);
    background: #1a2448;
}

.sb-avatar-placeholder {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1a3a7a, #2d5cc8);
    border: 2px solid var(--sb-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 700;
    color: #fff;
}

.sb-level-badge {
    position: absolute;
    bottom: -4px;
    right: -4px;
    background: var(--sb-gold);
    color: #0a0f2a;
    font-size: 0.6rem;
    font-weight: 800;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid var(--sb-sidebar);
}

.sb-sidebar-name {
    font-size: 0.62rem;
    font-weight: 700;
    color: #fff;
    text-align: center;
    max-width: 76px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sb-xp-bar-wrap {
    width: 72px;
}

.sb-xp-label {
    font-size: 0.5rem;
    color: var(--sb-muted);
    text-align: center;
    margin-bottom: 3px;
}

.sb-xp-bar {
    height: 4px;
    background: #1c2a5a;
    border-radius: 4px;
    overflow: hidden;
}

.sb-xp-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--sb-accent), var(--sb-gold));
    border-radius: 4px;
    transition: width 0.6s ease;
}

/* ── SIDEBAR NAV ──────────────────────────────── */
.sb-nav {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    flex: 1;
    padding: 14px 0;
    width: 100%;
}

.sb-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    width: 76px;
    padding: 9px 4px;
    border-radius: 10px;
    text-decoration: none;
    color: var(--sb-muted);
    font-size: 0.52rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    transition: background 0.2s, color 0.2s;
    position: relative;
}

.sb-nav-item:hover {
    background: rgba(58,123,213,0.15);
    color: #fff;
}

.sb-nav-item.active {
    background: rgba(26,115,232,0.2);
    color: #fff;
}

.sb-nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    bottom: 20%;
    width: 3px;
    background: var(--sb-blue);
    border-radius: 0 3px 3px 0;
}

.sb-nav-icon {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sb-nav-item .notif-dot {
    position: absolute;
    top: 6px;
    right: 10px;
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    border: 1.5px solid var(--sb-sidebar);
}

.sb-sidebar-bottom {
    padding: 12px 8px 0;
    width: 100%;
    border-top: 1px solid var(--sb-border);
}

.sb-create-team-btn {
    display: block;
    width: 100%;
    background: var(--sb-blue);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 4px;
    font-size: 0.5rem;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.2s;
}

.sb-create-team-btn:hover { background: #1665d8; color: #fff; }

/* ── MAIN AREA ────────────────────────────────── */
.sb-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    overflow: hidden;
}

/* ── TOP BAR ──────────────────────────────────── */
.sb-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    background: var(--sb-sidebar);
    border-bottom: 1px solid var(--sb-border);
    flex-shrink: 0;
}

.sb-topbar-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
}

.sb-logo-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sb-logo-img {
    height: 36px;
    object-fit: contain;
}

.sb-logo-text {
    font-size: 1.5rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    background: linear-gradient(135deg, #fff 0%, var(--sb-gold) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-transform: uppercase;
}

.sb-logo-tagline {
    font-size: 0.6rem;
    color: var(--sb-gold);
    letter-spacing: 0.15em;
    text-transform: uppercase;
    margin-top: 1px;
}

.sb-topbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sb-coin-display {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--sb-border);
    border-radius: 20px;
    padding: 5px 10px;
}

.sb-coin-icon {
    width: 20px;
    height: 20px;
    object-fit: contain;
}

.sb-coin-value {
    font-size: 0.8rem;
    font-weight: 700;
    color: #fff;
}

.sb-coin-add {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--sb-green);
    border: none;
    color: #fff;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-weight: 700;
    transition: background 0.2s;
    text-decoration: none;
}

.sb-coin-add:hover { background: #16a34a; color: #fff; }

.sb-topbar-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--sb-border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    text-decoration: none;
    color: var(--sb-muted);
    transition: background 0.2s, color 0.2s;
}

.sb-topbar-icon:hover { background: rgba(255,255,255,0.12); color: #fff; }

/* ── CONTENT AREA ─────────────────────────────── */
.sb-content {
    flex: 1;
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow-y: auto;
}

/* ── ROW 1 : SEASON + DAILY ───────────────────── */
.sb-row1 {
    display: grid;
    grid-template-columns: 1fr 1.8fr;
    gap: 12px;
}

.sb-season-card {
    background: linear-gradient(135deg, #0e1a3a 0%, #162040 100%);
    border: 1px solid rgba(232,196,58,0.25);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.sb-season-icon {
    font-size: 2.2rem;
    flex-shrink: 0;
}

.sb-season-info {}

.sb-season-label {
    font-size: 0.6rem;
    color: var(--sb-gold);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 700;
    margin-bottom: 4px;
}

.sb-season-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: #fff;
    text-transform: uppercase;
}

.sb-season-end {
    font-size: 0.7rem;
    color: var(--sb-muted);
    margin-top: 3px;
}

.sb-daily-card {
    background: linear-gradient(135deg, #130d2e 0%, #1e1040 100%);
    border: 1px solid rgba(124,58,237,0.3);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.sb-daily-chest {
    font-size: 2.8rem;
    flex-shrink: 0;
}

.sb-daily-info {
    flex: 1;
    min-width: 0;
}

.sb-daily-label {
    font-size: 0.6rem;
    color: #a78bfa;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 700;
    margin-bottom: 3px;
}

.sb-daily-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
    line-height: 1.3;
}

.sb-daily-progress-bar {
    height: 6px;
    background: rgba(255,255,255,0.1);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 4px;
}

.sb-daily-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--sb-purple), #a855f7);
    border-radius: 6px;
}

.sb-daily-progress-text {
    font-size: 0.65rem;
    color: var(--sb-muted);
}

.sb-daily-reward {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
}

.sb-daily-reward-icon {
    font-size: 1.4rem;
}

.sb-daily-reward-value {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--sb-gold);
}

/* ── ROW 2 : GAME MODES ───────────────────────── */
.sb-row2 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.sb-mode-card {
    background: var(--sb-card);
    border: 1px solid var(--sb-border);
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
    min-height: 0;
}

.sb-mode-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}

.sb-mode-card.locked {
    opacity: 0.5;
    pointer-events: none;
}

.sb-mode-img-wrap {
    position: relative;
    height: 130px;
    overflow: hidden;
    background: #0a1228;
}

.sb-mode-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
}

.sb-mode-img-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
}

.sb-mode-img-wrap::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 50%;
    background: linear-gradient(to bottom, transparent, var(--sb-card));
}

.sb-mode-title-overlay {
    position: absolute;
    bottom: 6px;
    left: 0;
    right: 0;
    text-align: center;
    z-index: 2;
    font-size: 1.2rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-shadow: 0 2px 8px rgba(0,0,0,0.8);
    text-transform: uppercase;
}

.sb-mode-body {
    padding: 10px 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

.sb-mode-desc {
    font-size: 0.7rem;
    color: var(--sb-muted);
    line-height: 1.4;
    text-align: center;
    flex: 1;
}

.sb-mode-btn {
    display: block;
    width: 100%;
    padding: 9px;
    border-radius: 8px;
    border: none;
    font-size: 0.8rem;
    font-weight: 800;
    text-align: center;
    cursor: pointer;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    text-decoration: none;
    transition: filter 0.2s, transform 0.15s;
}

.sb-mode-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }
.sb-mode-btn:active { transform: translateY(0); }

.btn-blue   { background: #1a73e8; color: #fff; }
.btn-green  { background: #22c55e; color: #fff; }
.btn-purple { background: #7c3aed; color: #fff; }
.btn-orange { background: #f97316; color: #fff; }

.sb-lock-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(255,255,255,0.08);
    border-radius: 6px;
    padding: 5px 8px;
    font-size: 0.65rem;
    color: var(--sb-muted);
    font-weight: 600;
    text-align: center;
    justify-content: center;
}

/* ── ROW 3 : INFO PANELS ──────────────────────── */
.sb-row3 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.sb-panel {
    background: var(--sb-card2);
    border: 1px solid var(--sb-border);
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sb-panel-title {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--sb-muted);
    padding-bottom: 8px;
    border-bottom: 1px solid var(--sb-border);
}

.sb-activity-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.7rem;
    color: #cbd5f5;
}

.sb-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dot-green  { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
.dot-blue   { background: #3b82f6; box-shadow: 0 0 6px #3b82f6; }
.dot-purple { background: #a855f7; box-shadow: 0 0 6px #a855f7; }
.dot-orange { background: #f97316; box-shadow: 0 0 6px #f97316; }

.sb-rank-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.72rem;
}

.sb-rank-num {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 800;
    flex-shrink: 0;
}

.rank-1 { background: #f5a623; color: #0a0f2a; }
.rank-2 { background: #9ca3af; color: #0a0f2a; }
.rank-3 { background: #cd7f32; color: #0a0f2a; }

.sb-rank-name {
    flex: 1;
    color: #fff;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sb-rank-score {
    color: var(--sb-gold);
    font-weight: 700;
    font-size: 0.68rem;
}

.sb-panel-btn {
    display: block;
    width: 100%;
    padding: 7px;
    border: 1px solid var(--sb-accent);
    border-radius: 7px;
    background: transparent;
    color: var(--sb-accent);
    font-size: 0.6rem;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    margin-top: auto;
}

.sb-panel-btn:hover { background: var(--sb-accent); color: #fff; }

.sb-stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sb-stat-icon { font-size: 1.1rem; flex-shrink: 0; }

.sb-stat-info { flex: 1; }

.sb-stat-label {
    font-size: 0.58rem;
    color: var(--sb-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.sb-stat-value {
    font-size: 0.85rem;
    font-weight: 700;
    color: #fff;
}

.sb-eco-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
}

.sb-eco-coin {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--sb-gold2), var(--sb-gold));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 1rem;
    color: #0a0f2a;
    border: 2px solid rgba(255,255,255,0.2);
    box-shadow: 0 4px 12px rgba(245,166,35,0.3);
}

.sb-eco-links {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sb-eco-link {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.68rem;
    color: #cbd5f5;
    text-decoration: none;
    padding: 3px 0;
    transition: color 0.2s;
}

.sb-eco-link:hover { color: #fff; }

.sb-eco-link-icon { font-size: 0.85rem; }

/* ── BOTTOM BAR ───────────────────────────────── */
.sb-bottombar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 18px;
    background: var(--sb-sidebar);
    border-top: 1px solid var(--sb-border);
    flex-shrink: 0;
    gap: 12px;
}

.sb-bottom-create {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--sb-blue);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
    white-space: nowrap;
}

.sb-bottom-create:hover { background: #1665d8; color: #fff; }

.sb-bottom-ads {
    flex: 1;
    max-width: 460px;
    height: 36px;
    background: rgba(255,255,255,0.04);
    border: 1px dashed rgba(255,255,255,0.1);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    color: rgba(255,255,255,0.2);
    letter-spacing: 0.1em;
}

.sb-social-links {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sb-social-icon {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    text-decoration: none;
    transition: transform 0.2s, opacity 0.2s;
    opacity: 0.7;
}

.sb-social-icon:hover { transform: scale(1.15); opacity: 1; }
.sb-social-discord  { background: #5865f2; color: #fff; }
.sb-social-youtube  { background: #ff0000; color: #fff; }
.sb-social-facebook { background: #1877f2; color: #fff; }
.sb-social-insta    { background: linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); color:#fff; }
.sb-social-twitter  { background: #1da1f2; color: #fff; }

/* ── NOTIFICATION BADGE ───────────────────────── */
.sb-notif-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    background: #ef4444;
    color: #fff;
    font-size: 0.5rem;
    font-weight: 800;
    min-width: 14px;
    height: 14px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    border: 1.5px solid var(--sb-sidebar);
}

/* ── MOBILE BOTTOM NAV ────────────────────────── */
.sb-mobile-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: #0b1230;
    border-top: 1px solid rgba(100,140,255,0.18);
    height: 60px;
    align-items: stretch;
    justify-content: space-around;
    padding: 0;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    box-shadow: 0 -4px 20px rgba(0,0,0,0.5);
}

.sb-mn-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    flex: 1;
    padding: 6px 2px;
    text-decoration: none;
    color: #6b84c4;
    font-size: 0.48rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    position: relative;
    transition: color 0.2s, background 0.2s;
    border-top: 2px solid transparent;
}

.sb-mn-item:hover { color: #fff; background: rgba(255,255,255,0.04); }

.sb-mn-item.active {
    color: #fff;
    border-top-color: #1a73e8;
    background: rgba(26,115,232,0.1);
}

.sb-mn-item svg { width: 20px; height: 20px; flex-shrink: 0; }

.sb-mn-notif {
    position: absolute;
    top: 6px;
    right: calc(50% - 16px);
    width: 7px;
    height: 7px;
    background: #ef4444;
    border-radius: 50%;
    border: 1.5px solid #0b1230;
}

/* ── RESPONSIVE ───────────────────────────────── */

/* Large tablet: 2-column grids */
@media (max-width: 1200px) {
    .sb-row2, .sb-row3 { grid-template-columns: repeat(2, 1fr); }
}

/* Tablet: icons-only sidebar */
@media (max-width: 1023px) {
    :root { --sidebar-w: 64px; }
    .sb-sidebar-name,
    .sb-xp-bar-wrap { display: none; }
    .sb-nav-item span { display: none; }
    .sb-nav-item { width: 46px; padding: 9px 6px; }
    .sb-create-team-btn { font-size: 0; line-height: 1.2; padding: 8px 4px; border-radius: 6px; }
    .sb-create-team-btn::before { content: '+'; font-size: 1.1rem; font-weight: 900; }
    .sb-row1 { grid-template-columns: 1fr; }
    .sb-logo-text { font-size: 1.2rem; }
    .sb-logo-tagline { display: none; }
}

/* Small tablet */
@media (max-width: 860px) {
    .sb-row3 { grid-template-columns: repeat(2, 1fr); }
    .sb-content { padding: 12px 14px; }
}

/* Mobile: hide sidebar, show bottom nav */
@media (max-width: 639px) {
    /* Layout switches to vertical, sidebar hidden */
    .sb-sidebar { display: none; }
    .sb-layout { flex-direction: column; }
    .sb-mobile-nav { display: flex; }

    /* Content scrolls, bottom padding to clear fixed nav + safe-area */
    .sb-content {
        padding: 10px 10px calc(64px + env(safe-area-inset-bottom, 0px));
        overflow-y: auto;
    }

    /* Compact topbar */
    .sb-topbar { padding: 8px 12px; gap: 0; }
    .sb-topbar-spacer { display: none; }
    .sb-topbar-logo { align-items: center; }
    .sb-logo-tagline { display: none; }
    .sb-logo-text { font-size: 1rem; letter-spacing: 0.05em; }
    .sb-logo-title span:first-child,
    .sb-logo-title span:last-child { font-size: 1rem; }
    .sb-topbar-right { gap: 6px; }
    .sb-topbar-icon { width: 30px; height: 30px; border-radius: 6px; }
    .sb-coin-display { padding: 4px 8px; gap: 4px; }
    .sb-coin-value { font-size: 0.72rem; }
    .sb-coin-icon { width: 16px; height: 16px; }
    .sb-coin-add { width: 24px; height: 24px; font-size: 0.9rem; }

    /* Bottom bar hidden on mobile (replaced by bottom nav) */
    .sb-bottombar { display: none; }

    /* Rows */
    .sb-row1 { grid-template-columns: 1fr; gap: 8px; }
    .sb-row2 { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .sb-row3 { grid-template-columns: 1fr; gap: 8px; }

    /* Smaller game card images */
    .sb-mode-img-wrap { height: 100px; }
    .sb-mode-body { padding: 8px 10px 10px; gap: 6px; }
    .sb-mode-btn { padding: 8px; font-size: 0.72rem; }

    /* Compact panels */
    .sb-panel { padding: 12px; gap: 8px; }
}

/* Very small phones (≤ 400px) */
@media (max-width: 400px) {
    .sb-logo-text { font-size: 0.85rem; }
    .sb-topbar-icon { display: none; }
    .sb-coin-add { display: none; }
    .sb-row2 { grid-template-columns: 1fr; }
    .sb-mode-img-wrap { height: 120px; }
    .sb-season-card,
    .sb-daily-card { flex-direction: column; align-items: flex-start; gap: 8px; }
    .sb-daily-reward { flex-direction: row; align-self: flex-end; gap: 4px; }
}
</style>

<div class="sb-layout">

    {{-- ══════════ SIDEBAR ══════════ --}}
    <aside class="sb-sidebar">

        {{-- User block --}}
        <div class="sb-sidebar-user">
            <div class="sb-avatar-wrap">
                @if($avatarUrl)
                    <img class="sb-avatar" src="{{ $avatarUrl }}" alt="{{ $displayName }}">
                @else
                    <div class="sb-avatar-placeholder">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</div>
                @endif
                <div class="sb-level-badge">{{ min($level, 99) }}</div>
            </div>
            <div class="sb-sidebar-name">{{ $displayName }}</div>
            <div class="sb-xp-bar-wrap">
                <div class="sb-xp-label">{{ number_format($victories) }} / {{ number_format(max(50, (int)(ceil(($level + 1) / 50) * 50))) }}</div>
                <div class="sb-xp-bar">
                    <div class="sb-xp-fill" style="width:{{ $xpPercent }}%"></div>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="sb-nav">
            <a href="{{ route('menu') }}" class="sb-nav-item active">
                <div class="sb-nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <span>{{ __('Accueil') }}</span>
            </a>

            <a href="{{ R::has('leaderboard') ? route('leaderboard') : url('/classements') }}" class="sb-nav-item">
                <div class="sb-nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 000 5H6"/><path d="M18 9h1.5a2.5 2.5 0 010 5H18"/><path d="M8 9h8"/><path d="M8 15h8"/><path d="M8 3v6"/><path d="M16 3v6"/><rect x="8" y="3" width="8" height="18" rx="2"/></svg>
                </div>
                <span>{{ __('Classements') }}</span>
            </a>

            <a href="{{ R::has('quests.index') ? route('quests.index') : url('/quests') }}" class="sb-nav-item" style="position:relative">
                @if($questsNotifications > 0 || $dailyQuestsNotifications > 0)
                    <div class="notif-dot" id="sidebar-quest-dot"></div>
                @endif
                <div class="sb-nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <span>{{ __('Quêtes') }}</span>
            </a>

            <a href="{{ R::has('boutique') ? route('boutique') : url('/boutique') }}" class="sb-nav-item">
                <div class="sb-nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61l1.6-8.39H6"/></svg>
                </div>
                <span>{{ __('Boutique') }}</span>
            </a>

            <a href="{{ R::has('avatar') ? route('avatar') : url('/avatar') }}" class="sb-nav-item">
                <div class="sb-nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <span>{{ __('Collection') }}</span>
            </a>

            <a href="{{ R::has('ligue') ? route('ligue') : url('/ligue') }}" class="sb-nav-item" style="position:relative">
                @if($ligueNotifications > 0)
                    <div class="notif-dot" id="sidebar-ligue-dot"></div>
                @endif
                <div class="sb-nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <span>{{ __('Ligues') }}</span>
            </a>
        </nav>

        <div class="sb-sidebar-bottom">
            <a href="{{ R::has('ligue') ? route('ligue') : url('/ligue') }}" class="sb-create-team-btn">
                <span>{{ __('Créer équipe') }}</span>
            </a>
        </div>

    </aside>

    {{-- ══════════ MAIN ══════════ --}}
    <div class="sb-main">

        {{-- ── TOP BAR ── --}}
        <header class="sb-topbar">
            <div class="sb-topbar-spacer" style="width:200px"></div>

            <div class="sb-topbar-logo">
                <div class="sb-logo-title">
                    <span style="font-size:1.6rem">⚡</span>
                    <span class="sb-logo-text">Strategy Buzzer</span>
                    <span style="font-size:1.6rem">⚡</span>
                </div>
                <div class="sb-logo-tagline">{{ __('La connaissance est votre meilleure stratégie') }}</div>
            </div>

            <div class="sb-topbar-right">
                <div class="sb-coin-display">
                    <img class="sb-coin-icon" src="{{ asset('images/coin-intelligence.png') }}" alt="Intelligence" onerror="this.style.display='none'" style="object-fit:cover;">
                    <span class="sb-coin-value" id="topbar-intel">{{ number_format($intelligencePieces) }}</span>
                </div>
                <div class="sb-coin-display">
                    <img class="sb-coin-icon" src="{{ asset('images/skill_coin.png') }}" alt="Compétence" onerror="this.replaceWith('⭐')">
                    <span class="sb-coin-value" id="topbar-comp">{{ number_format($competenceCoins) }}</span>
                </div>
                <a href="{{ R::has('boutique') ? route('boutique') : url('/boutique') }}" class="sb-coin-add" title="{{ __('Obtenir des pièces') }}">+</a>

                <a href="{{ url('/messages') }}" class="sb-topbar-icon" title="{{ __('Messages') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </a>
                <a href="{{ R::has('profile') ? route('profile') : url('/profile') }}" class="sb-topbar-icon" title="{{ __('Profil') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
                <a href="{{ R::has('profile') ? route('profile') : url('/profile') }}" class="sb-topbar-icon" title="{{ __('Paramètres') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                </a>
            </div>
        </header>

        {{-- ── CONTENT ── --}}
        <div class="sb-content">

            {{-- ROW 1 : Season + Daily Challenge --}}
            <div class="sb-row1">

                {{-- Season --}}
                <div class="sb-season-card">
                    <div class="sb-season-icon">🏆</div>
                    <div class="sb-season-info">
                        <div class="sb-season-label">{{ __('Saison actuelle') }}</div>
                        <div class="sb-season-name">{{ $seasonName ?? __('Hors saison') }}</div>
                        @if($season && $seasonDays > 0)
                            <div class="sb-season-end">{{ __('Fin dans') }} {{ $seasonDays }}{{ __('j') }}</div>
                        @elseif(!$season)
                            <div class="sb-season-end">{{ __('Aucune saison active') }}</div>
                        @endif
                    </div>
                </div>

                {{-- Daily Challenge --}}
                <div class="sb-daily-card">
                    <div class="sb-daily-chest">🎁</div>
                    <div class="sb-daily-info">
                        <div class="sb-daily-label">{{ __('Défi quotidien') }}</div>
                        @if($dailyChallenge)
                            <div class="sb-daily-title">{{ $challengeTitle ?? __('Défi du jour') }}</div>
                            <div class="sb-daily-progress-bar">
                                <div class="sb-daily-fill" style="width:{{ $challengePercent }}%"></div>
                            </div>
                            <div class="sb-daily-progress-text">{{ $challengeProgress }}/{{ $challengeGoal }}</div>
                        @else
                            <div class="sb-daily-title">{{ __('Connectez-vous pour voir votre défi') }}</div>
                            <div class="sb-daily-progress-bar">
                                <div class="sb-daily-fill" style="width:0%"></div>
                            </div>
                            <div class="sb-daily-progress-text">0/3</div>
                        @endif
                    </div>
                    <div class="sb-daily-reward">
                        <div class="sb-daily-reward-icon">⭐</div>
                        <div class="sb-daily-reward-value">{{ $challengeReward }}</div>
                    </div>
                </div>

            </div>

            {{-- ROW 2 : Game Modes --}}
            <div class="sb-row2">

                {{-- SOLO --}}
                <div class="sb-mode-card {{ $soloUnlocked ? '' : 'locked' }}">
                    <div class="sb-mode-img-wrap">
                        <img src="{{ asset('images/solo_boss100.png') }}" alt="Solo"
                             onerror="this.parentElement.innerHTML='<div class=\'sb-mode-img-placeholder\'>🤖</div>'"
                             style="object-fit:cover; object-position:center 55%;">
                        <div class="sb-mode-title-overlay" style="color:#60a5fa">SOLO</div>
                    </div>
                    <div class="sb-mode-body">
                        <div class="sb-mode-desc">{{ __('Faites votre ascension avec vos connaissances') }}</div>
                        @if($soloUnlocked)
                            <a href="{{ R::has('solo.index') ? route('solo.index') : url('/solo') }}" class="sb-mode-btn btn-blue">{{ __('Jouer') }}</a>
                        @else
                            <div class="sb-lock-badge">🔒 {{ __('Complétez votre profil') }}</div>
                        @endif
                    </div>
                </div>

                {{-- DUO --}}
                <div class="sb-mode-card" style="position:relative">
                    @if($duoNotifications > 0)
                        <div class="sb-notif-badge" id="duo-badge">{{ $duoNotifications }}</div>
                    @endif
                    <div class="sb-mode-img-wrap">
                        <img src="{{ asset('images/duo_splash_landscape.png') }}" alt="Duo"
                             onerror="this.parentElement.innerHTML='<div class=\'sb-mode-img-placeholder\'>👥</div>'">
                        <div class="sb-mode-title-overlay" style="color:#86efac">DUO</div>
                    </div>
                    <div class="sb-mode-body">
                        <div class="sb-mode-desc">{{ __('Affrontez d\'autres joueurs en temps réel') }}</div>
                        <a href="{{ route('duo.splash') }}" class="sb-mode-btn btn-green">{{ __('Jouer') }}</a>
                    </div>
                </div>

                {{-- LIGUE --}}
                <div class="sb-mode-card {{ $ligueUnlocked ? '' : '' }}" style="position:relative">
                    @if($ligueNotifications > 0)
                        <div class="sb-notif-badge" id="ligue-badge">{{ $ligueNotifications }}</div>
                    @endif
                    <div class="sb-mode-img-wrap">
                        <img src="{{ asset('images/ligue_hero.png') }}" alt="Ligue"
                             onerror="this.parentElement.innerHTML='<div class=\'sb-mode-img-placeholder\'>🛡️</div>'"
                             style="object-fit:cover; object-position:center 12%;">
                        <div class="sb-mode-title-overlay" style="color:#c4b5fd">LIGUE</div>
                    </div>
                    <div class="sb-mode-body">
                        <div class="sb-mode-desc">{{ __('Gravissez les ligues en Solo ou en Équipe et devenez «légende»') }}</div>
                        @if($ligueUnlocked)
                            <a href="{{ R::has('ligue') ? route('ligue') : url('/ligue') }}" class="sb-mode-btn btn-purple">{{ __('Entrer') }}</a>
                        @else
                            <div style="font-size:0.6rem; color:var(--sb-muted); text-align:center">{{ __('25 matchs Duo requis') }} ({{ $duoMatches }}/25)</div>
                        @endif
                    </div>
                </div>

                {{-- MAÎTRE DU JEU --}}
                <div class="sb-mode-card">
                    <div class="sb-mode-img-wrap">
                        <img src="{{ asset('images/master-home-landscape.png') }}" alt="Maître du Jeu"
                             onerror="this.parentElement.innerHTML='<div class=\'sb-mode-img-placeholder\'>👑</div>'">
                        <div class="sb-mode-title-overlay" style="color:#fdba74; font-size:0.95rem">{{ __('Maître du jeu') }}</div>
                    </div>
                    <div class="sb-mode-body">
                        <div class="sb-mode-desc">{{ __('Jusqu\'à 40 joueurs par Quiz!') }}</div>
                        @if($masterPurchased && $profileComplete)
                            <a href="{{ url('/master') }}" class="sb-mode-btn btn-orange">{{ __('Jouer') }}</a>
                        @else
                            <a href="{{ route('boutique') }}?tab=master" class="sb-mode-btn btn-orange">{{ __('Découvrir') }}</a>
                        @endif
                    </div>
                </div>

            </div>

            {{-- ROW 3 : Info Panels --}}
            <div class="sb-row3">

                {{-- Activité en direct --}}
                <div class="sb-panel">
                    <div class="sb-panel-title">{{ __('Activité en direct') }}</div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-green"></div>
                        <span id="live-players">—</span> {{ __('joueurs en ligne') }}
                    </div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-blue"></div>
                        <span>{{ number_format($liveActivity['games_today']) }}</span> {{ __('parties aujourd\'hui') }}
                    </div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-purple"></div>
                        <span>{{ $liveActivity['active_leagues'] }}</span> {{ __('ligues actives') }}
                    </div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-orange"></div>
                        <span id="live-questions">—</span> {{ __('questions disponibles') }}
                    </div>
                </div>

                {{-- Classement mondial --}}
                <div class="sb-panel">
                    <div class="sb-panel-title">{{ __('Classement mondial') }}</div>
                    @foreach($topPlayers as $i => $p)
                        @php $rank = $i + 1; @endphp
                        <div class="sb-rank-item">
                            <div class="sb-rank-num rank-{{ $rank }}">{{ $rank }}</div>
                            <div class="sb-rank-name">{{ $p->user->display_name ?? $p->user->name ?? __('Joueur') }}</div>
                            <div class="sb-rank-score">{{ number_format($p->victories) }}</div>
                        </div>
                    @endforeach
                    @for($i = $topPlayers->count(); $i < 3; $i++)
                        @php $rank = $i + 1; @endphp
                        <div class="sb-rank-item">
                            <div class="sb-rank-num rank-{{ $rank }}">{{ $rank }}</div>
                            <div class="sb-rank-name" style="color:var(--sb-muted)">—</div>
                            <div class="sb-rank-score">0</div>
                        </div>
                    @endfor
                    <a href="{{ R::has('duo.rankings') ? route('duo.rankings') : url('/classements') }}" class="sb-panel-btn">{{ __('Voir le classement') }}</a>
                </div>

                {{-- Vos stats --}}
                <div class="sb-panel">
                    <div class="sb-panel-title">{{ __('Vos stats') }}</div>
                    <div class="sb-stat-item">
                        <div class="sb-stat-icon">🏆</div>
                        <div class="sb-stat-info">
                            <div class="sb-stat-label">{{ __('Victoires') }}</div>
                            <div class="sb-stat-value">{{ number_format($victories) }}</div>
                        </div>
                    </div>
                    <div class="sb-stat-item">
                        <div class="sb-stat-icon">🎯</div>
                        <div class="sb-stat-info">
                            <div class="sb-stat-label">{{ __('Taux de réussite') }}</div>
                            <div class="sb-stat-value">{{ number_format($winRate, 0) }}%</div>
                        </div>
                    </div>
                    <div class="sb-stat-item">
                        <div class="sb-stat-icon">🛡️</div>
                        <div class="sb-stat-info">
                            <div class="sb-stat-label">{{ __('Meilleure ligue') }}</div>
                            <div class="sb-stat-value">{{ $totalMatches > 0 ? __('Maître I') : '—' }}</div>
                        </div>
                    </div>
                    <a href="{{ R::has('profile') ? route('profile') : url('/profile') }}" class="sb-panel-btn">{{ __('Voir le profil') }}</a>
                </div>

                {{-- Écosystème --}}
                <div class="sb-panel">
                    <div class="sb-panel-title">{{ __('Écosystème') }}</div>
                    <div class="sb-eco-logo">
                        <div class="sb-eco-coin">SB</div>
                    </div>
                    <div class="sb-eco-links">
                        <a href="{{ R::has('avatar') ? route('avatar') : url('/avatar') }}" class="sb-eco-link">
                            <span class="sb-eco-link-icon">🎭</span> {{ __('Avatars & personnalisation') }}
                        </a>
                        <a href="{{ R::has('boutique') ? route('boutique') : url('/boutique') }}" class="sb-eco-link">
                            <span class="sb-eco-link-icon">🛒</span> {{ __('Boutique & items') }}
                        </a>
                        <a href="{{ R::has('quests.index') ? route('quests.index') : url('/quests') }}" class="sb-eco-link">
                            <span class="sb-eco-link-icon">🎯</span> {{ __('Quêtes & récompenses') }}
                        </a>
                        <a href="{{ R::has('quetes-quotidiennes') ? route('quetes-quotidiennes') : url('/quetes-quotidiennes') }}" class="sb-eco-link">
                            <span class="sb-eco-link-icon">⭐</span> {{ __('Saisons & récompenses') }}
                        </a>
                        <a href="{{ url('/master') }}" class="sb-eco-link">
                            <span class="sb-eco-link-icon">🏆</span> {{ __('Tournois & événements') }}
                        </a>
                    </div>
                </div>

            </div>

        </div>{{-- /sb-content --}}

        {{-- ── BOTTOM BAR ── --}}
        <footer class="sb-bottombar">
            <a href="{{ R::has('ligue') ? route('ligue') : url('/ligue') }}" class="sb-bottom-create">
                + {{ __('Créer une équipe') }}
            </a>

            <div class="sb-bottom-ads">{{ __('Pubs') }}</div>

            <div class="sb-social-links">
                <a href="#" class="sb-social-icon sb-social-discord" title="Discord">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.045.03.056a19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03z"/></svg>
                </a>
                <a href="#" class="sb-social-icon sb-social-youtube" title="YouTube">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>
                </a>
                <a href="#" class="sb-social-icon sb-social-facebook" title="Facebook">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="#" class="sb-social-icon sb-social-insta" title="Instagram">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                <a href="#" class="sb-social-icon sb-social-twitter" title="Twitter / X">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                </a>
            </div>
        </footer>

    </div>{{-- /sb-main --}}

    {{-- ══════════ MOBILE BOTTOM NAV (visible < 640px) ══════════ --}}
    <nav class="sb-mobile-nav">

        <a href="{{ route('menu') }}" class="sb-mn-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>{{ __('Accueil') }}</span>
        </a>

        <a href="{{ route('duo.splash') }}" class="sb-mn-item" style="position:relative">
            @if($duoNotifications > 0)
                <div class="sb-mn-notif" id="mn-duo-dot"></div>
            @endif
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            <span>{{ __('Duo') }}</span>
        </a>

        <a href="{{ R::has('ligue') ? route('ligue') : url('/ligue') }}" class="sb-mn-item" style="position:relative">
            @if($ligueNotifications > 0)
                <div class="sb-mn-notif" id="mn-ligue-dot"></div>
            @endif
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>{{ __('Ligue') }}</span>
        </a>

        <a href="{{ R::has('quests.index') ? route('quests.index') : url('/quests') }}" class="sb-mn-item" style="position:relative">
            @if($questsNotifications > 0 || $dailyQuestsNotifications > 0)
                <div class="sb-mn-notif" id="mn-quest-dot"></div>
            @endif
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>{{ __('Quêtes') }}</span>
        </a>

        <a href="{{ R::has('boutique') ? route('boutique') : url('/boutique') }}" class="sb-mn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61l1.6-8.39H6"/></svg>
            <span>{{ __('Boutique') }}</span>
        </a>

        <a href="{{ R::has('profile') ? route('profile') : url('/profile') }}" class="sb-mn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>{{ __('Profil') }}</span>
        </a>

    </nav>

</div>{{-- /sb-layout --}}

<script>
(function() {
    const POLL_INTERVAL = 10000;

    function updateBadge(id, count) {
        const el = document.getElementById(id);
        if (!el) return;
        if (count > 0) { el.textContent = count; el.style.display = ''; }
        else { el.style.display = 'none'; }
    }

    async function pollNotifications() {
        try {
            const r = await fetch('/api/notifications', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            updateBadge('duo-badge', d.duo || 0);
            updateBadge('ligue-badge', d.ligue || 0);
        } catch (e) {}
    }

    pollNotifications();
    setInterval(pollNotifications, POLL_INTERVAL);

    (function tryStartMusic() {
        if (window.startAmbientMusicSession) { window.startAmbientMusicSession(); }
        else { setTimeout(tryStartMusic, 100); }
    })();
})();
</script>

@endsection
