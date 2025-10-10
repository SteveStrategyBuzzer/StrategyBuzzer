@extends('layouts.app')

@section('content')
<style>
  .container-solo{ overflow-x:hidden; overflow-y:visible; }
  *, *::before, *::after { box-sizing: border-box; }
  body{ background:#003DA5; color:#fff;  overflow-x:hidden; }
  .container-solo{ max-width:980px; margin:40px auto;  padding:0 16px; overflow-x:hidden; overflow-y:visible; }
  .grid-2{ display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:12px; }
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
  Menu
</a>

<div class="container-solo">
  <h1 class="display-4 text-center">Mode Solo</h1>
  <p class="lead text-center" style="margin-top:6px">Votre Niveau : <strong>{{ $choix_niveau }}</strong></p>

  <form id="soloForm" action="{{ route('solo.start') }}" method="POST">
    @csrf

    <div class="box text-center">
      <div class="mb-2"><strong>Choisissez vos options puis un thème pour commencer la partie :</strong></div>

      <div class="mb-2">
        <span class="lbl">Nombre de questions :</span>
        <select name="nb_questions" id="nb_questions" class="form-select d-inline-block" style="width:auto;">
          <option value="">-- Choisissez --</option>
          <option value="10"  {{ (isset($nb_questions) && $nb_questions==10)  ? 'selected' : '' }}>10</option>
          <option value="20"  {{ (isset($nb_questions) && $nb_questions==20)  ? 'selected' : '' }}>20</option>
          <option value="30"  {{ (isset($nb_questions) && $nb_questions==30)  ? 'selected' : '' }}>30</option>
          <option value="40"  {{ (isset($nb_questions) && $nb_questions==40)  ? 'selected' : '' }}>40</option>
          <option value="50"  {{ (isset($nb_questions) && $nb_questions==50)  ? 'selected' : '' }}>50</option>
        </select>
      </div>

      <div class="mb-2">
        <span class="lbl">Niveau :</span>
        <select name="niveau_joueur" id="niveau_joueur" class="form-select d-inline-block" style="width:auto;">
          @for ($i=1; $i<=100; $i++)
            <option value="{{ $i }}"
              {{ $i == ($niveau_selectionne ?? $choix_niveau) ? 'selected' : '' }}
              {{ $i > $choix_niveau ? 'disabled' : '' }}>
              Niveau {{ $i }}{{ $i > $choix_niveau ? ' 🔒' : '' }}
            </option>
          @endfor
        </select>
      </div>

      <div class="mb-1">
        <span class="lbl">Choix de l’Avatar Stratégique (optionnel) :</span>
        <span>{{ $avatar_stratégique ?? 'Aucun' }}</span>
        <a href="{{ \Illuminate\Support\Facades\Route::has('avatar') ? route('avatar') : url('/avatar') }}"
           class="btn btn-sm btn-outline-light ms-2">Sélectionner</a>
      </div>
    </div>

    <div class="grid-2">
      <button type="submit" class="btn-theme" name="theme" value="general">🧠 Général</button>
      <button type="submit" class="btn-theme" name="theme" value="geographie">🌐 Géographie</button>
      <button type="submit" class="btn-theme" name="theme" value="histoire">📜 Histoire</button>
      <button type="submit" class="btn-theme" name="theme" value="art">🎨 Art</button>
      <button type="submit" class="btn-theme" name="theme" value="cinema">🎬 Cinéma</button>
      <button type="submit" class="btn-theme" name="theme" value="sport">🏅 Sport</button>
      <button type="submit" class="btn-theme" name="theme" value="faune">🦁 Faune</button>
      <button type="submit" class="btn-theme" name="theme" value="cuisine">🍳 Cuisine</button>
                  <button type="submit" name="theme" value="sciences" class="btn-theme">🔬 Sciences</button>
    </div>
  </form>
</div>

<script>
  // Avatar est optionnel → on ne vérifie que nb_questions et que le niveau sélectionné est valide
  const form = document.getElementById('soloForm');
  document.querySelectorAll('.btn-theme').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const nbq = document.getElementById('nb_questions').value;
      const niv = document.getElementById('niveau_joueur');
      if (!nbq) {
        e.preventDefault();
        alert('Choisissez le nombre de questions.');
        return false;
      }
      // Si l’option niveau sélectionnée est disabled (devrait pas arriver), on bloque
      if (niv.selectedOptions.length && niv.selectedOptions[0].disabled) {
        e.preventDefault();
        alert('Ce niveau est verrouillé.');
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
  /* Empêche tout débordement visuel dans la carte d'options */
  .container .row{overflow:hidden;}
</style>

@endsection
