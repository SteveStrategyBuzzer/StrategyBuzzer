<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Quêtes — StrategyBuzzer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <style>
:root{
  --gap:14px; --radius:18px; --shadow:0 10px 24px rgba(0,0,0,.12);
  --bg:#0b1020; --card:#111735; --ink:#ecf0ff; --muted:#9fb6ff;
  --blue:#2c4bff; --ok:#22c55e; --danger:#ef4444; --line:rgba(255,255,255,.08);
  --gold:#fbbf24;
}
*{box-sizing:border-box}
body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto; background:var(--bg); color:var(--ink); }
.wrap{ max-width:1200px; margin:0 auto; padding:20px 16px 80px; }

.topbar{ display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap; pointer-events:none; }
.topbar a{ pointer-events:auto; }
.pill{ background:linear-gradient(135deg,#1a2344,#15224c); border:1px solid var(--line); padding:10px 14px; border-radius:999px; box-shadow:var(--shadow); display:flex; align-items:center; gap:10px; }
.pill b{color:#fff}
a.clean{ color:var(--muted); text-decoration:none; }

.tabs{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px }
.tab{ appearance:none; border:none; cursor:pointer; border-radius:999px; padding:10px 14px; background:#121c3f; color:#cfe1ff; border:1px solid var(--line); text-decoration:none; transition:all .2s; }
.tab.active{ background:var(--blue); color:#fff; }
.tab:not(.active):hover{ background:#1a2855; }

.hero{ background:linear-gradient(135deg,#15224c,#0f1836); border:1px solid var(--line); padding:16px; border-radius:20px; box-shadow:var(--shadow); margin-bottom:18px; text-align:center; }
.hero h1{ font-size:1.8rem; margin:0 0 8px; color:#fff; }
.hero p{ margin:0; color:var(--muted); }

.grid{ display:grid; gap:var(--gap); }
.cols-4{ grid-template-columns:repeat(4,minmax(0,1fr)); }
.cols-3{ grid-template-columns:repeat(3,minmax(0,1fr)); }
@media (max-width:960px){ .cols-4{ grid-template-columns:repeat(3,1fr);} }
@media (max-width:760px){ .cols-4,.cols-3{ grid-template-columns:repeat(2,1fr);} }

.quest-card{
  background:var(--card);
  border:1px solid var(--line);
  border-radius:var(--radius);
  padding:16px;
  box-shadow:var(--shadow);
  display:flex;
  flex-direction:column;
  align-items:center;
  text-align:center;
  transition:transform .2s, box-shadow .2s;
  position:relative;
}

.quest-card:hover{ transform:translateY(-2px); box-shadow:0 16px 40px rgba(0,0,0,.2); }

.quest-card.locked{
  opacity:0.5;
  filter:grayscale(1);
}

.quest-card.completed{
  border-color:var(--gold);
  background:linear-gradient(135deg,#1a2344,#15224c);
}

.quest-card.completed .badge{
  filter:none !important;
}

.badge{
  font-size:3rem;
  margin-bottom:8px;
  line-height:1;
}

.quest-card.locked .badge{
  filter:grayscale(1) brightness(0.5);
}

.quest-name{
  font-size:1rem;
  font-weight:600;
  color:#fff;
  margin:0 0 6px;
}

.quest-desc{
  font-size:0.85rem;
  color:var(--muted);
  margin:0 0 10px;
  line-height:1.4;
}

.quest-reward{
  display:flex;
  align-items:center;
  gap:6px;
  font-size:0.9rem;
  color:var(--gold);
  font-weight:600;
}

.completed-badge{
  position:absolute;
  top:8px;
  right:8px;
  background:var(--ok);
  color:#fff;
  padding:4px 8px;
  border-radius:999px;
  font-size:0.7rem;
  font-weight:700;
  text-transform:uppercase;
}

.coin-icon{
  width:20px;
  height:20px;
  display:inline-block;
}

.stats{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:12px;
  margin-bottom:20px;
}

.stat-card{
  background:var(--card);
  border:1px solid var(--line);
  border-radius:var(--radius);
  padding:14px;
  text-align:center;
  box-shadow:var(--shadow);
}

.stat-value{
  font-size:1.8rem;
  font-weight:700;
  color:#fff;
  margin-bottom:4px;
}

.stat-label{
  font-size:0.85rem;
  color:var(--muted);
}

@media (max-width:480px){
  .wrap{padding:16px 12px 60px}
  .topbar{flex-direction:column;align-items:stretch}
  .pill{padding:8px 12px;font-size:0.9rem}
  .tabs{gap:6px}
  .tab{padding:8px 12px;font-size:0.9rem}
  .cols-4,.cols-3{grid-template-columns:repeat(2,1fr)}
  .stats{grid-template-columns:1fr}
  .hero h1{font-size:1.4rem}
}
  </style>
</head>
<body>

<div class="wrap">

  <!-- Topbar -->
  <div class="topbar">
    <a href="/menu" class="clean">← Menu</a>
    <div class="pill">
      <svg class="coin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="10" fill="#fbbf24"/>
        <text x="12" y="16" text-anchor="middle" font-size="12" font-weight="bold" fill="#0b1020">C</text>
      </svg>
      <b>{{ number_format($userCoins) }}</b> pièces
    </div>
  </div>

  <!-- Hero -->
  <div class="hero">
    <h1>🏆 Quêtes</h1>
    <p>Complétez des quêtes pour gagner des pièces et débloquer des récompenses</p>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-value">{{ $quests->filter(fn($q) => $q['is_completed'])->count() }}</div>
      <div class="stat-label">Complétées</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ $quests->count() }}</div>
      <div class="stat-label">Total</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ $quests->filter(fn($q) => $q['is_completed'])->sum(fn($q) => $q['quest']->reward_coins) }}</div>
      <div class="stat-label">Pièces gagnées</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <a href="/quests?rarity=Standard" class="tab {{ $currentRarity === 'Standard' ? 'active' : '' }}">💚 Standard</a>
    <a href="/quests?rarity=Rare" class="tab {{ $currentRarity === 'Rare' ? 'active' : '' }}">💎 Rare</a>
    <a href="/quests?rarity=Épique" class="tab {{ $currentRarity === 'Épique' ? 'active' : '' }}">🔮 Épique</a>
    <a href="/quests?rarity=Légendaire" class="tab {{ $currentRarity === 'Légendaire' ? 'active' : '' }}">🌟 Légendaire</a>
    <a href="/quests?rarity=Maître" class="tab {{ $currentRarity === 'Maître' ? 'active' : '' }}">👑 Maître</a>
    <a href="/quests?rarity=Quotidiennes" class="tab {{ $currentRarity === 'Quotidiennes' ? 'active' : '' }}">📅 Quotidiennes</a>
  </div>

  <!-- Quests Grid -->
  <div class="grid cols-4">
    @foreach($quests as $questData)
      @php
        $quest = $questData['quest'];
        $isCompleted = $questData['is_completed'];
      @endphp
      <div class="quest-card {{ $isCompleted ? 'completed' : 'locked' }}">
        @if($isCompleted)
          <span class="completed-badge">✓ Fait</span>
        @endif
        
        <div class="badge">{{ $quest->badge_emoji }}</div>
        <h3 class="quest-name">{{ $quest->name }}</h3>
        <p class="quest-desc">{{ $quest->condition }}</p>
        <div class="quest-reward">
          <svg class="coin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="#fbbf24"/>
            <text x="12" y="16" text-anchor="middle" font-size="12" font-weight="bold" fill="#0b1020">C</text>
          </svg>
          +{{ $quest->reward_coins }}
        </div>
      </div>
    @endforeach
  </div>

  @if($quests->isEmpty())
    <div style="text-align:center; padding:60px 20px; color:var(--muted)">
      <p style="font-size:3rem; margin:0">🎯</p>
      <p style="margin:12px 0 0">Aucune quête dans cette catégorie pour le moment</p>
    </div>
  @endif

</div>

</body>
</html>
