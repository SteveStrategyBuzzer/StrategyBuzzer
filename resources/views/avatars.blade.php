@extends('layouts.app')
@section('title', 'Avatars — StrategyBuzzer')

@section('content')
@php
    use Illuminate\Support\Facades\Route;
    // Routes sûres (si la route n’existe pas, on tombe sur une URL par défaut)
    $rAvatarSelect      = Route::has('avatar.select')     ? route('avatar.select')     : url('/avatar/select');
    $rBoutique          = Route::has('boutique')          ? route('boutique')          : url('/boutique');
    $rBoutiquePurchase  = Route::has('boutique.purchase') ? route('boutique.purchase') : url('/boutique/purchase');
@endphp

<style>
:root{
  --bg:#003DA5; --card:#0b2a66; --ok:#22c55e; --muted:#94a3b8; --ink:#ffffff;
  --shadow: 0 10px 24px rgba(0,0,0,.18);
  --radius: 16px; --gap: 14px;
}
.page{min-height:100dvh;background:var(--bg);color:var(--ink);padding:24px;overflow-y:auto;}
.wrap{max-width:1200px;margin:0 auto;display:flex;flex-direction:column;gap:14px}

/* header */
.header{display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between;margin-bottom:6px}
.h-title{font-size:clamp(1.6rem,3vw,2rem);font-weight:800}
.pill{background:#0f3b8a;border-radius:999px;padding:8px 12px}
.pill b{color:#fff}

/* helpers */
.center-wrap{display:flex;justify-content:center}

/* cards */
.card{background:var(--card);border-radius:var(--radius);padding:16px;border:1px solid rgba(255,255,255,.08);position:relative;box-shadow:var(--shadow)}
.card .inner{display:flex;align-items:center;justify-content:center;min-height:40px;cursor:pointer}
.card h3{margin:0;font-size:1.1rem;letter-spacing:.3px}
.badge{position:absolute;top:10px;left:10px;font-size:.85rem;background:rgba(255,255,255,.12);padding:6px 10px;border-radius:999px}
.locked{filter: blur(2px) saturate(.7); opacity:.72;}
.lock-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(180deg,transparent,rgba(0,0,0,.45));pointer-events:none;border-radius:var(--radius)}
.alert{margin:6px 0;padding:10px 12px;border-radius:10px}
.alert-ok{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.35)}
.alert-ko{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.35)}

/* Standards mini-carousel */
.std-card{padding:12px}
.std-viewport{overflow-x:auto;overflow-y:hidden;max-width:1100px;margin:0 auto;scroll-behavior:smooth;-webkit-overflow-scrolling:touch}
.std-track{display:flex;gap:10px;will-change:transform;padding:4px 0}
.std-thumb{width:90px;height:90px;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.12);background:#0f1530;flex:0 0 90px;cursor:pointer;position:relative;transition:transform .2s,box-shadow .2s}
.std-thumb:hover{transform:scale(1.05);box-shadow:0 4px 12px rgba(255,255,255,.15)}
.std-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.std-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.2);width:34px;height:34px;border-radius:999px;display:grid;place-items:center;cursor:pointer;z-index:5}
.std-left{left:6px} .std-right{right:6px}

/* Packs carousel (3 desktop / 1 mobile) */
.carousel{position:relative;margin:6px 0 10px}
.viewport{overflow:hidden;margin:0 auto;max-width:calc(3*320px + 2*var(--gap))}
.track{display:flex;gap:var(--gap);will-change:transform;touch-action:pan-y;user-select:none}
.pack-card{width:320px;flex:0 0 320px}
@media (max-width:760px){
  .viewport{max-width:92vw}
  .pack-card{width:92vw;flex:0 0 92vw}
}
.arrow{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.2);width:38px;height:38px;border-radius:999px;display:grid;place-items:center;cursor:pointer;z-index:5}
.arrow.left{left:6px} .arrow.right{right:6px}
.arrow span{font-size:18px;line-height:1}

