<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ __('Boutique') }} — StrategyBuzzer</title>
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

/* === RESPONSIVE POUR ORIENTATION === */

/* Mobile Portrait (320px - 480px) */
@media (max-width: 480px) and (orientation: portrait) {
  .wrap{padding:16px 12px 60px}
  .topbar{flex-direction:column;align-items:stretch}
  .pill{padding:8px 12px;font-size:0.9rem}
  .tabs{gap:6px}
  .tab{padding:8px 12px;font-size:0.9rem}
  .cols-4,.cols-3,.cols-2{grid-template-columns:1fr}
  .avatar-row .thumb img{height:180px}
  .coin-icon--topbar{width:32px;height:32px}
}

/* Mobile Paysage (orientation horizontale) */
@media (max-height: 500px) and (orientation: landscape) {
  .wrap{padding:12px}
  .topbar{gap:8px;margin-bottom:10px}
  .pill{padding:6px 10px;font-size:0.85rem}
  .hero{padding:12px;margin-bottom:12px}
  .tabs{gap:6px;margin-bottom:10px}
  .tab{padding:6px 10px;font-size:0.85rem}
  .cols-4{grid-template-columns:repeat(4,1fr)}
  .cols-3{grid-template-columns:repeat(3,1fr)}
  .card{font-size:0.9rem}
  .avatar-row .thumb img{height:160px}
  .coin-icon--topbar{width:32px;height:32px}
  .details .big{max-height:200px}
}

/* Tablettes Portrait */
@media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
  .cols-4{grid-template-columns:repeat(2,1fr)}
  .cols-3{grid-template-columns:repeat(2,1fr)}
  .avatar-row .thumb img{height:220px}
}

/* Tablettes Paysage */
@media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
  .cols-4{grid-template-columns:repeat(3,1fr)}
  .cols-3{grid-template-columns:repeat(3,1fr)}
  .avatar-row .thumb img{height:200px}
}

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

