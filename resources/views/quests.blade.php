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
  --gap:16px; --radius:20px; --shadow:0 10px 24px rgba(0,0,0,.12);
  --bg:#0b1020; --card:#111735; --ink:#ecf0ff; --muted:#9fb6ff;
  --blue:#2c4bff; --ok:#22c55e; --danger:#ef4444; --line:rgba(255,255,255,.08);
  --gold:#fbbf24;
}
*{box-sizing:border-box}
body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto; background:var(--bg); color:var(--ink); overflow-x:hidden; }
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
.badge-grid{ grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); }
@media (max-width:760px){ .badge-grid{ grid-template-columns:repeat(auto-fill, minmax(80px, 1fr)); } }

.badge-item{
  background:var(--card);
  border:2px solid var(--line);
  border-radius:var(--radius);
  padding:20px;
  box-shadow:var(--shadow);
  display:flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
  transition:all .3s cubic-bezier(0.4, 0, 0.2, 1);
  position:relative;
  aspect-ratio:1;
}

.badge-item:hover{ transform:translateY(-4px) scale(1.05); box-shadow:0 20px 40px rgba(0,0,0,.25); }

.badge-item.locked{
  opacity:0.4;
  filter:grayscale(1) blur(1px);
  cursor:default;
}

.badge-item.locked:hover{
  transform:none;
  box-shadow:var(--shadow);
}

.badge-item.completed{
  border-color:var(--gold);
  border-width:3px;
  background:linear-gradient(135deg,rgba(251,191,36,0.1),rgba(251,191,36,0.05));
  box-shadow:0 0 20px rgba(251,191,36,0.3), var(--shadow);
}

.badge-item.completed:hover{
  box-shadow:0 0 30px rgba(251,191,36,0.5), 0 20px 40px rgba(0,0,0,.25);
}

.badge-emoji{
  font-size:3.5rem;
  line-height:1;
  filter:drop-shadow(0 2px 8px rgba(0,0,0,.3));
}

.badge-item.locked .badge-emoji{
  filter:grayscale(1) brightness(0.6) blur(1px);
}

.badge-item.in-progress{
  border-color:var(--gold);
  border-width:3px;
  background:var(--card);
  opacity:0.85;
}

.badge-item.in-progress .badge-emoji{
  filter:grayscale(1) brightness(0.7) blur(1px);
}

.badge-item.in-progress:hover{
  transform:translateY(-4px) scale(1.05);
  box-shadow:0 0 20px rgba(251,191,36,0.4), 0 20px 40px rgba(0,0,0,.25);
}

.tab.has-progress{
  border-color:var(--gold);
  border-width:2px;
  box-shadow:0 0 15px rgba(251,191,36,0.2);
}