/* Scale hint */
.pack-anim{transition: transform .3s cubic-bezier(.2,.8,.2,1), opacity .3s ease}
.pack-anim.outgoing{transform: scale(.96); opacity:.9}
.pack-anim.incoming{transform: scale(1.02); opacity:1}

/* pack 2x2 preview or full selected image */
.pack-preview{margin-top:10px}
.preview-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.preview-grid img{width:100%;aspect-ratio:1/1;height:auto;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,.12)}
.preview-main{width:100%;height:196px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,.12)}

/* Stratégiques grid (4-4-4, no global title) */
.stratégiques{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
@media (max-width:900px){ .stratégiques{grid-template-columns:repeat(3,minmax(0,1fr))} }
@media (max-width:640px){ .stratégiques{grid-template-columns:repeat(2,minmax(0,1fr))} }
.stratégique-card{position:relative;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.1);background:#0f1530;cursor:pointer}
.stratégique-card img{width:100%;height:120px;object-fit:cover;display:block}
.tier-pill{position:absolute;top:8px;left:8px;padding:4px 8px;border-radius:999px;font-size:.78rem;border:1px solid rgba(255,255,255,.22)}
.t-rare{background:#1e3a8a}.t-epic{background:#6d28d9}.t-legend{background:#b45309}

/* === RESPONSIVE POUR ORIENTATION === */

/* Mobile Portrait (320px - 480px) */
@media (max-width: 480px) and (orientation: portrait) {
  .page{padding:16px 12px;overflow-y:auto;height:100vh}
  .h-title{font-size:1.4rem}
  .pill{font-size:0.85rem;padding:6px 10px}
  .thumbs{grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px}
  .std-thumb{width:70px;height:70px;flex:0 0 70px}
  .preview-grid img{aspect-ratio:1/1;height:auto}
  .stratégique-card img{height:100px}
  .wrap{padding-bottom:80px}
}

/* Mobile Paysage (orientation horizontale) */
@media (max-height: 500px) and (orientation: landscape) {
  .page{padding:12px}
  .wrap{gap:10px}
  .header{margin-bottom:4px}
  .h-title{font-size:1.3rem}
  .card{padding:12px}
  .std-card{padding:8px}
  .std-thumb{width:70px;height:70px;flex:0 0 70px}
  .stratégiques{gap:10px}
  .stratégique-card img{height:90px}
  .thumbs{grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px}
  .preview-grid{gap:4px}
  .preview-grid img{aspect-ratio:1/1;height:auto}
  .modal .card{max-height:85vh;overflow-y:auto}
}

/* Tablettes Portrait */
@media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
  .thumbs{grid-template-columns:repeat(auto-fill,minmax(140px,1fr))}
  .stratégiques{grid-template-columns:repeat(3,minmax(0,1fr))}
}

/* Tablettes Paysage */
@media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
  .thumbs{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
  .stratégiques{grid-template-columns:repeat(4,minmax(0,1fr))}
  .page{padding:20px}
}

/* “Actif” */
.active-tag{
  position:absolute;left:0;right:0;bottom:0;
  background:rgba(0,0,0,.55);
  color:var(--ok);
  text-align:center;padding:6px 8px;font-weight:800;letter-spacing:.2px
}

/* Modal */
.modal{
  position:fixed;inset:0;z-index:999;
  background:rgba(0,0,0,.75);
  display:flex;align-items:center;justify-content:center;
  backdrop-filter:blur(4px)
}

/* Thumbs grid in modal */
.thumbs{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
  gap:12px;
  padding:4px
}
.thumb{
  position:relative;
  background:#0f1530;
  border:1px solid rgba(255,255,255,.1);
  border-radius:12px;
  overflow:hidden;
  aspect-ratio:1/1;
  display:flex;
  flex-direction:column
}
.thumb img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block
}
.thumb .actions{
  position:absolute;
  bottom:0;left:0;right:0;
  display:flex;
  justify-content:center;
  padding:8px;
  background:linear-gradient(to top,rgba(0,0,0,.8),transparent)
}
.thumb .actions .pill{
  background:#2c4bff;
  color:#fff;
  padding:6px 12px;
  border-radius:999px;
  font-size:.9rem;
  font-weight:700;
  cursor:pointer;
  border:none
}
.thumb .actions form{
  margin:0
}
.thumb .actions button.pill{
  appearance:none
}
</style>

