@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\Route as R;

    // ── Identity ──
    $displayName  = $user ? ($user->display_name ?? $user->name ?? __('Joueur')) : __('Joueur');
    $avatarUrl    = null;
    if ($user && $user->profile_settings) {
        $settings  = is_array($user->profile_settings) ? $user->profile_settings : json_decode($user->profile_settings, true);
        $avatarUrl = $settings['avatar']['url'] ?? null;
    }

    $victories    = $playerStats->victories ?? 0;
    $level        = $playerStats->level ?? $victories;
    $winRate      = $playerStats->win_rate ?? 0;
    $xpMilestone  = max(50, (int)(ceil(($level + 1) / 50) * 50));
    $xpPercent    = $xpMilestone > 0 ? min(100, round(($level / $xpMilestone) * 100)) : 0;

    $intelligencePieces = $user ? ($user->intelligence_pieces ?? 0) : 0;
    $competenceCoins    = $user ? ($user->competence_coins ?? 0) : 0;

    // ── Season ──
    $seasonName = $season ? $season->name : null;
    $seasonDays = $season ? $season->daysRemaining() : 0;

    // ── League division / position / points ──
    $divNames = [
        'bronze'  => __('Bronze'),
        'argent'  => __('Argent'),
        'or'      => __('Or'),
        'platine' => __('Platine'),
        'diamant' => __('Diamant'),
        'legende' => __('Légende'),
    ];
    $divEmojis = [
        'bronze'  => '🥉',
        'argent'  => '⚪',
        'or'      => '🥇',
        'platine' => '💠',
        'diamant' => '💎',
        'legende' => '👑',
    ];
    $divKey       = $leagueDivision->division ?? null;
    $divisionName = $divKey ? ($divNames[$divKey] ?? ucfirst($divKey)) : __('Non classé');
    $divisionIcon = $divKey ? ($divEmojis[$divKey] ?? '🛡️') : '🛡️';

    // ── Daily challenge ──
    $challengeTitle    = null;
    $challengeProgress = 0;
    $challengeGoal     = 3;
    $challengeReward   = 150;
    if ($dailyChallenge) {
        $challengeTitle  = $dailyChallenge->quest->name ?? null;
        $challengeReward = $dailyChallenge->quest->reward_coins ?? 150;
        $challengeGoal   = $dailyChallenge->quest->goal ?? 3;
        $prog            = $dailyChallenge->progress ?? [];
        if (is_array($prog)) {
            $challengeProgress = $prog['count'] ?? $prog['current'] ?? array_sum($prog) ?? 0;
        }
    }
    $challengePercent = $challengeGoal > 0 ? min(100, round(($challengeProgress / $challengeGoal) * 100)) : 0;

    // ── Season standing (progression / rewards) ──
    $siWon       = $seasonInfo['matches_won'] ?? 0;
    $siThreshold = $seasonInfo['wins_threshold'] ?? 10;
    $siPercent   = $seasonInfo['progress_percent'] ?? 0;
    $siEligible  = $seasonInfo['eligible'] ?? false;
    $siPrizes    = $seasonInfo['prizes'] ?? [];

    // ── Live activity ──
    $liveMatches   = $liveActivity['matches_in_progress'] ?? null;
    $liveTeams     = $liveActivity['active_teams'] ?? null;
    $livePlayers   = $liveActivity['online_players'] ?? null;
    $liveTournois  = $liveActivity['tournaments_today'] ?? null;

    $invitationCount = $pendingInvitations->count();
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

.sb-avatar-wrap { position: relative; width: 52px; height: 52px; }

.sb-avatar {
    width: 52px; height: 52px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--sb-gold);
    background: #1a2448;
}

.sb-avatar-placeholder {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1a3a7a, #2d5cc8);
    border: 2px solid var(--sb-gold);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 700; color: #fff;
}

.sb-level-badge {
    position: absolute; bottom: -4px; right: -4px;
    background: var(--sb-gold); color: #0a0f2a;
    font-size: 0.6rem; font-weight: 800;
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    border: 1.5px solid var(--sb-sidebar);
}

