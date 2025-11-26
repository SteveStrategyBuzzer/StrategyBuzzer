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
    box-sizing: border-box;
}
.header-menu {
    position: absolute;
    top: 20px;
    right: 20px;
}
.reglements-container {
    max-width: 800px;
    margin: 0 auto;
    width: 100%;
}
.reglements-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
}
.reglements-content {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 2rem;
    margin-top: 2rem;
    text-align: left;
}
.reglements-content h2 {
    color: #FFD700;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}
.reglements-content ul {
    list-style: none;
    padding-left: 0;
}
.reglements-content li {
    padding: 0.5rem 0;
    padding-left: 1.5rem;
    position: relative;
}
.reglements-content li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #FFD700;
    font-weight: bold;
}

/* Responsive Portrait */
@media (max-width: 480px) and (orientation: portrait) {
    body {
        overflow-x: hidden;
        padding: 0.5rem;
    }
    .reglements-container {
        padding: 0.5rem;
        max-width: 100%;
    }
    .reglements-title {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    .reglements-content {
        padding: 1rem;
        margin-top: 1rem;
        font-size: 0.9rem;
    }
    .reglements-content h2 {
        font-size: 1.3rem;
        margin-top: 1rem;
    }
    .header-menu {
        padding: 8px 16px !important;
        font-size: 0.9rem !important;
        top: 10px;
        right: 10px;
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

<div class="reglements-container">
    <h1 class="reglements-title">📜 {{ __('RÈGLEMENTS') }}</h1>
    
    <div class="reglements-content">
        <h2>🎮 {{ __('Règles du Jeu') }}</h2>
        <ul>
            <li>{{ __('Répondez aux questions le plus rapidement possible') }}</li>
            <li>{{ __('Utilisez le buzzer pour être le premier à répondre') }}</li>
            <li>{{ __('Une bonne réponse rapporte des points') }}</li>
            <li>{{ __('Une mauvaise réponse entraîne une pénalité') }}</li>
        </ul>

        <h2>🏆 {{ __('Système de Points') }}</h2>
        <ul>
            <li>{{ __('Points première bonne réponse') }}</li>
            <li>{{ __('Points deuxième bonne réponse') }}</li>
            <li>{{ __('Points mauvaise réponse') }}</li>
            <li>{{ __('Points pas de réponse') }}</li>
        </ul>

        <h2>⚖️ {{ __('Fair-Play') }}</h2>
        <ul>
            <li>{{ __('Respectez vos adversaires') }}</li>
            <li>{{ __('Jouez dans l\'esprit du jeu') }}</li>
            <li>{{ __('Pas de triche ou d\'exploitation de bugs') }}</li>
            <li>{{ __('Signalez tout problème technique') }}</li>
        </ul>
    </div>
</div>
@endsection