<div class="page">
  <div class="wrap">

    @if(session('error'))   <div class="alert alert-ko">{{ session('error') }}</div>   @endif

    <div class="header">
      <div class="h-title">🎭 Choix des Avatars</div>
      <div style="display:flex; align-items:center; gap:12px;">
        <div class="pill">💰 Pièces : <b>{{ number_format($coins ?? 0) }}</b></div>
        <a href="{{ route('menu') }}" style="
          background: white;
          color: #003DA5;
          padding: 8px 16px;
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
      </div>
    </div>

    @php
      // scan des images d’un pack (public/images/avatars/<slug>)
      if (!function_exists('__sb_pack_images')) {
        function __sb_pack_images(string $slug, int $limit = 32): array {
          $dir = public_path("images/avatars/$slug");
          $out = [];
          if (is_dir($dir)) {
            $files = glob($dir.'/*.{png,jpg,jpeg,webp}', GLOB_BRACE);
            natsort($files);
            foreach ($files as $f) {
              $out[] = 'images/avatars/'.$slug.'/'.basename($f);
              if (count($out) >= $limit) break;
            }
          }
          return $out;
        }
      }
      $FROM = request('from');
    @endphp

    {{-- ==== Standards (mini-carrousel) ==== --}}
    <div class="card std-card" style="position:relative">
      <h3 style="margin:0 0 12px 0;font-size:1.1rem;color:#fff">😊 Avatars Standards</h3>
      <button class="std-nav std-left"  onclick="stdPrev()" aria-label="Précédent"><span>‹</span></button>
      <button class="std-nav std-right" onclick="stdNext()" aria-label="Suivant"><span>›</span></button>
      <div class="std-viewport" id="stdViewport">
        <div id="stdTrack" class="std-track">
          @php
            $stdImgs = [
              'images/avatars/standard/standard1.png','images/avatars/standard/standard2.png','images/avatars/standard/standard3.png','images/avatars/standard/standard4.png',
              'images/avatars/standard/standard5.png','images/avatars/standard/standard6.png','images/avatars/standard/standard7.png','images/avatars/standard/standard8.png',
            ];
          @endphp
          @foreach($stdImgs as $simg)
            @php $isStdActive = ($selected ?? '') === $simg; @endphp
            <div class="std-thumb" onclick="stdSelect('{{ $simg }}')">
              <img src="{{ asset($simg) }}" alt="standard" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
              @if($isStdActive)<div class="active-tag">Actif</div>@endif
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- ==== Packs (carrousel) ==== --}}
    <div class="center-wrap">
      <div class="carousel" aria-label="Packs d’avatars">
        <button class="arrow left"  onclick="slidePrev()" aria-label="Précédent"><span>‹</span></button>
        <button class="arrow right" onclick="slideNext()" aria-label="Suivant"><span>›</span></button>

        <div class="viewport">
          <div class="track" id="track">
            @php
              $unlockedPacks = $unlockedPacks ?? [];
              $packs = [
                ['slug'=>'portraits','label'=>'Portraits'],
                ['slug'=>'cartoon','label'=>'Cartoon'],
                ['slug'=>'animal','label'=>'Animaux 1'],
                ['slug'=>'mythique','label'=>'Mythique'],
                ['slug'=>'paysage','label'=>'Paysage'],
                ['slug'=>'objet','label'=>'Objet'],
                ['slug'=>'clown','label'=>'Clown'],
                ['slug'=>'musicien','label'=>'Musicien'],
                ['slug'=>'automobile','label'=>'Automobile'],
              ];
              foreach ($packs as $k => $p) {
                $imgs = __sb_pack_images($p['slug'], 32);
                $packs[$k]['images'] = $imgs;
                $packs[$k]['count']  = count($imgs);
              }
            @endphp

            @foreach($packs as $p)
              @php
                $locked = !in_array($p['slug'], $unlockedPacks, true);
                $count  = (int)($p['count'] ?? 0);
              @endphp
