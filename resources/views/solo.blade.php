@extends('layouts.app')

@section('content')
@include('partials.game-context', [
    'mode'       => 'solo',
    'page'       => 'lobby',
    'playerName' => auth()->user()->name ?? 'Joueur',
])
@php
$soloDisadvantagedAvatars = [
    'défenseur'  => "Cet avatar ne sera pas nécessaire en mode Solo car il n'y aura pas d'attaque des joueurs adverses.",
    'defenseur'  => "Cet avatar ne sera pas nécessaire en mode Solo car il n'y aura pas d'attaque des joueurs adverses.",
    'comédienne' => "Cet avatar ne vous sera pas avantageux en mode Solo car ses skills affectent les adversaires humains.",
    'comedienne' => "Cet avatar ne vous sera pas avantageux en mode Solo car ses skills affectent les adversaires humains.",
];
$currentStrAvatarLower = strtolower($avatar_stratégique ?? 'aucun');
$showSoloWarning   = isset($soloDisadvantagedAvatars[$currentStrAvatarLower]);
$soloWarningMsg    = $showSoloWarning ? $soloDisadvantagedAvatars[$currentStrAvatarLower] : '';
$tierColors        = ['Rare' => '#3b82f6','Épique' => '#a855f7','Légendaire' => '#f59e0b'];
$selSlug           = $current_strategic_slug ?? '';
$selAvatarData     = ($strategic_avatars ?? [])[$selSlug] ?? null;
$selSkillsShort    = $selAvatarData['skills_short'] ?? [];
$selSkillsFull     = $selAvatarData['skills'] ?? [];
// Parse emoji + nom de skill depuis la forme "🤖 IA Assist : ..."
$selSkillsParsed   = array_map(function($s) {
    // Lookahead sur ":" pour stopper exactement au nom (ex: "IA Assist")
    if (preg_match('/^(\S+)\s+(.+?)(?=\s*:)/u', $s, $m)) {
        return ['emoji' => $m[1], 'name' => trim($m[2])];
    }
    preg_match('/^(\S+)\s+(.+)$/u', $s, $m2);
    return ['emoji' => $m2[1] ?? '⚡', 'name' => trim($m2[2] ?? $s)];
}, array_slice($selSkillsFull, 0, 3));
$selAvatarName     = $selAvatarData ? strtoupper($selAvatarData['name'] ?? '') : strtoupper($avatar_stratégique ?? __('Aucun'));
$selTier           = $selAvatarData['tier'] ?? '';
$selTierColor      = $tierColors[$selTier] ?? '#ffffff';
// Avatars débloqués en premier
$avatarsSorted = collect($strategic_avatars ?? [])
    ->sortByDesc(fn($a) => $a['unlocked'] ? 1 : 0)
    ->all();
$themeList = [
    ['key'=>'general',    'emoji'=>'🧠','label'=>'Général',    'desc'=>'Culture générale'],
    ['key'=>'geographie', 'emoji'=>'🌐','label'=>'Géographie', 'desc'=>'Pays, villes, lieux'],
    ['key'=>'histoire',   'emoji'=>'📜','label'=>'Histoire',   'desc'=>'Époques et événements'],
    ['key'=>'art',        'emoji'=>'🎨','label'=>'Art',        'desc'=>'Peinture, sculpture, etc.'],
    ['key'=>'cinema',     'emoji'=>'🎬','label'=>'Cinéma',     'desc'=>'Films, acteurs, réalisateurs'],
    ['key'=>'sport',      'emoji'=>'🏅','label'=>'Sport',      'desc'=>'Sports et athlètes'],
    ['key'=>'faune',      'emoji'=>'🦁','label'=>'Faune',      'desc'=>'Animaux et espèces'],
    ['key'=>'cuisine',    'emoji'=>'🍳','label'=>'Cuisine',    'desc'=>'Recettes et gastronomie'],
    ['key'=>'sciences',   'emoji'=>'🔬','label'=>'Sciences',   'desc'=>'Découvertes et savoirs'],
];
@endphp

<style>
/* ===== BASE ===== */
body { background: #030924; color: #fff; overflow-x: hidden; }
*, *::before, *::after { box-sizing: border-box; }

/* ===== LAYOUT ===== */
.sl-wrap { max-width: 900px; margin: 0 auto; padding: 20px 16px 40px; }

/* ===== HEADER ===== */
.sl-menu-btn {
  position: fixed; top: 18px; right: 18px; z-index: 200;
  display: inline-flex; align-items: center; gap: 7px;
  background: rgba(255,255,255,0.95); color: #030924;
  padding: 10px 20px; border-radius: 10px; font-weight: 700;
  font-size: 0.95rem; text-decoration: none; border: none; cursor: pointer;
  transition: transform .15s, box-shadow .15s;
  box-shadow: 0 4px 16px rgba(0,0,0,0.3);
}
.sl-menu-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.4); color: #030924; }