.completed-mark{
  position:absolute;
  top:6px;
  right:6px;
  background:var(--ok);
  color:#fff;
  width:24px;
  height:24px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:0.75rem;
  font-weight:700;
  box-shadow:0 2px 8px rgba(34,197,94,0.4);
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

.modal-overlay{
  display:none;
  position:fixed;
  top:0;
  left:0;
  right:0;
  bottom:0;
  background:rgba(0,0,0,0.8);
  backdrop-filter:blur(8px);
  z-index:1000;
  align-items:center;
  justify-content:center;
  padding:20px;
  animation:fadeIn .2s;
}

.modal-overlay.show{
  display:flex;
}

@keyframes fadeIn{
  from{ opacity:0; }
  to{ opacity:1; }
}

.modal{
  background:linear-gradient(135deg,#1a2344,#15224c);
  border:2px solid var(--line);
  border-radius:24px;
  box-shadow:0 20px 60px rgba(0,0,0,.5);
  max-width:500px;
  width:100%;
  max-height:90vh;
  overflow-y:auto;
  animation:slideUp .3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideUp{
  from{ transform:translateY(40px); opacity:0; }
  to{ transform:translateY(0); opacity:1; }
}

.modal-header{
  padding:24px 24px 16px;
  text-align:center;
  border-bottom:1px solid var(--line);
}

.modal-emoji{
  font-size:5rem;
  line-height:1;
  margin-bottom:12px;
  filter:drop-shadow(0 4px 12px rgba(0,0,0,.4));
}

.modal-title{
  font-size:1.6rem;
  font-weight:700;
  color:#fff;
  margin:0 0 8px;
}

.modal-desc{
  font-size:1rem;
  color:var(--muted);
  margin:0;
  line-height:1.5;
}

.modal-body{
  padding:20px 24px;
}

.progress-section{
  margin-bottom:20px;
}

.progress-label{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:8px;
  font-size:0.9rem;
}

.progress-text{
  color:var(--muted);
}

.progress-value{
  color:#fff;
  font-weight:600;
}

.progress-bar-bg{
  background:rgba(255,255,255,0.05);
  border-radius:999px;
  height:12px;
  overflow:hidden;
  border:1px solid var(--line);
}

.progress-bar-fill{
  background:linear-gradient(90deg,var(--blue),#4f6fff);
  height:100%;
  border-radius:999px;
  transition:width .3s;
  box-shadow:0 0 10px rgba(44,75,255,0.5);
}

.reward-box{
  background:rgba(251,191,36,0.1);
  border:2px solid var(--gold);
  border-radius:16px;
  padding:16px;
  text-align:center;
  margin-bottom:16px;
}

.reward-label{
  font-size:0.85rem;
  color:var(--muted);
  margin-bottom:8px;
}

.reward-amount{
  font-size:2rem;
  font-weight:700;
  color:var(--gold);
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
}

.status-badge{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:8px 16px;
  border-radius:999px;
  font-size:0.9rem;
  font-weight:600;
  margin-top:12px;
}

.status-badge.completed{
  background:var(--ok);
  color:#fff;
}

.status-badge.locked{
  background:rgba(255,255,255,0.1);
  color:var(--muted);
}

.modal-footer{
  padding:16px 24px 24px;
  text-align:center;
}

.close-btn{
  appearance:none;
  border:none;
  background:rgba(255,255,255,0.1);
  color:#fff;
  padding:12px 24px;
  border-radius:999px;
  font-size:1rem;
  font-weight:600;
  cursor:pointer;
  transition:all .2s;
  border:1px solid var(--line);
}

.close-btn:hover{
  background:rgba(255,255,255,0.15);
  transform:translateY(-2px);
}

.daily-banner{
  background:linear-gradient(135deg, #ff9a00, #ff6a00);
  border:2px solid #ff7700;
  border-radius:20px;
  padding:20px 24px;
  margin-bottom:20px;
  box-shadow:0 10px 30px rgba(255,106,0,0.3);
  display:flex;
  align-items:center;
  gap:20px;
  animation:pulseGlow 3s infinite;
}

@keyframes pulseGlow{
  0%, 100%{ box-shadow:0 10px 30px rgba(255,106,0,0.3); }
  50%{ box-shadow:0 10px 40px rgba(255,106,0,0.5); }
}

.daily-icon{
  font-size:3.5rem;
  line-height:1;
  filter:drop-shadow(0 4px 8px rgba(0,0,0,0.3));
  animation:rotate 10s linear infinite;
}

@keyframes rotate{
  from{ transform:rotate(0deg); }
  to{ transform:rotate(360deg); }
}

.daily-content{
  flex:1;
}

.daily-title{
  font-size:1.5rem;
  font-weight:700;
  color:#fff;
  margin:0 0 4px;
  text-shadow:0 2px 4px rgba(0,0,0,0.3);
}

.daily-subtitle{
  font-size:0.95rem;
  color:rgba(255,255,255,0.9);
  margin:0;
}

.daily-timer{
  background:rgba(0,0,0,0.2);
  border:1px solid rgba(255,255,255,0.3);
  border-radius:12px;
  padding:12px 16px;
  text-align:center;
  backdrop-filter:blur(10px);
}

.timer-label{
  font-size:0.75rem;
  color:rgba(255,255,255,0.8);
  margin-bottom:4px;
  text-transform:uppercase;
  letter-spacing:0.5px;
}

.timer-value{
  font-size:1.3rem;
  font-weight:700;
  color:#fff;
  font-family:'Courier New', monospace;
  text-shadow:0 2px 4px rgba(0,0,0,0.3);
}

@media (max-width:480px){
  .wrap{padding:16px 12px 60px}
  .topbar{flex-direction:column;align-items:stretch}
  .pill{padding:8px 12px;font-size:0.9rem}
  .tabs{gap:6px}
  .tab{padding:8px 12px;font-size:0.9rem}
  .stats{grid-template-columns:1fr}
  .hero h1{font-size:1.4rem}
  .badge-emoji{font-size:2.8rem}
  .modal{max-width:calc(100vw - 32px)}
  .modal-emoji{font-size:4rem}
  .modal-title{font-size:1.3rem}
  .daily-banner{flex-direction:column;text-align:center;gap:16px;padding:16px}
  .daily-icon{font-size:3rem}
  .daily-title{font-size:1.3rem}
  .timer-value{font-size:1.1rem}
}
  </style>
</head>
<body>

<div class="wrap">

  <!-- Topbar -->
  <div class="topbar">
    <a href="/menu" class="clean">← {{ __('Menu') }}</a>
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
    <h1>🏆 {{ __('Quêtes') }}</h1>
    <p>{{ __('Cliquez sur un badge pour voir les détails') }}</p>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-value">{{ $quests->filter(fn($q) => $q['is_completed'])->count() }}</div>
      <div class="stat-label">{{ __('Complétées') }}</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ $quests->count() }}</div>
      <div class="stat-label">{{ __('Total') }}</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ $quests->filter(fn($q) => $q['is_completed'])->sum(fn($q) => $q['quest']->reward_coins) }}</div>
      <div class="stat-label">{{ __('Pièces gagnées') }}</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <a href="/quests?rarity=Standard" class="tab {{ $currentRarity === 'Standard' ? 'active' : '' }} {{ in_array('Standard', $raritiesWithProgress) ? 'has-progress' : '' }}">💚 {{ __('Standard') }}</a>
    <a href="/quests?rarity=Rare" class="tab {{ $currentRarity === 'Rare' ? 'active' : '' }} {{ in_array('Rare', $raritiesWithProgress) ? 'has-progress' : '' }}">💎 {{ __('Rare') }}</a>
    <a href="/quests?rarity=Épique" class="tab {{ $currentRarity === 'Épique' ? 'active' : '' }} {{ in_array('Épique', $raritiesWithProgress) ? 'has-progress' : '' }}">🔮 {{ __('Épique') }}</a>
    <a href="/quests?rarity=Légendaire" class="tab {{ $currentRarity === 'Légendaire' ? 'active' : '' }} {{ in_array('Légendaire', $raritiesWithProgress) ? 'has-progress' : '' }}">🌟 {{ __('Légendaire') }}</a>
    <a href="/quests?rarity=Maître" class="tab {{ $currentRarity === 'Maître' ? 'active' : '' }} {{ in_array('Maître', $raritiesWithProgress) ? 'has-progress' : '' }}">👑 {{ __('Maître') }}</a>
  </div>

  <!-- Badge Grid -->
  <div class="grid badge-grid">
    @foreach($quests as $questData)
      @php
        $quest = $questData['quest'];
        $isCompleted = $questData['is_completed'];
        $hasProgress = $questData['has_progress'] ?? false;
        $progressCurrent = $questData['progress_current'] ?? 0;
        $progressTotal = $questData['progress_total'] ?? 1;
        $progressPercent = $progressTotal > 0 ? min(100, ($progressCurrent / $progressTotal) * 100) : 0;
        
        $badgeClass = $isCompleted ? 'completed' : ($hasProgress ? 'in-progress' : 'locked');
      @endphp
      <div class="badge-item {{ $badgeClass }}" 
           onclick="openModal({{ json_encode([
             'name' => $quest->name,
             'emoji' => $quest->badge_emoji,
             'description' => $quest->condition,
             'reward' => $quest->reward_coins,
             'completed' => $isCompleted,
             'progress' => $progressCurrent,
             'total' => $progressTotal,
             'progressPercent' => $progressPercent
           ]) }})">
        @if($isCompleted)
          <span class="completed-mark">✓</span>
        @endif
        <div class="badge-emoji">{{ $quest->badge_emoji }}</div>
      </div>
    @endforeach
  </div>

  @if($quests->isEmpty())
    <div style="text-align:center; padding:60px 20px; color:var(--muted)">
      <p style="font-size:3rem; margin:0">🎯</p>
      <p style="margin:12px 0 0">{{ __('Aucune quête dans cette catégorie pour le moment') }}</p>
    </div>
  @endif

