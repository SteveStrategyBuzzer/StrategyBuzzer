@extends('layouts.app')

@section('title', __('Profile du Joueur') . ' — StrategyBuzzer')

@section('content')
@php
    use Symfony\Component\Intl\Countries;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Auth;
    use Carbon\Carbon;

    $s = $settings ?? [];

    // Normalise chemins Images -> images + fabrique URL web si nécessaire
    $normalize = function (?string $path) {
        if (!$path) return null;
        $fixed = preg_replace('#^Images/#', 'images/', $path);
        if (str_starts_with($fixed, 'http') || str_starts_with($fixed, '/')) return $fixed;
        return asset(ltrim($fixed, '/'));
    };

    // Avatar principal
    $avatarUrlRaw = data_get($s, 'avatar.url');
    $avatarUrl    = $normalize($avatarUrlRaw ?: '');
    $avatarId     = data_get($s, 'avatar.id');
    $hasAvatar    = !empty($avatarId) || !empty($avatarUrlRaw);

    // Avatar stratégique
    $stratUrlRaw  = data_get($s, 'strategic_avatar.url');
    $stratUrl     = $normalize($stratUrlRaw ?: '');
    $stratId      = data_get($s, 'strategic_avatar.id');
    $stratName    = data_get($s, 'strategic_avatar.name', '');
    $hasStrat     = !empty($stratId) && !empty($stratUrlRaw);

    // Infos joueur
    $player       = Auth::user();
    $playerId     = $player?->id;
    $playerName   = $player?->name ?? __('Invité');
    $playerEmail  = $player?->email ?? '—';

    // Langue & Pays
    $allLanguages = config('languages.supported');
    $currentLangCode = $player?->preferred_language ?? config('languages.default', 'fr');
    $currentLangData = $allLanguages[$currentLangCode] ?? $allLanguages['fr'];
    $language = $currentLangData['native_name'];
    $locale   = $currentLangCode;

    try {
        $countries = Countries::getNames($locale);
        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);
    } catch (\Throwable $e) {
        // fallback si jamais Countries::getNames plante
        $countries = [
            'FR' => 'France',
            'CA' => 'Canada',
            'US' => 'États-Unis',
            'BE' => 'Belgique',
            'CH' => 'Suisse',
        ];
    }

    $currentCountry = strtoupper((string) data_get($settings, 'country', ''));

    // Audio / thème / visibilité
    $ambianceOn = (bool) data_get($s, 'sound.ambiance', true);
    $buzzerOn   = (bool) data_get($s, 'sound.buzzer', true);
    $resultsOn  = (bool) data_get($s, 'sound.results', true); // Gameplay activé par défaut

    // BUG FIX #8: Valeurs par défaut pour les nouveaux comptes
    $buzzerId = data_get($s, 'sound.buzzer_id', 'buzzer_default_1'); // Par défaut: Buzzer 1
    $correctSoundId = data_get($s, 'sound.correct_sound_id', 'correct_default'); // Par défaut: Son correct
    $wrongSoundId = data_get($s, 'sound.wrong_sound_id', 'wrong_default'); // Par défaut: Son incorrect
    $musicId  = data_get($s, 'sound.music_id', 'strategybuzzer');     // Par défaut: StrategyBuzzer
    $gameplayMusicId = data_get($s, 'gameplay.music_id', $musicId ?: 'strategybuzzer'); // Par défaut = ambiance ou StrategyBuzzer
    $theme    = data_get($s, 'theme.style', 'Classique');

    $unlockedBuzzers = data_get($s, 'unlocked.buzzers', []) ?: [
        ['id'=>'buzzer_default_1','label'=>__('Buzzer Par Défaut 1')],
        ['id'=>'buzzer_default_2','label'=>__('Buzzer Par Défaut 2')],
        ['id'=>'classic_beep','label'=>__('Classique')],
        ['id'=>'retro','label'=>__('Rétro')],
        ['id'=>'laser','label'=>__('Laser')],
    ];
    $unlockedCorrectSounds = data_get($s, 'unlocked.correct_sounds', []) ?: [
        ['id'=>'correct_default','label'=>__('Par défaut')],
        ['id'=>'correct_chime','label'=>__('Carillon')],
        ['id'=>'correct_success','label'=>__('Succès')],
        ['id'=>'correct_coin','label'=>__('Pièce')],
    ];
    $unlockedWrongSounds = data_get($s, 'unlocked.wrong_sounds', []) ?: [
        ['id'=>'wrong_default','label'=>__('Par défaut')],
        ['id'=>'wrong_buzzer','label'=>__('Buzzer erreur')],
        ['id'=>'wrong_fail','label'=>__('Échec')],
        ['id'=>'wrong_bonk','label'=>__('Bonk')],
    ];
    $unlockedMusic = data_get($s, 'unlocked.music', []) ?: [
        ['id'=>'strategybuzzer', 'label'=>__('StrategyBuzzer')],
        ['id'=>'fun_01','label'=>__('Fun 01')],
        ['id'=>'chill','label'=>__('Chill')],
        ['id'=>'punchy','label'=>__('Punchy')],
    ];
    // Thèmes : identifiants canoniques (stockés en DB)
    $themeOptions = [
        'Classique' => __('Classique'),
        'Fun' => __('Fun'),
        'Intello' => __('Intello'),
        'Party' => __('Party'),
        'Punchy' => __('Punchy')
    ];

    $showInLeague = data_get($s, 'show_in_league', 'Oui');
    $showOnline   = (bool) data_get($s, 'show_online', true);

    // “Maître du jeu” & niveaux
    $grade        = (string) data_get($s, 'gm.grade', __('Rookie'));
    $soloLevel    = max(1, (int) data_get($s, 'choix_niveau', session('choix_niveau', 1))); // Minimum niveau 1, synchronisé avec session
    $duoLevel     = max(0, (int) data_get($s, 'gm.duo_level', 0)); // Niveau Duo
    $leagueLevel  = max(0, (int) data_get($s, 'gm.league_level', 0));

    // ID public (par défaut = nom)
    $pseudonym = trim((string) data_get($s, 'pseudonym', ''));
    if ($pseudonym === '') $pseudonym = $playerName !== __('Invité') ? $playerName : '';

    // URLs de choix d’avatars (retour vers profil)
    $backToProfile        = route('profile.show');
    $avatarsUrl           = route('avatars', ['from' => $backToProfile]);
    $avatarsStrategicUrl  = route('avatars.strategic', ['back' => $backToProfile]);

    // === Vies & compte à rebours ===
    $lifeService = app(\App\Services\LifeService::class);
    $user        = $player;
    $lifeMax     = (int) config('game.life_max', 5);
    $hasInfinite = $user && !empty($user->infinite_lives_until) && now()->lt($user->infinite_lives_until);

    // Libellé "Vies"
    $currentLivesLabel = $hasInfinite
    ? ('∞ / ' . $lifeMax)
    : ((string) ($user?->lives ?? 0) . ' / ' . $lifeMax);

    // "Vie dans : ..."
    $lifeCountdown = $hasInfinite
        ? Carbon::parse($user->infinite_lives_until)->diff(now())->format('%Hh %Im %Ss')
        : ($user ? $lifeService->timeUntilNextRegen($user) : null);

    // Bust de cache après sélection d’avatar
    $avatarBust = session('avatar_updated') ? ('?t='.time()) : '';
@endphp

