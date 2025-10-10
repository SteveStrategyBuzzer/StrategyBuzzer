@extends('layouts.app')

@section('content')
<style>
.header-menu {
    position: absolute;
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

<div class="container text-center">
    <h1>Quêtes</h1>
    <p>Complétez des quêtes pour gagner des titres, des vies et des styles visuels.</p>

    <ul class="list-group mt-4">
        <li class="list-group-item">Quête 1 : Remporter 5 parties consécutives</li>
        <li class="list-group-item">Quête 2 : Répondre correctement à 50 questions</li>
        <li class="list-group-item">Quête 3 : Débloquer un titre rare</li>
    </ul>
</div>
@endsection
