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
    align-items: center;
    padding: 20px;
}
.header-menu {
    position: absolute;
    top: 20px;
    right: 20px;
}
.ligue-container {
    max-width: 800px;
    width: 100%;
}
.ligue-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
    text-transform: uppercase;
}
.ligue-subtitle {
    font-size: 1.2rem;
    margin-bottom: 3rem;
    opacity: 0.9;
}
.ligue-modes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}
.ligue-mode-card {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    padding: 2rem;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.ligue-mode-card:hover {
    transform: translateY(-8px);
    background: rgba(255, 255, 255, 0.2);
    border-color: white;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}
.mode-icon {
    font-size: 4rem;
}
.mode-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
}
.mode-description {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
}
.mode-badge {
    background: rgba(255, 215, 0, 0.2);
    border: 1px solid #FFD700;
    color: #FFD700;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 0.5rem;
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
  ← Menu
</a>

<div class="ligue-container">
    <h1 class="ligue-title">🏆 LIGUE</h1>
    <p class="ligue-subtitle">Choisissez votre mode de compétition</p>
    
    <div class="ligue-modes">
        <a href="{{ route('league.individual.lobby') }}" class="ligue-mode-card">
            <div class="mode-icon">👤</div>
            <h2 class="mode-title">INDIVIDUEL</h2>
            <p class="mode-description">Affrontez des adversaires en 1v1 et grimpez dans les divisions</p>
            <div class="mode-badge">Carrière Solo</div>
        </a>

        <a href="{{ route('league.team.management') }}" class="ligue-mode-card">
            <div class="mode-icon">👥</div>
            <h2 class="mode-title">ÉQUIPE</h2>
            <p class="mode-description">Formez une équipe de 5 joueurs et dominez la compétition</p>
            <div class="mode-badge">5v5</div>
        </a>
    </div>

    <div style="margin-top: 3rem; opacity: 0.8; font-size: 0.9rem;">
        <p>📊 Système de divisions: Bronze → Argent → Or → Platine → Diamant → Légende</p>
    </div>
</div>
@endsection