<div class="card pack-card pack-anim pack-clickable"
    data-locked="{{ $locked ? '1' : '0' }}"
    data-slug="{{ $p['slug'] }}"
    data-label="{{ $p['label'] }}"
    style="cursor:pointer"
>
                <div class="badge">{{ $p['label'] }}{{ $count ? ' · '.$count : '' }}</div>
                <div class="inner {{ $locked ? 'locked' : '' }}"><h3>{{ $locked ? '' : $p['label'] }}</h3></div>

                @php
                  $selectedPath = is_string($selected ?? '') ? $selected : '';
                  $needle = 'images/avatars/'.$p['slug'].'/';
                  $isFromThisPack = $selectedPath && substr($selectedPath,0,strlen($needle)) === $needle;
                @endphp
                <div class="pack-preview">
                  @if(!$locked && $isFromThisPack)
                    <div style="position:relative">
                      <img class="preview-main" src="{{ asset($selectedPath) }}" alt="actif" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                      <div class="active-tag">Actif</div>
                    </div>
                  @else
                    @php $grid = array_slice($p['images'] ?? [], 0, 4); @endphp
                    <div class="preview-grid">
                      @foreach($grid as $g)
                        <img class="{{ $locked ? 'locked' : '' }}" src="{{ asset($g) }}" alt="apercu" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
                      @endforeach
                    </div>
                  @endif
                </div>

                @if($locked)<div class="lock-overlay"></div>@endif
 <template type="application/json" data-pack="{{ $p['slug'] }}">{!! json_encode($p['images'] ?? []) !!}</template>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- ==== Avatars stratégiques : une seule grille 4-4-4 ==== --}}
    @php
      $stratégiques = $groups['stratégique'] ?? [];
      $selectedVal = $selectedStrat ?? '';
    @endphp

    <div class="card" style="margin-top:20px">
      <h3 style="margin:0 0 12px 0;font-size:1.1rem;color:#fff">⚔️ Avatars Stratégiques</h3>
      <div class="stratégiques">
      @foreach($stratégiques as $a)
        @php
          $slug = $a['slug'];
          $isUnlocked = !empty($a['unlocked']);
          $tierName = $a['tier'] ?? 'Rare';
          $tierClass = $tierName==='Légendaire' ? 't-legend' : ($tierName==='Épique' ? 't-epic' : 't-rare');
          $imgMap = [
            'mathematicien'=>'images/avatars/mathematicien.png',
            'scientifique' =>'images/avatars/scientifique.png',
            'explorateur'  =>'images/avatars/explorateur.png',
            'defenseur'    =>'images/avatars/defenseur.png',
            'historien'    =>'images/avatars/historien.png',
            'comedienne'   =>'images/avatars/comedienne.png',
            'magicienne'   =>'images/avatars/magicienne.png',
            'challenger'   =>'images/avatars/challenger.png',
            'ia-junior'    =>'images/avatars/ia-junior.png',
            'stratege'     =>'images/avatars/stratege.png',
            'sprinteur'    =>'images/avatars/sprinteur.png',
            'visionnaire'  =>'images/avatars/visionnaire.png',
          ];
          $img = $imgMap[$slug] ?? null;
          $isActive = ($selectedVal === $slug);
        @endphp

        <div class="stratégique-card" onclick="onStratégiqueClick('{{ $slug }}', {{ $isUnlocked ? 'true' : 'false' }})">
          <div class="tier-pill {{ $tierClass }}">{{ $tierName }}</div>
          @if($img)
            <img src="{{ asset($img) }}" alt="{{ $a['name'] ?? $slug }}" class="{{ $isUnlocked ? '' : 'locked' }}" onerror="this.src='{{ asset('images/avatars/default.png') }}'">
          @else
            <div style="height:120px;display:grid;place-items:center;color:#cbd5e1">{{ ucfirst($slug) }}</div>
          @endif
          @if($isActive) <div class="active-tag">Actif</div> @endif
        </div>
      @endforeach
      </div>
    </div>

  </div> {{-- /wrap --}}