</div>

<!-- Modal -->
<div class="modal-overlay" id="questModal" onclick="closeModal(event)">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-emoji" id="modalEmoji"></div>
      <h2 class="modal-title" id="modalTitle"></h2>
      <p class="modal-desc" id="modalDesc"></p>
    </div>
    <div class="modal-body">
      <div class="reward-box">
        <div class="reward-label">{{ __('Récompense') }}</div>
        <div class="reward-amount">
          <svg class="coin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:32px;height:32px">
            <circle cx="12" cy="12" r="10" fill="#fbbf24"/>
            <text x="12" y="16" text-anchor="middle" font-size="12" font-weight="bold" fill="#0b1020">C</text>
          </svg>
          <span id="modalReward"></span>
        </div>
      </div>
      
      <div class="progress-section" id="progressSection">
        <div class="progress-label">
          <span class="progress-text">{{ __('Progression') }}</span>
          <span class="progress-value" id="progressValue"></span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill" id="progressBar"></div>
        </div>
      </div>

      <div style="text-align:center">
        <span class="status-badge" id="statusBadge"></span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="close-btn" onclick="closeModal()">{{ __('Fermer') }}</button>
    </div>
  </div>
</div>

<script>
function openModal(data) {
  document.getElementById('modalEmoji').textContent = data.emoji;
  document.getElementById('modalTitle').textContent = data.name;
  document.getElementById('modalDesc').textContent = data.description;
  document.getElementById('modalReward').textContent = '+' + data.reward;
  document.getElementById('progressValue').textContent = data.progress + ' / ' + data.total;
  document.getElementById('progressBar').style.width = data.progressPercent + '%';
  
  const statusBadge = document.getElementById('statusBadge');
  if (data.completed) {
    statusBadge.className = 'status-badge completed';
    statusBadge.innerHTML = '✓ {{ __('Complétée') }}';
  } else {
    statusBadge.className = 'status-badge locked';
    statusBadge.innerHTML = '🔒 {{ __('En cours') }}';
  }
  
  document.getElementById('questModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeModal(event) {
  if (!event || event.target.id === 'questModal') {
    document.getElementById('questModal').classList.remove('show');
    document.body.style.overflow = '';
  }
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal();
  }
});

// Timer de reset quotidien
function updateDailyTimer() {
  const timerElement = document.getElementById('dailyTimer');
  if (!timerElement) return;
  
  const now = new Date();
  const tomorrow = new Date(now);
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(0, 0, 0, 0);
  
  const diff = tomorrow - now;
  
  if (diff <= 0) {
    timerElement.textContent = '00:00:00';
    // Recharger la page pour obtenir les nouvelles quêtes
    setTimeout(() => window.location.reload(), 1000);
    return;
  }
  
  const hours = Math.floor(diff / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);
  
  const formatted = 
    String(hours).padStart(2, '0') + ':' +
    String(minutes).padStart(2, '0') + ':' +
    String(seconds).padStart(2, '0');
  
  timerElement.textContent = formatted;
}

// Mettre à jour le timer toutes les secondes si on est sur l'onglet quotidien
if (document.getElementById('dailyTimer')) {
  updateDailyTimer();
  setInterval(updateDailyTimer, 1000);
}
</script>

</body>
</html>