.sl-header { text-align: center; padding: 28px 0 20px; }
.sl-title {
  font-size: 2.8rem; font-weight: 900; letter-spacing: 2px;
  text-transform: uppercase; margin: 0 0 12px;
}
.sl-level-badge {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 1rem; color: rgba(255,255,255,0.75);
}
.sl-level-num {
  background: #1d4ed8; color: #fff;
  border-radius: 50%; width: 30px; height: 30px;
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 1rem;
}

/* ===== OPTIONS CARD ===== */
.sl-card {
  background: rgba(10, 25, 80, 0.75);
  border: 1px solid rgba(96, 165, 250, 0.18);
  border-radius: 16px; padding: 22px 20px 18px;
  margin-bottom: 14px; backdrop-filter: blur(4px);
}
.sl-card-title {
  text-align: center; font-weight: 600; font-size: 0.95rem;
  color: rgba(255,255,255,0.85); margin-bottom: 18px;
}

/* Options 3-col */
.sl-opts-row {
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  gap: 14px; margin-bottom: 18px;
}
.sl-opt { display: flex; flex-direction: column; gap: 8px; }
.sl-opt-label {
  display: flex; align-items: center; gap: 6px;
  font-size: 0.8rem; color: rgba(255,255,255,0.65); font-weight: 500;
}
.sl-opt-label .sl-opt-icon {
  width: 28px; height: 28px; border-radius: 8px;
  background: rgba(99,102,241,0.25);
  display: flex; align-items: center; justify-content: center; font-size: 0.9rem;
}
.sl-select {
  background: rgba(5,15,60,0.8); border: 1px solid rgba(96,165,250,0.3);
  color: #fff; border-radius: 10px; padding: 10px 12px;
  font-size: 0.9rem; width: 100%; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2393c5fd' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 12px center;
  cursor: pointer;
}
.sl-select:focus { outline: none; border-color: #60a5fa; }

/* Niveau stepper */
.sl-level-box {
  background: rgba(5,15,60,0.8); border: 1px solid rgba(96,165,250,0.3);
  border-radius: 10px; padding: 8px 12px;
  display: flex; align-items: center; justify-content: space-between;
}
.sl-level-display { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 1.4rem; font-weight: 800; }
.sl-brain-img { width: 38px; height: 38px; object-fit: contain; filter: drop-shadow(0 0 6px rgba(168,85,247,0.6)); }
.sl-level-btn {
  background: rgba(255,255,255,0.1); border: none; color: rgba(255,255,255,0.7);
  width: 28px; height: 28px; border-radius: 7px; cursor: pointer;
  font-size: 1.1rem; display: flex; align-items: center; justify-content: center;
  transition: background .15s;
}
.sl-level-btn:hover { background: rgba(255,255,255,0.2); color: #fff; }
.sl-level-btn:disabled { opacity: 0.3; cursor: not-allowed; }

/* Adversaire button */
.sl-adv-btn {
  background: linear-gradient(135deg, #7c3aed, #4f46e5);
  color: #fff; border: none; border-radius: 10px; padding: 11px 12px;
  font-size: 0.85rem; font-weight: 700; cursor: pointer; width: 100%;
  text-decoration: none; display: flex; align-items: center; justify-content: center;
  transition: transform .15s, box-shadow .15s;
  box-shadow: 0 3px 12px rgba(124,58,237,0.4);
}
.sl-adv-btn:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(124,58,237,0.5); color: #fff; }

/* Avatar stratégique row */
.sl-strat-row {
  border-top: 1px solid rgba(255,255,255,0.08);
  padding-top: 14px;
  display: flex; flex-direction: column; gap: 10px;
}
.sl-strat-label { display: flex; align-items: center; gap: 7px; font-size: 0.82rem; color: rgba(255,255,255,0.7); }
.sl-strat-icon { font-size: 1rem; }
.sl-strat-bottom { display: flex; align-items: center; gap: 12px; min-width: 0; }
.sl-strat-name { font-weight: 800; font-size: 0.9rem; letter-spacing: 0.5px; flex-shrink: 0; }
/* ── Ability cards ── */
.sl-skill-cards { display: flex; gap: 8px; margin-left: auto; flex-shrink: 0; }
.sl-skill-card {
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  width: 62px; cursor: default;
}
.sl-skill-icon {
  width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.45rem;
  border: 2px solid;
  backdrop-filter: blur(6px);
  transition: transform .15s, box-shadow .15s;
}
.sl-skill-icon:hover { transform: translateY(-2px); }
.sl-skill-name {
  font-size: 0.6rem; font-weight: 700; text-align: center; line-height: 1.25;
  color: rgba(255,255,255,0.8); text-transform: uppercase; letter-spacing: 0.4px;
  max-width: 62px;
}

/* ===== AVATAR GALLERY CARD ===== */
.sl-av-header { display: flex; flex-direction: column; gap: 2px; margin-bottom: 14px; }
.sl-av-title { font-size: 1.05rem; font-weight: 700; }
.sl-av-sub { font-size: 0.75rem; color: rgba(255,255,255,0.5); }

.sl-av-gallery {
  display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px;
  scrollbar-width: thin; scrollbar-color: rgba(96,165,250,0.3) transparent;
}
.sl-av-gallery::-webkit-scrollbar { height: 4px; }
.sl-av-gallery::-webkit-scrollbar-track { background: transparent; }
.sl-av-gallery::-webkit-scrollbar-thumb { background: rgba(96,165,250,0.3); border-radius: 4px; }

.sl-av-portrait {
  position: relative; flex: 0 0 88px; cursor: pointer;
  transition: transform .2s, filter .2s;
  display: flex; flex-direction: column; align-items: center; gap: 0;
}
.sl-av-portrait:hover { transform: translateY(-4px); }
.sl-av-portrait.locked { opacity: 0.5; cursor: not-allowed; filter: grayscale(40%); }
.sl-av-portrait.locked:hover { transform: none; }

/* Card frame */
.sl-av-frame {
  width: 88px; height: 88px; border-radius: 16px; overflow: hidden;
  position: relative;
  border: 2px solid rgba(255,255,255,0.12);
  transition: border-color .2s, box-shadow .2s;
  background: rgba(10,20,60,0.8);
}
.sl-av-portrait.selected .sl-av-frame {
  border-width: 2.5px;
}
.sl-av-portrait img {
  width: 100%; height: 100%; object-fit: cover; display: block;
}
/* Gradient tier overlay at bottom of card */
.sl-av-frame-overlay {
  position: absolute; bottom: 0; left: 0; right: 0; height: 36px;
  background: linear-gradient(to top, rgba(3,9,36,0.85) 0%, transparent 100%);
  pointer-events: none;
}
/* Tier pill inside card */
.sl-av-tier-pill {
  position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%);
  font-size: 0.5rem; font-weight: 800; letter-spacing: 0.6px;
  text-transform: uppercase; padding: 2px 7px; border-radius: 20px;
  background: rgba(3,9,36,0.7); white-space: nowrap;
  backdrop-filter: blur(4px);
}
.sl-av-check {
  position: absolute; top: 6px; right: 6px;
  width: 20px; height: 20px; border-radius: 50%;
  border: 2px solid rgba(3,9,36,0.9);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.6rem; font-weight: 900; color: #fff;
}
.sl-av-lock {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; background: rgba(0,0,0,0.35); border-radius: 14px;
}
.sl-av-name {
  text-align: center; font-size: 0.64rem; margin-top: 6px;
  font-weight: 600; white-space: nowrap; overflow: hidden;
  text-overflow: ellipsis; max-width: 88px; color: rgba(255,255,255,0.75);
}

/* ===== THEME GRID ===== */
.sl-theme-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
  margin-bottom: 14px;
}
.sl-theme-btn {
  display: flex; align-items: center; gap: 12px;
  background: rgba(10, 25, 80, 0.75); border: 1px solid rgba(96,165,250,0.15);
  border-radius: 13px; padding: 14px 14px; cursor: pointer; width: 100%;
  text-align: left; color: #fff; transition: background .15s, border-color .2s, transform .12s;
  text-decoration: none;
}
.sl-theme-btn:hover {
  background: rgba(20, 45, 120, 0.9); border-color: rgba(96,165,250,0.4);
  transform: translateY(-1px); color: #fff;
}
.sl-theme-btn:active { transform: translateY(0); }
.sl-theme-emoji {
  width: 40px; height: 40px; border-radius: 12px; flex: 0 0 40px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; background: rgba(255,255,255,0.07);
}
.sl-theme-info { flex: 1; min-width: 0; }
.sl-theme-name { font-weight: 700; font-size: 0.88rem; display: block; }
.sl-theme-desc { font-size: 0.72rem; color: rgba(255,255,255,0.5); margin-top: 2px; display: block; }
.sl-theme-arrow { color: rgba(255,255,255,0.35); font-size: 0.9rem; flex: 0 0 auto; }

/* ===== TIP BAR ===== */
.sl-tip {
  background: rgba(10,25,80,0.6); border: 1px solid rgba(96,165,250,0.12);
  border-radius: 12px; padding: 12px 16px;
  display: flex; align-items: center; gap: 8px;
  font-size: 0.8rem; color: rgba(255,255,255,0.65);
}
.sl-tip-icon { font-size: 1rem; flex: 0 0 auto; }

/* ===== VALIDATION MSG ===== */
.sl-validation-msg {
  display: none; position: fixed; top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  background: rgba(220,38,38,0.95); color: #fff;
  padding: 20px 36px; border-radius: 14px;
  font-size: 1.05rem; font-weight: 700;
  box-shadow: 0 10px 40px rgba(0,0,0,0.4); z-index: 9000;
  text-align: center; backdrop-filter: blur(4px);
}

/* ===== WARNING POPUP ===== */
.sl-warn-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.75);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  animation: slFadeIn .25s ease;
}
.sl-warn-popup {
  background: linear-gradient(145deg, #2d1f3d, #1a1a2e);
  border: 2px solid #f39c12; border-radius: 20px; padding: 30px;
  max-width: 400px; margin: 20px; position: relative;
  box-shadow: 0 0 40px rgba(243,156,18,0.3); animation: slScaleIn .25s ease;
}
.sl-warn-close {
  position: absolute; top: 10px; right: 14px; font-size: 1.8rem;
  cursor: pointer; color: rgba(255,255,255,0.5); background: none; border: none;
  transition: color .15s; line-height: 1;
}
.sl-warn-close:hover { color: #fff; }

/* ===== TEAMMATE DROPDOWN ===== */
.sl-teammate-wrap { position: relative; }
.sl-teammate-btn {
  background: transparent; border: none; cursor: pointer;
  font-size: 0.9rem; padding: 2px 6px; color: rgba(255,255,255,0.5);
  transition: transform .15s;
}
.sl-teammate-btn.open { transform: rotate(180deg); }
.sl-teammate-dropdown {
  position: absolute; top: calc(100% + 8px); left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(145deg, #1a3a6e, #0d2347);
  border: 1.5px solid rgba(255,255,255,0.25); border-radius: 12px;
  min-width: 260px; max-width: 300px; z-index: 500; overflow: hidden;
  box-shadow: 0 10px 40px rgba(0,0,0,0.4); animation: slDropdown .15s ease;
}
.sl-td-header { background: rgba(255,255,255,0.1); padding: 9px 14px; font-weight: 700; font-size: 0.85rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
.sl-td-opt {
  display: flex; align-items: center; gap: 10px; padding: 11px 14px; cursor: pointer;
  transition: background .15s; border-bottom: 1px solid rgba(255,255,255,0.08);
}
.sl-td-opt:last-child { border-bottom: none; }
.sl-td-opt.unlocked:hover { background: rgba(255,255,255,0.12); }
.sl-td-opt.locked { opacity: 0.45; cursor: not-allowed; }
.sl-td-opt.selected { background: rgba(46,204,113,0.2); border-left: 3px solid #2ecc71; }
.sl-td-icon { font-size: 1.3rem; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.08); border-radius: 50%; }
.sl-td-info { flex: 1; }
.sl-td-name { font-weight: 700; font-size: 0.88rem; }
.sl-td-skill { font-size: 0.72rem; color: rgba(255,255,255,0.6); margin-top: 1px; }
.sl-td-check { color: #2ecc71; font-weight: 900; }

@keyframes slFadeIn  { from { opacity:0 } to { opacity:1 } }
@keyframes slScaleIn { from { opacity:0; transform:scale(.85) } to { opacity:1; transform:scale(1) } }
@keyframes slDropdown { from { opacity:0; transform:translateX(-50%) translateY(-6px) } to { opacity:1; transform:translateX(-50%) translateY(0) } }

/* ===== RESPONSIVE ===== */
@media (max-width: 640px) {
  .sl-title { font-size: 2rem; }
  .sl-opts-row { grid-template-columns: 1fr; gap: 10px; }
  .sl-theme-grid { grid-template-columns: repeat(2, 1fr); }
  .sl-adv-btn { font-size: 0.8rem; }
}
@media (max-width: 400px) {
  .sl-theme-grid { grid-template-columns: 1fr; }
}
</style>

{{-- Bouton Menu fixe --}}
<a href="{{ route('menu') }}" class="sl-menu-btn">← {{ __('Menu') }}</a>

<div class="sl-wrap">

  {{-- HEADER --}}
  <div class="sl-header">
    <h1 class="sl-title">{{ __('MODE SOLO') }}</h1>
    <div class="sl-level-badge">
      🧠 {{ __('Votre niveau actuel') }}
      <span class="sl-level-num" id="display-choix-niveau">{{ $choix_niveau }}</span>
    </div>
  </div>

  {{-- FORMULAIRE PRINCIPAL --}}
  <form id="soloForm" action="{{ route('solo.start') }}" method="POST">
    @csrf
    <input type="hidden" name="niveau_joueur" id="niveau_joueur" value="{{ $niveau_selectionne ?? $choix_niveau }}">

    {{-- OPTIONS CARD --}}
    <div class="sl-card">
      <div class="sl-card-title">{{ __('Choisissez vos options puis un thème pour commencer la partie :') }}</div>

      <div class="sl-opts-row">
        {{-- Questions par manche --}}
        <div class="sl-opt">
          <div class="sl-opt-label">
            <span class="sl-opt-icon">📅</span>
            {{ __('Questions par manche') }}
          </div>
          <select name="nb_questions" id="nb_questions" class="sl-select">
            <option value="">-- {{ __('Choisissez') }} --</option>
            @foreach([10,20,30,40,50] as $n)
              <option value="{{ $n }}" {{ (isset($nb_questions) && $nb_questions == $n) ? 'selected' : '' }}>{{ $n }}</option>
            @endforeach
          </select>
        </div>

        {{-- Niveau sélectionné --}}
        <div class="sl-opt">
          <div class="sl-opt-label">
            <span class="sl-opt-icon">📊</span>
            {{ __('Niveau sélectionné') }}
          </div>
          <div class="sl-level-box">
            <div class="sl-level-display">
              <img src="{{ asset('images/brain.png') }}" alt="🧠" class="sl-brain-img">
              <span id="display-niveau">{{ $niveau_selectionne ?? $choix_niveau }}</span>
            </div>
            <button type="button" class="sl-level-btn" id="btn-niveau-moins"
                    {{ ($niveau_selectionne ?? $choix_niveau) <= 1 ? 'disabled' : '' }}>−</button>
          </div>
        </div>

        {{-- Choisir un adversaire --}}
        <div class="sl-opt">
          <div class="sl-opt-label">
            <span class="sl-opt-icon">👥</span>
            {{ __('Choisir un adversaire') }}
          </div>
          <a href="{{ route('solo.opponents') }}" class="sl-adv-btn">
            {{ __('Choisir un Adversaire') }}
          </a>
        </div>
      </div>

      {{-- Avatar Stratégique --}}
      <div class="sl-strat-row">
        <div class="sl-strat-label">
          <span class="sl-strat-icon">🛡</span>
          {{ __("Choix de l'Avatar Stratégique") }}
          <span style="color:rgba(255,255,255,0.4);font-size:0.78rem">({{ __('optionnel') }})</span>
        </div>

        <div class="sl-strat-bottom">
          @if(!empty($selSlug) || ($avatar_stratégique && strtolower($avatar_stratégique) !== 'aucun'))
            <span class="sl-strat-name" id="strat-name-display"
                  style="color: {{ $selTierColor }}">{{ $selAvatarName }}</span>
            @if($is_stratege && !empty($selected_teammate))
              <div class="sl-teammate-wrap">
                <button type="button" class="sl-teammate-btn" id="teammate_dropdown_btn" onclick="toggleTeammateDropdown()">🔽</button>
                <div id="teammate_dropdown" class="sl-teammate-dropdown" style="display:none;">
                  <div class="sl-td-header">👥 {{ __('Sélectionner un coéquipier') }}</div>
                  <div class="sl-td-opt {{ empty($selected_teammate) ? 'selected' : '' }} unlocked" data-slug="" data-locked="0">
                    <span class="sl-td-icon">❌</span>
                    <div class="sl-td-info"><span class="sl-td-name">{{ __('Aucun coéquipier') }}</span></div>
                    @if(empty($selected_teammate))<span class="sl-td-check">✓</span>@endif
                  </div>
                  @foreach($rare_avatars_data ?? [] as $slug => $ad)
                    @php $isU=$ad['unlocked']??false; $isSel=($selected_teammate??'')===$slug; @endphp
                    <div class="sl-td-opt {{ $isU?'unlocked':'locked' }} {{ $isSel?'selected':'' }}" data-slug="{{ $slug }}" data-locked="{{ $isU?'0':'1' }}">
                      <span class="sl-td-icon">{{ $ad['icon']??'🎯' }}</span>
                      <div class="sl-td-info">
                        <span class="sl-td-name">{{ $ad['name'] }} @if(!$isU)🔒@endif</span>
                        <span class="sl-td-skill">{{ ($ad['skills'][0]['icon']??'') }} {{ ($ad['skills'][0]['name']??'') }}</span>
                      </div>
                      @if($isSel)<span class="sl-td-check">✓</span>@endif
                    </div>
                  @endforeach
                </div>
              </div>
            @endif
          @else
            <span class="sl-strat-name" style="color:rgba(255,255,255,0.35);font-weight:500" id="strat-name-display">{{ __('Aucun') }}</span>
          @endif

          {{-- Ability cards — icône large + nom extrait --}}
          <div class="sl-skill-cards" id="sl-skills-badges">
            @foreach($selSkillsParsed as $sk)
              <div class="sl-skill-card" title="{{ $sk['emoji'] }} {{ $sk['name'] }}"
                   style="--tc:{{ $selTierColor }}">
                <div class="sl-skill-icon"
                     style="background:linear-gradient(135deg,{{ $selTierColor }}33 0%,{{ $selTierColor }}0d 100%);
                            border-color:{{ $selTierColor }}99;
                            box-shadow:0 4px 14px {{ $selTierColor }}40;">{{ $sk['emoji'] }}</div>
                <div class="sl-skill-name" style="color:{{ $selTierColor }}">{{ $sk['name'] }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- GALERIE AVATARS STRATÉGIQUES --}}
    <div class="sl-card">
      <div class="sl-av-header">
        <div class="sl-av-title">{{ __('Vos Avatar Stratégique') }}</div>
        <div class="sl-av-sub">{{ __('Sélectionnez vos avatars débloqués pour vous accompagner dans vos confrontations.') }}</div>
      </div>

      <div class="sl-av-gallery" id="avatar-gallery">
        @foreach($avatarsSorted as $slug => $av)
          @php
            $tier      = $av['tier'] ?? 'Rare';
            $tierColor = $tierColors[$tier] ?? '#f59e0b';
            $imgPath   = $av['path'] ?? "images/avatars/{$slug}.png";
            $isUnlocked = $av['unlocked'] ?? false;
            $isSelected = ($slug === $selSlug);
          @endphp
          <div class="sl-av-portrait {{ $isSelected ? 'selected' : '' }} {{ !$isUnlocked ? 'locked' : '' }}"
               data-slug="{{ $slug }}"
               data-unlocked="{{ $isUnlocked ? '1' : '0' }}"
               data-name="{{ strtoupper($av['name'] ?? $slug) }}"
               data-tier-color="{{ $tierColor }}"
               data-skills="{{ htmlspecialchars(json_encode($av['skills'] ?? []), ENT_QUOTES) }}"
               title="{{ $isUnlocked ? ($av['name'] ?? $slug) : __('Verrouillé — achetez en boutique') }}"
               onclick="selectStrategicAvatar(this)">
            <div class="sl-av-frame"
                 style="border-color:{{ $isSelected ? $tierColor : $tierColor.'26' }};
                        {{ $isSelected ? 'box-shadow:0 0 16px '.$tierColor.'55;' : '' }}">
              <img src="{{ asset($imgPath) }}" alt="{{ $av['name'] ?? $slug }}"
                   onerror="this.src='{{ asset('images/avatars/default.png') }}'">
              <div class="sl-av-frame-overlay"></div>
              <div class="sl-av-tier-pill" style="color:{{ $tierColor }}; border:1px solid {{ $tierColor }}55;">{{ $tier }}</div>
              @if($isSelected)
                <div class="sl-av-check" style="background:{{ $tierColor }}">✓</div>
              @endif
              @if(!$isUnlocked)<div class="sl-av-lock">🔒</div>@endif
            </div>
            <div class="sl-av-name" style="{{ $isSelected ? 'color:'.$tierColor.';font-weight:800;' : '' }}">{{ $av['name'] ?? $slug }}</div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- GRILLE THÈMES --}}
    <div class="sl-theme-grid">
      @foreach($themeList as $t)
        <button type="submit" class="sl-theme-btn" name="theme" value="{{ $t['key'] }}">
          <span class="sl-theme-emoji">{{ $t['emoji'] }}</span>
          <span class="sl-theme-info">
            <span class="sl-theme-name">{{ __($t['label']) }}</span>
            <span class="sl-theme-desc">{{ __($t['desc']) }}</span>
          </span>
          <span class="sl-theme-arrow">›</span>
        </button>
      @endforeach
    </div>
  </form>

  {{-- TIP --}}
  <div class="sl-tip">
    <span class="sl-tip-icon">ℹ️</span>
    <span><strong>{{ __('Conseil') }} :</strong> {{ __('Plus votre niveau augmente, plus les questions deviennent difficiles et rapportent plus de points !') }}</span>
  </div>

</div>

{{-- MESSAGE VALIDATION --}}
<div class="sl-validation-msg" id="validationMessage">
  {{ __('Choisissez le nombre de questions') }}.
</div>

{{-- POPUP AVERTISSEMENT AVATAR --}}
@if($showSoloWarning)
<div class="sl-warn-overlay" id="soloWarningOverlay">
  <div class="sl-warn-popup">
    <button class="sl-warn-close" onclick="closeSoloWarning()">×</button>
    <div style="font-size:3rem;text-align:center;margin-bottom:14px">⚠️</div>
    <div style="font-size:1.2rem;font-weight:700;text-align:center;margin-bottom:12px;color:#f39c12">{{ __('Avertissement Avatar Stratégique') }}</div>
    <div style="font-size:0.95rem;line-height:1.5;text-align:center;color:rgba(255,255,255,0.85)">{{ __($soloWarningMsg) }}</div>
  </div>
</div>
@endif

<script>
(function () {
  /* ====== NIVEAU STEPPER ====== */
  const niveauInput   = document.getElementById('niveau_joueur');
  const niveauDisplay = document.getElementById('display-niveau');
  const btnMoins      = document.getElementById('btn-niveau-moins');
  const maxNiveau     = {{ (int)$choix_niveau }};
  let   curNiveau     = {{ (int)($niveau_selectionne ?? $choix_niveau) }};

  function setNiveau(n) {
    n = Math.max(1, Math.min(maxNiveau, n));
    curNiveau = n;
    niveauInput.value = n;
    niveauDisplay.textContent = n;
    btnMoins.disabled = (n <= 1);
  }
  if (btnMoins) btnMoins.addEventListener('click', () => setNiveau(curNiveau - 1));

  /* ====== RESTAURER NB_QUESTIONS ====== */
  const nbQSel = document.getElementById('nb_questions');
  const saved  = sessionStorage.getItem('solo_nb_questions');
  if (saved && nbQSel) nbQSel.value = saved;
  if (nbQSel) nbQSel.addEventListener('change', () => sessionStorage.setItem('solo_nb_questions', nbQSel.value));

  /* ====== VALIDATION AU SUBMIT ====== */
  const validMsg = document.getElementById('validationMessage');
  document.querySelectorAll('.sl-theme-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!nbQSel || !nbQSel.value) {
        e.preventDefault();
        validMsg.style.display = 'block';
        setTimeout(() => { validMsg.style.display = 'none'; }, 2200);
        return false;
      }
      sessionStorage.removeItem('solo_nb_questions');
    });
  });

  /* ====== AVATAR STRATÉGIQUE SELECTION ====== */
  const selectUrl  = '{{ route("avatars.select") }}';
  const csrfToken  = '{{ csrf_token() }}';

  /* ── Helpers ── */
  function buildSkillCards(badgesEl, tierColor, skills) {
    badgesEl.innerHTML = '';
    skills.slice(0, 3).forEach(sk => {
      const withColon = sk.match(/^(\S+)\s+(.+?)(?=\s*:)/u);
      const fallback  = sk.match(/^(\S+)\s+(.+)$/u);
      const m = withColon || fallback;
      const emoji = m ? m[1] : '⚡';
      const name  = m ? m[2].trim() : sk;

      const card  = document.createElement('div');
      card.className = 'sl-skill-card';
      card.title = name;

      const icon = document.createElement('div');
      icon.className = 'sl-skill-icon';
      icon.style.cssText = `background:linear-gradient(135deg,${tierColor}33 0%,${tierColor}0d 100%);border-color:${tierColor}99;box-shadow:0 4px 14px ${tierColor}40`;
      icon.textContent = emoji;

      const lbl = document.createElement('div');
      lbl.className = 'sl-skill-name';
      lbl.style.color = tierColor;
      lbl.textContent = name;

      card.appendChild(icon);
      card.appendChild(lbl);
      badgesEl.appendChild(card);
    });
  }

  function applyAvatarUI(el, select) {
    const tierColor = el.dataset.tierColor || '#ffffff';
    const frame     = el.querySelector('.sl-av-frame');
    const nm        = el.querySelector('.sl-av-name');
    const nameEl    = document.getElementById('strat-name-display');
    const badgesEl  = document.getElementById('sl-skills-badges');

    // Reset ALL portraits first
    document.querySelectorAll('.sl-av-portrait').forEach(p => {
      p.classList.remove('selected');
      const c = p.querySelector('.sl-av-check');
      if (c) c.remove();
      const f = p.querySelector('.sl-av-frame');
      if (f) { const tc = p.dataset.tierColor || '#fff'; f.style.borderColor = tc + '26'; f.style.boxShadow = 'none'; }
      const n = p.querySelector('.sl-av-name');
      if (n) { n.style.color = ''; n.style.fontWeight = ''; }
    });

    if (select) {
      el.classList.add('selected');
      if (frame) { frame.style.borderColor = tierColor; frame.style.boxShadow = `0 0 16px ${tierColor}55`; }
      // Checkmark dans la frame
      const chk = document.createElement('div');
      chk.className = 'sl-av-check'; chk.textContent = '✓'; chk.style.background = tierColor;
      if (frame) frame.appendChild(chk); else el.appendChild(chk);
      // Nom portrait coloré
      if (nm) { nm.style.color = tierColor; nm.style.fontWeight = '800'; }
      // Nom strat-row
      if (nameEl) { nameEl.textContent = el.dataset.name; nameEl.style.color = tierColor; nameEl.style.fontWeight = '800'; nameEl.style.opacity = '1'; }
      // Skills
      if (badgesEl) {
        let skills = [];
        try { skills = JSON.parse(el.dataset.skills || '[]'); } catch(e) {}
        buildSkillCards(badgesEl, tierColor, skills);
      }
    } else {
      // Désélection
      if (nameEl) { nameEl.textContent = '{{ __("Aucun") }}'; nameEl.style.color = 'rgba(255,255,255,0.35)'; nameEl.style.fontWeight = '500'; }
      if (badgesEl) badgesEl.innerHTML = '';
    }
  }

  window.selectStrategicAvatar = function (el) {
    if (el.dataset.unlocked === '0') {
      window.location.href = '{{ route("boutique") }}?tab=avatars';
      return;
    }
    const slug        = el.dataset.slug;
    const wasSelected = el.classList.contains('selected');

    // ① Mise à jour UI IMMÉDIATE (optimiste) — n'attend pas le serveur
    applyAvatarUI(el, !wasSelected);

    // ② Sauvegarde en arrière-plan — le résultat n'impacte pas l'UI
    fetch(selectUrl, {
      method:  'POST',
      redirect: 'manual',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body:    new URLSearchParams({ _token: csrfToken, avatar: slug, from: 'solo' })
    }).catch(() => { /* échec silencieux — l'UI est déjà mise à jour */ });
  };

  /* ====== TEAMMATE DROPDOWN ====== */
  let dropdownOpen = false;
  window.toggleTeammateDropdown = function () {
    const dd  = document.getElementById('teammate_dropdown');
    const btn = document.getElementById('teammate_dropdown_btn');
    if (!dd || !btn) return;
    dropdownOpen = !dropdownOpen;
    dd.style.display = dropdownOpen ? 'block' : 'none';
    btn.classList.toggle('open', dropdownOpen);
  };

  const dd = document.getElementById('teammate_dropdown');
  if (dd) {
    dd.addEventListener('click', function (e) {
      const opt = e.target.closest('.sl-td-opt');
      if (!opt || opt.dataset.locked === '1') return;
      const slug = opt.dataset.slug || '';
      fetch('{{ route("solo.set-teammate") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ teammate: slug })
      }).then(r => r.json()).then(data => {
        if (data.success) {
          document.querySelectorAll('.sl-td-opt').forEach(o => {
            o.classList.remove('selected');
            const c = o.querySelector('.sl-td-check');
            if (c) c.remove();
          });
          const sel = slug === '' ? dd.querySelector('.sl-td-opt:first-child') : dd.querySelector(`.sl-td-opt[data-slug="${slug}"]`);
          if (sel) {
            sel.classList.add('selected');
            const c = document.createElement('span'); c.className = 'sl-td-check'; c.textContent = '✓';
            sel.appendChild(c);
          }
          toggleTeammateDropdown();
        }
      });
    });
  }

  document.addEventListener('click', function (e) {
    const ddEl  = document.getElementById('teammate_dropdown');
    const ddBtn = document.getElementById('teammate_dropdown_btn');
    if (ddEl && ddBtn && !ddEl.contains(e.target) && !ddBtn.contains(e.target)) {
      ddEl.style.display = 'none';
      if (ddBtn) ddBtn.classList.remove('open');
      dropdownOpen = false;
    }
  });

  /* ====== WARNING CLOSE ====== */
  window.closeSoloWarning = function () {
    const ov = document.getElementById('soloWarningOverlay');
    if (ov) { ov.style.opacity = '0'; ov.style.transition = 'opacity .25s'; setTimeout(() => ov.remove(), 260); }
  };
})();
</script>

@endsection
