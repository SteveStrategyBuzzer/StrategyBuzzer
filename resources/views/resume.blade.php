@extends('layouts.app')

@section('content')
<style>
  body { 
    background: linear-gradient(135deg, #003DA5 0%, #001A52 100%); 
    color: #fff; 
    min-height: 100vh;
    overflow-x: hidden;
  }
  
  .resume-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
  }
  
  .title-section {
    text-align: center;
    margin-bottom: 40px;
  }
  
  .title-section h1 {
    font-size: 3rem;
    font-weight: 700;
    text-shadow: 0 4px 8px rgba(0,0,0,0.3);
    margin-bottom: 10px;
  }
  
  .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 40px;
  }
  
  .info-card {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
  }
  
  .info-label {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    margin-bottom: 8px;
  }
  
  .info-value {
    font-size: 1.5rem;
    font-weight: 700;
  }
  
  .avatars-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
  }
  
  @media (max-width: 768px) {
    .avatars-section {
      grid-template-columns: 1fr;
      gap: 20px;
    }
  }
  
  .avatar-card {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.15);
    border-radius: 20px;
    padding: 30px;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  
  .avatar-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  }
  
  .avatar-card.player {
    border-color: #28a745;
    box-shadow: 0 0 20px rgba(40, 167, 69, 0.3);
  }
  
  .avatar-card.boss {
    border-color: #dc3545;
    box-shadow: 0 0 20px rgba(220, 53, 69, 0.3);
  }
  
  .avatar-title {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 15px;
    opacity: 0.8;
  }
  
  .avatar-img {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255,255,255,0.3);
    margin: 0 auto 20px;
    display: block;
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
  }
  
  .avatar-name {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 15px;
  }
  
  .skills-list {
    background: rgba(0,0,0,0.2);
    border-radius: 12px;
    padding: 15px;
    margin-top: 15px;
  }
  
  .skills-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 10px;
    color: #FFD700;
  }
  
  .skill-item {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 8px 12px;
    margin: 6px 0;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .skill-item::before {
    content: '⚡';
    margin-right: 8px;
    font-size: 1.2rem;
  }
  
  .start-button {
    display: block;
    margin: 0 auto;
    padding: 18px 60px;
    font-size: 1.3rem;
    font-weight: 700;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: #fff;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
  }
  
  .start-button:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 10px 30px rgba(40, 167, 69, 0.6);
  }
  
  .start-button:active {
    transform: translateY(-1px);
  }
</style>

<div class="resume-container">
  <!-- Titre -->
  <div class="title-section">
    <h1>🧾 Résumé de la Partie</h1>
  </div>

  <!-- Informations de la partie -->
  <div class="info-grid">
    <div class="info-card">
      <div class="info-label">Thème</div>
      <div class="info-value">{{ ucfirst($params['theme']) }} {{ $params['theme_icon'] ?? '' }}</div>
    </div>
    
    <div class="info-card">
      <div class="info-label">Questions</div>
      <div class="info-value">{{ $params['nb_questions'] }}</div>
    </div>
    
    <div class="info-card">
      <div class="info-label">Niveau</div>
      <div class="info-value">{{ $params['niveau_joueur'] }}</div>
    </div>
  </div>

  <!-- Alerte si conflit d'avatar -->
  @if($params['avatar_conflict'] ?? false)
    <div style="background: rgba(220, 53, 69, 0.2); border: 2px solid #dc3545; border-radius: 12px; padding: 15px; margin-bottom: 30px; text-align: center;">
      <strong>⚠️ Attention :</strong> Vous ne pouvez pas utiliser le même avatar stratégique que le Boss ! Votre avatar a été réinitialisé.
    </div>
  @endif

  <!-- Avatars côte à côte -->
  <div class="avatars-section">
    <!-- Avatar Joueur (Gauche) -->
    <div class="avatar-card player">
      <div class="avatar-title">👤 Joueur</div>
      <img src="{{ asset('images/avatars/portraits/' . ($params['player_avatar'] ?? 'default') . '.png') }}?v={{ time() }}" 
           alt="Avatar Joueur" 
           class="avatar-img"
           onerror="this.src='{{ asset('images/avatars/default.png') }}'">
      <div class="avatar-name">Vous</div>
      
      @if($params['avatar'] !== 'Aucun')
        <div class="skills-list" style="border-color: #28a745;">
          <div class="skills-title">⚔️ Avatar Stratégique : {{ $params['avatar'] }}</div>
          @if(!empty($params['avatar_skills']))
            @foreach ($params['avatar_skills'] as $skill)
              <div class="skill-item">{{ $skill }}</div>
            @endforeach
          @endif
        </div>
      @else
        <div style="margin-top: 15px; opacity: 0.7; font-size: 0.95rem;">Aucun avatar stratégique</div>
      @endif
    </div>

    <!-- Avatar Boss (Droite) - Uniquement si niveau >= 10 -->
    @if($params['has_boss'] ?? false)
      <div class="avatar-card boss">
        <div class="avatar-title">🤖 Boss de Niveau {{ $params['niveau_joueur'] }}</div>
        <img src="{{ asset($params['boss_avatar']) }}?v={{ time() }}" 
             alt="{{ $params['boss_name'] }}" 
             class="avatar-img">
        <div class="avatar-name">{{ $params['boss_name'] }}</div>
        
        @if(!empty($params['boss_skills']))
          <div class="skills-list">
            <div class="skills-title">⚔️ Compétences du Boss</div>
            @foreach ($params['boss_skills'] as $skill)
              <div class="skill-item">{{ $skill }}</div>
            @endforeach
          </div>
        @endif
      </div>
    @else
      <div class="avatar-card" style="border-color: rgba(255,255,255,0.3);">
        <div class="avatar-title">🎯 Niveau d'Entraînement</div>
        <div style="padding: 40px 20px; text-align: center;">
          <div style="font-size: 4rem; margin-bottom: 20px;">🎓</div>
          <div style="font-size: 1.3rem; font-weight: 600; margin-bottom: 10px;">Pas de Boss</div>
          <div style="opacity: 0.8; font-size: 1rem;">
            Le premier boss apparaît au niveau 10.<br>
            Continuez à vous entraîner !
          </div>
        </div>
      </div>
    @endif
  </div>

  <!-- Bouton Démarrer -->
  <form action="{{ route('solo.game') }}" method="GET">
    <button type="submit" class="start-button">
      ▶️ Démarrer la Partie
    </button>
  </form>
</div>
@endsection