<style>
  /* ===== Layout de base ===== */
  .sb-wrap, .sb-wrap * { box-sizing: border-box; min-width: 0; }
  .sb-page { overflow-x: hidden; }
  .sb-wrap { max-width:1280px; margin:0 auto; padding:20px; font-size:14px; }
  .sb-wrap > h1 { font-size:28px; font-weight:800; text-align:center; margin-bottom:14px; }

  /* ===== Panneaux ===== */
  .sb-panel {
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.18);
    border-radius:16px;
    padding:14px;
    height:100%;
  }

  /* ===== Grille principale (3 colonnes) ===== */
  .sb-three{
    display:grid !important;
    grid-template-columns:
      minmax(240px, calc(25% - 8px))
      minmax(240px, calc(25% - 8px))
      minmax(400px, calc(50% - 8px));
    gap:12px;
    align-items:start;
  }
  @media (max-width:900px){ .sb-three{ grid-template-columns:1fr 1fr; } .sb-b3{ grid-column:1 / -1; } }
  @media (max-width:560px){ .sb-three{ grid-template-columns:1fr; } .sb-b3{ grid-column:auto; } }
  
  /* ===== Fix clavier mobile - empêche le chevauchement ===== */
  .keyboard-open .sb-three {
    display: flex !important;
    flex-direction: column !important;
  }
  .keyboard-open .sb-panel {
    width: 100% !important;
    max-width: 100% !important;
  }
  
  /* Mode paysage - Pleine largeur */
  @media (orientation: landscape) and (max-height: 500px) {
    .sb-wrap { 
      max-width: 100vw !important; 
      padding: 10px !important; 
      margin: 0 !important;
    }
    .sb-three { 
      grid-template-columns: 1fr 1fr 1fr !important; 
      gap: 8px !important;
    }
  }

  /* ===== Lignes de champs (labels + valeurs) ===== */
  .sb-row{
    display:grid;
    grid-template-columns:110px 1fr; /* largeur standard des labels */
    gap:6px;
    align-items:center;
  }

  /* ===== Vignette avatar ===== */
  .sb-thumb{
    width:120px; height:120px; border-radius:12px; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
    background:rgba(255,255,255,.05);
    box-shadow:0 0 0 2px rgba(255,255,255,.12) inset;
    margin:0 0 10px 0; position:relative; z-index:1; cursor:pointer;
  }
  .sb-thumb img{ width:100%; height:100%; object-fit:cover; display:block; }

  /* ===== Typo/états ===== */
  .sb-muted{ opacity:.8; }
  .sb-title{ font-size:18px; font-weight:800; margin:8px 0 10px; }
  .sb-rows{ display:flex; flex-direction:column; gap:8px; }
  .sb-k{ opacity:.85; font-weight:600; }
  .sb-v{ opacity:.95; text-align:left; word-break:normal; } /* pas de cassures bizarres */

  /* ===== Inputs ===== */
  .sb-input{
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.22);
    border-radius:10px;
    padding:.55rem .7rem;
    color:#fff;
    width:100%;
    max-width:100%;         /* jamais plus large que la colonne */
    min-width:140px;
    text-align:left;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }

  /* ===== Inlines / toggles / sélecteurs ===== */
  .sb-inline{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .sb-toggle{ display:flex; align-items:center; gap:.45rem; }
  .sb-chooser{
    position:relative; display:inline-flex; align-items:center; justify-content:center;
    width:30px; height:28px; border:1px solid rgba(255,255,255,.35); border-radius:8px;
    background:rgba(255,255,255,.08); cursor:pointer; font-weight:700; user-select:none;
  }
  .sb-chooser select{ position:absolute; inset:0; opacity:0; width:100%; height:100%; cursor:pointer; }
  .sb-btn{ background:#fff; color:#0A2C66; border:0; border-radius:10px; padding:.6rem 1rem; font-weight:700; }

  /* ===== Bulle 1 : labels alignés à gauche ===== */
  .sb-three > .sb-panel:first-child .sb-k{ text-align:left; }

  /* ===== Bulle 1 : ligne "ID joueur" seulement ===== */
  .sb-three > .sb-panel:first-child .sb-rows > .sb-row:first-child{
    display:grid;
    grid-template-columns:92px 1fr; /* label compact = 92px, le reste pour le champ */
    gap:6px;                        /* rapproche le champ du label */
    align-items:center;
  }
  .sb-three > .sb-panel:first-child .sb-rows > .sb-row:first-child .sb-v{
    padding:0 !important;
    margin:0 !important;
    white-space:normal !important;  /* autorise la colonne à s’étirer */
  }
  .sb-three > .sb-panel:first-child #idPublic{
    display:block;
    width:100% !important;
    max-width:100% !important;
    min-width:0 !important;
    box-sizing:border-box;
    white-space:nowrap;
    text-overflow:ellipsis;
    overflow:hidden;
    padding:.45rem .6rem;           /* gain de quelques px visuels */
  }

  /* ===== Compte à rebours d’une seule ligne ===== */
  .sb-three > .sb-panel:first-child #sb-countdown{ white-space:nowrap; }
/* === Spécial Bulle 3 : pousser uniquement les menus déroulants à droite === */
.sb-b3 .sb-row .sb-v .sb-inline {
  justify-content: flex-end; /* pousse les menus déroulants à droite */
}

.sb-b3 .sb-row .sb-v .sb-inline label {
  margin-right: auto; /* garde les checkbox alignées à gauche */
}

/* ===== Sélecteurs audio custom dépliables ===== */
.sb-audio-selector {
  position: relative;
  z-index: 10;
}

.sb-selector-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.35);
  border-radius: 10px;
  padding: 8px 12px;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  min-width: 140px;
  text-align: left;
}

.sb-selector-toggle:hover {
  background: rgba(255,255,255,.12);
  border-color: rgba(255,255,255,.5);
}

.sb-selector-toggle:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}

.sb-selector-toggle[aria-expanded="true"] .sb-selector-arrow {
  transform: rotate(180deg);
}

.sb-selector-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sb-selector-arrow {
  transition: transform 0.2s ease;
  font-size: 12px;
}

/* Variante compacte: juste la flèche, même taille que .sb-chooser (30px × 28px) */
.sb-audio-selector.compact .sb-selector-toggle {
  min-width: 30px;
  width: 30px;
  height: 28px;
  padding: 0;
  gap: 0;
  justify-content: center;
}

.sb-audio-selector.compact .sb-selector-label {
  display: none;
}

.sb-selector-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  right: 0;
  background: rgb(10, 44, 102);
  border: 1px solid rgba(255,255,255,.35);
  border-radius: 10px;
  padding: 6px;
  min-width: 220px;
  max-width: 280px;
  max-height: 300px;
  z-index: 100;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0,0,0,.5);
}

.sb-selector-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s ease;
  margin-bottom: 4px;
  background: rgb(10, 44, 102);
}

.sb-selector-option:last-child {
  margin-bottom: 0;
}

.sb-selector-option:hover {
  background: rgb(20, 60, 120);
}

.sb-selector-option:focus-within {
  background: rgb(25, 65, 125);
  outline: 2px solid rgba(255,255,255,.5);
  outline-offset: -2px;
}

.sb-selector-option input[type="radio"] {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  cursor: pointer;
  accent-color: #fff;
}

.sb-selector-option input[type="radio"]:checked + .sb-option-text {
  font-weight: 700;
}

.sb-option-text {
  flex: 1;
  font-size: 14px;
  color: #fff;
}

.sb-option-speaker {
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.25);
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 1.2rem;
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.sb-option-speaker:hover {
  background: rgba(255,255,255,.2);
  border-color: rgba(255,255,255,.4);
  transform: scale(1.1);
}

.sb-option-speaker:active {
  transform: scale(0.95);
}

/* Scrollbar pour dropdown */
.sb-selector-dropdown::-webkit-scrollbar {
  width: 8px;
}