.sb-sidebar-name {
    font-size: 0.62rem; font-weight: 700; color: #fff;
    text-align: center; max-width: 76px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.sb-xp-bar-wrap { width: 72px; }
.sb-xp-label { font-size: 0.5rem; color: var(--sb-muted); text-align: center; margin-bottom: 3px; }
.sb-xp-bar { height: 4px; background: #1c2a5a; border-radius: 4px; overflow: hidden; }
.sb-xp-fill { height: 100%; background: linear-gradient(90deg, var(--sb-accent), var(--sb-gold)); border-radius: 4px; transition: width 0.6s ease; }

/* ── SIDEBAR NAV ──────────────────────────────── */
.sb-nav { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; padding: 14px 0; width: 100%; }

.sb-nav-item {
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    width: 76px; padding: 9px 4px; border-radius: 10px;
    text-decoration: none; color: var(--sb-muted);
    font-size: 0.52rem; font-weight: 600; letter-spacing: 0.03em; text-transform: uppercase;
    transition: background 0.2s, color 0.2s; position: relative;
}
.sb-nav-item:hover { background: rgba(58,123,213,0.15); color: #fff; }
.sb-nav-item.active { background: rgba(26,115,232,0.2); color: #fff; }
.sb-nav-item.active::before {
    content: ''; position: absolute; left: 0; top: 20%; bottom: 20%;
    width: 3px; background: var(--sb-blue); border-radius: 0 3px 3px 0;
}
.sb-nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.sb-nav-item .notif-dot {
    position: absolute; top: 6px; right: 10px;
    width: 8px; height: 8px; background: #ef4444; border-radius: 50%;
    border: 1.5px solid var(--sb-sidebar);
}

.sb-sidebar-bottom { padding: 12px 8px 0; width: 100%; border-top: 1px solid var(--sb-border); }
.sb-create-team-btn {
    display: block; width: 100%; background: var(--sb-blue); color: #fff;
    border: none; border-radius: 8px; padding: 8px 4px;
    font-size: 0.5rem; font-weight: 700; text-align: center;
    text-decoration: none; letter-spacing: 0.04em; text-transform: uppercase;
    cursor: pointer; transition: background 0.2s;
}
.sb-create-team-btn:hover { background: #1665d8; color: #fff; }

/* ── MAIN AREA ────────────────────────────────── */
.sb-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }

/* ── TOP BAR ──────────────────────────────────── */
.sb-topbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px; background: var(--sb-sidebar);
    border-bottom: 1px solid var(--sb-border); flex-shrink: 0;
}
.sb-topbar-spacer { flex: 1 1 0; min-width: 0; }
.sb-topbar-logo { display: flex; flex-direction: column; align-items: center; flex: 1 1 0; min-width: 0; }
.sb-logo-title { display: flex; align-items: center; gap: 8px; min-width: 0; max-width: 100%; }
.sb-logo-text {
    font-size: 1.5rem; font-weight: 900; letter-spacing: 0.08em;
    background: linear-gradient(135deg, #fff 0%, var(--sb-gold) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
}
.sb-logo-tagline { font-size: 0.6rem; color: var(--sb-gold); letter-spacing: 0.15em; text-transform: uppercase; margin-top: 1px; }

.sb-topbar-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.sb-coin-display {
    display: flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.06); border: 1px solid var(--sb-border);
    border-radius: 20px; padding: 5px 10px;
}
.sb-coin-icon { width: 20px; height: 20px; object-fit: contain; }
.sb-coin-value { font-size: 0.8rem; font-weight: 700; color: #fff; }
.sb-coin-add {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--sb-green); border: none; color: #fff; font-size: 1rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    font-weight: 700; transition: background 0.2s; text-decoration: none;
}
.sb-coin-add:hover { background: #16a34a; color: #fff; }
.sb-topbar-icon {
    width: 34px; height: 34px; border-radius: 8px;
    background: rgba(255,255,255,0.06); border: 1px solid var(--sb-border);
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    text-decoration: none; color: var(--sb-muted); transition: background 0.2s, color 0.2s;
}
.sb-topbar-icon:hover { background: rgba(255,255,255,0.12); color: #fff; }

/* ── CONTENT AREA ─────────────────────────────── */
.sb-content { flex: 1; padding: 14px 18px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }

/* ── ROW 1 : SEASON/RANK + DAILY ──────────────── */
.sb-row1 { display: grid; grid-template-columns: 1fr 1.4fr; gap: 12px; }

.sb-season-card {
    background: linear-gradient(135deg, #0e1a3a 0%, #162040 100%);
    border: 1px solid rgba(232,196,58,0.25); border-radius: 12px;
    padding: 14px 16px; display: flex; flex-direction: column; gap: 12px;
}
.sb-season-top { display: flex; align-items: center; gap: 14px; }
.sb-season-icon { font-size: 2.2rem; flex-shrink: 0; }
.sb-season-label { font-size: 0.6rem; color: var(--sb-gold); text-transform: uppercase; letter-spacing: 0.12em; font-weight: 700; margin-bottom: 4px; }
.sb-season-name { font-size: 1.1rem; font-weight: 800; color: #fff; text-transform: uppercase; }
.sb-season-end { font-size: 0.7rem; color: var(--sb-muted); margin-top: 3px; }

.lg-rank-row {
    display: flex; align-items: center; gap: 12px;
    padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.08);
}
.lg-rank-emblem {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2), rgba(255,255,255,0.03));
    border: 1px solid rgba(255,255,255,0.22);
}
.lg-rank-info { flex: 1; min-width: 0; }
.lg-rank-label { font-size: 0.55rem; color: var(--sb-muted); text-transform: uppercase; letter-spacing: 0.08em; }
.lg-rank-value { font-size: 1rem; font-weight: 800; color: #fff; }
.lg-rank-side { text-align: right; }
.lg-rank-pos { font-size: 0.95rem; font-weight: 800; color: var(--sb-gold); }
.lg-rank-pts { font-size: 0.62rem; color: var(--sb-muted); }

.sb-daily-card {
    background: linear-gradient(135deg, #130d2e 0%, #1e1040 100%);
    border: 1px solid rgba(124,58,237,0.3); border-radius: 12px;
    padding: 14px 16px; display: flex; align-items: center; gap: 16px;
}
.sb-daily-chest { font-size: 2.8rem; flex-shrink: 0; }
.sb-daily-info { flex: 1; min-width: 0; }
.sb-daily-label { font-size: 0.6rem; color: #a78bfa; text-transform: uppercase; letter-spacing: 0.12em; font-weight: 700; margin-bottom: 3px; }
.sb-daily-title { font-size: 0.85rem; font-weight: 600; color: #fff; margin-bottom: 8px; line-height: 1.3; }
.sb-daily-progress-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 6px; overflow: hidden; margin-bottom: 4px; }
.sb-daily-fill { height: 100%; background: linear-gradient(90deg, var(--sb-purple), #a855f7); border-radius: 6px; }
.sb-daily-progress-text { font-size: 0.65rem; color: var(--sb-muted); }
.sb-daily-reward { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
.sb-daily-reward-icon { font-size: 1.4rem; }
.sb-daily-reward-value { font-size: 0.8rem; font-weight: 800; color: var(--sb-gold); }

/* ── ROW 2 : LIGUE MODE CARDS ─────────────────── */
.lg-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.lg-card {
    background: var(--sb-card); border: 1px solid var(--sb-border);
    border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 12px;
    position: relative; overflow: hidden;
}
.lg-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, transparent, var(--sb-gold), transparent); opacity: 0.7;
}
.lg-card-head { display: flex; align-items: center; gap: 10px; }
.lg-card-icon {
    width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.18), rgba(255,255,255,0.03));
    border: 1px solid var(--sb-border);
}
.lg-card-title { font-size: 0.92rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
.lg-card-desc { font-size: 0.72rem; color: var(--sb-muted); line-height: 1.5; flex: 1; }
.lg-btn {
    display: block; width: 100%; padding: 10px; border-radius: 9px; border: none;
    font-weight: 800; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
    text-align: center; text-decoration: none; cursor: pointer; transition: filter .2s, transform .15s;
}
.lg-btn:hover { filter: brightness(1.12); transform: translateY(-1px); }
.btn-blue   { background: #1a73e8; color: #fff; }
.btn-green  { background: #22c55e; color: #fff; }
.btn-purple { background: #7c3aed; color: #fff; }
.btn-orange { background: #f97316; color: #fff; }
.lg-btn-row { display: flex; gap: 8px; }
.lg-btn-row .lg-btn { flex: 1; }
.lg-btn-ghost {
    background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.25);
    position: relative;
}
.lg-btn-ghost:hover { background: rgba(255,255,255,0.16); }
.lg-inv-badge {
    position: absolute; top: -6px; right: -6px; min-width: 18px; height: 18px;
    padding: 0 5px; background: #ef4444; border-radius: 9px;
    font-size: 0.62rem; font-weight: 800; color: #fff;
    display: flex; align-items: center; justify-content: center;
    border: 1.5px solid var(--sb-card); animation: lgPulse 2s infinite;
}
@keyframes lgPulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.12);} }

/* progression */
.lg-prog-meta { display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--sb-muted); }
.lg-prog-meta strong { color: #fff; }
.lg-prog-bar { height: 8px; background: rgba(255,255,255,0.1); border-radius: 8px; overflow: hidden; }
.lg-prog-fill { height: 100%; background: linear-gradient(90deg, var(--sb-accent), var(--sb-gold)); border-radius: 8px; transition: width .6s ease; }
.lg-prog-note { font-size: 0.68rem; line-height: 1.45; }
.lg-prog-note.ok  { color: #86efac; }
.lg-prog-note.wait { color: var(--sb-muted); }

/* ── RANK LADDER ──────────────────────────────── */
.lg-ladder {
    background: var(--sb-card2); border: 1px solid var(--sb-border);
    border-radius: 12px; padding: 14px 16px;
}
.lg-ladder-label { font-size: 0.6rem; letter-spacing: 0.14em; text-transform: uppercase; color: var(--sb-muted); margin-bottom: 12px; font-weight: 700; }
.lg-ladder-track { display: flex; align-items: center; justify-content: center; gap: 0.4rem; flex-wrap: wrap; }
.rank-chip {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.42rem 0.85rem; border-radius: 999px;
    font-weight: 800; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.03em;
    box-shadow: 0 4px 14px rgba(0,0,0,0.32); border: 1px solid rgba(255,255,255,0.25); white-space: nowrap;
}
.rank-chip.is-current { outline: 2px solid var(--sb-gold); outline-offset: 2px; }
.rank-arrow { opacity: 0.4; font-weight: 700; font-size: 0.85rem; }
.chip-bronze  { background: linear-gradient(135deg,#cd7f32,#a35e22); color:#fff; }
.chip-argent  { background: linear-gradient(135deg,#e9e9e9,#b9b9b9); color:#2a2a2a; }
.chip-or      { background: linear-gradient(135deg,#ffe27a,#ffcb05); color:#3a2c00; }
.chip-platine { background: linear-gradient(135deg,#f4f4f2,#cfd6d8); color:#2a2a2a; }
.chip-diamant { background: linear-gradient(135deg,#b9f2ff,#00d4ff); color:#063248; }
.chip-legende { background: linear-gradient(135deg,#c4b5fd,#7c3aed); color:#fff; box-shadow:0 0 18px rgba(124,58,237,0.6); }

/* ── PANELS (teams / rewards / live) ──────────── */
.lg-grid-panels { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.sb-panel {
    background: var(--sb-card2); border: 1px solid var(--sb-border);
    border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 10px;
}
.sb-panel-title {
    font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;
    color: var(--sb-muted); padding-bottom: 8px; border-bottom: 1px solid var(--sb-border);
}
.sb-rank-item { display: flex; align-items: center; gap: 8px; font-size: 0.72rem; }
.sb-rank-num {
    width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.65rem; font-weight: 800;
}
.rank-1 { background: #f5a623; color: #0a0f2a; }
.rank-2 { background: #9ca3af; color: #0a0f2a; }
.rank-3 { background: #cd7f32; color: #0a0f2a; }
.rank-x { background: #1c2a5a; color: #cbd5f5; }
.sb-rank-name { flex: 1; color: #fff; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sb-rank-score { color: var(--sb-gold); font-weight: 700; font-size: 0.68rem; }
.sb-panel-btn {
    display: block; width: 100%; padding: 7px;
    border: 1px solid var(--sb-accent); border-radius: 7px; background: transparent; color: var(--sb-accent);
    font-size: 0.6rem; font-weight: 700; text-align: center; text-decoration: none;
    letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer;
    transition: background 0.2s, color 0.2s; margin-top: auto;
}
.sb-panel-btn:hover { background: var(--sb-accent); color: #fff; }
.sb-empty { font-size: 0.7rem; color: var(--sb-muted); text-align: center; padding: 8px 0; }

.lg-reward-item { display: flex; align-items: center; gap: 8px; font-size: 0.72rem; color: #cbd5f5; }
.lg-reward-rank { font-weight: 800; color: var(--sb-gold); min-width: 28px; }
.lg-reward-coins { margin-left: auto; font-weight: 700; color: #fff; }

.sb-activity-item { display: flex; align-items: center; gap: 8px; font-size: 0.72rem; color: #cbd5f5; }
.sb-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-green  { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
.dot-blue   { background: #3b82f6; box-shadow: 0 0 6px #3b82f6; }
.dot-purple { background: #a855f7; box-shadow: 0 0 6px #a855f7; }
.dot-orange { background: #f97316; box-shadow: 0 0 6px #f97316; }
.sb-activity-item strong { color: #fff; }

/* ── MY TEAMS SECTION ─────────────────────────── */
.lg-teams {
    background: var(--sb-card2); border: 1px solid var(--sb-border);
    border-radius: 12px; padding: 16px;
}
.lg-teams-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.lg-teams-title { font-size: 0.95rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
.lg-team-list { display: flex; flex-direction: column; gap: 10px; }
.lg-team-card {
    background: rgba(255,255,255,0.05); border: 1px solid var(--sb-border);
    border-radius: 12px; padding: 12px; display: flex; align-items: center;
    justify-content: space-between; gap: 12px; flex-wrap: wrap; transition: background .2s;
}
.lg-team-card:hover { background: rgba(255,255,255,0.09); }
.lg-team-info { display: flex; align-items: center; gap: 12px; min-width: 0; }
.lg-team-emblem {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.18), rgba(0,0,0,0.25));
    border: 1px solid rgba(255,255,255,0.16);
}
.lg-team-name { font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.lg-team-meta { font-size: 0.7rem; color: var(--sb-muted); margin-top: 2px; }
.lg-captain-badge {
    background: linear-gradient(135deg, var(--sb-gold), var(--sb-gold2)); color: #0a0f2a;
    padding: 0.1rem 0.45rem; border-radius: 5px; font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.03em;
}
.lg-team-actions { display: flex; align-items: center; gap: 10px; margin-left: auto; flex-wrap: wrap; }
.lg-team-eff { font-size: 0.78rem; font-weight: 700; color: #fff; }
.lg-team-level {
    padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.66rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.03em; border: 1px solid rgba(255,255,255,0.22);
}
.lg-team-level.bronze  { background: linear-gradient(135deg,#cd7f32,#a35e22); color:#fff; }
.lg-team-level.argent  { background: linear-gradient(135deg,#e9e9e9,#b9b9b9); color:#2a2a2a; }
.lg-team-level.or      { background: linear-gradient(135deg,#ffe27a,#ffcb05); color:#3a2c00; }
.lg-team-level.platine { background: linear-gradient(135deg,#f4f4f2,#cfd6d8); color:#2a2a2a; }
.lg-team-level.diamant { background: linear-gradient(135deg,#b9f2ff,#00d4ff); color:#063248; }
.lg-team-level.legende { background: linear-gradient(135deg,#c4b5fd,#7c3aed); color:#fff; }
.lg-btn-select {
    padding: 0.5rem 1.05rem; border-radius: 9px; font-weight: 800; font-size: 0.74rem;
    text-decoration: none; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 0.04em;
    background: linear-gradient(135deg, var(--sb-green), #2e9e53); color: #fff; transition: filter .2s, transform .15s;
}
.lg-btn-select:hover { filter: brightness(1.1); transform: translateY(-1px); }
.lg-empty { text-align: center; padding: 1.8rem 1rem; color: var(--sb-muted); }
.lg-empty-icon { font-size: 2.4rem; margin-bottom: 6px; }

.lg-invites { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--sb-border); }
.lg-invites-title { font-size: 0.78rem; font-weight: 700; margin-bottom: 8px; }
.lg-invite-card {
    background: linear-gradient(135deg, rgba(255,215,0,0.14), rgba(255,215,0,0.05));
    border: 1px solid rgba(255,215,0,0.35); border-radius: 10px; padding: 10px 12px;
    display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px;
}
.lg-invite-info { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.8rem; }
.lg-invite-actions { display: flex; gap: 8px; }
.lg-btn-accept, .lg-btn-decline {
    padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.74rem; font-weight: 700;
    border: none; cursor: pointer; color: #fff; transition: transform .15s;
}
.lg-btn-accept { background: linear-gradient(135deg, #4CAF50, #2e9e53); }
.lg-btn-decline { background: linear-gradient(135deg, #e0464f, #c1303a); }
.lg-btn-accept:hover, .lg-btn-decline:hover { transform: translateY(-1px); }

/* ── MOBILE BOTTOM NAV ────────────────────────── */
.sb-mobile-nav {
    display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;
    background: #0b1230; border-top: 1px solid rgba(100,140,255,0.18);
    height: 60px; align-items: stretch; justify-content: space-around;
    padding-bottom: env(safe-area-inset-bottom, 0px); box-shadow: 0 -4px 20px rgba(0,0,0,0.5);
}
.sb-mn-item {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
    flex: 1; padding: 6px 2px; text-decoration: none; color: #6b84c4;
    font-size: 0.48rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;
    position: relative; transition: color 0.2s, background 0.2s; border-top: 2px solid transparent;
}
.sb-mn-item:hover { color: #fff; background: rgba(255,255,255,0.04); }
.sb-mn-item.active { color: #fff; border-top-color: #1a73e8; background: rgba(26,115,232,0.1); }
.sb-mn-item svg { width: 20px; height: 20px; flex-shrink: 0; }
.sb-mn-notif {
    position: absolute; top: 6px; right: calc(50% - 16px);
    width: 7px; height: 7px; background: #ef4444; border-radius: 50%; border: 1.5px solid #0b1230;
}

/* ── RESPONSIVE ───────────────────────────────── */
@media (max-width: 1200px) {
    .lg-grid3, .lg-grid-panels { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 1023px) {
    :root { --sidebar-w: 64px; }
    .sb-sidebar-name, .sb-xp-bar-wrap { display: none; }
    .sb-nav-item span { display: none; }
    .sb-nav-item { width: 46px; padding: 9px 6px; }
    .sb-create-team-btn { font-size: 0; line-height: 1.2; padding: 8px 4px; border-radius: 6px; }
    .sb-create-team-btn::before { content: '+'; font-size: 1.1rem; font-weight: 900; }
    .sb-row1 { grid-template-columns: 1fr; }
    .sb-logo-text { font-size: 1.2rem; }
    .sb-logo-tagline { display: none; }
}
@media (max-width: 860px) {
    .lg-grid-panels { grid-template-columns: repeat(2, 1fr); }
    .sb-content { padding: 12px 14px; }
}
@media (max-width: 639px) {
    .sb-sidebar { display: none; }
    .sb-layout { flex-direction: column; }
    .sb-mobile-nav { display: flex; }
    .sb-content { padding: 10px 10px calc(64px + env(safe-area-inset-bottom, 0px)); }
    .sb-topbar { padding: 8px 12px; gap: 0; }
    .sb-topbar-spacer { display: none; }
    .sb-logo-text { font-size: 1rem; letter-spacing: 0.05em; }
    .sb-topbar-right { gap: 6px; }
    .sb-topbar-icon { width: 30px; height: 30px; border-radius: 6px; }
    .sb-coin-display { padding: 4px 8px; gap: 4px; }
    .sb-coin-value { font-size: 0.72rem; }
    .sb-coin-icon { width: 16px; height: 16px; }
    .sb-coin-add { width: 24px; height: 24px; font-size: 0.9rem; }
    .sb-row1 { grid-template-columns: 1fr; gap: 8px; }
    .lg-grid3 { grid-template-columns: 1fr; gap: 8px; }
    .lg-grid-panels { grid-template-columns: 1fr; gap: 8px; }
    .lg-team-card { flex-direction: column; align-items: flex-start; }
    .lg-team-actions { margin-left: 0; width: 100%; }
    .lg-invite-card { flex-direction: column; align-items: flex-start; }
    .lg-invite-actions { width: 100%; justify-content: flex-end; }
}
@media (max-width: 400px) {
    .sb-logo-text { font-size: 0.85rem; }
    .sb-topbar-icon { display: none; }
    .sb-coin-add { display: none; }
    .sb-season-top { flex-direction: column; align-items: flex-start; gap: 8px; }
    .sb-daily-card { flex-direction: column; align-items: flex-start; gap: 8px; }
    .sb-daily-reward { flex-direction: row; align-self: flex-end; gap: 4px; }
}
</style>

{{-- ── SPLASH INTRO ── --}}
<div id="ligue-splash">
    <img src="{{ asset('images/ligue_hero.png') }}" alt="{{ __('Ligue') }}" id="ligue-splash-img">
    <div id="ligue-splash-overlay">
        <div id="ligue-splash-title">{{ __('LIGUE') }}</div>
        <div id="ligue-splash-sub">{{ __('Arène des Champions') }}</div>
    </div>
</div>
<style>
#ligue-splash { position: fixed; inset: 0; z-index: 9999; background: #000; display: flex; align-items: center; justify-content: center; overflow: hidden; animation: splashExit 0.6s ease-in 2.6s forwards; pointer-events: all; }
#ligue-splash-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center 12%; animation: splashZoom 3.2s ease-out forwards; }
#ligue-splash-overlay { position: relative; z-index: 2; text-align: center; animation: splashTextIn 0.8s ease-out 0.3s both; }
#ligue-splash-title { font-size: clamp(3rem, 12vw, 7rem); font-weight: 900; letter-spacing: 0.15em; color: #fff; text-shadow: 0 0 40px rgba(196,181,253,0.9), 0 4px 20px rgba(0,0,0,0.8); text-transform: uppercase; }
#ligue-splash-sub { font-size: clamp(0.9rem, 3vw, 1.4rem); font-weight: 500; letter-spacing: 0.3em; color: rgba(196,181,253,0.9); text-transform: uppercase; margin-top: 0.5rem; text-shadow: 0 2px 10px rgba(0,0,0,0.7); }
@keyframes splashZoom { 0% { transform: scale(1.08); } 100% { transform: scale(1); } }
@keyframes splashTextIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes splashExit { 0% { opacity: 1; transform: translateY(0); } 100% { opacity: 0; transform: translateY(-30px); pointer-events: none; } }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var splash = document.getElementById('ligue-splash');
        if (!splash) return;
        setTimeout(function () {
            splash.style.pointerEvents = 'none';
            setTimeout(function () { splash.style.display = 'none'; }, 700);
        }, 2600);
    });
</script>

<div class="sb-layout">

    {{-- ══════════ SIDEBAR ══════════ --}}
    <aside class="sb-sidebar">
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
                <div class="sb-xp-label">{{ number_format($victories) }} / {{ number_format($xpMilestone) }}</div>
                <div class="sb-xp-bar"><div class="sb-xp-fill" style="width:{{ $xpPercent }}%"></div></div>
            </div>
        </div>

        <nav class="sb-nav">
            <a href="{{ route('menu') }}" class="sb-nav-item">
                <div class="sb-nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                <span>{{ __('Accueil') }}</span>
            </a>
            <a href="{{ R::has('leaderboard') ? route('leaderboard') : url('/classements') }}" class="sb-nav-item">
                <div class="sb-nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 000 5H6"/><path d="M18 9h1.5a2.5 2.5 0 010 5H18"/><path d="M8 9h8"/><path d="M8 15h8"/><path d="M8 3v6"/><path d="M16 3v6"/><rect x="8" y="3" width="8" height="18" rx="2"/></svg></div>
                <span>{{ __('Classements') }}</span>
            </a>
            <a href="{{ R::has('quests.index') ? route('quests.index') : url('/quests') }}" class="sb-nav-item">
                <div class="sb-nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                <span>{{ __('Quêtes') }}</span>
            </a>
            <a href="{{ R::has('boutique') ? route('boutique') : url('/boutique') }}" class="sb-nav-item">
                <div class="sb-nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61l1.6-8.39H6"/></svg></div>
                <span>{{ __('Boutique') }}</span>
            </a>
            <a href="{{ R::has('avatar') ? route('avatar') : url('/avatar') }}" class="sb-nav-item">
                <div class="sb-nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
                <span>{{ __('Collection') }}</span>
            </a>
            <a href="{{ R::has('ligue') ? route('ligue') : url('/ligue') }}" class="sb-nav-item active" style="position:relative">
                @if($invitationCount > 0)<div class="notif-dot"></div>@endif
                <div class="sb-nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                <span>{{ __('Ligues') }}</span>
            </a>
        </nav>

        <div class="sb-sidebar-bottom">
            <a href="{{ route('league.team.create') }}" class="sb-create-team-btn"><span>{{ __('Créer équipe') }}</span></a>
        </div>
    </aside>

    {{-- ══════════ MAIN ══════════ --}}
    <div class="sb-main">

        {{-- ── TOP BAR ── --}}
        <header class="sb-topbar">
            <div class="sb-topbar-spacer"></div>
            <div class="sb-topbar-logo">
                <div class="sb-logo-title">
                    <span style="font-size:1.5rem">🛡️</span>
                    <span class="sb-logo-text">{{ __('LIGUE') }}</span>
                    <span style="font-size:1.5rem">🛡️</span>
                </div>
                <div class="sb-logo-tagline">{{ __('Arène des Champions') }}</div>
            </div>
            <div class="sb-topbar-right">
                <div class="sb-coin-display">
                    <img class="sb-coin-icon" src="{{ asset('images/coin-intelligence.png') }}" alt="{{ __('Intelligence') }}" onerror="this.style.display='none'" style="object-fit:cover;">
                    <span class="sb-coin-value" id="topbar-intel">{{ number_format($intelligencePieces) }}</span>
                </div>
                <div class="sb-coin-display">
                    <img class="sb-coin-icon" src="{{ asset('images/skill_coin.png') }}" alt="{{ __('Compétence') }}" onerror="this.replaceWith('⭐')">
                    <span class="sb-coin-value" id="topbar-comp">{{ number_format($competenceCoins) }}</span>
                </div>
                <a href="{{ R::has('boutique') ? route('boutique') : url('/boutique') }}" class="sb-coin-add" title="{{ __('Obtenir des pièces') }}">+</a>
                <a href="{{ url('/messages') }}" class="sb-topbar-icon" title="{{ __('Messages') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </a>
                <a href="{{ R::has('profile') ? route('profile') : url('/profile') }}" class="sb-topbar-icon" title="{{ __('Profil') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
            </div>
        </header>

        {{-- ── CONTENT ── --}}
        <div class="sb-content">

            {{-- ROW 1 : Season + Rank, Daily challenge --}}
            <div class="sb-row1">
                <div class="sb-season-card">
                    <div class="sb-season-top">
                        <div class="sb-season-icon">🏆</div>
                        <div>
                            <div class="sb-season-label">{{ __('Saison actuelle') }}</div>
                            <div class="sb-season-name">{{ $seasonName ?? __('Hors saison') }}</div>
                            @if($season && $seasonDays > 0)
                                <div class="sb-season-end">{{ __('Fin dans') }} {{ $seasonDays }}{{ __('j') }}</div>
                            @else
                                <div class="sb-season-end">{{ __('Aucune saison active') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="lg-rank-row">
                        <div class="lg-rank-emblem">{{ $divisionIcon }}</div>
                        <div class="lg-rank-info">
                            <div class="lg-rank-label">{{ __('Votre division') }}</div>
                            <div class="lg-rank-value">{{ $divisionName }}</div>
                        </div>
                        <div class="lg-rank-side">
                            <div class="lg-rank-pos">{{ $leaguePosition ? '#'.number_format($leaguePosition) : '—' }}</div>
                            <div class="lg-rank-pts">{{ number_format($leaguePoints) }} {{ __('pts') }}</div>
                        </div>
                    </div>
                </div>

                <div class="sb-daily-card">
                    <div class="sb-daily-chest">🎁</div>
                    <div class="sb-daily-info">
                        <div class="sb-daily-label">{{ __('Défi quotidien') }}</div>
                        @if($dailyChallenge)
                            <div class="sb-daily-title">{{ $challengeTitle ?? __('Défi du jour') }}</div>
                            <div class="sb-daily-progress-bar"><div class="sb-daily-fill" style="width:{{ $challengePercent }}%"></div></div>
                            <div class="sb-daily-progress-text">{{ $challengeProgress }}/{{ $challengeGoal }}</div>
                        @else
                            <div class="sb-daily-title">{{ __('Aucun défi disponible') }}</div>
                        @endif
                    </div>
                    @if($dailyChallenge)
                        <div class="sb-daily-reward">
                            <div class="sb-daily-reward-icon">⭐</div>
                            <div class="sb-daily-reward-value">{{ $challengeReward }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ROW 2 : Mode cards --}}
            <div class="lg-grid3">
                {{-- Solo classé --}}
                <div class="lg-card">
                    <div class="lg-card-head">
                        <div class="lg-card-icon">⚔️</div>
                        <div class="lg-card-title">{{ __('Solo classé') }}</div>
                    </div>
                    <div class="lg-card-desc">{{ __('Affrontez des adversaires en 1v1 et grimpez dans les divisions') }}</div>
                    <a href="{{ route('league.individual.lobby') }}" class="lg-btn btn-blue">{{ __('Jouer') }}</a>
                </div>

                {{-- Équipe 5v5 --}}
                <div class="lg-card">
                    <div class="lg-card-head">
                        <div class="lg-card-icon">👥</div>
                        <div class="lg-card-title">{{ __('Équipe 5v5') }}</div>
                    </div>
                    <div class="lg-card-desc">{{ __('Choisissez l\'équipe avec laquelle vous souhaitez participer aux matchs 5v5.') }}</div>
                    <div class="lg-btn-row">
                        <a href="{{ route('league.team.create') }}" class="lg-btn btn-purple">+ {{ __('Créer') }}</a>
                        <a href="{{ route('league.team.search') }}" class="lg-btn lg-btn-ghost">
                            {{ __('Rejoindre') }}
                            @if($invitationCount > 0)<span class="lg-inv-badge">{{ $invitationCount }}</span>@endif
                        </a>
                    </div>
                </div>

                {{-- Votre progression --}}
                <div class="lg-card">
                    <div class="lg-card-head">
                        <div class="lg-card-icon">📈</div>
                        <div class="lg-card-title">{{ __('Votre progression') }}</div>
                    </div>
                    @if(!empty($seasonInfo['active_season']))
                        <div class="lg-prog-meta">
                            <span>{{ __('matchs gagnés') }}</span>
                            <span><strong>{{ $siWon }}</strong> / {{ $siThreshold }}</span>
                        </div>
                        <div class="lg-prog-bar"><div class="lg-prog-fill" style="width:{{ $siPercent }}%"></div></div>
                        @if($siEligible)
                            <div class="lg-prog-note ok">✅ {{ __('Éligible aux récompenses de saison') }}</div>
                        @else
                            <div class="lg-prog-note wait">{{ __('Continuez à gagner pour débloquer les récompenses') }}</div>
                        @endif
                    @else
                        <div class="lg-prog-note wait">{{ __('Aucune saison active') }}</div>
                    @endif
                </div>
            </div>

            {{-- DIVISION LADDER --}}
            <div class="lg-ladder">
                <div class="lg-ladder-label">📊 {{ __('Système de divisions') }}</div>
                <div class="lg-ladder-track">
                    <span class="rank-chip chip-bronze {{ $divKey === 'bronze' ? 'is-current' : '' }}">🥉 {{ __('Bronze') }}</span>
                    <span class="rank-arrow">→</span>
                    <span class="rank-chip chip-argent {{ $divKey === 'argent' ? 'is-current' : '' }}">⚪ {{ __('Argent') }}</span>
                    <span class="rank-arrow">→</span>
                    <span class="rank-chip chip-or {{ $divKey === 'or' ? 'is-current' : '' }}">🥇 {{ __('Or') }}</span>
                    <span class="rank-arrow">→</span>
                    <span class="rank-chip chip-platine {{ $divKey === 'platine' ? 'is-current' : '' }}">💠 {{ __('Platine') }}</span>
                    <span class="rank-arrow">→</span>
                    <span class="rank-chip chip-diamant {{ $divKey === 'diamant' ? 'is-current' : '' }}">💎 {{ __('Diamant') }}</span>
                    <span class="rank-arrow">→</span>
                    <span class="rank-chip chip-legende {{ $divKey === 'legende' ? 'is-current' : '' }}">👑 {{ __('Légende') }}</span>
                </div>
            </div>

            {{-- MY TEAMS --}}
            <div class="lg-teams">
                <div class="lg-teams-head">
                    <div class="lg-teams-title">👥 {{ __('Mes équipes') }}</div>
                    <a href="{{ route('league.team.create') }}" class="lg-btn-select" style="width:auto">+ {{ __('Créer') }}</a>
                </div>

                @if($userTeams->count() > 0)
                    <div class="lg-team-list">
                        @foreach($userTeams as $team)
                            @php
                                $avgEfficiency = $teamEfficiency[$team->id] ?? 0;
                                $teamDivKey = strtolower($team->division ?? 'bronze');
                                $teamLevel = $divNames[$teamDivKey] ?? ucfirst($teamDivKey);
                            @endphp
                            <div class="lg-team-card">
                                <div class="lg-team-info">
                                    <div class="lg-team-emblem">{{ $team->emblem ?? '🛡️' }}</div>
                                    <div>
                                        <div class="lg-team-name">
                                            {{ $team->name }}
                                            @if($team->captain_id === $user->id)
                                                <span class="lg-captain-badge">{{ __('Capitaine') }}</span>
                                            @endif
                                        </div>
                                        <div class="lg-team-meta">{{ $team->members->count() }} / 5 {{ __('joueurs') }}</div>
                                    </div>
                                </div>
                                <div class="lg-team-actions">
                                    <span class="lg-team-eff" title="{{ __('Efficacité moyenne') }}">🎯 {{ $avgEfficiency }}%</span>
                                    <span class="lg-team-level {{ $teamDivKey }}">{{ $teamLevel }}</span>
                                    <a href="{{ route('league.team.management', ['teamId' => $team->id]) }}" class="lg-btn-select">{{ __('Choisir') }}</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="lg-empty">
                        <div class="lg-empty-icon">🔍</div>
                        <p>{{ __('Vous n\'appartenez à aucune équipe.') }}</p>
                        <p style="font-size: 0.8rem; margin-top:4px;">{{ __('Créez votre propre équipe ou rejoignez-en une existante !') }}</p>
                    </div>
                @endif

                @if($invitationCount > 0)
                    <div class="lg-invites">
                        <div class="lg-invites-title">📩 {{ __('Invitations en attente') }}</div>
                        @foreach($pendingInvitations as $invitation)
                            <div class="lg-invite-card">
                                <div class="lg-invite-info">
                                    <span>{{ $invitation->team->emblem ?? '🛡️' }}</span>
                                    <span><strong>{{ $invitation->team->name }}</strong> ({{ __('par') }} {{ $invitation->team->captain->name ?? __('Inconnu') }})</span>
                                </div>
                                <div class="lg-invite-actions">
                                    <form action="{{ route('league.team.invitation.accept', $invitation->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="lg-btn-accept">{{ __('Accepter') }}</button>
                                    </form>
                                    <form action="{{ route('league.team.invitation.decline', $invitation->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="lg-btn-decline">{{ __('Refuser') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ROW : Panels --}}
            <div class="lg-grid-panels">
                {{-- Top teams --}}
                <div class="sb-panel">
                    <div class="sb-panel-title">{{ __('Classement des équipes') }}</div>
                    @forelse($topTeams as $i => $team)
                        @php $rank = $i + 1; @endphp
                        <div class="sb-rank-item">
                            <div class="sb-rank-num {{ $rank <= 3 ? 'rank-'.$rank : 'rank-x' }}">{{ $rank }}</div>
                            <div class="sb-rank-name">{{ $team->emblem ?? '🛡️' }} {{ $team->name }}</div>
                            <div class="sb-rank-score">{{ number_format($team->points ?? 0) }}</div>
                        </div>
                    @empty
                        <div class="sb-empty">{{ __('Aucune équipe classée pour le moment') }}</div>
                    @endforelse
                    <a href="{{ route('league.team.search') }}" class="sb-panel-btn">{{ __('Voir les équipes') }}</a>
                </div>

                {{-- Season rewards --}}
                <div class="sb-panel">
                    <div class="sb-panel-title">{{ __('Récompenses de saison') }}</div>
                    @forelse($siPrizes as $prize)
                        <div class="lg-reward-item">
                            <span class="lg-reward-rank">#{{ $prize['rank'] ?? '?' }}</span>
                            <span>🏅 {{ __('Récompense de classement') }}</span>
                            @if(isset($prize['coins']))
                                <span class="lg-reward-coins">{{ number_format($prize['coins']) }} ⭐</span>
                            @endif
                        </div>
                    @empty
                        <div class="sb-empty">{{ __('Récompenses annoncées en fin de saison') }}</div>
                    @endforelse
                    <a href="{{ R::has('quetes-quotidiennes') ? route('quetes-quotidiennes') : url('/quetes-quotidiennes') }}" class="sb-panel-btn">{{ __('Voir les détails') }}</a>
                </div>

                {{-- Live --}}
                <div class="sb-panel">
                    <div class="sb-panel-title">{{ __('Activité en direct') }}</div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-green"></div>
                        <strong>{{ $livePlayers !== null ? number_format($livePlayers) : '—' }}</strong> {{ __('joueurs en ligne') }}
                    </div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-blue"></div>
                        <strong>{{ $liveMatches !== null ? number_format($liveMatches) : '—' }}</strong> {{ __('Matchs en cours') }}
                    </div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-purple"></div>
                        <strong>{{ $liveTeams !== null ? number_format($liveTeams) : '—' }}</strong> {{ __('Équipes actives') }}
                    </div>
                    <div class="sb-activity-item">
                        <div class="sb-dot dot-orange"></div>
                        <strong>{{ $liveTournois !== null ? number_format($liveTournois) : '—' }}</strong> {{ __('Tournois aujourd\'hui') }}
                    </div>
                </div>
            </div>

        </div>{{-- /sb-content --}}
    </div>{{-- /sb-main --}}

    {{-- ── MOBILE BOTTOM NAV ── --}}
    <nav class="sb-mobile-nav">
        <a href="{{ route('menu') }}" class="sb-mn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>{{ __('Accueil') }}</span>
        </a>
        <a href="{{ R::has('leaderboard') ? route('leaderboard') : url('/classements') }}" class="sb-mn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="3" width="8" height="18" rx="2"/><path d="M8 9h8"/><path d="M8 15h8"/></svg>
            <span>{{ __('Classements') }}</span>
        </a>
        <a href="{{ R::has('ligue') ? route('ligue') : url('/ligue') }}" class="sb-mn-item active" style="position:relative">
            @if($invitationCount > 0)<div class="sb-mn-notif"></div>@endif
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>{{ __('Ligues') }}</span>
        </a>
        <a href="{{ R::has('quests.index') ? route('quests.index') : url('/quests') }}" class="sb-mn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>{{ __('Quêtes') }}</span>
        </a>
        <a href="{{ R::has('boutique') ? route('boutique') : url('/boutique') }}" class="sb-mn-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61l1.6-8.39H6"/></svg>
            <span>{{ __('Boutique') }}</span>
        </a>
    </nav>

</div>
@endsection
