@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\Auth;
    $user = Auth::user();
    $purchaseUrl = route('boutique.purchase');
    $avatarUrl = route('avatars');
    
    $categoryTitles = [
        'packs' => __("Packs d'avatars"),
        'musiques' => __("Musiques d'Ambiance"),
        'buzzers' => __('Sons de Buzzers'),
        'strategiques' => __('Avatars Stratégiques'),
        'master' => __('Modes de jeux'),
        'coins' => __('Pièces de Compétence'),
        'vies' => __('Vies'),
    ];
    
    $categoryIcons = [
        'packs' => '🎨',
        'musiques' => '🎵',
        'buzzers' => '🔊',
        'strategiques' => '🛡️',
        'master' => '🎮',
        'coins' => '<img src="' . asset('images/skill_coin.png') . '" alt="' . __('Pièce de Compétence') . '" style="width:32px;height:32px;vertical-align:middle;">',
        'vies' => '❤️',
    ];
    
    $packs = [];
    foreach ($catalog as $slug => $entry) {
        if (is_array($entry) && isset($entry['price']) && !in_array($slug, ['buzzers', 'stratégiques', 'musiques'])) {
            $packs[] = array_merge($entry, ['slug' => $slug]);
        }
    }
    
    $buzzers = [];
    foreach (($catalog['buzzers']['items'] ?? []) as $slug => $bz) {
        $buzzers[] = array_merge($bz, ['slug' => $slug]);
    }
    
    $strategiques = [];
    foreach (($catalog['stratégiques']['items'] ?? []) as $slug => $a) {
        $strategiques[] = array_merge($a, ['slug' => $slug]);
    }
@endphp

<style>
:root {
    --gap: 14px;
    --radius: 18px;
    --shadow: 0 10px 24px rgba(0,0,0,.12);
    --bg: #0b1020;
    --card: #111735;
    --ink: #ecf0ff;
    --muted: #9fb6ff;
    --blue: #2c4bff;
    --ok: #22c55e;
    --danger: #ef4444;
    --line: rgba(255,255,255,.08);
}

* { box-sizing: border-box; }
body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto; background: var(--bg); color: var(--ink); }

.wrap { max-width: 1200px; margin: 0 auto; padding: 20px 16px 80px; }

.topbar {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.pill {
    background: linear-gradient(135deg, #1a2344, #15224c);
    border: 1px solid var(--line);
    padding: 10px 14px;
    border-radius: 999px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 10px;
}

.pill b { color: #fff; }

.nav-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.nav-btn {
    background: white;
    color: #0b1020;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.nav-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,255,255,0.3);
}

.nav-btn.secondary {
    background: var(--blue);
    color: white;
}

.hero {
    background: linear-gradient(135deg, #15224c, #0f1836);
    border: 1px solid var(--line);
    padding: 16px;
    border-radius: 20px;
    box-shadow: var(--shadow);
    margin-bottom: 18px;
    text-align: center;
}

.hero-title {
    font-size: 1.5rem;
    margin-bottom: 8px;
}

.grid { display: grid; gap: var(--gap); }
.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }

.card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
    position: relative;
}

.card .head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
}

.card .title { font-weight: 800; }
.badge { font-size: .85rem; background: rgba(255,255,255,.08); padding: 6px 10px; border-radius: 999px; }
.price { font-weight: 800; display: flex; align-items: center; gap: 4px; }

.thumb { position: relative; overflow: hidden; border-top: 1px solid var(--line); }
.thumb img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }

.actions {
    display: flex;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid var(--line);
    justify-content: center;
    background: rgba(0,0,0,.22);
}

.btn {
    appearance: none;
    border: none;
    cursor: pointer;
    border-radius: 12px;
    padding: 10px 12px;
    background: var(--blue);
    color: #fff;
    font-weight: 800;
}