.sb-selector-dropdown::-webkit-scrollbar-track {
  background: rgba(255,255,255,.05);
  border-radius: 4px;
}

.sb-selector-dropdown::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,.2);
  border-radius: 4px;
}

.sb-selector-dropdown::-webkit-scrollbar-thumb:hover {
  background: rgba(255,255,255,.3);
}

</style>

<div class="min-h-screen bg-[#0A2C66] text-white sb-page">
  <div class="sb-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h1 style="margin:0;">{{ __('Profile du Joueur') }}</h1>
      @if($hasAvatar && !empty(trim($pseudonym ?? '')))
        <a href="{{ route('menu') }}" style="
          background: white;
          color: #0A2C66;
          padding: 10px 20px;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 700;
          font-size: 1rem;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          gap: 6px;
        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(255,255,255,0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
          {{ __('Menu') }}
        </a>
      @else
        <div style="
          background: #94a3b8;
          color: #475569;
          padding: 10px 20px;
          border-radius: 8px;
          font-weight: 700;
          font-size: 1rem;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          cursor: not-allowed;
          opacity: 0.6;
        " title="{{ __('Complétez votre profil (avatar + pseudonym) pour accéder au menu') }}">
          🔒 {{ __('Menu') }}
        </div>
      @endif
    </div>

    <div class="sb-three">
{{-- =================== BULLE 1 (25%) : Avatar principal =================== --}}
<div class="sb-panel">
  <div class="sb-muted mb-1">{{ __('Avatar principal') }}</div>

  <a class="sb-thumb" href="{{ $avatarsUrl }}" title="{{ __('Choisir / Modifier l\'avatar') }}">
@if(session('avatar_image'))
  <img src="{{ session('avatar_image') }}" alt="{{ __('Avatar principal') }}" style="width:120px;height:auto;">
@elseif($hasAvatar)
  <img src="{{ ($avatarUrl ?? asset('images/avatars/default.png')) . $avatarBust }}" alt="{{ __('Avatar principal') }}">
@else
  <span class="underline" style="font-size:13px">{{ __('Choisir avatar') }}</span>
@endif

  </a>

  <div class="sb-rows">
    <div class="sb-row">
      <div class="sb-k" style="cursor: pointer;" onclick="document.getElementById('idPublic').focus()" title="{{ __('Cliquer pour modifier') }}">{{ __('ID joueur') }}</div>
      <div class="sb-v">
        <input class="sb-input" type="text" name="pseudonym" form="profileForm"
               placeholder="{{ __('Votre ID public') }}" value="{{ $pseudonym }}" id="idPublic" maxlength="10">
      </div>
    </div>

    <div class="sb-row">
      <div class="sb-k">{{ __('Langue') }}</div>
      <div class="sb-v" id="apercu-lang">{{ $language }}</div>
    </div>

    <div class="sb-row">
      <div class="sb-k">{{ __('Pays') }}</div>
      <div class="sb-v" id="apercu-pays">
        {{ $currentCountry && isset($countries[$currentCountry]) ? $countries[$currentCountry] : '—' }}
      </div>
    </div>

    <div class="sb-row">
      <div class="sb-k">{{ __('Thème') }}</div>
      <div class="sb-v" id="apercu-theme">{{ $themeOptions[$theme] ?? $theme }}</div>
    </div>

    <div class="sb-row">
      <div class="sb-k">{{ __('Ambiance') }}</div>
      <div class="sb-v" id="apercu-ambiance">
        {{ $musicId ? collect($unlockedMusic)->firstWhere('id',$musicId)['label'] ?? __('Aucun') : __('Aucun') }}
      </div>
    </div>


<div class="sb-row">
  <div class="sb-k">{{ __('Gameplay') }}</div>
  <div class="sb-v" id="apercu-gameplay">
    @if($resultsOn && !empty($gameplayMusicId))
      {{ collect($unlockedMusic)->firstWhere('id', $gameplayMusicId)['label'] ?? __('Aucun') }}
    @else
      {{ __('Désactivé') }}
    @endif
  </div>
</div>

    {{-- === DÉPLACÉ DE LA BULLE 2 → BULLE 1 === --}}
@php
  $currentLives = $user && isset($user->lives) ? (int)$user->lives : $lifeMax;
  $livesLabel   = $hasInfinite ? '∞ / ' . $lifeMax : "{$currentLives} / {$lifeMax}";
  $target       = $hasInfinite ? $user->infinite_lives_until : ($user?->next_life_regen);
@endphp

<div class="sb-row">
  <div class="sb-k">{{ __('Vies') }}</div>
  <div class="sb-v" id="sb-lives-label">{{ $livesLabel }}</div>
</div>

<div class="sb-row">
  <div class="sb-k">{{ __('Vie dans') }}</div>
  <div class="sb-v">
    <span
      id="sb-countdown"
      data-type="{{ $hasInfinite ? 'infinite_pack' : (($user && $user->lives < $lifeMax && $user->next_life_regen) ? 'life_regen' : 'idle') }}"
      data-target="{{ $target ? \Carbon\Carbon::parse($target)->toIso8601String() : '' }}"
      data-life-max="{{ $lifeMax }}"
      data-regen-mins="{{ (int) config('game.life_regen_minutes', 60) }}"
      data-wait-text="{{ (string) config('game.life_wait_text', '—') }}"
      data-lives="{{ $currentLives }}"
    >
      @if($hasInfinite)
        {{ \Carbon\Carbon::parse($user->infinite_lives_until)->diff(now())->format('%Hh %Im %Ss') }}
      @elseif($user && $user->lives >= $lifeMax)
        {{ config('game.life_wait_text', '—') }}
      @else
        {{ $lifeService->timeUntilNextRegen($user) ?? '—' }}
      @endif
    </span>
  </div>
</div>


    {{-- === FIN DÉPLACEMENT === --}}
  </div>
</div>

{{-- ==================== BULLE 2 (25%) : Avatar stratégique ==================== --}} 
<div class="sb-panel sb-b2">

  <div class="sb-muted mb-1">{{ __('Avatar stratégique') }}</div>

  {{-- Vignette avatar stratégique --}}
  <a class="sb-thumb" 
     href="{{ route('avatars', ['from' => 'profile']) }}" 
     title="{{ __('Choisir / Modifier l\'avatar stratégique') }}">
      @if($stratUrl ?? false)
          <img src="{{ $stratUrl . $avatarBust }}" alt="{{ __('Avatar stratégique') }}">
      @else
          <span class="underline" style="font-size:13px;">{{ __('Choisir avatar stratégique') }}</span>
      @endif
  </a>

  <div class="sb-rows">

{{-- Nom avatar + Skills --}}
<div class="sb-row" style="flex-direction:column; align-items:flex-start; text-align:left; gap:4px;">
  
  {{-- Label --}}
  <div class="sb-k" style="font-weight:600; opacity:.9;">{{ __('Nom avatar') }}</div>

  {{-- Badge du nom --}}
  @php
    // Traduction des tiers avec couleurs
    $tierLabels = [
        'Rare' => __('Rare'),
        'Épique' => __('Épique'),
        'Légendaire' => __('Légendaire')
    ];
    $tierColors = ['Rare' => '#1E90FF', 'Épique' => '#800080', 'Légendaire' => '#FFD700'];
    $tier   = $stratTier ?? null;
    $color  = $tierColors[$tier] ?? '#ccc';
  @endphp
  <div style="display:inline-block; background:#fff; color:{{ $color }};
              padding:4px 16px; border-radius:6px; font-size:13px; font-weight:bold;
              border:2px solid {{ $color }}; white-space: nowrap;">
    {{ $stratName ?? '—' }}
  </div>

  {{-- Skills list alignés à gauche --}}
  @if(!empty($stratSkills))
    <div style="margin-top:4px; display:flex; flex-direction:column; gap:2px; font-size:12px; text-align:left;">
      @foreach($stratSkills as $skill)
        <span>• {{ __($skill) }}</span>
      @endforeach
    </div>
  @endif

</div>

    {{-- Niveau solo --}}
    <div class="sb-row">
      <div class="sb-k" style="text-align:left;">{{ __('Niveau solo') }}</div>
      <div class="sb-v" style="text-align:right;">{{ $soloLevel ?? 0 }}</div>
    </div>

    {{-- Niveau Duo --}}
    <div class="sb-row">
      <div class="sb-k" style="text-align:left;">{{ __('Niveau Duo') }}</div>
      <div class="sb-v" style="text-align:right;">{{ $duoLevel ?? 0 }}</div>
    </div>

    {{-- Niveau ligue --}}
    <div class="sb-row">
      <div class="sb-k" style="text-align:left;">{{ __('Niveau ligue') }}</div>
      <div class="sb-v" style="text-align:right;">{{ $leagueLevel ?? 0 }}</div>
    </div>

    {{-- Maître du jeu --}}
    <div class="sb-row">
      <div class="sb-k" style="text-align:left;">{{ __('Maître du jeu') }}</div>
      <div class="sb-v" style="text-align:right;">{{ $grade ?? __('Rookie') }}</div>
    </div>

    {{-- Compte --}}
    <div class="sb-row" style="margin-top:10px;">
      <div class="sb-k" style="text-align:left;">{{ __('Compte') }}</div>
      <div class="sb-v" style="grid-column: span 2; text-align:left; display:flex; flex-direction:column; gap:2px;">
        @if($player?->provider === 'facebook')
          <span>{{ __('Facebook') }}</span>
        @elseif($player?->provider === 'google')
          <span>{{ __('Google') }}</span>
        @else
          <span>{{ __('Code') }} #{{ $player?->player_code ?? 'SB-XXXX' }}</span>
        @endif
        <span>{{ $playerEmail ?? '—' }}</span>
      </div>
    </div>

  </div>
</div>

{{-- =================== BULLE 3 (50%) : Réglages =================== --}}
<form id="profileForm" method="POST"
      action="{{ Route::has('profile.update') ? route('profile.update') : '#' }}"
      class="sb-panel sb-b3">
  @csrf
  <div class="sb-title">{{ __('Réglages') }}</div>

  <div class="sb-rows">
    {{-- Langue --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Langue') }}</div>
      <div class="sb-v" style="text-align:right;">
        <span class="sb-chooser" title="{{ __('Choisir') }}">▼
          <select name="language" id="sel-lang">
            @foreach($allLanguages as $code => $langData)
              <option value="{{ $code }}" @selected($currentLangCode === $code)>
                {{ $langData['native_name'] }}
              </option>
            @endforeach
          </select>
        </span>
      </div>
    </div>

    {{-- Pays --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Pays') }}</div>
      <div class="sb-v" style="text-align:right;">
        <span class="sb-chooser" title="{{ __('Choisir') }}">▼
          <select name="country" id="sel-pays">
            <option value="">—</option>
            @foreach($countries as $code => $name)
              <option value="{{ $code }}" @selected($currentCountry === $code)>{{ $name }}</option>
            @endforeach
          </select>
        </span>
      </div>
    </div>

    {{-- Visible en ligue --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Visible en ligue') }}</div>
      <div class="sb-v" style="text-align:right;">
        <span class="sb-chooser" title="{{ __('Choisir') }}">▼
          <select name="show_in_league" id="sel-ligue">
            <option value="Oui" @selected($showInLeague==='Oui')>{{ __('Oui') }}</option>
            <option value="Non" @selected($showInLeague==='Non')>{{ __('Non') }}</option>
          </select>
        </span>
      </div>
    </div>

    {{-- Thème --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Thème') }}</div>
      <div class="sb-v" style="text-align:right;">
        <span class="sb-chooser" title="{{ __('Choisir') }}">▼
          <select name="theme[style]" id="sel-theme">
            @foreach($themeOptions as $themeId => $themeLabel)
              <option value="{{ $themeId }}" @selected($theme === $themeId)>{{ $themeLabel }}</option>
            @endforeach
          </select>
        </span>
      </div>
    </div>

    {{-- 🎵 Musique --}}
    <div style="text-align:center; font-weight:bold; margin:10px 0; opacity:.9;">
      {{ __('🎵 Musique') }}
    </div>

    {{-- Ambiance --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Ambiance') }}</div>
      <div class="sb-v" style="display:flex; align-items:center; justify-content:flex-end; gap:10px;">
        <label class="sb-toggle" style="margin-right:auto;">
          <input type="checkbox" name="options[ambiance]" value="1" id="chk-amb" {{ $ambianceOn ? 'checked' : '' }}>
          <span>{{ __('Activer') }}</span>
        </label>
        
        {{-- Sélecteur custom dépliable (compact: juste la flèche) --}}
        <div class="sb-audio-selector compact" id="ambiance-selector">
          <button type="button" class="sb-selector-toggle" data-selector="ambiance" 
                  role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="ambiance-dropdown">
            <span class="sb-selector-label">{{ collect($unlockedMusic)->firstWhere('id', (string)$musicId)['label'] ?? __('Choisir') }}</span>
            <span class="sb-selector-arrow">▼</span>
          </button>
          
          <div class="sb-selector-dropdown" id="ambiance-dropdown" data-dropdown="ambiance" 
               role="listbox" style="display:none;">
            <input type="hidden" name="sound[music_id]" id="sel-amb" value="{{ $musicId }}">
            @foreach($unlockedMusic as $m)
              <label class="sb-selector-option" data-value="{{ $m['id'] }}" data-label="{{ $m['label'] }}" role="option">
                <input type="radio" name="ambiance_choice" value="{{ $m['id'] }}" {{ (string)$musicId === (string)$m['id'] ? 'checked' : '' }}>
                <span class="sb-option-text">{{ $m['label'] }}</span>
                <button type="button" class="sb-option-speaker" data-audio="{{ $m['id'] }}" data-duration="10000" title="{{ __('Tester') }}">🔊</button>
              </label>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- Gameplay --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Gameplay') }}</div>
      <div class="sb-v" style="display:flex; align-items:center; justify-content:flex-end; gap:10px;">
        <label class="sb-toggle" style="margin-right:auto;">
          <input type="checkbox" name="options[results]" value="1" id="chk-gameplay" {{ $resultsOn ? 'checked' : '' }}>
          <span>{{ __('Activer') }}</span>
        </label>
        
        {{-- Sélecteur custom dépliable (compact: juste la flèche) --}}
        <div class="sb-audio-selector compact" id="gameplay-selector">
          <button type="button" class="sb-selector-toggle" data-selector="gameplay"
                  role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="gameplay-dropdown">
            <span class="sb-selector-label">{{ collect($unlockedMusic)->firstWhere('id', (string)$gameplayMusicId)['label'] ?? __('Choisir') }}</span>
            <span class="sb-selector-arrow">▼</span>
          </button>
          
          <div class="sb-selector-dropdown" id="gameplay-dropdown" data-dropdown="gameplay"
               role="listbox" style="display:none;">
            <input type="hidden" name="gameplay[music_id]" id="sel-gameplay" value="{{ $gameplayMusicId }}">
            @foreach($unlockedMusic as $m)
              <label class="sb-selector-option" data-value="{{ $m['id'] }}" data-label="{{ $m['label'] }}" role="option">
                <input type="radio" name="gameplay_choice" value="{{ $m['id'] }}" {{ (string)$gameplayMusicId === (string)$m['id'] ? 'checked' : '' }}>
                <span class="sb-option-text">{{ $m['label'] }}</span>
                <button type="button" class="sb-option-speaker" data-audio="{{ $m['id'] }}" data-duration="10000" title="{{ __('Tester') }}">🔊</button>
              </label>
            @endforeach
          </div>
        </div>
      </div>
    </div>
    
    {{-- Script inline pour initialiser localStorage AVANT le chargement de app.blade.php --}}
    <script>
    (function() {
      // Initialiser localStorage avec l'état serveur PHP si pas déjà défini
      const savedAmbianceState = localStorage.getItem('ambient_music_enabled');
      if (savedAmbianceState === null) {
        // Utiliser l'état serveur PHP
        const serverAmbianceState = {{ $ambianceOn ? 'true' : 'false' }};
        localStorage.setItem('ambient_music_enabled', serverAmbianceState.toString());
      }
    })();
    </script>

    {{-- Buzzer --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Son du buzzer') }}</div>
      <div class="sb-v" style="display:flex; align-items:center; justify-content:flex-end; gap:10px;">
        
        {{-- Sélecteur custom dépliable (compact: juste la flèche) --}}
        <div class="sb-audio-selector compact" id="buzzer-selector">
          <button type="button" class="sb-selector-toggle" data-selector="buzzer"
                  role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="buzzer-dropdown">
            <span class="sb-selector-label">{{ collect($unlockedBuzzers)->firstWhere('id', (string)$buzzerId)['label'] ?? __('Choisir') }}</span>
            <span class="sb-selector-arrow">▼</span>
          </button>
          
          <div class="sb-selector-dropdown" id="buzzer-dropdown" data-dropdown="buzzer"
               role="listbox" style="display:none;">
            <input type="hidden" name="sound[buzzer_id]" id="sel-buzzer" value="{{ $buzzerId }}">
            @foreach($unlockedBuzzers as $b)
              <label class="sb-selector-option" data-value="{{ $b['id'] }}" data-label="{{ $b['label'] }}" role="option">
                <input type="radio" name="buzzer_choice" value="{{ $b['id'] }}" {{ (string)$buzzerId === (string)$b['id'] ? 'checked' : '' }}>
                <span class="sb-option-text">{{ $b['label'] }}</span>
                <button type="button" class="sb-option-speaker" data-audio="{{ $b['id'] }}" data-duration="2000" title="{{ __('Tester') }}">🔊</button>
              </label>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- Son Bonne réponse --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Son bonne réponse') }}</div>
      <div class="sb-v" style="display:flex; align-items:center; justify-content:flex-end; gap:10px;">
        
        <div class="sb-audio-selector compact" id="correct-sound-selector">
          <button type="button" class="sb-selector-toggle" data-selector="correct_sound"
                  role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="correct-sound-dropdown">
            <span class="sb-selector-label">{{ collect($unlockedCorrectSounds)->firstWhere('id', (string)$correctSoundId)['label'] ?? __('Choisir') }}</span>
            <span class="sb-selector-arrow">▼</span>
          </button>
          
          <div class="sb-selector-dropdown" id="correct-sound-dropdown" data-dropdown="correct_sound"
               role="listbox" style="display:none;">
            <input type="hidden" name="sound[correct_sound_id]" id="sel-correct_sound" value="{{ $correctSoundId }}">
            @foreach($unlockedCorrectSounds as $cs)
              <label class="sb-selector-option" data-value="{{ $cs['id'] }}" data-label="{{ $cs['label'] }}" role="option">
                <input type="radio" name="correct_sound_choice" value="{{ $cs['id'] }}" {{ (string)$correctSoundId === (string)$cs['id'] ? 'checked' : '' }}>
                <span class="sb-option-text">{{ $cs['label'] }}</span>
                <button type="button" class="sb-option-speaker" data-audio="{{ $cs['id'] }}" data-duration="2000" title="{{ __('Tester') }}">🔊</button>
              </label>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- Son Mauvaise réponse --}}
    <div class="sb-row" style="text-align:left;">
      <div class="sb-k">{{ __('Son mauvaise réponse') }}</div>
      <div class="sb-v" style="display:flex; align-items:center; justify-content:flex-end; gap:10px;">
        
        <div class="sb-audio-selector compact" id="wrong-sound-selector">
          <button type="button" class="sb-selector-toggle" data-selector="wrong_sound"
                  role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="wrong-sound-dropdown">
            <span class="sb-selector-label">{{ collect($unlockedWrongSounds)->firstWhere('id', (string)$wrongSoundId)['label'] ?? __('Choisir') }}</span>
            <span class="sb-selector-arrow">▼</span>
          </button>
          
          <div class="sb-selector-dropdown" id="wrong-sound-dropdown" data-dropdown="wrong_sound"
               role="listbox" style="display:none;">
            <input type="hidden" name="sound[wrong_sound_id]" id="sel-wrong_sound" value="{{ $wrongSoundId }}">
            @foreach($unlockedWrongSounds as $ws)
              <label class="sb-selector-option" data-value="{{ $ws['id'] }}" data-label="{{ $ws['label'] }}" role="option">
                <input type="radio" name="wrong_sound_choice" value="{{ $ws['id'] }}" {{ (string)$wrongSoundId === (string)$ws['id'] ? 'checked' : '' }}>
                <span class="sb-option-text">{{ $ws['label'] }}</span>
                <button type="button" class="sb-option-speaker" data-audio="{{ $ws['id'] }}" data-duration="2000" title="{{ __('Tester') }}">🔊</button>
              </label>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    {{-- Boutons Enregistrer et Déconnexion côte à côte --}}
    <div style="margin-top:15px; text-align:center; display:flex; gap:10px; justify-content:center; align-items:center; flex-wrap:wrap;">
      <button type="submit" class="sb-btn" style="display:inline-block; width:auto; min-width:120px;">{{ __('Sauvegarder') }}</button>
      <button type="button" class="sb-btn" style="display:inline-block; width:auto; min-width:120px;" onclick="document.getElementById('logout-form').submit();">{{ __('Déconnexion') }}</button>
    </div>

  </div>
</form>

    </div> {{-- Fin de sb-three --}}

{{-- =================== BULLE STATS (Pleine largeur) =================== --}}
@php
    use App\Models\ProfileStat;
    $stats = $user ? ProfileStat::where('user_id', $user->id)->first() : null;
    
    // Stats Solo
    $soloMatchs = $stats ? ($stats->solo_matchs_joues ?? 0) : 0;
    $soloVictoires = $stats ? ($stats->solo_victoires ?? 0) : 0;
    $soloDefaites = $stats ? ($stats->solo_defaites ?? 0) : 0;
    $soloRatio = ($soloMatchs > 0) ? round(($soloVictoires / $soloMatchs) * 100, 1) : 0;
    $soloMatchs3 = $stats ? ($stats->solo_matchs_3_manches ?? 0) : 0;
    $soloVictoires3 = $stats ? ($stats->solo_victoires_difficiles ?? 0) : 0;
    $soloEfficacite = $stats ? round($stats->solo_performance_moyenne ?? 0, 1) : 0;
    $soloEfficaciteJoueur = $stats ? round($stats->solo_efficacite_joueur ?? 0, 1) : 0;
    
    // Stats Duo
    $duoMatchs = $stats ? ($stats->duo_matchs_joues ?? 0) : 0;
    $duoVictoires = $stats ? ($stats->duo_victoires ?? 0) : 0;
    $duoDefaites = $stats ? ($stats->duo_defaites ?? 0) : 0;
    $duoRatio = ($duoMatchs > 0) ? round(($duoVictoires / $duoMatchs) * 100, 1) : 0;
    $duoMatchs3 = $stats ? ($stats->duo_matchs_3_manches ?? 0) : 0;
    $duoVictoires3 = $stats ? ($stats->duo_victoires_difficiles ?? 0) : 0;
    $duoEfficacite = $stats ? round($stats->duo_performance_moyenne ?? 0, 1) : 0;
    $duoEfficaciteJoueur = $stats ? round($stats->duo_efficacite_joueur ?? 0, 1) : 0;
    
    // Stats Ligue
    $ligueMatchs = $stats ? ($stats->league_matchs_joues ?? 0) : 0;
    $ligueVictoires = $stats ? ($stats->league_victoires ?? 0) : 0;
    $ligueDefaites = $stats ? ($stats->league_defaites ?? 0) : 0;
    $ligueRatio = ($ligueMatchs > 0) ? round(($ligueVictoires / $ligueMatchs) * 100, 1) : 0;
    $ligueMatchs3 = $stats ? ($stats->league_matchs_3_manches ?? 0) : 0;
    $ligueVictoires3 = $stats ? ($stats->league_victoires_difficiles ?? 0) : 0;
    $ligueEfficacite = $stats ? round($stats->league_performance_moyenne ?? 0, 1) : 0;
    $ligueEfficaciteJoueur = $stats ? round($stats->league_efficacite_joueur ?? 0, 1) : 0;
@endphp

<div class="sb-panel" style="margin-top:12px;">
  <div class="sb-title" style="text-align:center; margin-bottom:16px;">📊 {{ __('Statistiques du Joueur') }}</div>
  
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
    
    {{-- SOLO --}}
    <div style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:12px;">
      <div style="font-size:16px; font-weight:700; margin-bottom:10px; color:#4FC3F7;">🎯 {{ __('Solo') }}</div>
      <div style="display:flex; flex-direction:column; gap:6px; font-size:13px;">
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Matchs joués') }}</span>
          <span style="font-weight:700;">{{ $soloMatchs }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Victoires / Défaites') }}</span>
          <span><span style="font-weight:700; color:#4CAF50;">{{ $soloVictoires }}</span> / <span style="font-weight:700; color:#F44336;">{{ $soloDefaites }}</span></span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Ratio victoires') }}</span>
          <span style="font-weight:700; color:#FFD700;">{{ $soloRatio }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Efficacité Solo') }}</span>
          <span style="font-weight:700; color:#9C27B0;">{{ $soloEfficacite }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Efficacité du Joueur') }}</span>
          <span style="font-weight:700; color:#00BCD4;">{{ $soloEfficaciteJoueur }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Matchs 3 manches') }}</span>
          <span style="font-weight:700;">{{ $soloMatchs3 }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Manches Décisives') }}</span>
          <span style="font-weight:700; color:#FF9800;">{{ $soloVictoires3 }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:6px; border-top:1px solid rgba(255,255,255,.1);">
          <span style="opacity:.85;">{{ __('Titre Full Mode Duo') }}</span>
          <span style="font-weight:700; color:#E91E63;">{{ __('Niveau') }} {{ $soloLevel }}/10</span>
        </div>
      </div>
    </div>
    
    {{-- DUO --}}
    <div style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:12px;">
      <div style="font-size:16px; font-weight:700; margin-bottom:10px; color:#AB47BC;">👥 {{ __('Duo') }}</div>
      <div style="display:flex; flex-direction:column; gap:6px; font-size:13px;">
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Matchs joués') }}</span>
          <span style="font-weight:700;">{{ $duoMatchs }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Victoires / Défaites') }}</span>
          <span><span style="font-weight:700; color:#4CAF50;">{{ $duoVictoires }}</span> / <span style="font-weight:700; color:#F44336;">{{ $duoDefaites }}</span></span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Ratio victoires') }}</span>
          <span style="font-weight:700; color:#FFD700;">{{ $duoRatio }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Efficacité Duo') }}</span>
          <span style="font-weight:700; color:#9C27B0;">{{ $duoEfficacite }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Efficacité du Joueur') }}</span>
          <span style="font-weight:700; color:#00BCD4;">{{ $duoEfficaciteJoueur }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Matchs 3 manches') }}</span>
          <span style="font-weight:700;">{{ $duoMatchs3 }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Manches Décisives') }}</span>
          <span style="font-weight:700; color:#FF9800;">{{ $duoVictoires3 }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:6px; border-top:1px solid rgba(255,255,255,.1);">
          <span style="opacity:.85;">{{ __('Titre Mode Ligue') }}</span>
          <span style="font-weight:700; color:#FFD700;">{{ __('Matchs') }} {{ $duoMatchs }}/25</span>
        </div>
      </div>
    </div>
    
    {{-- LIGUE --}}
    <div style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:12px;">
      <div style="font-size:16px; font-weight:700; margin-bottom:10px; color:#FFD700;">🏆 {{ __('Ligue') }}</div>
      <div style="display:flex; flex-direction:column; gap:6px; font-size:13px;">
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Matchs joués') }}</span>
          <span style="font-weight:700;">{{ $ligueMatchs }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Victoires / Défaites') }}</span>
          <span><span style="font-weight:700; color:#4CAF50;">{{ $ligueVictoires }}</span> / <span style="font-weight:700; color:#F44336;">{{ $ligueDefaites }}</span></span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Ratio victoires') }}</span>
          <span style="font-weight:700; color:#FFD700;">{{ $ligueRatio }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Efficacité Ligue') }}</span>
          <span style="font-weight:700; color:#9C27B0;">{{ $ligueEfficacite }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Efficacité du Joueur') }}</span>
          <span style="font-weight:700; color:#00BCD4;">{{ $ligueEfficaciteJoueur }}%</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Matchs 3 manches') }}</span>
          <span style="font-weight:700;">{{ $ligueMatchs3 }}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="opacity:.85;">{{ __('Manches Décisives') }}</span>
          <span style="font-weight:700; color:#FF9800;">{{ $ligueVictoires3 }}</span>
        </div>
      </div>
    </div>
    
  </div>
</div>

  </div> {{-- Fin de sb-wrap --}}
</div> {{-- Fin de sb-page --}}

{{-- Formulaire de déconnexion invisible --}}
<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
  @csrf
</form>

<script>
// Script 1 : Mise à jour immédiate des aperçus
document.addEventListener('DOMContentLoaded', () => {
  const byId = id => document.getElementById(id);
  const setTxt = (id, v) => { const n = byId(id); if(n) n.textContent = v; };

  // ===== Fix clavier mobile - détection focus =====
  const inputs = document.querySelectorAll('input, textarea, select');
  inputs.forEach(input => {
    input.addEventListener('focus', () => {
      document.body.classList.add('keyboard-open');
    });
    input.addEventListener('blur', () => {
      setTimeout(() => {
        if (!document.activeElement || !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
          document.body.classList.remove('keyboard-open');
        }
      }, 100);
    });
  });

  const selLang  = byId('sel-lang');
  const selPays  = byId('sel-pays');
  const selTheme = byId('sel-theme');
  const selAmb   = byId('sel-amb');
  const chkAmb   = byId('chk-amb');     // ✅ toggle Ambiance
  const chkGame  = byId('chk-gameplay'); // ✅ toggle Gameplay
  const selLigue = byId('sel-ligue');
  const selBuzzer = byId('sel-buzzer'); // ✅ sélecteur Buzzer
  
  // Synchroniser l'état initial avec localStorage
  if (chkAmb) {
    const savedState = localStorage.getItem('ambient_music_enabled');
    if (savedState !== null) {
      chkAmb.checked = (savedState === 'true');
    }
    // Note: L'initialisation localStorage est faite dans le script inline avant DOMContentLoaded
  }

  // Fonctions génériques pour Ambiance & Gameplay (sélecteurs toujours visibles pour tester)
  const updateAmbiance = () => {
    if (chkAmb && selAmb) {
      if (chkAmb.checked) {
        const toggleLabel = document.querySelector('#ambiance-selector .sb-selector-label');
        const labelText = toggleLabel ? toggleLabel.textContent : '{{ __("Classique") }}';
        setTxt('apercu-ambiance', labelText);
      } else {
        setTxt('apercu-ambiance', '{{ __("Désactivé") }}');
      }
    }
  };

  const updateGameplay = () => {
    const selGameplay = byId('sel-gameplay');
    if (chkGame && selGameplay) {
      if (chkGame.checked) {
        const toggleLabel = document.querySelector('#gameplay-selector .sb-selector-label');
        const labelText = toggleLabel ? toggleLabel.textContent : 'StrategyBuzzer';
        setTxt('apercu-gameplay', labelText);
      } else {
        setTxt('apercu-gameplay', '{{ __("Désactivé") }}');
      }
    }
  };

  // Langue / Pays / Thème
  if (selLang)  selLang.addEventListener('change', () => setTxt('apercu-lang',  selLang.options[selLang.selectedIndex].text));
  if (selPays)  selPays.addEventListener('change', () => setTxt('apercu-pays',  selPays.options[selPays.selectedIndex].text || '—'));
  if (selTheme) selTheme.addEventListener('change', () => setTxt('apercu-theme', selTheme.value));
  if (selLigue) selLigue.addEventListener('change', () => setTxt('apercu-ligue', selLigue.value));

  // Ambiance (toggle + menu) - Synchroniser avec la musique globale
  if (chkAmb) {
    chkAmb.addEventListener('change', function() {
      updateAmbiance();
      // Synchroniser avec la musique globale dans app.blade.php
      if (window.toggleAmbientMusic) {
        window.toggleAmbientMusic(chkAmb.checked);
      }
    });
  }
  if (selAmb) {
    selAmb.addEventListener('change', function() {
      updateAmbiance();
      // Changer la musique en temps réel
      if (window.changeAmbientMusic) {
        window.changeAmbientMusic(selAmb.value);
      }
    });
  }

  // Gameplay (toggle + menu)
  const selGameplay = byId('sel-gameplay');
  if (chkGame) chkGame.addEventListener('change', updateGameplay);
  if (selGameplay) selGameplay.addEventListener('change', updateGameplay);

  // Initialiser les aperçus au chargement de la page
  updateAmbiance();
  updateGameplay();

  // Buzzer - Synchroniser avec localStorage
  if (selBuzzer) {
    // Charger le buzzer sélectionné depuis localStorage au chargement
    const savedBuzzer = localStorage.getItem('selectedBuzzer');
    if (savedBuzzer) {
      selBuzzer.value = savedBuzzer;
    }
    
    // Sauvegarder le choix dans localStorage lors du changement
    selBuzzer.addEventListener('change', function() {
      localStorage.setItem('selectedBuzzer', selBuzzer.value);
      console.log('✅ Buzzer changé:', selBuzzer.value);
    });
  }

  // ===== SYSTÈME DE SÉLECTEURS AUDIO CUSTOM =====
  let currentTestAudio = null;
  let currentTestTimeout = null;
  let currentOpenDropdown = null;

  // Fonction pour tester une musique
  function playTestAudio(audioId, duration = 10000) {
    if (currentTestTimeout) {
      clearTimeout(currentTestTimeout);
      currentTestTimeout = null;
    }

    if (currentTestAudio) {
      currentTestAudio.pause();
      currentTestAudio.currentTime = 0;
      currentTestAudio = null;
    }

    const audioPath = `/sounds/${audioId}.mp3`;
    const audio = new Audio(audioPath);
    audio.volume = 0.5;
    currentTestAudio = audio;
    
    audio.play().catch(err => {
      console.error('Erreur lors de la lecture audio:', err);
      currentTestAudio = null;
    });

    audio.addEventListener('ended', () => {
      if (currentTestAudio === audio) {
        currentTestAudio = null;
      }
    });

    currentTestTimeout = setTimeout(() => {
      if (currentTestAudio === audio) {
        audio.pause();
        audio.currentTime = 0;
        currentTestAudio = null;
      }
      currentTestTimeout = null;
    }, duration);
  }

  // Classe pour gérer un sélecteur audio
  class AudioSelector {
    constructor(selectorName) {
      this.name = selectorName;
      this.container = byId(`${selectorName}-selector`);
      if (!this.container) return;

      this.toggle = this.container.querySelector('.sb-selector-toggle');
      this.dropdown = this.container.querySelector('.sb-selector-dropdown');
      this.hiddenInput = this.container.querySelector('input[type="hidden"]');
      this.label = this.toggle.querySelector('.sb-selector-label');
      this.options = this.dropdown.querySelectorAll('.sb-selector-option');
      
      this.init();
    }

    init() {
      // Toggle dropdown
      this.toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        this.toggleDropdown();
      });

      // Sélection d'option
      this.options.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        const speaker = option.querySelector('.sb-option-speaker');

        // Clic sur l'option (sauf speaker)
        option.addEventListener('click', (e) => {
          if (e.target === speaker || speaker.contains(e.target)) return;
          radio.checked = true;
          this.updateSelection(option);
        });

        // Bouton speaker
        if (speaker) {
          speaker.addEventListener('click', (e) => {
            e.stopPropagation();
            const audioId = speaker.dataset.audio;
            const duration = parseInt(speaker.dataset.duration) || 10000;
            playTestAudio(audioId, duration);
          });
        }
      });

      // Fermer au clic en dehors
      document.addEventListener('click', (e) => {
        if (!this.container.contains(e.target) && this.isOpen()) {
          this.closeDropdown();
        }
      });

      // Navigation clavier
      this.toggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.toggleDropdown();
        } else if (e.key === 'Escape') {
          this.closeDropdown();
        }
      });
    }

    updateSelection(option) {
      const value = option.dataset.value;
      const label = option.dataset.label;

      // Mettre à jour le hidden input
      this.hiddenInput.value = value;
      
      // Mettre à jour le label du bouton
      this.label.textContent = label;

      // Déclencher l'événement change pour la synchronisation
      const changeEvent = new Event('change', { bubbles: true });
      this.hiddenInput.dispatchEvent(changeEvent);

      // Synchronisation spécifique pour Ambiance et Gameplay
      if (this.name === 'ambiance') {
        updateAmbiance();
        if (window.changeAmbientMusic) {
          window.changeAmbientMusic(value);
        }
      } else if (this.name === 'gameplay') {
        updateGameplay();
      } else if (this.name === 'buzzer') {
        localStorage.setItem('selectedBuzzer', value);
        console.log('✅ Buzzer changé:', value);
      } else if (this.name === 'correct_sound') {
        localStorage.setItem('selectedCorrectSound', value);
        console.log('✅ Son bonne réponse changé:', value);
      } else if (this.name === 'wrong_sound') {
        localStorage.setItem('selectedWrongSound', value);
        console.log('✅ Son mauvaise réponse changé:', value);
      }
    }

    toggleDropdown() {
      if (this.isOpen()) {
        this.closeDropdown();
      } else {
        this.openDropdown();
      }
    }

    openDropdown() {
      // Fermer les autres dropdowns
      if (currentOpenDropdown && currentOpenDropdown !== this) {
        currentOpenDropdown.closeDropdown();
      }

      this.dropdown.style.display = 'block';
      this.toggle.setAttribute('aria-expanded', 'true');
      currentOpenDropdown = this;
    }

    closeDropdown() {
      this.dropdown.style.display = 'none';
      this.toggle.setAttribute('aria-expanded', 'false');
      if (currentOpenDropdown === this) {
        currentOpenDropdown = null;
      }
    }

    isOpen() {
      return this.dropdown.style.display === 'block';
    }
  }

  // Initialiser les cinq sélecteurs
  const ambianceSelectorObj = new AudioSelector('ambiance');
  const gameplaySelectorObj = new AudioSelector('gameplay');
  const buzzerSelectorObj = new AudioSelector('buzzer');
  const correctSoundSelectorObj = new AudioSelector('correct_sound');
  const wrongSoundSelectorObj = new AudioSelector('wrong_sound');

  // Initialisation au chargement (les sélecteurs restent toujours visibles pour tester)
  updateAmbiance();
  updateGameplay();
});
</script>

{{-- Script 2 : Compte à rebours vivant (vies & packs) --}}
<script>
(function () {
  const el = document.getElementById('sb-countdown');
  if (!el) return;

  const livesLabelEl = document.getElementById('sb-lives-label');
  let type        = el.dataset.type;              // 'life_regen' | 'infinite_pack' | 'idle'
  let targetIso   = el.dataset.target || null;    // ISO date
  const lifeMax   = parseInt(el.dataset.lifeMax || '3', 10);
  const regenMins = parseInt(el.dataset.regenMins || '60', 10);
  const waitText  = el.dataset.waitText || '—';
  let lives       = parseInt(el.dataset.lives || '0', 10);

  const fmt = (ms) => {
    if (ms < 0) ms = 0;
    const totalSec = Math.floor(ms / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    return `${String(h).padStart(2,'0')}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
  };

  const syncServer = () => {
    fetch("{{ route('profile.regen') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(r => r.ok ? r.json() : null)
      .then(data => {
        if (!data) return;
        // Met à jour l’UI d’après la vérité serveur
        if (livesLabelEl) livesLabelEl.textContent = `${data.lives} / {{ (int)config('game.life_max',3) }}`;
        if (data.countdown && el) el.textContent = data.countdown;
        if (data.next_life_regen) {
          // Si non max, on repart correctement au nouveau cycle
          if (data.lives < {{ (int)config('game.life_max',3) }}) {
            type = 'life_regen';
            targetIso = data.next_life_regen;
          } else {
            type = 'idle';
            targetIso = null;
          }
        }
      });
  };

  const tick = () => {
    if (type === 'idle' || !targetIso) {
      el.textContent = waitText; // vies max ou pas de timer
      return;
    }

    const target = new Date(targetIso).getTime();
    const now    = Date.now();
    const diffMs = target - now;

    if (diffMs > 0) {
      el.textContent = fmt(diffMs);
      return;
    }

    // arrivé à 0
    if (type === 'infinite_pack') {
      type = 'idle';
      targetIso = null;
      el.textContent = waitText;
      if (livesLabelEl) livesLabelEl.textContent = `${Math.min(lives, lifeMax)} / ${lifeMax}`;
      // ping au cas où la période infinie a expiré côté serveur
      syncServer();
      return;
    }

    if (type === 'life_regen') {
      // incrément optimiste côté front
      lives = Math.min(lives + 1, lifeMax);
      if (livesLabelEl) livesLabelEl.textContent = `${lives} / ${lifeMax}`;

      if (lives >= lifeMax) {
        type = 'idle';
        targetIso = null;
        el.textContent = waitText;
      } else {
        const next = new Date(Date.now() + regenMins * 60 * 1000);
        targetIso = next.toISOString();
        el.textContent = fmt(regenMins * 60 * 1000);
      }

      // synchronise immédiatement côté serveur
      syncServer();
    }
  };

  tick();
  setInterval(tick, 1000);
})();

// === Sauvegarde/Restauration des données du formulaire ===
(function() {
  const form = document.getElementById('profileForm');
  const avatarLinks = document.querySelectorAll('a.sb-thumb');
  
  if (!form) {
    console.warn('Formulaire profileForm introuvable');
    return;
  }
  
  // Sauvegarder les données du formulaire avant navigation vers avatars
  avatarLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const formData = new FormData(form);
      const data = {};
      
      formData.forEach((value, key) => {
        data[key] = value;
      });
      
      sessionStorage.setItem('profile_form_backup', JSON.stringify(data));
      console.log('Données sauvegardées:', data);
    });
  });
  
  // Restaurer les données au chargement de la page
  const backup = sessionStorage.getItem('profile_form_backup');
  if (backup) {
    try {
      const data = JSON.parse(backup);
      console.log('Restauration des données:', data);
      
      // Restaurer chaque champ
      Object.keys(data).forEach(name => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) {
          if (input.type === 'checkbox') {
            input.checked = data[name] === '1';
          } else {
            input.value = data[name];
          }
          
          // Déclencher l'événement change pour mettre à jour l'aperçu
          const event = new Event('change', { bubbles: true });
          input.dispatchEvent(event);
        }
      });
      
      // Nettoyer la sauvegarde après restauration
      sessionStorage.removeItem('profile_form_backup');
      console.log('Données restaurées avec succès');
    } catch(e) {
      console.error('Erreur restauration:', e);
      sessionStorage.removeItem('profile_form_backup');
    }
  }
})();

// === Sauvegarde automatique du formulaire ===
(function() {
  const form = document.getElementById('profileForm');
  if (!form) return;
  
  let saveTimeout;
  let isDirty = false;
  
  // Fonction de sauvegarde immédiate
  const saveNow = () => {
    if (!isDirty) return;
    
    clearTimeout(saveTimeout);
    const formData = new FormData(form);
    
    // Utiliser sendBeacon pour garantir l'envoi même si navigation immédiate
    const blob = new Blob([new URLSearchParams(formData)], { type: 'application/x-www-form-urlencoded' });
    navigator.sendBeacon(form.action, blob);
    
    isDirty = false;
    console.log('✅ Profil sauvegardé immédiatement');
  };
  
  // Fonction de sauvegarde automatique avec debounce
  const autoSave = () => {
    isDirty = true;
    clearTimeout(saveTimeout);
    
    saveTimeout = setTimeout(() => {
      const formData = new FormData(form);
      
      fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.json())
      .then(data => {
        isDirty = false;
        console.log('✅ Profil sauvegardé automatiquement');
      })
      .catch(error => {
        console.log('⚠️ Erreur sauvegarde auto:', error);
      });
    }, 1500);
  };
  
  // Écouter tous les changements de champs
  form.querySelectorAll('input, select').forEach(field => {
    field.addEventListener('change', autoSave);
    if (field.type === 'text') {
      field.addEventListener('input', autoSave);
    }
  });
  
  // Forcer la sauvegarde avant navigation
  document.querySelectorAll('a.sb-thumb').forEach(link => {
    link.addEventListener('click', function(e) {
      saveNow();
    });
  });
  
  // Sauvegarder avant fermeture/rechargement de page
  window.addEventListener('beforeunload', saveNow);
})();

// === Validation du bouton Enregistrer ===
(function() {
  const saveBtn = document.querySelector('button[type="submit"].sb-btn');
  const hasAvatar = {{ $hasAvatar ? 'true' : 'false' }};
  
  if (saveBtn && !hasAvatar) {
    saveBtn.disabled = true;
    saveBtn.style.opacity = '0.5';
    saveBtn.style.cursor = 'not-allowed';
    saveBtn.title = 'Veuillez sélectionner un avatar avant d\'enregistrer';
  }
})();
</script>
@endsection
