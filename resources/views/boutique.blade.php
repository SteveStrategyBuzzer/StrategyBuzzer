<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Boutique — StrategyBuzzer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
:root{
  --gap:14px; --radius:18px; --shadow:0 10px 24px rgba(0,0,0,.12);
  --bg:#0b1020; --card:#111735; --ink:#ecf0ff; --muted:#9fb6ff;
  --blue:#2c4bff; --ok:#22c55e; --danger:#ef4444; --line:rgba(255,255,255,.08);
}
*{box-sizing:border-box}
body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto; background:var(--bg); color:var(--ink); }
.wrap{ max-width:1200px; margin:0 auto; padding:20px 16px 80px; }

.topbar{ display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap }
.pill{ background:linear-gradient(135deg,#1a2344,#15224c); border:1px solid var(--line); padding:10px 14px; border-radius:999px; box-shadow:var(--shadow); display:flex; align-items:center; gap:10px; }
.pill b{color:#fff}
a.clean{ color:var(--muted); text-decoration:none; }

.tabs{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px }
.tab{ appearance:none; border:none; cursor:pointer; border-radius:999px; padding:10px 14px; background:#121c3f; color:#cfe1ff; border:1px solid var(--line); }
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
  position: relative; /* 🔑 nécessaire pour contenir le cadenas */
}
.card .head{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border-bottom:1px solid var(--line); }
.card .title{ font-weight:800 }
.badge{ font-size:.85rem; background:rgba(255,255,255,.08); padding:6px 10px; border-radius:999px; }

/* thumbs & previews */
.thumb{ position:relative; overflow:hidden; border-top:1px solid var(--line); }
.thumb img{ width:100%; height:100%; object-fit:cover; object-position:top; display:block; image-rendering:-webkit-optimize-contrast; }

.pack-preview{ padding:12px }
.preview-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:8px }
.preview-grid img{ width:100%; height:96px; object-fit:cover; object-position:top; border-radius:10px; border:1px solid var(--line) }
.preview-main{ width:100%; height:210px; object-fit:cover; object-position:top; border-radius:12px; border:1px solid var(--line) }

.meta{ padding:10px 12px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.actions{ display:flex; gap:8px; padding:12px; border-top:1px solid var(--line); justify-content:center; background:rgba(0,0,0,.22); }

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

/* pastilles tiers (avatars stratégiques) */
.tier{ position:absolute; top:8px; left:8px; padding:4px 8px; border-radius:999px; font-size:.78rem; border:1px solid rgba(255,255,255,.22) }
.t-rare{ background:#1e3a8a } .t-epic{ background:#6d28d9 } .t-legend{ background:#b45309 }

/* === Disposition spécifique "Avatars stratégiques" === */
.avatar-row {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 0;
}
.avatar-row .thumb img {
  width: auto;
  height: 240px; /* ajuste selon la maquette */
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

/* Détails (image grande + skills) */
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
    // ==== Helpers robustes ====
    $coins = (int)($coins ?? (session('coins') ?? 0));
    $avatarUrl   = app('router')->has('avatar')            ? route('avatar')            : url('/avatar');
    $purchaseUrl = app('router')->has('boutique.purchase') ? route('boutique.purchase') : url('/boutique/purchase');

    // cache-busting pour les images
    if (!function_exists('_assetv')) {
      function _assetv($rel) {
        $ts = @filemtime(public_path($rel));
        return asset($rel).'?v='.($ts ?: time());
      }
    }

    // table de prix éventuellement fournie par le contrôleur
    $pricing = $pricing ?? [];

    // Détecte l’onglet par défaut
    $tab = request('tab');
    if (!$tab) {
      if (request()->has('stratégique')) $tab = 'stratégiques';
      elseif (request()->has('item'))     $tab = 'packs';
      else                                $tab = 'packs';
    }

    // Scanner fichiers (pour aperçu des packs)
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

    // ==== Packs d’avatars ====
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

    // ==== Buzzers ambiance (audio) ====
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

    // ==== Avatars stratégiques (depuis le service) ====
    $stratégiques = $catalog['stratégiques']['items'] ?? [];
    foreach ($stratégiques as $slug => &$a) {
      if (!isset($a['label'])) $a['label'] = $a['name'] ?? ucfirst(str_replace('-', ' ', $slug));
      if (isset($pricing['stratégique'][$slug])) $a['price'] = $pricing['stratégique'][$slug];
    }
    unset($a);

    // raccourcis
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
    <a class="tab {{ $tab==='packs'?'active':'' }}"    href="#packs"    onclick="setTab('packs')">🎨 Packs d’avatars</a>
    <a class="tab {{ $tab==='buzzers'?'active':'' }}"  href="#buzzers"  onclick="setTab('buzzers')">🎵 Buzzers d’ambiance</a>
    <a class="tab {{ $tab==='stratégiques'?'active':'' }}"  href="#stratégiques"  onclick="setTab('stratégiques')">🛡️ Avatars stratégiques</a>
    <a class="tab {{ $tab==='vies'?'active':'' }}"     href="#vies"     onclick="setTab('vies')">❤️ Vies</a>
  </div>

  <!-- ====== Packs d’avatars ====== -->
  <section id="packs" style="display: {{ $tab==='packs'?'block':'none' }}">
    <div class="hero"><b>Packs d’avatars</b> — Prévisualisez tout le contenu des packs avant d’acheter.</div>

    <div class="grid cols-3">
      @foreach($packs as $p)
        @php $isUnlockedPack = in_array($p['slug'], $unlocked, true); @endphp
        <div class="card" id="pack-{{ $p['slug'] }}">
          <div class="head">
            <div class="title">{{ $p['label'] }}</div>
            <div class="badge">{{ $p['count'] }} images</div>
          </div>

          <div class="pack-preview">
            @php
              $grid = array_slice($p['images'], 0, 4);
              $hero = $p['images'][0] ?? null;
            @endphp
            @if($grid)
              <div class="preview-grid" aria-label="Aperçu">
                @foreach($grid as $g)
                  <img src="{{ _assetv($g) }}" alt="vignette pack" loading="lazy" decoding="async">
                @endforeach
              </div>
            @elseif($hero)
              <img class="preview-main" src="{{ _assetv($hero) }}" alt="aperçu" loading="lazy" decoding="async">
            @else
              <div style="padding:14px;color:#cbd5e1">Aucune image trouvée dans <code>public/images/avatars/{{ $p['slug'] }}</code></div>
            @endif
          </div>

          @if($isUnlockedPack)
            <div class="actions">
              <button class="btn success" disabled>Actif</button>
              <button class="btn ghost" type="button" onclick="openPack('{{ $p['slug'] }}','{{ $p['label'] }}')">Voir le pack</button>
            </div>
          @else
            <form method="POST" action="{{ $purchaseUrl }}" class="actions">
              @csrf
              <input type="hidden" name="kind" value="pack">
              <input type="hidden" name="target" value="{{ $p['slug'] }}">
              <span class="price">💰 {{ $p['price'] }}</span>
              <button class="btn danger" type="submit">Acheter le pack</button>
            </form>
            <div class="actions" style="border-top:none;padding-top:0">
              <button class="btn ghost" type="button" onclick="alert('BOUTON CLIQUÉ: {{ $p['slug'] }}'); openPack('{{ $p['slug'] }}','{{ $p['label'] }}'); return false;">Voir Avatars</button>
            </div>
          @endif
        </div>
      @endforeach
    </div>

    <!-- Templates de données pour les packs -->
    @foreach($packs as $p)
      <template data-pack="{{ $p['slug'] }}">{!! json_encode($p['images']) !!}</template>
    @endforeach
  </section>

  <!-- ====== Buzzers d’ambiance ====== -->
  <section id="buzzers" style="display: {{ $tab==='buzzers'?'block':'none' }}">
    <div class="hero"><b>Buzzers & musiques d’ambiance</b> — Écoute avant d’acheter.</div>
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
              Votre navigateur ne supporte pas l’audio HTML5.
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
        <!-- Titre + Prix -->
        <div class="head">
          <div class="title" style="text-transform:capitalize">{{ $a['label'] }}</div>
          @unless($isUnlockedStrategic)
            <div class="price">💰 {{ $a['price'] }}</div>
          @endunless
        </div>

        <!-- Vignette avatar + cadenas -->
        <div class="avatar-row">
          <div class="thumb">
            <span class="tier {{ $tClass }}">{{ $t }}</span>
            <img src="{{ _assetv($a['path']) }}" alt="{{ $a['label'] }}" loading="lazy" decoding="async">

            <div class="avatar-actions">
              @if($isUnlockedStrategic)
                <!-- Bulle verte avec cadenas ouvert -->
                <button class="btn success lock-btn" type="button" disabled title="Débloqué">🔓</button>
              @else
                <!-- Bulle rouge avec cadenas fermé -->
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

        <!-- Bouton Voir Avatar -->
        <div class="view-btn">
          <button class="btn ghost" type="button"
                  aria-expanded="false"
                  aria-controls="details-{{ $slug }}"
                  onclick="toggleDetails('{{ $slug }}')">
            Voir Avatar
          </button>
        </div>

        <!-- Détails -->
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
          <div class="muted" style="margin-top:8px">Le débit total sera calculé au moment de l’achat (quantité × prix unitaire).</div>
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
  // Active l’onglet (affichage côté client)
  function setTab(id){
    for (const sec of ['packs','buzzers','stratégiques','vies']) {
      const el = document.getElementById(sec);
      if(el) el.style.display = (sec===id ? 'block' : 'none');
    }
    document.querySelectorAll('.tabs .tab').forEach(t => t.classList.remove('active'));
    const idx = {packs:0,buzzers:1,stratégiques:2,vies:3}[id] ?? 0;
    const tab = document.querySelectorAll('.tabs .tab')[idx];
    if(tab) tab.classList.add('active');
    if(history.pushState){ history.pushState(null,'', '#'+id); }
  }
  // Ouvre l’onglet correct selon la query (?item= / ?stratégique=) ou hash
  (function initTab(){
    const hash = (location.hash||'').replace('#','');
    if(hash && ['packs','buzzers','stratégiques','vies'].includes(hash)) setTab(hash);
    const pack = new URLSearchParams(location.search).get('item');
    const aid  = new URLSearchParams(location.search).get('stratégique');
    if (pack) { setTab('packs'); const el=document.getElementById('pack-'+pack); if(el) el.scrollIntoView({behavior:'smooth',block:'start'}); }
    if (aid)  { setTab('stratégiques'); const el=document.getElementById('stratégique-'+aid); if(el) el.scrollIntoView({behavior:'smooth',block:'start'}); }
  })();

  // Ouvre/ferme le panneau "Voir Avatar" dans la boutique (sans quitter la page)
  function toggleDetails(slug){
    const id = 'details-' + slug;
    const box = document.getElementById(id);
    if(!box) return;
    const opened = box.classList.contains('open');
    // refermer les autres
    document.querySelectorAll('.details.open').forEach(d => {
      d.classList.remove('open');
      d.setAttribute('aria-hidden','true');
    });
    // toggle courant
    if(!opened){
      box.classList.add('open');
      box.setAttribute('aria-hidden','false');
    } else {
      box.classList.remove('open');
      box.setAttribute('aria-hidden','true');
    }
    // aria-expanded du bouton
    const btn = document.querySelector('button[aria-controls="'+id+'"]');
    if(btn) btn.setAttribute('aria-expanded', (!opened).toString());
    // scroll doux vers la carte
    const card = document.getElementById('stratégique-'+slug);
    if(card) card.scrollIntoView({behavior:'smooth', block:'start'});
  }
  // Version Boutique : aperçu du pack avec possibilité de sélection si débloqué
  function openModalPreview(title, images, isUnlocked = false){
    document.getElementById('modalTitle').textContent = title;
    const thumbs = document.getElementById('thumbs');
    thumbs.innerHTML = '';
    images.forEach((p) => {
      const imgUrl = assetPath(p);
      const el = document.createElement('div');
      el.className = 'thumb';
      el.style.position = 'relative';
      
      let content = `<img src="${imgUrl}" alt="avatar" 
                       onerror="this.src='${assetPath('images/avatars/default.png')}'">`;
      
      if (isUnlocked) {
        content += `<button class="btn" style="position:absolute;bottom:8px;left:8px;right:8px;padding:6px;font-size:0.85rem" 
                      onclick="selectAvatar('${p}')">Choisir</button>`;
      }
      
      el.innerHTML = content;
      thumbs.appendChild(el);
    });
    document.getElementById('modal').style.display = 'flex';
  }

  function openPack(slug, label){
    console.log('✅ openPack appelé avec:', slug, label);
    const tpl = document.querySelector(`template[data-pack="${slug}"]`);
    if(!tpl) {
      console.error('❌ Template non trouvé pour:', slug);
      alert('Template non trouvé pour: ' + slug);
      return;
    }
    try{
      const rawContent = tpl.content.textContent || tpl.innerHTML;
      const jsonStr = rawContent.trim();
      console.log('📄 JSON brut:', jsonStr);
      const images = JSON.parse(jsonStr);
      console.log('✅ Images parsées:', images);
      
      // Vérifier si le pack est débloqué
      const packCard = document.getElementById(`pack-${slug}`);
      const isUnlocked = packCard && packCard.querySelector('.btn.success[disabled]');
      console.log('🔓 Pack débloqué?', isUnlocked ? 'OUI' : 'NON');
      
      openModalPreview(label || 'Pack', images, !!isUnlocked);
    }catch(e){ 
      console.error('❌ Erreur dans openPack:', e); 
      alert('Erreur lors de l\'ouverture du pack: ' + e.message);
    }
  }

  // Fonction pour sélectionner un avatar
  function selectAvatar(imagePath) {
    fetch('/avatar/select', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({ path: imagePath })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        closeModal();
        alert('Avatar sélectionné avec succès !');
        // Optionnel : recharger la page pour voir le changement
        // window.location.reload();
      } else {
        alert('Erreur : ' + (data.message || 'Impossible de sélectionner cet avatar'));
      }
    })
    .catch(error => {
      console.error('Erreur:', error);
      alert('Erreur de connexion');
    });
  }
function closeModal(){
  document.getElementById('modal').style.display='none';
}

function assetPath(p){
  if(!p) return '';
  return (p.startsWith('http') ? p : (window.location.origin + '/' + p)).replace(/([^:]\/)\/+/g, "$1");
}
</script>
</body>
</html>