</div>   {{-- /page --}}

{{-- MODALE (Standards + Packs) --}}
<div id="modal" class="modal" role="dialog" aria-modal="true" style="display:none">
  <div class="card" style="width:min(980px,92vw);max-height:86vh;overflow:auto;border-radius:20px;background:#0b1020;border:1px solid rgba(255,255,255,.1)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 18px;position:sticky;top:0;background:#0b1020;border-bottom:1px solid rgba(255,255,255,.06)">
      <div class="h-title" id="modalTitle" style="font-size:1.1rem">Pack</div>
      <button class="pill" onclick="closeModal()">Fermer</button>
    </div>
    <div style="padding:18px">
      <div id="thumbs" class="thumbs"></div>
    </div>
  </div>
</div>

<script>
  const ROUTE_SELECT = @json($rAvatarSelect);
  const CSRF        = @json(csrf_token());
  const FROM        = @json(request('from') ?? '');
  const ASSET_BASE  = @json(asset(''));
  const SELECTED    = @json($selected ?? '');

  function assetPath(p){ return (ASSET_BASE + p).replace(/\/+$/, '').replace(/([^:]\/)\/+/g, '$1'); }

  /* ===== Standards (mini-carousel) ===== */
  const STANDARDS = [
    'images/avatars/standard/standard1.png','images/avatars/standard/standard2.png','images/avatars/standard/standard3.png','images/avatars/standard/standard4.png',
    'images/avatars/standard/standard5.png','images/avatars/standard/standard6.png','images/avatars/standard/standard7.png','images/avatars/standard/standard8.png',
  ];
  const stdViewport = document.querySelector('.std-viewport');
  const stdTrack = document.getElementById('stdTrack');
  function stdNext(){ stdViewport.scrollBy({left: 220, behavior:'smooth'}); }
  function stdPrev(){ stdViewport.scrollBy({left:-220, behavior:'smooth'}); }
  let stdTimer = setInterval(stdNext, 3500);

  /* ===== Packs (modale + carrousel) ===== */
  function openPack(slug, label){
    const tpl = document.querySelector(`template[data-pack="${slug}"]`);
    if(!tpl) return;
    try{
      const images = JSON.parse(tpl.innerHTML.trim());
      openModal(label || 'Pack', images);
    }catch(e){ console.error(e); }
  }

  /* ===== Gestion des clics sur les packs (géré par pointerup pour compatibilité drag) ===== */

  /* ===== Modale générique ===== */
  const modal = document.getElementById('modal');
  function openModal(title, images){
    document.getElementById('modalTitle').textContent = title;
    const thumbs = document.getElementById('thumbs');
    thumbs.innerHTML = '';
    images.forEach((p) => {
      const imgUrl = assetPath(p);
      const el = document.createElement('div');
      el.className = 'thumb';
      el.innerHTML = `
        <img src="${imgUrl}" alt="avatar" onerror="this.src='${assetPath('images/avatars/default.png')}'">
        <div class="actions">
          ${ (SELECTED === p) ? `<div class="pill">Actif</div>` : `
          <form method="POST" action="${ROUTE_SELECT}">
            <input type="hidden" name="_token" value="${CSRF}">
            <input type="hidden" name="avatar" value="${p}">
            <input type="hidden" name="from" value="${FROM}">
            <button class="pill" type="submit">Choisir</button>
          </form>`}
        </div>`;
      thumbs.appendChild(el);
    });
    modal.style.display = 'flex';
  }
  function closeModal(){ modal.style.display = 'none'; }

  /* ===== Packs carousel (normal, non-infini) ===== */
  const track = document.getElementById('track');
  let index = 0, w;
  function cardWidth(){ const c = track.querySelector('.pack-card'); return c ? (c.getBoundingClientRect().width + parseFloat(getComputedStyle(track).gap||0)) : 0; }
  function measure(){ w = cardWidth(); }
  function setTransform(tx, withTransition=true){
    track.style.transition = withTransition ? 'transform .3s cubic-bezier(.2,.8,.2,1)' : 'none';
    track.style.transform  = `translateX(${tx}px)`;
    hintScale();
  }
  function apply(){ if(!w) measure(); setTransform(-index * w, true); }
  function noTransApply(){ if(!w) measure(); setTransform(-index * w, false); }
  function hintScale(){
    const cards = [...track.querySelectorAll('.pack-card')];
    cards.forEach((c)=>c.classList.remove('incoming','outgoing'));
    const prev = cards[index-1], curr = cards[index];
    if(prev) prev.classList.add('outgoing'); if(curr) curr.classList.add('incoming');
  }
  function slideNext(){ 
    const total = track.querySelectorAll('.pack-card').length;
    if(index < total - 1){ index++; apply(); }
    else { index = 0; apply(); }
  }
  function slidePrev(){ 
    if(index > 0){ index--; apply(); }
    else { 
      const total = track.querySelectorAll('.pack-card').length;
      index = total - 1; apply(); 
    }
  }
  let packsTimer = setInterval(slideNext, 4200);

  // drag
  let startX=0, startY=0, delta=0, dragging=false;
  track.addEventListener('pointerdown',(e)=>{ dragging=true; startX=e.clientX; startY=e.clientY; track.setPointerCapture(e.pointerId); track.style.transition='none'; clearInterval(packsTimer); });
  track.addEventListener('pointermove',(e)=>{ if(!dragging) return; delta=e.clientX-startX; setTransform((-index*w)+delta, false); });
  track.addEventListener('pointerup',(e)=>{ 
    if(!dragging) return; 
    dragging=false; 
    track.releasePointerCapture(e.pointerId);
    
    // Détecter un tap (petit mouvement) pour ouvrir la modale
    if(Math.abs(delta)<=50 && Math.abs(e.clientY-startY)<=50){ 
      const el = document.elementFromPoint(e.clientX, e.clientY);
      const packCard = el?.closest('.pack-clickable');
      if(packCard){
        const locked = packCard.dataset.locked === '1';
        const slug = packCard.dataset.slug;
        const label = packCard.dataset.label;
        if(locked){ 
          window.location.href = @json($rBoutique) + '?item=' + slug; 
        } else { 
          openPack(slug, label); 
        }
      }
      apply(); 
    } else if(Math.abs(delta)>50){ 
      delta>0? slidePrev(): slideNext(); 
    } else { 
      apply(); 
    } 
    delta=0; 
    packsTimer=setInterval(slideNext,4200);
  });
  window.addEventListener('resize',()=>{ measure(); noTransApply(); });
  measure(); noTransApply(); requestAnimationFrame(apply);

  /* ===== Sélection serveur ===== */
  function postSelect(value){
    const f = document.createElement('form');
    f.method = 'POST'; f.action = @json($rAvatarSelect);
    f.innerHTML = `<input type="hidden" name="_token" value="${CSRF}">
                   <input type="hidden" name="avatar" value="${value}">
                   <input type="hidden" name="from" value="${FROM}">`;
    document.body.appendChild(f); f.submit();
  }
  function onStratégiqueClick(slug, unlocked){
    if(unlocked){ postSelect(slug); }
    else { window.location.href = @json($rBoutique).concat(`?stratégique=${encodeURIComponent(slug)}`); }
  }
  function stdSelect(path){ postSelect(path); }
</script>
@endsection