.btn.ghost { background: transparent; border: 1px solid rgba(255,255,255,.25); color: #cfe1ff; }
.btn[disabled] { opacity: .65; cursor: not-allowed; }
.btn.success { background: var(--ok); color: #fff; }
.btn.danger { background: var(--danger); color: #fff; }

.audio { padding: 12px; display: grid; gap: 10px; }
audio { width: 100%; }

.note { margin: 10px 0; padding: 10px 12px; border-radius: 10px; background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.35); }
.warn { margin: 10px 0; padding: 10px 12px; border-radius: 10px; background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35); }

.coin-icon { width: 24px; height: 24px; vertical-align: middle; }
.coin-icon--price { width: 20px; height: 20px; }

.tier { position: absolute; top: 8px; left: 8px; padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.t-rare { background: #3b82f6; color: white; }
.t-epic { background: #a855f7; color: white; }
.t-legend { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }

.avatar-actions { position: absolute; bottom: 8px; right: 8px; }
.lock-btn { padding: 8px 12px; font-size: 1rem; }

.view-btn { padding: 12px; text-align: center; }

.avatar-row .thumb img { height: 200px; }

.details { display: none; padding: 12px; background: rgba(0,0,0,.3); }
.details.open { display: block; }
.details .big { width: 100%; max-height: 300px; object-fit: contain; border-radius: 12px; margin-bottom: 12px; }
.details .skills { font-size: 0.9rem; color: var(--muted); margin-bottom: 12px; }
.details .close { width: 100%; }

/* === RESPONSIVE === */
@media (max-width: 960px) { .cols-4 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 760px) { .cols-4, .cols-3 { grid-template-columns: repeat(2, 1fr); } }

@media (max-width: 480px) and (orientation: portrait) {
    .wrap { padding: 16px 12px 60px; }
    .topbar { flex-direction: column; align-items: stretch; }
    .nav-buttons { justify-content: center; }
    .cols-4, .cols-3, .cols-2 { grid-template-columns: 1fr; }
    .avatar-row .thumb img { height: 180px; }
    .hero-title { font-size: 1.2rem; }
}

@media (max-height: 500px) and (orientation: landscape) {
    .wrap { padding: 12px; }
    .topbar { gap: 8px; margin-bottom: 10px; }
    .hero { padding: 12px; margin-bottom: 12px; }
    .cols-4 { grid-template-columns: repeat(4, 1fr); }
    .cols-3 { grid-template-columns: repeat(3, 1fr); }
    .avatar-row .thumb img { height: 160px; }
}

@media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
    .cols-4 { grid-template-columns: repeat(2, 1fr); }
    .cols-3 { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
    .cols-4 { grid-template-columns: repeat(3, 1fr); }
    .cols-3 { grid-template-columns: repeat(3, 1fr); }
}

.master-card {
    background: linear-gradient(135deg, #1a1a4a, #2d1b69);
    border-color: #6366f1;
    padding: 24px;
    text-align: center;
}

.master-features { text-align: left; margin: 16px 0; padding: 16px; background: rgba(0,0,0,.2); border-radius: 12px; }
.master-features ul { margin: 8px 0 0; padding-left: 20px; }
.master-features li { margin: 6px 0; color: var(--muted); }

.coins-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.coin-pack { text-align: center; padding: 24px; }
.coin-amount { font-size: 3rem; font-weight: 900; color: #fbbf24; text-shadow: 0 2px 8px rgba(251,191,36,0.3); }
.coin-price { font-size: 1.8rem; font-weight: 800; margin-top: 16px; }

.lives-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.life-pack { text-align: center; padding: 24px; }
.life-amount { font-size: 3rem; font-weight: 900; color: #ef4444; }

@media (max-width: 760px) {
    .coins-grid, .lives-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .coins-grid, .lives-grid { grid-template-columns: 1fr; }
}
</style>

<div class="wrap">
    <div class="topbar">
        <div class="pill">
            <img src="{{ asset('images/coin-intelligence.png') }}" alt="Pièce" class="coin-icon">
            {{ __('Pièces') }} : <b>{{ number_format($coins) }}</b>
        </div>
        <div class="nav-buttons">
            <a href="{{ route('boutique') }}" class="nav-btn secondary">← {{ __('Boutique') }}</a>
            <a href="{{ route('menu') }}" class="nav-btn">{{ __('Menu') }}</a>
        </div>
    </div>

    @if(session('success')) <div class="note">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="warn">{{ session('error') }}</div> @endif

    <div class="hero">
        <div class="hero-title">{{ $categoryIcons[$category] ?? '' }} {{ $categoryTitles[$category] ?? ucfirst($category) }}</div>
    </div>

    @if($category === 'packs')
        <div class="grid cols-4">
            @foreach($packs as $p)
                @php 
                    $isUnlockedPack = in_array($p['slug'], $unlocked, true);
                    $previewImages = array_slice($p['images'] ?? [], 0, 4);
                @endphp
                <div class="card" id="pack-{{ $p['slug'] }}">
                    <div class="head">
                        <div class="title">{{ $p['label'] ?? $p['name'] ?? ucfirst($p['slug']) }}</div>
                        @unless($isUnlockedPack)
                            <div class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="" class="coin-icon--price">{{ $p['price'] ?? 300 }}</div>
                        @endunless
                    </div>
                    <div class="avatar-row">
                        <div class="thumb" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:12px;">
                            <span class="tier t-rare" style="position:absolute;top:8px;left:8px;">{{ $p['count'] ?? count($p['images'] ?? []) }} {{ __('images') }}</span>
                            @forelse($previewImages as $img)
                                <img src="{{ asset($img) }}" alt="{{ $p['label'] ?? '' }}" loading="lazy" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
                            @empty
                                <div style="padding:40px 14px;color:#cbd5e1;text-align:center;font-size:12px;grid-column:span 2;">{{ __('Aucune image') }}</div>
                            @endforelse
                            <div class="avatar-actions">
                                @if($isUnlockedPack)
                                    <button class="btn success lock-btn" type="button" disabled>🔓</button>
                                @else
                                    <form method="POST" action="{{ $purchaseUrl }}">
                                        @csrf
                                        <input type="hidden" name="kind" value="pack">
                                        <input type="hidden" name="target" value="{{ $p['slug'] }}">
                                        <button class="btn danger lock-btn" type="submit">🔒</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    @elseif($category === 'musiques')
        <div class="grid cols-3">
            @forelse($buzzers as $bz)
                @php $isUnlockedBz = in_array($bz['slug'], $unlocked, true); @endphp
                <div class="card">
                    <div class="head">
                        <div class="title">{{ $bz['label'] ?? $bz['name'] ?? ucfirst($bz['slug']) }}</div>
                        <div class="badge">Audio</div>
                    </div>
                    <div class="audio">
                        <audio controls preload="none">
                            <source src="{{ asset($bz['path'] ?? '') }}" type="audio/{{ pathinfo($bz['path'] ?? '', PATHINFO_EXTENSION) }}">
                        </audio>
                    </div>
                    @if($isUnlockedBz)
                        <div class="actions"><button class="btn success" disabled>{{ __('Disponible') }}</button></div>
                    @else
                        <form method="POST" action="{{ $purchaseUrl }}" class="actions">
                            @csrf
                            <input type="hidden" name="kind" value="buzzer">
                            <input type="hidden" name="target" value="{{ $bz['slug'] }}">
                            <span class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="" class="coin-icon--price">{{ $bz['price'] ?? 80 }}</span>
                            <button class="btn danger" type="submit">{{ __('Acheter') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="card"><div class="head"><div class="title">{{ __('Aucune musique trouvée') }}</div></div></div>
            @endforelse
        </div>

    @elseif($category === 'buzzers')
        <div class="grid cols-3">
            @forelse($buzzers as $bz)
                @php $isUnlockedBz = in_array($bz['slug'], $unlocked, true); @endphp
                <div class="card">
                    <div class="head">
                        <div class="title">{{ $bz['label'] ?? $bz['name'] ?? ucfirst($bz['slug']) }}</div>
                        <div class="badge">Audio</div>
                    </div>
                    <div class="audio">
                        <audio controls preload="none">
                            <source src="{{ asset($bz['path'] ?? '') }}" type="audio/{{ pathinfo($bz['path'] ?? '', PATHINFO_EXTENSION) }}">
                        </audio>
                    </div>
                    @if($isUnlockedBz)
                        <div class="actions"><button class="btn success" disabled>{{ __('Disponible') }}</button></div>
                    @else
                        <form method="POST" action="{{ $purchaseUrl }}" class="actions">
                            @csrf
                            <input type="hidden" name="kind" value="buzzer">
                            <input type="hidden" name="target" value="{{ $bz['slug'] }}">
                            <span class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="" class="coin-icon--price">{{ $bz['price'] ?? 80 }}</span>
                            <button class="btn danger" type="submit">{{ __('Acheter') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="card"><div class="head"><div class="title">{{ __('Aucun buzzer trouvé') }}</div></div></div>
            @endforelse
        </div>

    @elseif($category === 'strategiques')
        <div class="grid cols-4">
            @foreach($strategiques as $a)
                @php
                    $t = $a['tier'] ?? 'Rare';
                    $tClass = $t==='Légendaire' ? 't-legend' : ($t==='Épique' ? 't-epic' : 't-rare');
                    $isUnlockedStrategic = in_array($a['slug'], $unlocked, true);
                    $slug = $a['slug'];
                @endphp
                <div class="card" id="strategique-{{ $slug }}">
                    <div class="head">
                        <div class="title">{{ $a['label'] ?? $a['name'] ?? ucfirst($slug) }}</div>
                        @unless($isUnlockedStrategic)
                            <div class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="" class="coin-icon--price">{{ $a['price'] ?? 500 }}</div>
                        @endunless
                    </div>
                    <div class="avatar-row">
                        <div class="thumb">
                            <span class="tier {{ $tClass }}">{{ $t }}</span>
                            <img src="{{ asset($a['path'] ?? '') }}" alt="{{ $a['label'] ?? '' }}" loading="lazy">
                            <div class="avatar-actions">
                                @if($isUnlockedStrategic)
                                    <button class="btn success lock-btn" type="button" disabled>🔓</button>
                                @else
                                    <form method="POST" action="{{ $purchaseUrl }}">
                                        @csrf
                                        <input type="hidden" name="kind" value="stratégique">
                                        <input type="hidden" name="target" value="{{ $slug }}">
                                        <button class="btn danger lock-btn" type="submit">🔒</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if(!empty($a['skills']))
                        <div class="view-btn">
                            <button class="btn ghost" type="button" onclick="toggleDetails('{{ $slug }}')">{{ __('Voir compétences') }}</button>
                        </div>
                        <div class="details" id="details-{{ $slug }}">
                            <div class="skills">
                                @foreach($a['skills'] as $sk)
                                    <div>• {{ $sk }}</div>
                                @endforeach
                            </div>
                            <button class="btn ghost close" type="button" onclick="toggleDetails('{{ $slug }}')">{{ __('Fermer') }}</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

    @elseif($category === 'master')
        <div class="grid cols-3">
            <!-- Mode Duo -->
            <div class="card master-card">
                <div style="font-size:3rem;margin-bottom:16px;">👥</div>
                <h2>{{ __('Mode Duo') }}</h2>
                <p style="color:var(--muted);margin-bottom:16px;">{{ __('Affrontez vos amis en 1v1 !') }}</p>
                
                <div class="master-features">
                    <strong>✨ {{ __('Fonctionnalités incluses') }} :</strong>
                    <ul>
                        <li>{{ __('Matchs 1 contre 1') }}</li>
                        <li>{{ __('Invitations par code') }}</li>
                        <li>{{ __('Classement ELO') }}</li>
                        <li>{{ __('Carnet de contacts') }}</li>
                    </ul>
                </div>
                
                @if($duoPurchased ?? false)
                    <button class="btn success" disabled style="width:100%;padding:16px;font-size:1.1rem;">✓ {{ __('Mode débloqué') }}</button>
                @else
                    <form method="POST" action="{{ route('modes.checkout', 'duo') }}" style="width:100%;">
                        @csrf
                        <button class="btn" type="submit" style="width:100%;padding:16px;font-size:1.1rem;background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
                            💳 {{ __('Acheter') }} - $12.50
                        </button>
                    </form>
                @endif
            </div>

            <!-- Mode Ligue -->
            <div class="card master-card">
                <div style="font-size:3rem;margin-bottom:16px;">🏆</div>
                <h2>{{ __('Mode Ligue') }}</h2>
                <p style="color:var(--muted);margin-bottom:16px;">{{ __('Compétition classée entre joueurs !') }}</p>
                
                <div class="master-features">
                    <strong>✨ {{ __('Fonctionnalités incluses') }} :</strong>
                    <ul>
                        <li>{{ __('Matchmaking automatique') }}</li>
                        <li>{{ __('Classement mondial') }}</li>
                        <li>{{ __('Saisons compétitives') }}</li>
                        <li>{{ __('Récompenses exclusives') }}</li>
                    </ul>
                </div>
                
                @if($leaguePurchased ?? false)
                    <button class="btn success" disabled style="width:100%;padding:16px;font-size:1.1rem;">✓ {{ __('Mode débloqué') }}</button>
                @else
                    <form method="POST" action="{{ route('modes.checkout', 'league') }}" style="width:100%;">
                        @csrf
                        <button class="btn" type="submit" style="width:100%;padding:16px;font-size:1.1rem;background:linear-gradient(135deg,#8b5cf6,#6d28d9);">
                            💳 {{ __('Acheter') }} - $15.75
                        </button>
                    </form>
                @endif
            </div>

            <!-- Mode Maître du Jeu -->
            <div class="card master-card">
                <div style="font-size:3rem;margin-bottom:16px;">🎮</div>
                <h2>{{ __('Maître du Jeu') }}</h2>
                <p style="color:var(--muted);margin-bottom:16px;">{{ __('Créez vos propres parties personnalisées !') }}</p>
                
                <div class="master-features">
                    <strong>✨ {{ __('Fonctionnalités incluses') }} :</strong>
                    <ul>
                        <li>{{ __("Jusqu'à 40 joueurs par partie") }}</li>
                        <li>{{ __('Questions personnalisées') }}</li>
                        <li>{{ __('Animation en temps réel') }}</li>
                        <li>{{ __('Contrôle total de la partie') }}</li>
                    </ul>
                </div>
                
                @if($masterPurchased)
                    <button class="btn success" disabled style="width:100%;padding:16px;font-size:1.1rem;">✓ {{ __('Mode débloqué') }}</button>
                @else
                    <form method="POST" action="{{ route('master.checkout') }}" style="width:100%;">
                        @csrf
                        <button class="btn" type="submit" style="width:100%;padding:16px;font-size:1.1rem;background:linear-gradient(135deg,#10b981,#059669);">
                            💳 {{ __('Acheter') }} - $29.99
                        </button>
                    </form>
                @endif
            </div>
        </div>

    @elseif($category === 'coins')
        <div class="coins-grid">
            @foreach($coinPacks ?? [] as $pack)
                <div class="card coin-pack">
                    <div class="coin-amount">{{ number_format($pack['coins'] ?? 0) }}</div>
                    <div style="color:var(--muted);margin-top:8px;">{{ __("pièces de compétence") }}</div>
                    <div class="coin-price">{{ number_format(($pack['amount_cents'] ?? 0) / 100, 2) }}€</div>
                    <form method="POST" action="{{ route('coins.checkout') }}" style="margin-top:16px;">
                        @csrf
                        <input type="hidden" name="product_key" value="{{ $pack['key'] ?? '' }}">
                        <button class="btn" type="submit" style="width:100%;background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                            💳 {{ __('Acheter') }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
        @if(empty($coinPacks))
            <div class="card" style="padding:24px;text-align:center;">
                <p style="color:var(--muted);">{{ __('Les packs de pièces seront bientôt disponibles.') }}</p>
            </div>
        @endif

    @elseif($category === 'vies')
        <div class="lives-grid">
            @php
                $lifePacks = [
                    ['lives' => 1, 'price' => 25],
                    ['lives' => 3, 'price' => 50],
                    ['lives' => 5, 'price' => 75],
                ];
            @endphp
            @foreach($lifePacks as $lp)
                <div class="card life-pack">
                    <div class="life-amount">{{ $lp['lives'] }}</div>
                    <div style="color:var(--muted);margin-top:8px;">{{ $lp['lives'] > 1 ? __('vies') : __('vie') }}</div>
                    <div class="price" style="justify-content:center;margin-top:16px;font-size:1.2rem;">
                        <img src="{{ asset('images/coin-intelligence.png') }}" alt="" class="coin-icon">
                        {{ $lp['price'] }}
                    </div>
                    <form method="POST" action="{{ $purchaseUrl }}" style="margin-top:16px;">
                        @csrf
                        <input type="hidden" name="kind" value="life">
                        <input type="hidden" name="quantity" value="{{ $lp['lives'] }}">
                        <button class="btn danger" type="submit" style="width:100%;">{{ __('Acheter') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
function toggleDetails(slug) {
    const d = document.getElementById('details-' + slug);
    if (d) {
        d.classList.toggle('open');
    }
}
</script>
@endsection
