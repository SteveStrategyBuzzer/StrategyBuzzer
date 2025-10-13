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
.quests-container {
    max-width: 800px;
    margin: 0 auto;
}
.quests-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
}
.quests-description {
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
  ← Menu
</a>

<div class="quests-container">
    <h1 class="quests-title">🗺️ QUÊTES</h1>
    <p class="quests-description">
        Accomplissez des quêtes épiques pour gagner des récompenses !<br>
        Débloquez des avatars exclusifs et montez en niveau.
    </p>
    <div class="coming-soon">
        🚧 Bientôt disponible
    </div>
</div>
@endsection
