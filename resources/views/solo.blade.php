@extends('layouts.app')

@section('content')
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

      <div class="mb-1">
        <span class="lbl">{{ __('Choix de l\'Avatar Stratégique (optionnel)') }} :</span>
        <span>{{ $avatar_stratégique ?? __('Aucun') }}</span>
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

<script>
  const form = document.getElementById('soloForm');
  const validationMsg = document.getElementById('validationMessage');
  
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
