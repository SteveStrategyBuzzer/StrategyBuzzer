<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Boutique — StrategyBuzzer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <!-- Version: {{ time() }} -->
  <style>
:root{
  --gap:14px; --radius:18px; --shadow:0 10px 24px rgba(0,0,0,.12);
  --bg:#0b1020; --card:#111735; --ink:#ecf0ff; --muted:#9fb6ff;
  --blue:#2c4bff; --ok:#22c55e; --danger:#ef4444; --line:rgba(255,255,255,.08);
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
.tab{ appearance:none; border:none; cursor:pointer; border-radius:999px; padding:10px 14px; background:#121c3f; color:#cfe1ff; border:1px solid var(--line); text-decoration:none; }
.tab.active{ background:var(--blue); color:#fff; }

.hero{ background:linear-gradient(135deg,#15224c,#0f1836); border:1px solid var(--line); padding:16px; border-radius:20px; box-shadow:var(--shadow); margin-bottom:18px; }

.grid{ display:grid; gap:var(--gap); }
.cols-4{ grid-template-columns:repeat(4,minmax(0,1fr)); }
.cols-3{ grid-template-columns:repeat(3,minmax(0,1fr)); }
.cols-2{ grid-template-columns:repeat(2,minmax(0,1fr)); }
@media (max-width:960px){ .cols-4{ grid-template-columns:repeat(3,1fr);} }
@media (max-width:760px){ .cols-4,.cols-3{ grid-template-columns:repeat(2,1fr);} }

.card{
  background:var(--card);
  border:1px solid var(--line);
  border-radius:16px;
  overflow:hidden;
  box-shadow:var(--shadow);
  position: relative;
}
.card .head{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border-bottom:1px solid var(--line); }
.card .title{ font-weight:800 }
.badge{ font-size:.85rem; background:rgba(255,255,255,.08); padding:6px 10px; border-radius:999px; }

.thumb{ position:relative; overflow:hidden; border-top:1px solid var(--line); }
.thumb img{ width:100%; height:100%; object-fit:cover; object-position:top; display:block; image-rendering:-webkit-optimize-contrast; }

.meta{ padding:10px 12px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.actions{ display:flex; gap:8px; padding:12px; border-top:1px solid var(--line); justify-content:center; background:rgba(0,0,0,.22); }
.actions .btn.ghost{ position:relative; z-index:2; }

.btn{ appearance:none; border:none; cursor:pointer; border-radius:12px; padding:10px 12px; background:var(--blue); color:#fff; font-weight:800; }
.btn.ghost{ background:transparent; border:1px solid rgba(255,255,255,.25); color:#cfe1ff; }
.btn[disabled]{ opacity:.65; cursor:not-allowed }
.btn.success{ background:var(--ok); color:#fff; }
.btn.danger{  background:var(--danger); color:#fff; }

.audio{ padding:12px; display:grid; gap:10px; }
audio{ width:100% }

.row{ display:flex; align-items:center; gap:10px; flex-wrap:wrap }
.price{ font-weight:800 }
.ok{ color:var(--ok) }
.muted{ color:#bcd1ff }

.note{ margin:10px 0; padding:10px 12px; border-radius:10px; background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.35) }
.warn{ margin:10px 0; padding:10px 12px; border-radius:10px; background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.35) }

.qty{ display:flex; align-items:center; gap:8px }
.qty input{ width:90px; padding:8px 10px; border-radius:10px; border:1px solid var(--line); background:#0f1530; color:#fff }

.tier{ position:absolute; top:8px; left:8px; padding:4px 8px; border-radius:999px; font-size:.78rem; border:1px solid rgba(255,255,255,.22) }
.t-rare{ background:#1e3a8a } .t-epic{ background:#6d28d9 } .t-legend{ background:#b45309 }

.avatar-row {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 0;
}
.avatar-row .thumb img {
  width: auto;
  height: 240px;
  object-fit: cover;
  object-position: top center;
  border-radius: 12px;
  border: 1px solid var(--line);
}

.avatar-actions {
  position: absolute;
  top: 12px;
  right: 12px;
}
.avatar-actions .btn.lock-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  font-weight: 700;
  padding: 0;
}

.view-btn{ padding:12px; border-top:1px solid var(--line); text-align:center; background:rgba(0,0,0,.12); }

.details{ display:none; padding:12px; border-top:1px solid var(--line); background:rgba(255,255,255,.03); }
.details.open{ display:block; animation:fadeIn .2s ease-out; }
.details .big{ width:100%; max-height:360px; object-fit:contain; display:block; margin:6px 0 12px; border-radius:12px; border:1px solid var(--line); background:#0f1530; }
.skills{ display:flex; flex-direction:column; gap:6px; font-size:.95rem; color:#cfe1ff; }
.details .close{ margin-top:8px; }

@keyframes fadeIn { from { opacity:.6 } to { opacity:1 } }
  </style>
</head>
<body>
<div class="wrap">

  @php
    $coins = (int)($coins ?? (session('coins') ?? 0));
    $avatarUrl   = app('router')->has('avatar')            ? route('avatar')            : url('/avatar');
    $purchaseUrl = app('router')->has('boutique.purchase') ? route('boutique.purchase') : url('/boutique/purchase');

    if (!function_exists('_assetv')) {
      function _assetv($rel) {
        $ts = @filemtime(public_path($rel));
        return asset($rel).'?v='.($ts ?: time());
      }
    }

    $pricing = $pricing ?? [];

    $tab = request('tab');
    if (!$tab) {
      if (request()->has('stratégique')) $tab = 'stratégiques';
      elseif (request()->has('item'))     $tab = 'packs';
      else                                $tab = 'packs';
    }

    function _scan_files($dir, $patterns=array('*')) {
      $out=array(); if(!is_dir($dir)) return $out;
      foreach($patterns as $pat){ foreach(glob(rtrim($dir,'/').'/'.$pat, GLOB_BRACE) as $f){ $out[]=$f; } }
      natsort($out);
      return array_values($out);
    }
    function _rel_public($abs){
      $pub = public_path();
      if ($abs && substr($abs, 0, strlen($pub)) === $pub) {
        return ltrim(substr($abs, strlen($pub)), DIRECTORY_SEPARATOR);
      }
      return $abs;
    }

    $packSlugs = ['portraits','cartoon','animal','mythique','paysage','objet','clown','musicien','automobile'];
    $packs = [];
    foreach($packSlugs as $slug){
      $abs = public_path("images/avatars/$slug");
      $files = _scan_files($abs, ['*.png','*.jpg','*.jpeg','*.webp']);
      $rel = array_map(function($f){ return _rel_public($f); }, $files);
      $packs[] = [
        'slug'   => $slug,
        'label'  => ucfirst($slug),
        'images' => $rel,
        'count'  => count($rel),
        'price'  => $pricing['pack'][$slug] ?? 300,
      ];
    }

    $buzzDir  = is_dir(public_path('audio/buzzers')) ? public_path('audio/buzzers') : public_path('sounds/buzzers');
    $audios   = _scan_files($buzzDir, ['*.mp3','*.ogg','*.wav']);
    $buzzers  = [];
    foreach ($audios as $a) {
      $rel  = _rel_public($a);
      $slug = pathinfo($rel, PATHINFO_FILENAME);
      $buzzers[] = [
        'slug'  => $slug,
        'path'  => $rel,
        'label' => ucfirst(str_replace(['-','_'], ' ', $slug)),
        'price' => $pricing['buzzer'][$slug] ?? 80,
      ];
    }

    $stratégiques = $catalog['stratégiques']['items'] ?? [];
    foreach ($stratégiques as $slug => &$a) {
      if (!isset($a['label'])) $a['label'] = $a['name'] ?? ucfirst(str_replace('-', ' ', $slug));
      if (isset($pricing['stratégique'][$slug])) $a['price'] = $pricing['stratégique'][$slug];
    }
    unset($a);

    $unlocked = $unlocked ?? [];
  @endphp

  <div class="topbar">
    <div class="pill">💰 Pièces : <b>{{ number_format($coins) }}</b></div>
    <div class="row">
      <a class="pill clean" href="{{ $avatarUrl }}">← Retour Avatars</a>
    </div>
  </div>

  @if(session('success')) <div class="note">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="warn">{{ session('error') }}</div> @endif

  <div class="tabs" role="tablist">
    <a class="tab {{ $tab==='packs'?'active':'' }}"    href="#packs"    onclick="setTab('packs'); return false;">🎨 Packs d'avatars</a>
    <a class="tab {{ $tab==='buzzers'?'active':'' }}"  href="#buzzers"  onclick="setTab('buzzers'); return false;">🎵 Buzzers d'ambiance</a>
    <a class="tab {{ $tab==='stratégiques'?'active':'' }}"  href="#stratégiques"  onclick="setTab('stratégiques'); return false;">🛡️ Avatars stratégiques</a>
    <a class="tab {{ $tab==='coins'?'active':'' }}"    href="#coins"    onclick="setTab('coins'); return false;">💎 Pièces d'Intelligence</a>
    <a class="tab {{ $tab==='vies'?'active':'' }}"     href="#vies"     onclick="setTab('vies'); return false;">❤️ Vies</a>
  </div>

  <!-- ====== Packs d'avatars ====== -->
  <section id="packs" style="display: {{ $tab==='packs'?'block':'none' }}">
    <div class="hero"><b>Packs d'avatars</b> — Prévisualisez tout le contenu des packs avant d'acheter.</div>

    <div class="grid cols-4">
      @foreach($packs as $p)
        @php 
          $isUnlockedPack = in_array($p['slug'], $unlocked, true);
          $previewImages = array_slice($p['images'], 0, 4);
        @endphp
        <div class="card" id="pack-{{ $p['slug'] }}">
          <div class="head">
            <div class="title">{{ $p['label'] }}</div>
            @unless($isUnlockedPack)
              <div class="price">💰 {{ $p['price'] }}</div>
            @endunless
          </div>

          <div class="avatar-row">
            <div class="thumb" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:12px;">
              <span class="tier t-rare" style="position:absolute;top:8px;left:8px;">{{ $p['count'] }} images</span>
              @forelse($previewImages as $img)
                <img src="{{ _assetv($img) }}" alt="{{ $p['label'] }}" loading="lazy" decoding="async" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
              @empty
                <div style="padding:40px 14px;color:#cbd5e1;text-align:center;font-size:12px;grid-column:span 2;">Aucune image</div>
              @endforelse

              <div class="avatar-actions">
                @if($isUnlockedPack)
                  <button class="btn success lock-btn" type="button" disabled title="Débloqué">🔓</button>
                @else
                  <form method="POST" action="{{ $purchaseUrl }}">
                    @csrf
                    <input type="hidden" name="kind" value="pack">
                    <input type="hidden" name="target" value="{{ $p['slug'] }}">
                    <button class="btn danger lock-btn" type="submit" title="Débloquer">🔒</button>
                  </form>
                @endif
              </div>
            </div>
          </div>

          <div class="view-btn">
            <button class="btn ghost" type="button" onclick="openPack('{{ $p['slug'] }}','{{ $p['label'] }}')">
              Voir le pack
            </button>
          </div>
        </div>
      @endforeach
    </div>

    @foreach($packs as $p)
      <template data-pack="{{ $p['slug'] }}">{!! json_encode($p['images'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</template>
    @endforeach
  </section>

  <!-- ====== Buzzers d'ambiance ====== -->
  <section id="buzzers" style="display: {{ $tab==='buzzers'?'block':'none' }}">
    <div class="hero"><b>Buzzers & musiques d'ambiance</b> — Écoute avant d'acheter.</div>
    <div class="grid cols-3">
      @forelse($buzzers as $bz)
        @php $isUnlockedBz = in_array($bz['slug'], $unlocked, true); @endphp
        <div class="card" id="buzzer-{{ $bz['slug'] }}">
          <div class="head">
            <div class="title">{{ $bz['label'] }}</div>
            <div class="badge">Audio</div>
          </div>
          <div class="audio">
            <audio controls preload="none">
              <source src="{{ asset($bz['path']) }}" type="audio/{{ pathinfo($bz['path'], PATHINFO_EXTENSION) }}">
              Votre navigateur ne supporte pas l'audio HTML5.
            </audio>
          </div>

          @if($isUnlockedBz)
            <div class="actions">
              <button class="btn success" disabled>Disponible</button>
            </div>
          @else
            <form method="POST" action="{{ $purchaseUrl }}" class="actions">
              @csrf
              <input type="hidden" name="kind" value="buzzer">
              <input type="hidden" name="target" value="{{ $bz['slug'] }}">
              <span class="price">💰 {{ $bz['price'] }}</span>
              <button class="btn danger" type="submit">Acheter</button>
            </form>
          @endif
        </div>
      @empty
        <div class="card"><div class="head"><div class="title">Aucun fichier audio trouvé</div></div>
          <div style="padding:14px;color:#cbd5e1">
            Place des fichiers dans <code>public/audio/buzzers/</code> (mp3/ogg/wav) ou <code>public/sounds/buzzers/</code>.
          </div>
        </div>
      @endforelse
    </div>
  </section>

  <!-- ====== Avatars stratégiques ====== --> 
  <section id="stratégiques" style="display: {{ $tab==='stratégiques'?'block':'none' }}">
    <div class="hero"><b>Avatars stratégiques</b> — Rareté et capacités spéciales. Tout est visible ici (pas de flou).</div>

    <div class="grid cols-4">
      @foreach($stratégiques as $a)
        @php
          $t = $a['tier'];
          $tClass = $t==='Légendaire' ? 't-legend' : ($t==='Épique' ? 't-epic' : 't-rare');
          $isUnlockedStrategic = in_array($a['slug'], $unlocked, true);
          $slug = $a['slug'];
        @endphp
        <div class="card" id="stratégique-{{ $slug }}">
          <div class="head">
            <div class="title" style="text-transform:capitalize">{{ $a['label'] }}</div>
            @unless($isUnlockedStrategic)
              <div class="price">💰 {{ $a['price'] }}</div>
            @endunless
          </div>

          <div class="avatar-row">
            <div class="thumb">
              <span class="tier {{ $tClass }}">{{ $t }}</span>
              <img src="{{ _assetv($a['path']) }}" alt="{{ $a['label'] }}" loading="lazy" decoding="async">

              <div class="avatar-actions">
                @if($isUnlockedStrategic)
                  <button class="btn success lock-btn" type="button" disabled title="Débloqué">🔓</button>
                @else
                  <form method="POST" action="{{ $purchaseUrl }}">
                    @csrf
                    <input type="hidden" name="kind" value="stratégique">
                    <input type="hidden" name="target" value="{{ $slug }}">
                    <button class="btn danger lock-btn" type="submit" title="Débloquer">🔒</button>
                  </form>
                @endif
              </div>
            </div>
          </div>

          <div class="view-btn">
            <button class="btn ghost" type="button"
                    aria-expanded="false"
                    aria-controls="details-{{ $slug }}"
                    onclick="toggleDetails('{{ $slug }}')">
              Voir Avatar
            </button>
          </div>

          <div class="details" id="details-{{ $slug }}" aria-hidden="true">
            <img class="big" src="{{ _assetv($a['path']) }}" alt="Aperçu {{ $a['label'] }}">
            @if(!empty($a['skills']) && is_array($a['skills']))
              <div class="skills" role="list">
                @foreach($a['skills'] as $sk)
                  <div role="listitem">• {{ $sk }}</div>
                @endforeach
              </div>
            @endif
            <button class="btn ghost close" type="button" onclick="toggleDetails('{{ $slug }}')">Fermer</button>
          </div>
        </div>
      @endforeach
    </div>
  </section>

  <!-- ====== Pièces d'Intelligence (Stripe) ====== -->
  <section id="coins" style="display: {{ $tab==='coins'?'block':'none' }}">
    <div class="hero"><b>Pièces d'intelligence</b> — Achetez des pièces avec de la vraie monnaie pour débloquer des contenus exclusifs.</div>

    <div class="grid cols-3">
      @foreach($coinPacks ?? [] as $pack)
        <div class="card">
          <div class="head">
            <div class="title">{{ $pack['name'] }}</div>
            @if($pack['popular'] ?? false)
              <div class="badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff">⭐ Populaire</div>
            @endif
          </div>

          <div style="padding:24px;text-align:center">
            <div style="font-size:3rem;font-weight:900;color:#fbbf24;text-shadow:0 2px 8px rgba(251,191,36,0.3)">
              {{ number_format($pack['coins']) }}
            </div>
            <div style="color:#cbd5e1;margin-top:8px;font-size:0.95rem">pièces d'intelligence</div>
            
            <div style="margin-top:24px;font-size:1.8rem;font-weight:800;color:#fff">
              ${{ number_format($pack['amount_cents'] / 100, 2) }}
            </div>
            <div style="color:#94a3b8;font-size:0.85rem;margin-top:4px">
              {{ number_format($pack['coins'] / ($pack['amount_cents'] / 100), 1) }} pièces par dollar
            </div>
          </div>

          <div class="actions">
            <form method="POST" action="{{ route('coins.checkout') }}" style="width:100%">
              @csrf
              <input type="hidden" name="product_key" value="{{ $pack['key'] }}">
              <button class="btn" type="submit" style="width:100%;background:linear-gradient(135deg,#6366f1,#8b5cf6);font-size:1.05rem">
                💳 Acheter
              </button>
            </form>
          </div>
        </div>
      @endforeach
    </div>

    <div class="note" style="margin-top:24px">
      <b>💡 Paiement sécurisé via Stripe</b><br>
      Vos pièces seront automatiquement ajoutées à votre compte après le paiement. 
      Vous pourrez ensuite les utiliser pour acheter des packs d'avatars, des buzzers, des avatars stratégiques et plus encore !
    </div>
  </section>

  <!-- ====== Achat de vies ====== -->
  <section id="vies" style="display: {{ $tab==='vies'?'block':'none' }}">
    <div class="hero">
      <b>Vies supplémentaires</b> — Achetez des vies pour continuer vos parties.
      <div class="row" style="margin-top:8px">
        <span class="muted">Prix par vie :</span> <span class="price">💰 120</span>
      </div>
    </div>

    <div class="grid cols-2">
      <div class="card">
        <div class="head"><div class="title">Acheter des vies</div></div>
        <form method="POST" action="{{ $purchaseUrl }}" style="padding:12px 14px">
          @csrf
          <input type="hidden" name="kind" value="life">
          <div class="row" style="justify-content:space-between">
            <label class="qty">Quantité
              <input type="number" name="quantity" value="1" min="1" step="1" required>
            </label>
            <button class="btn" type="submit">Acheter</button>
          </div>
          <div class="muted" style="margin-top:8px">Le débit total sera calculé au moment de l'achat (quantité × prix unitaire).</div>
        </form>
      </div>

      <div class="card">
        <div class="head"><div class="title">Packs de vies rapides</div></div>
        <div class="actions" style="flex-wrap:wrap">
          <form method="POST" action="{{ $purchaseUrl }}">
            @csrf <input type="hidden" name="kind" value="life"><input type="hidden" name="quantity" value="3">
            <button class="btn" type="submit">+3 vies</button>
          </form>
          <form method="POST" action="{{ $purchaseUrl }}">
            @csrf <input type="hidden" name="kind" value="life"><input type="hidden" name="quantity" value="5">
            <button class="btn" type="submit">+5 vies</button>
          </form>
          <form method="POST" action="{{ $purchaseUrl }}">
            @csrf <input type="hidden" name="kind" value="life"><input type="hidden" name="quantity" value="10">
            <button class="btn" type="submit">+10 vies</button>
          </form>
        </div>
      </div>
    </div>
  </section>

</div>

<!-- ====== Modale de prévisualisation ====== -->
<div id="modal" class="modal" role="dialog" aria-modal="true" style="display:none;
     position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.65);align-items:center;justify-content:center">
  <div class="card" style="width:min(980px,92vw);max-height:86vh;overflow:auto;
       border-radius:20px;background:#0b1020;border:1px solid rgba(255,255,255,.1)">
    <div style="display:flex;align-items:center;justify-content:space-between;
         padding:16px 18px;position:sticky;top:0;background:#0b1020;
         border-bottom:1px solid rgba(255,255,255,.06)">
      <div class="title" id="modalTitle" style="font-size:1.1rem">Pack</div>
      <button class="btn ghost" onclick="closeModal()">Fermer</button>
    </div>
    <div style="padding:18px">
      <div id="thumbs" class="grid cols-4" style="gap:12px"></div>
    </div>
  </div>
</div>

<script>
  function setTab(id){
    for (const sec of ['packs','buzzers','stratégiques','coins','vies']) {
      const el = document.getElementById(sec);
      if(el) el.style.display = (sec===id ? 'block' : 'none');
    }
    document.querySelectorAll('.tabs .tab').forEach(function(t){ t.classList.remove('active'); });
    const map = {packs:0,buzzers:1,stratégiques:2,coins:3,vies:4};
    const idx = map.hasOwnProperty(id) ? map[id] : 0;
    const tab = document.querySelectorAll('.tabs .tab')[idx];
    if(tab) tab.classList.add('active');
    if(history.pushState){ history.pushState(null,'', '#'+id); }
  }

  (function initTab(){
    const hash = location.hash.slice(1);
    const pack = new URLSearchParams(location.search).get('item');
    const aid  = new URLSearchParams(location.search).get('stratégique');
    if (pack) { setTab('packs'); const el=document.getElementById('pack-'+pack); if(el) el.scrollIntoView({behavior:'smooth',block:'start'}); }
    else if (aid) { setTab('stratégiques'); const el=document.getElementById('stratégique-'+aid); if(el) el.scrollIntoView({behavior:'smooth',block:'start'}); }
    else if (hash && ['packs','buzzers','stratégiques','coins','vies'].indexOf(hash)!==-1) setTab(hash);
  })();

  function toggleDetails(slug){
    const d = document.getElementById('details-'+slug);
    if (!d) return;
    if (d.classList.contains('open')) {
      d.classList.remove('open');
      d.setAttribute('aria-hidden','true');
      const btn = d.previousElementSibling.querySelector('button');
      if(btn) btn.setAttribute('aria-expanded','false');
    } else {
      d.classList.add('open');
      d.setAttribute('aria-hidden','false');
      const btn = d.previousElementSibling.querySelector('button');
      if(btn) btn.setAttribute('aria-expanded','true');
    }
  }

  function openPack(slug, label){
    const tpl = document.querySelector('template[data-pack="'+slug+'"]');
    if (!tpl) {
      console.log('Template non trouvé pour:', slug);
      return;
    }
    let imgs = [];
    try { 
      imgs = JSON.parse(tpl.textContent.trim()); 
      console.log('Images trouvées:', imgs);
    } catch(e){ 
      console.error('Erreur parsing JSON:', e);
      return;
    }
    const modal = document.getElementById('modal');
    const title = document.getElementById('modalTitle');
    const thumbs= document.getElementById('thumbs');
    title.textContent = 'Pack: '+label;
    thumbs.innerHTML='';
    
    const baseUrl = window.location.origin + '/';
    
    imgs.forEach(function(p){
      const wrap = document.createElement('div');
      wrap.style='aspect-ratio:1;overflow:hidden;border-radius:10px;border:1px solid rgba(255,255,255,.1)';
      const img = document.createElement('img');
      img.src = baseUrl + p + '?v=' + (Date.now() % 100000);
      img.alt = 'avatar';
      img.loading='lazy';
      img.decoding='async';
      img.style='width:100%;height:100%;object-fit:cover;object-position:top;display:block';
      wrap.appendChild(img);
      thumbs.appendChild(wrap);
    });
    modal.style.display='flex';
    console.log('Modal ouvert avec', imgs.length, 'images');
  }

  function closeModal(){
    const m=document.getElementById('modal');
    if(m) m.style.display='none';
  }
</script>
</body>
</html>
