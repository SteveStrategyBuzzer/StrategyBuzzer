@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\Auth;
    $user = Auth::user();
    $purchaseUrl = route('boutique.purchase');
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
.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }

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

.card .title { font-weight: 800; color: #fff; font-size: 1.1rem; }
.badge { font-size: .85rem; background: rgba(255,255,255,.15); padding: 6px 10px; border-radius: 999px; color: #fff; }
.price { font-weight: 800; display: flex; align-items: center; gap: 6px; color: #fbbf24; font-size: 1.1rem; }

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
.coin-icon--price { width: 24px; height: 24px; object-fit: contain; }

@media (max-width: 960px) { .cols-3 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) and (orientation: portrait) {
    .wrap { padding: 16px 12px 60px; }
    .topbar { flex-direction: column; align-items: stretch; }
    .nav-buttons { justify-content: center; }
    .cols-3 { grid-template-columns: 1fr; }
    .hero-title { font-size: 1.2rem; }
}
</style>

<div class="wrap">
    <div class="topbar">
        <div class="pill">
            <img src="{{ asset('images/coin-intelligence.png') }}" alt="Intelligence" class="coin-icon">
            <b>{{ number_format($coins) }}</b>
        </div>
        <div class="pill">
            <img src="{{ asset('images/skill_coin.png') }}" alt="Compétence" class="coin-icon">
            <b>{{ number_format($competenceCoins ?? 0) }}</b>
        </div>
        <div class="nav-buttons">
            <a href="{{ route('boutique.category', 'buzzers') }}" class="nav-btn secondary">← {{ __('Catégories') }}</a>
            <a href="{{ route('boutique') }}" class="nav-btn">{{ __('Boutique') }}</a>
        </div>
    </div>

    @if(session('success')) <div class="note">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="warn">{{ session('error') }}</div> @endif

    <div class="hero">
        <div class="hero-title">{{ $subcategoryIcon ?? '🔊' }} {{ $subcategoryLabel }}</div>
    </div>

    <div class="grid cols-3">
        @forelse($buzzerItems as $slug => $bz)
            @php $isUnlockedBz = in_array($slug, $unlocked, true); @endphp
            <div class="card">
                <div class="head">
                    <div class="title">{{ $bz['label'] ?? ucfirst(str_replace(['-', '_'], ' ', $slug)) }}</div>
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
                        <input type="hidden" name="target" value="{{ $slug }}">
                        <span class="price"><img src="{{ asset('images/coin-intelligence.png') }}" alt="" class="coin-icon--price">{{ $bz['price'] ?? 120 }}</span>
                        <button class="btn danger" type="submit">{{ __('Acheter') }}</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="card"><div class="head"><div class="title">{{ __('Aucun buzzer trouvé dans cette catégorie') }}</div></div></div>
        @endforelse
    </div>
</div>
@endsection