/* Coin icon - perfectly round and crisp */
.coin-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  aspect-ratio: 1 / 1;
  border-radius: 50%;
  object-fit: cover;
  image-rendering: -webkit-optimize-contrast;
  image-rendering: crisp-edges;
  vertical-align: middle;
  flex-shrink: 0;
}
.coin-icon--topbar { width: 40px; height: 40px; }
.coin-icon--price { width: 32px; height: 32px; }
.coin-icon--tab { width: 28px; height: 28px; }

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
      else                                $tab = 'master';
    }

    if (!function_exists('_scan_files')) {
      function _scan_files($dir, $patterns=array('*')) {
        $out=array(); if(!is_dir($dir)) return $out;
        foreach($patterns as $pat){ foreach(glob(rtrim($dir,'/').'/'.$pat, GLOB_BRACE) as $f){ $out[]=$f; } }
        natsort($out);
        return array_values($out);
      }
    }
    if (!function_exists('_rel_public')) {
      function _rel_public($abs){
        $pub = public_path();
        if ($abs && substr($abs, 0, strlen($pub)) === $pub) {
          return ltrim(substr($abs, strlen($pub)), DIRECTORY_SEPARATOR);
        }
        return $abs;
      }
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
    <div class="pill"><img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--topbar" style="margin-right:6px;"> {{ __('Pièces') }} : <b>{{ number_format($coins) }}</b></div>
    <div class="row">
      @auth
        @if(request()->has('item') || request()->has('stratégique'))
          <a class="pill clean" href="{{ $avatarUrl }}">← {{ __('Avatars') }}</a>
        @endif
        <a href="{{ route('menu') }}" style="
          background: white;
          color: #0b1020;
          padding: 10px 18px;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 700;
          font-size: 0.95rem;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          gap: 6px;
        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(255,255,255,0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
          Menu
        </a>
      @endauth
    </div>
  </div>

  @if(session('success')) <div class="note">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="warn">{{ session('error') }}</div> @endif

  <div class="tabs" role="tablist">
    <a class="tab {{ $tab==='packs'?'active':'' }}"    href="#packs"    onclick="setTab('packs'); return false;">🎨 {{ __("Packs d'avatars") }}</a>
    <a class="tab {{ $tab==='musiques'?'active':'' }}"  href="#musiques"  onclick="setTab('musiques'); return false;">🎵 {{ __("Musiques d'Ambiance") }}</a>
    <a class="tab {{ $tab==='buzzers'?'active':'' }}"  href="#buzzers"  onclick="setTab('buzzers'); return false;">🔊 {{ __('Sons de Buzzers') }}</a>
    <a class="tab {{ $tab==='stratégiques'?'active':'' }}"  href="#stratégiques"  onclick="setTab('stratégiques'); return false;">🛡️ {{ __('Avatars Stratégiques') }}</a>
    <a class="tab {{ $tab==='master'?'active':'' }}"   href="#master"   onclick="setTab('master'); return false;">🎮 {{ __('Maître du Jeu') }}</a>
    <a class="tab {{ $tab==='coins'?'active':'' }}"    href="#coins"    onclick="setTab('coins'); return false;"><img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--tab" style="margin-right:4px;"> {{ __("Pièces d'Intelligence") }}</a>
    <a class="tab {{ $tab==='vies'?'active':'' }}"     href="#vies"     onclick="setTab('vies'); return false;">❤️ {{ __('Vies') }}</a>
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
              <div class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price" style="margin-right:4px;">{{ $p['price'] }}</div>
            @endunless
          </div>

          <div class="avatar-row">
            <div class="thumb" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:12px;">
              <span class="tier t-rare" style="position:absolute;top:8px;left:8px;">{{ $p['count'] }} {{ __('images') }}</span>
              @forelse($previewImages as $img)
                <img src="{{ _assetv($img) }}" alt="{{ $p['label'] }}" loading="lazy" decoding="async" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
              @empty
                <div style="padding:40px 14px;color:#cbd5e1;text-align:center;font-size:12px;grid-column:span 2;">{{ __('Aucune image') }}</div>
              @endforelse

              <div class="avatar-actions">
                @if($isUnlockedPack)
                  <button class="btn success lock-btn" type="button" disabled title="{{ __('Débloqué') }}">🔓</button>
                @else
                  <form method="POST" action="{{ $purchaseUrl }}">
                    @csrf
                    <input type="hidden" name="kind" value="pack">
                    <input type="hidden" name="target" value="{{ $p['slug'] }}">
                    <button class="btn danger lock-btn" type="submit" title="{{ __('Débloquer') }}">🔒</button>
                  </form>
                @endif
              </div>
            </div>
          </div>

          <div class="view-btn">
            <button class="btn ghost" type="button" 
                    onclick='openPack({{ json_encode($p["images"], JSON_UNESCAPED_SLASHES) }},"{{ $p["label"] }}")'>
              Voir le pack
            </button>
          </div>
        </div>
      @endforeach
    </div>

  </section>

  <!-- ====== Musiques d'Ambiance ====== -->
  <section id="musiques" style="display: {{ $tab==='musiques'?'block':'none' }}">
    <div class="hero"><b>Musiques d'Ambiance</b> — Écoute avant d'acheter.</div>
    <div class="grid cols-3">
      @forelse($buzzers as $bz)
        @php $isUnlockedBz = in_array($bz['slug'], $unlocked, true); @endphp
        <div class="card" id="musique-{{ $bz['slug'] }}">
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
              <button class="btn success" disabled>{{ __('Disponible') }}</button>
            </div>
          @else
            <form method="POST" action="{{ $purchaseUrl }}" class="actions">
              @csrf
              <input type="hidden" name="kind" value="buzzer">
              <input type="hidden" name="target" value="{{ $bz['slug'] }}">
              <span class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price" style="margin-right:4px;">{{ $bz['price'] }}</span>
              <button class="btn danger" type="submit">{{ __('Acheter') }}</button>
            </form>
          @endif
        </div>
      @empty
        <div class="card"><div class="head"><div class="title">Aucune musique d'ambiance trouvée</div></div>
          <div style="padding:14px;color:#cbd5e1">
            Place des fichiers dans <code>public/audio/buzzers/</code> (mp3/ogg/wav) ou <code>public/sounds/buzzers/</code>.
          </div>
        </div>
      @endforelse
    </div>
  </section>

  <!-- ====== Sons de Buzzers ====== -->
  <section id="buzzers" style="display: {{ $tab==='buzzers'?'block':'none' }}">
    <div class="hero"><b>Sons de Buzzers</b> — Sons d'alerte pour vos parties.</div>
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
              <button class="btn success" disabled>{{ __('Disponible') }}</button>
            </div>
          @else
            <form method="POST" action="{{ $purchaseUrl }}" class="actions">
              @csrf
              <input type="hidden" name="kind" value="buzzer">
              <input type="hidden" name="target" value="{{ $bz['slug'] }}">
              <span class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price" style="margin-right:4px;">{{ $bz['price'] }}</span>
              <button class="btn danger" type="submit">{{ __('Acheter') }}</button>
            </form>
          @endif
        </div>
      @empty
        <div class="card"><div class="head"><div class="title">Aucun son de buzzer trouvé</div></div>
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
            <div class="title" style="
              display: inline-flex;
              align-items: center;
              justify-content: center;
              background: white;
              color: #003DA5;
              padding: 6px 12px;
              border-radius: 6px;
              border: 2px solid #003DA5;
              font-size: 0.9rem;
              text-transform: capitalize;
              max-width: 70%;
              min-width: 0;
              flex: 1 1 auto;
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
            ">{{ $a['label'] }}</div>
            @unless($isUnlockedStrategic)
              <div class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price" style="margin-right:4px;">{{ $a['price'] }}</div>
            @endunless
          </div>

          <div class="avatar-row">
            <div class="thumb">
              <span class="tier {{ $tClass }}">{{ $t }}</span>
              <img src="{{ _assetv($a['path']) }}" alt="{{ $a['label'] }}" loading="lazy" decoding="async">

              <div class="avatar-actions">
                @if($isUnlockedStrategic)
                  <button class="btn success lock-btn" type="button" disabled title="{{ __('Débloqué') }}">🔓</button>
                @else
                  <form method="POST" action="{{ $purchaseUrl }}">
                    @csrf
                    <input type="hidden" name="kind" value="stratégique">
                    <input type="hidden" name="target" value="{{ $slug }}">
                    <button class="btn danger lock-btn" type="submit" title="{{ __('Débloquer') }}">🔒</button>
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
            <button class="btn ghost close" type="button" onclick="toggleDetails('{{ $slug }}')">{{ __('Fermer') }}</button>
          </div>
        </div>
      @endforeach
    </div>
  </section>

  <!-- ====== Maître du Jeu ====== -->
  <section id="master" style="display: {{ $tab==='master'?'block':'none' }}">
    <div class="hero"><b>Maître du Jeu</b> — Débloquez le mode de jeu exclusif pour créer vos propres parties personnalisées.</div>

    <div class="grid cols-2">
      @php
        $masterPurchased = auth()->check() && (auth()->user()->master_purchased ?? false);
      @endphp
      
      <div class="card">
        <div class="head">
          <div class="title">🎮 {{ __('Maître du Jeu') }}</div>
          @unless($masterPurchased)
            <div class="price" style="font-size:1.5rem;font-weight:800;color:#10b981">29,99</div>
          @endunless
        </div>

        <div style="padding:20px">
          <p style="color:#cbd5e1;line-height:1.6;margin:0 0 16px">
            Devenez Maître du Jeu et créez vos propres parties personnalisées ! Définissez vos questions, invitez jusqu'à 40 joueurs simultanément et animez vos propres quiz en temps réel.
          </p>
          
          <div style="background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);border-radius:8px;padding:12px;margin:16px 0">
            <div style="font-weight:700;color:#818cf8;margin-bottom:8px">✨ Fonctionnalités incluses :</div>
            <ul style="margin:0;padding-left:20px;color:#cbd5e1;font-size:0.9rem">
              <li>Jusqu'à 40 joueurs par partie</li>
              <li>Questions personnalisées</li>
              <li>Animation en temps réel</li>
              <li>Contrôle total de la partie</li>
            </ul>
          </div>
        </div>

        @if($masterPurchased)
          <div class="actions">
            <button class="btn success" disabled>✓ Mode débloqué</button>
          </div>
        @else
          <div class="actions">
            <form method="POST" action="{{ route('master.checkout') ?? '#' }}" style="width:100%">
              @csrf
              <button class="btn" type="submit" style="width:100%;background:linear-gradient(135deg,#10b981,#059669);font-size:1.05rem">
                💳 Acheter - 29,99
              </button>
            </form>
          </div>
        @endif
      </div>
    </div>

    <!-- Lien vers les pièces d'intelligence -->
    <div style="margin-top:20px;padding:16px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);border-radius:12px">
      <a href="#coins" onclick="setTab('coins'); return false;" style="color:#818cf8;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:8px;transition:color 0.2s" onmouseover="this.style.color='#a5b4fc'" onmouseout="this.style.color='#818cf8'">
        <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon" style="width:24px;height:24px">
        <span>Pièces d'intelligence</span> — Achetez des pièces avec de la vraie monnaie pour débloquer des contenus exclusifs.
      </a>
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
              {{ number_format($pack['amount_cents'] / 100, 2) }}
            </div>
            <div style="color:#94a3b8;font-size:0.85rem;margin-top:4px">
              {{ number_format($pack['coins'] / ($pack['amount_cents'] / 100), 1) }} pièces
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
      <b>Vies supplémentaires</b> — Achetez des vies pour continuer vos parties ou profitez de vies illimitées pendant une durée limitée.
    </div>

    <div style="margin-bottom:24px">
      <h3 style="font-size:1.1rem;margin:0 0 12px;color:#fff">❤️ Packs de vies</h3>
      <div class="grid cols-3">
        <div class="card">
          <div style="padding:24px;text-align:center">
            <div style="font-size:3.5rem;font-weight:900;color:#ef4444;margin-bottom:8px">1</div>
            <div style="color:#cbd5e1;font-size:0.95rem;margin-bottom:20px">vie</div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:20px">
              <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price">
              <span style="font-size:1.5rem;font-weight:800;color:#fff">25</span>
            </div>

            <form method="POST" action="{{ $purchaseUrl }}" style="width:100%">
              @csrf
              <input type="hidden" name="kind" value="life">
              <input type="hidden" name="quantity" value="1">
              <button class="btn danger" type="submit" style="width:100%">{{ __('Acheter') }}</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div style="padding:24px;text-align:center">
            <div style="font-size:3.5rem;font-weight:900;color:#ef4444;margin-bottom:8px">3</div>
            <div style="color:#cbd5e1;font-size:0.95rem;margin-bottom:20px">vies</div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:20px">
              <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price">
              <span style="font-size:1.5rem;font-weight:800;color:#fff">50</span>
            </div>

            <form method="POST" action="{{ $purchaseUrl }}" style="width:100%">
              @csrf
              <input type="hidden" name="kind" value="life">
              <input type="hidden" name="quantity" value="3">
              <button class="btn danger" type="submit" style="width:100%">{{ __('Acheter') }}</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div style="padding:24px;text-align:center">
            <div style="font-size:3.5rem;font-weight:900;color:#ef4444;margin-bottom:8px">5</div>
            <div style="color:#cbd5e1;font-size:0.95rem;margin-bottom:20px">vies</div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:20px">
              <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price">
              <span style="font-size:1.5rem;font-weight:800;color:#fff">75</span>
            </div>

            <form method="POST" action="{{ $purchaseUrl }}" style="width:100%">
              @csrf
              <input type="hidden" name="kind" value="life">
              <input type="hidden" name="quantity" value="5">
              <button class="btn danger" type="submit" style="width:100%">{{ __('Acheter') }}</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div>
      <h3 style="font-size:1.1rem;margin:0 0 12px;color:#fff">⏱️ Vies illimitées temporaires</h3>
      <div class="grid cols-3">
        <div class="card">
          <div style="padding:24px;text-align:center">
            <div style="font-size:2.5rem;margin-bottom:8px">⚡</div>
            <div style="font-size:1.8rem;font-weight:900;color:#fbbf24;margin-bottom:8px">30 min</div>
            <div style="color:#cbd5e1;font-size:0.9rem;margin-bottom:20px">vies illimitées</div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:20px">
              <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price">
              <span style="font-size:1.5rem;font-weight:800;color:#fff">125</span>
            </div>

            <form method="POST" action="{{ $purchaseUrl }}" style="width:100%">
              @csrf
              <input type="hidden" name="kind" value="unlimited_lives">
              <input type="hidden" name="duration" value="30">
              <button class="btn" type="submit" style="width:100%;background:linear-gradient(135deg,#f59e0b,#d97706)">{{ __('Acheter') }}</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div style="padding:24px;text-align:center">
            <div style="font-size:2.5rem;margin-bottom:8px">🔥</div>
            <div style="font-size:1.8rem;font-weight:900;color:#fbbf24;margin-bottom:8px">1 heure</div>
            <div style="color:#cbd5e1;font-size:0.9rem;margin-bottom:20px">vies illimitées</div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:20px">
              <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price">
              <span style="font-size:1.5rem;font-weight:800;color:#fff">200</span>
            </div>

            <form method="POST" action="{{ $purchaseUrl }}" style="width:100%">
              @csrf
              <input type="hidden" name="kind" value="unlimited_lives">
              <input type="hidden" name="duration" value="60">
              <button class="btn" type="submit" style="width:100%;background:linear-gradient(135deg,#f59e0b,#d97706)">{{ __('Acheter') }}</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div style="padding:24px;text-align:center">
            <div style="font-size:2.5rem;margin-bottom:8px">💫</div>
            <div style="font-size:1.8rem;font-weight:900;color:#fbbf24;margin-bottom:8px">2 heures</div>
            <div style="color:#cbd5e1;font-size:0.9rem;margin-bottom:20px">vies illimitées</div>
            
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:20px">
              <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon coin-icon--price">
              <span style="font-size:1.5rem;font-weight:800;color:#fff">350</span>
            </div>

            <form method="POST" action="{{ $purchaseUrl }}" style="width:100%">
              @csrf
              <input type="hidden" name="kind" value="unlimited_lives">
              <input type="hidden" name="duration" value="120">
              <button class="btn" type="submit" style="width:100%;background:linear-gradient(135deg,#f59e0b,#d97706)">{{ __('Acheter') }}</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="note" style="margin-top:24px">
      <b>💡 Comment ça marche ?</b><br>
      Les vies normales restent dans votre compte indéfiniment. Les vies illimitées vous permettent de jouer sans limites pendant la durée choisie (le compteur démarre dès l'achat).
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
      <button class="btn ghost" onclick="closeModal()">{{ __('Fermer') }}</button>
    </div>
    <div style="padding:18px">
      <div id="thumbs" class="grid cols-4" style="gap:12px"></div>
    </div>
  </div>
</div>

<script>
  function setTab(id){
    for (const sec of ['packs','musiques','buzzers','stratégiques','coins','vies']) {
      const el = document.getElementById(sec);
      if(el) el.style.display = (sec===id ? 'block' : 'none');
    }
    document.querySelectorAll('.tabs .tab').forEach(function(t){ t.classList.remove('active'); });
    const map = {packs:0,musiques:1,buzzers:2,stratégiques:3,coins:4,vies:5};
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
    else if (hash && ['packs','musiques','buzzers','stratégiques','coins','vies'].indexOf(hash)!==-1) setTab(hash);
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

  function openPack(imgs, label){
    if (!Array.isArray(imgs)) {
      console.error('Images invalides:', imgs);
      return;
    }
    
    const modal = document.getElementById('modal');
    const title = document.getElementById('modalTitle');
    const thumbs= document.getElementById('thumbs');
    title.textContent = 'Pack: ' + label;
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
