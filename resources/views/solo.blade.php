@extends('layouts.app')

@section('content')
@php
// Avatars non-avantageux en mode Solo (skills orientés multijoueur)
$soloDisadvantagedAvatars = [
    'défenseur' => 'Cet avatar ne sera pas nécessaire en mode Solo car il n\'y aura pas d\'attaque des joueurs adverses.',
    'defenseur' => 'Cet avatar ne sera pas nécessaire en mode Solo car il n\'y aura pas d\'attaque des joueurs adverses.',
    'comédienne' => 'Cet avatar ne vous sera pas avantageux en mode Solo car ses skills affectent les adversaires humains.',
    'comedienne' => 'Cet avatar ne vous sera pas avantageux en mode Solo car ses skills affectent les adversaires humains.',
];
$currentStrategicAvatar = strtolower($avatar_stratégique ?? 'aucun');
$showSoloWarning = isset($soloDisadvantagedAvatars[$currentStrategicAvatar]);
$soloWarningMessage = $showSoloWarning ? $soloDisadvantagedAvatars[$currentStrategicAvatar] : '';
@endphp

<style>
  .container-solo{ overflow-x:hidden; overflow-y:visible; }
  *, *::before, *::after { box-sizing: border-box; }
  body{ background:#003DA5; color:#fff;  overflow-x:hidden; }
  .container-solo{ max-width:980px; margin:40px auto;  padding:0 16px; overflow-x:hidden; overflow-y:visible; }
  .grid-2{ display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; width: 100%; }
  .btn-theme{ display:block; width:100%; padding:14px 16px; border-radius:10px; background:#1E90FF; color:#fff; border:0; cursor:pointer;  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .btn-theme:disabled{ opacity:.4; cursor:not-allowed; }
  .box{ background:rgba(0,0,0,.15); padding:18px; border-radius:12px; margin-bottom:16px; }
  .lbl{ font-weight:600; margin-right:8px; }
  select, .form-select{ color:#000; }
/* UNIFORM BTN THEME v3 */
  .btn-theme{display:flex;align-items:center;justify-content:center;gap:10px;min-height:58px;box-shadow:2px 2px 6px rgba(0,0,0,.3);} 
  .btn-theme:hover{transform:translateY(-1px);} 
  .btn-theme:active{transform:translateY(0);} 
  .grid-2{overflow:hidden;} 
  .header-menu {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
  }
  
  @media (max-width: 600px) {
    .grid-2 {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  /* Popup avertissement avatar non-avantageux en Solo */
  .solo-warning-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeInWarning 0.3s ease;
  }

  .solo-warning-popup {
    background: linear-gradient(145deg, #2d1f3d, #1a1a2e);
    border: 2px solid #f39c12;
    border-radius: 20px;
    padding: 30px;
    max-width: 400px;
    margin: 20px;
    position: relative;
    box-shadow: 0 0 40px rgba(243, 156, 18, 0.3);
    animation: scaleInWarning 0.3s ease;
  }

  .solo-warning-close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 1.8rem;
    cursor: pointer;
    color: rgba(255,255,255,0.6);
    transition: color 0.2s, transform 0.2s;
    background: none;
    border: none;
  }

  .solo-warning-close:hover {
    color: #fff;
    transform: scale(1.2);
  }

  .solo-warning-icon {
    font-size: 3rem;
    text-align: center;
    margin-bottom: 15px;
  }

  .solo-warning-title {
    font-size: 1.3rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 15px;
    color: #f39c12;
  }

  .solo-warning-message {
    font-size: 1rem;
    line-height: 1.5;
    text-align: center;
    color: rgba(255,255,255,0.85);
  }

  @keyframes fadeInWarning {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes scaleInWarning {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
  }

  @keyframes fadeOutWarning {
    from { opacity: 1; }
    to { opacity: 0; }
  }

  /* Teammate Dropdown Styles */
  .teammate-dropdown-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    padding: 2px 8px;
    margin-left: 4px;
    transition: transform 0.2s ease;
  }
  .teammate-dropdown-btn:hover {
    transform: scale(1.2);
  }
  .teammate-dropdown-btn.open {
    transform: rotate(180deg);
  }
  
  .teammate-dropdown {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(145deg, #1a3a6e, #0d2347);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 12px;
    min-width: 280px;
    max-width: 320px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.4);
    z-index: 1000;
    overflow: hidden;
    animation: dropdownSlide 0.2s ease;
  }
  
  @keyframes dropdownSlide {
    from { opacity: 0; transform: translateX(-50%) translateY(-10px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
  }
  
  .teammate-dropdown-header {
    background: rgba(255,255,255,0.1);
    padding: 10px 14px;
    font-weight: 700;
    font-size: 0.9rem;
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }
  
  .teammate-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    cursor: pointer;
    transition: background 0.2s ease;
    border-bottom: 1px solid rgba(255,255,255,0.1);
  }
  
  .teammate-option:last-child {
    border-bottom: none;
  }
  
  .teammate-option.unlocked:hover {
    background: rgba(255,255,255,0.15);
  }
  
  .teammate-option.locked {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .teammate-option.selected {
    background: rgba(46, 204, 113, 0.25);
    border-left: 3px solid #2ecc71;
  }
  
  .teammate-icon {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
  }
  
  .teammate-info {
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  
  .teammate-name {
    font-weight: 700;
    font-size: 0.95rem;
  }
  
  .teammate-skill {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.7);
    margin-top: 2px;
  }
  
  .teammate-check {
    color: #2ecc71;
    font-size: 1.2rem;
    font-weight: bold;
  }

</style>

<a href="{{ route('menu') }}" class="header-menu" style="
  background: white;
  color: #003DA5;
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

<div class="container-solo">
  <h1 class="display-4 text-center">{{ __('Mode Solo') }}</h1>
  <p class="lead text-center" style="margin-top:6px">{{ __('Votre Niveau') }} : <strong>{{ $choix_niveau }}</strong></p>

  <form id="soloForm" action="{{ route('solo.start') }}" method="POST">
    @csrf

    <div class="box text-center">
      <div class="mb-2"><strong>{{ __('Choisissez vos options puis un thème pour commencer la partie') }} :</strong></div>

      <div class="mb-2">
        <span class="lbl">{{ __('Questions par Manche') }} :</span>
        <select name="nb_questions" id="nb_questions" class="form-select d-inline-block" style="width:auto;">
          <option value="">-- {{ __('Choisissez') }} --</option>
          <option value="10"  {{ (isset($nb_questions) && $nb_questions==10)  ? 'selected' : '' }}>10</option>
          <option value="20"  {{ (isset($nb_questions) && $nb_questions==20)  ? 'selected' : '' }}>20</option>
          <option value="30"  {{ (isset($nb_questions) && $nb_questions==30)  ? 'selected' : '' }}>30</option>
          <option value="40"  {{ (isset($nb_questions) && $nb_questions==40)  ? 'selected' : '' }}>40</option>
          <option value="50"  {{ (isset($nb_questions) && $nb_questions==50)  ? 'selected' : '' }}>50</option>
        </select>
      </div>

      <div class="mb-3">
        <input type="hidden" name="niveau_joueur" id="niveau_joueur" value="{{ $niveau_selectionne ?? $choix_niveau }}">
        <div style="display:flex; align-items:center; justify-content:center; gap:12px;">
          <span class="lbl">{{ __('Niveau sélectionné') }} : <strong>{{ $niveau_selectionne ?? $choix_niveau }}</strong></span>
          <a href="{{ route('solo.opponents') }}" class="btn btn-outline-light btn-sm">
            👥 {{ __('Choisir un Adversaire') }}
          </a>
        </div>
      </div>

      <div class="mb-1" style="position: relative;">
        <span class="lbl">{{ __('Choix de l\'Avatar Stratégique (optionnel)') }} :</span>
        <span id="avatar_name_display">{{ $avatar_stratégique ?? __('Aucun') }}</span>
        @if($is_stratege ?? false)
          <button type="button" id="teammate_dropdown_btn" class="teammate-dropdown-btn" onclick="toggleTeammateDropdown()">🔽</button>
          <div id="teammate_dropdown" class="teammate-dropdown" style="display: none;">
            <div class="teammate-dropdown-header">👥 {{ __('Sélectionner un coéquipier') }}</div>
            <div class="teammate-option {{ empty($selected_teammate) ? 'selected' : '' }}" onclick="selectTeammate('')">
              <span class="teammate-icon">❌</span>
              <div class="teammate-info">
                <span class="teammate-name">{{ __('Aucun coéquipier') }}</span>
              </div>
            </div>
            @foreach($rare_avatars_data ?? [] as $slug => $avatarData)
              @php
                $isUnlocked = $avatarData['unlocked'] ?? false;
                $isSelected = ($selected_teammate ?? '') === $slug;
                $avatarIcon = $avatarData['icon'] ?? '🎯';
                $skillName = $avatarData['skills'][0]['name'] ?? '';
                $skillIcon = $avatarData['skills'][0]['icon'] ?? '✨';
                $skillDesc = $avatarData['skills'][0]['description_short'] ?? $avatarData['skills'][0]['description'] ?? '';
              @endphp
              <div class="teammate-option {{ $isUnlocked ? 'unlocked' : 'locked' }} {{ $isSelected ? 'selected' : '' }}" 
                   onclick="{{ $isUnlocked ? "selectTeammate('$slug')" : 'return false;' }}">
                <span class="teammate-icon">{{ $avatarIcon }}</span>
                <div class="teammate-info">
                  <span class="teammate-name">{{ $avatarData['name'] }} @if(!$isUnlocked) 🔒 @endif</span>
                  <span class="teammate-skill">{{ $skillIcon }} {{ $skillName }}</span>
                </div>
                @if($isSelected)
                  <span class="teammate-check">✓</span>
                @endif
              </div>
            @endforeach
          </div>
        @endif
        <a href="{{ \Illuminate\Support\Facades\Route::has('avatar') ? route('avatar') : url('/avatar') }}"
           class="btn btn-sm btn-outline-light ms-2">{{ __('Sélectionner') }}</a>
      </div>
    </div>

    <div class="grid-2">
      <button type="submit" class="btn-theme" name="theme" value="general">🧠 {{ __('Général') }}</button>
      <button type="submit" class="btn-theme" name="theme" value="geographie">🌐 {{ __('Géographie') }}</button>
      <button type="submit" class="btn-theme" name="theme" value="histoire">📜 {{ __('Histoire') }}</button>
      <button type="submit" class="btn-theme" name="theme" value="art">🎨 {{ __('Art') }}</button>
      <button type="submit" class="btn-theme" name="theme" value="cinema">🎬 {{ __('Cinéma') }}</button>
      <button type="submit" class="btn-theme" name="theme" value="sport">🏅 {{ __('Sport') }}</button>
      <button type="submit" class="btn-theme" name="theme" value="faune">🦁 {{ __('Faune') }}</button>
      <button type="submit" class="btn-theme" name="theme" value="cuisine">🍳 {{ __('Cuisine') }}</button>
      <button type="submit" name="theme" value="sciences" class="btn-theme">🔬 {{ __('Sciences') }}</button>
    </div>
  </form>
</div>

<div id="validationMessage" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(231, 76, 60, 0.95); color: white; padding: 25px 40px; border-radius: 15px; font-size: 1.2rem; font-weight: 700; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); z-index: 10000; text-align: center;">
  {{ __('Choisissez le nombre de questions') }}.
</div>

@if($showSoloWarning)
<div class="solo-warning-overlay" id="soloWarningOverlay">
    <div class="solo-warning-popup">
        <button class="solo-warning-close" onclick="closeSoloWarning()">&times;</button>
        <div class="solo-warning-icon">⚠️</div>
        <div class="solo-warning-title">{{ __('Avertissement Avatar Stratégique') }}</div>
        <div class="solo-warning-message">{{ __($soloWarningMessage) }}</div>
    </div>
</div>
@endif

<script>
  // Fonction pour fermer le popup d'avertissement avatar
  function closeSoloWarning() {
    const overlay = document.getElementById('soloWarningOverlay');
    if (overlay) {
      overlay.style.animation = 'fadeOutWarning 0.3s ease forwards';
      setTimeout(() => overlay.remove(), 300);
    }
  }
  
  // Toggle le dropdown du coéquipier
  function toggleTeammateDropdown() {
    const dropdown = document.getElementById('teammate_dropdown');
    const btn = document.getElementById('teammate_dropdown_btn');
    if (dropdown.style.display === 'none') {
      dropdown.style.display = 'block';
      btn.classList.add('open');
    } else {
      dropdown.style.display = 'none';
      btn.classList.remove('open');
    }
  }
  
  // Sélectionner un coéquipier
  function selectTeammate(slug) {
    // Sauvegarder via AJAX
    fetch('{{ route("solo.set-teammate") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({ teammate: slug })
    }).then(response => response.json())
      .then(data => {
        if (data.success) {
          console.log('Coéquipier sauvegardé:', slug);
          // Mettre à jour visuellement
          document.querySelectorAll('.teammate-option').forEach(opt => {
            opt.classList.remove('selected');
            const checkEl = opt.querySelector('.teammate-check');
            if (checkEl) checkEl.remove();
          });
          // Trouver l'option cliquée et la marquer comme sélectionnée
          document.querySelectorAll('.teammate-option').forEach(opt => {
            if ((slug === '' && opt.querySelector('.teammate-name').textContent.includes('{{ __("Aucun") }}')) ||
                (slug && opt.getAttribute('onclick') && opt.getAttribute('onclick').includes(slug))) {
              opt.classList.add('selected');
              if (!opt.querySelector('.teammate-check')) {
                const check = document.createElement('span');
                check.className = 'teammate-check';
                check.textContent = '✓';
                opt.appendChild(check);
              }
            }
          });
          // Fermer le dropdown
          toggleTeammateDropdown();
        }
      });
  }
  
  // Fermer le dropdown si on clique en dehors
  document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('teammate_dropdown');
    const btn = document.getElementById('teammate_dropdown_btn');
    if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
      dropdown.style.display = 'none';
      btn.classList.remove('open');
    }
  });

  const form = document.getElementById('soloForm');
  const validationMsg = document.getElementById('validationMessage');
  const nbQuestionsSelect = document.getElementById('nb_questions');
  
  // Restaurer la valeur sauvegardée au chargement
  const savedNbQuestions = sessionStorage.getItem('solo_nb_questions');
  if (savedNbQuestions && nbQuestionsSelect) {
    nbQuestionsSelect.value = savedNbQuestions;
  }
  
  // Sauvegarder quand l'utilisateur change la valeur
  if (nbQuestionsSelect) {
    nbQuestionsSelect.addEventListener('change', () => {
      sessionStorage.setItem('solo_nb_questions', nbQuestionsSelect.value);
    });
  }
  
  document.querySelectorAll('.btn-theme').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const nbq = document.getElementById('nb_questions').value;
      if (!nbq) {
        e.preventDefault();
        
        validationMsg.style.display = 'block';
        
        setTimeout(() => {
          validationMsg.style.display = 'none';
        }, 2000);
        
        return false;
      }
      // Nettoyer sessionStorage quand le jeu commence
      sessionStorage.removeItem('solo_nb_questions');
    });
  });
</script>
<style>
  /* THEME BUTTONS UNIFORM */
  .btn-theme{display:block;width:100%;padding:14px 18px;border-radius:12px;font-size:1.1rem;box-shadow:2px 2px 6px rgba(0,0,0,.3);}
  .btn-theme:hover{transform:translateY(-1px);}
  .btn-theme:active{transform:translateY(0);}
  .container .row{overflow:hidden;}
</style>

@endsection
