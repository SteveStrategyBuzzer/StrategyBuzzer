@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    text-align: center;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 20px;
}
.header-menu {
    position: absolute;
    top: 20px;
    right: 20px;
}
.master-container {
    max-width: 800px;
    margin: 0 auto;
}
.master-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
}
.master-description {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}
.coming-soon {
    background: rgba(255, 215, 0, 0.2);
    border: 2px solid #FFD700;
    color: #FFD700;
    padding: 1rem 2rem;
    border-radius: 12px;
    display: inline-block;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Responsive Portrait */
@media (max-width: 480px) and (orientation: portrait) {
    body {
        overflow-x: hidden;
        padding: 0;
    }
    .master-container {
        padding: 1rem;
        max-width: 100%;
    }
    .master-title {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    .master-description {
        font-size: 1rem;
        margin-bottom: 1.5rem;
        padding: 0 0.5rem;
    }
    .coming-soon {
        font-size: 1rem;
        padding: 0.8rem 1.5rem;
    }
    .header-menu {
        padding: 8px 16px !important;
        font-size: 0.9rem !important;
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
" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(255,255,255,0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
  ← {{ __('Menu') }}
</a>

<div class="master-container">
    <h1 class="master-title">🎓 {{ __('MAÎTRE DU JEU') }}</h1>
    <p class="master-description">
        {{ __('Interface pour créer et lancer des parties avec IA') }}.<br>
        {{ __('Créez vos propres questions et devenez le maître du jeu') }} !
    </p>
    <div class="coming-soon">
        🚧 {{ __('Bientôt disponible') }}
    </div>
</div>
@endsection
