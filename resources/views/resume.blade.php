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
  
  .avatar-clickable {
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
  }
  
  .avatar-clickable::after {
    content: '✏️ Modifier';
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    opacity: 0;
    transition: opacity 0.3s ease;
  }
  
  .avatar-clickable:hover::after {
    opacity: 1;
  }
  
  .avatar-slot {
    background: rgba(0,0,0,0.3);
    border: 2px dashed rgba(255,255,255,0.4);
    border-radius: 16px;
    padding: 20px;
    margin-top: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
  }
  
  .avatar-slot:hover {
    border-color: #FFD700;
    background: rgba(255,215,0,0.1);
    transform: scale(1.02);
  }
  
  .avatar-slot.selected {
    border-style: solid;
    border-color: #28a745;
    background: rgba(40, 167, 69, 0.15);
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
  
  .menu-button {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 30px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 30px;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  }
  
  .menu-button:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
  }
  
  @media (max-width: 768px) {
    .menu-button {
      top: 10px;
      right: 10px;
      padding: 10px 20px;
      font-size: 0.9rem;
    }
  }
</style>

<!-- Bouton Retour Solo -->
<a href="{{ route('solo.index') }}" class="menu-button">
  ← Solo
</a>

<div class="resume-container">
  <!-- Titre -->
  <div class="title-section">
    <h1>🧾 Descriptif de la Partie</h1>
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
      <div class="info-label">Adversaire</div>
      <div class="info-value">
        @if(isset($params['opponent_info']) && isset($params['opponent_info']['name']))
          {{ $params['opponent_info']['name'] }} (Niveau {{ $params['niveau_joueur'] }})
        @else
          Niveau {{ $params['niveau_joueur'] }}
        @endif
      </div>
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
      <div class="avatar-title">👤 Avatar Joueur</div>
      
      <!-- Emplacement Avatar Portrait - Cliquable -->
      <a href="{{ route('avatar', ['from' => 'resume']) }}" class="avatar-clickable" style="display: block; text-decoration: none; color: inherit;">
        @php
          $avatarPath = $params['player_avatar'] ?? 'default';
          // Si le chemin contient déjà 'images/', l'utiliser tel quel, sinon construire le chemin
          if (strpos($avatarPath, 'images/') === 0 || strpos($avatarPath, '/') !== false) {
            $fullPath = $avatarPath;
          } else {
            $fullPath = 'images/avatars/standard/' . $avatarPath . '.png';
          }
        @endphp
        <img src="{{ asset($fullPath) }}?v={{ time() }}" 
             alt="Avatar Joueur" 
             class="avatar-img"
             onerror="this.src='{{ asset('images/avatars/default.png') }}'">
        <div class="avatar-name">Vous</div>
      </a>
      
      <!-- Emplacement Avatar Stratégique - Cliquable -->
      <a href="{{ route('avatar', ['from' => 'resume']) }}" style="text-decoration: none; color: inherit;">
        @if($params['avatar'] !== 'Aucun')
          <div class="avatar-slot selected">
            <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 15px;">⚔️ Avatar Stratégique</div>
            
            @if(!empty($params['avatar_image']))
              <img src="{{ asset($params['avatar_image']) }}" 
                   alt="{{ $params['avatar'] }}" 
                   style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #FFD700; margin: 0 auto 15px; display: block; box-shadow: 0 6px 15px rgba(255, 215, 0, 0.4);"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
              <div style="display: none; font-size: 3rem; margin-bottom: 10px;">⚔️</div>
            @else
              <div style="font-size: 3rem; margin-bottom: 10px;">⚔️</div>
            @endif
            
            <div style="font-size: 1.3rem; color: #FFD700; margin-bottom: 15px; font-weight: 700;">{{ $params['avatar'] }}</div>
            @php
              // Mapping complet : emoji + nom + description pour chaque avatar
              $avatarSkillsComplete = [
                  'Mathématicien' => [
                      ['icon' => '🔢', 'name' => 'Calcul Rapide', 'desc' => 'Peut faire illuminer une bonne réponse si il y a un chiffre dans la réponse']
                  ],
                  'Scientifique' => [
                      ['icon' => '⚗️', 'name' => 'Analyse', 'desc' => 'Peut acidifier une mauvaise réponse 1 fois avant de choisir']
                  ],
                  'Explorateur' => [
                      ['icon' => '🧭', 'name' => 'Navigation', 'desc' => 'La réponse s\'illumine du choix du joueur adverse ou la réponse la plus cliqué']
                  ],
                  'Défenseur' => [
                      ['icon' => '🛡️', 'name' => 'Protection', 'desc' => 'Peut annuler une attaque de n\'importe quel Avatar']
                  ],
                  'Comédien' => [
                      ['icon' => '🎯', 'name' => 'Précision', 'desc' => 'Peut indiquer un score moins élevé jusqu\'à la fin de la partie'],
                      ['icon' => '🌀', 'name' => 'Confusion', 'desc' => 'Capacité de tromper les joueurs sur une bonne réponse en mauvaise réponse']
                  ],
                  'Comédienne' => [
                      ['icon' => '🎯', 'name' => 'Précision', 'desc' => 'Peut indiquer un score moins élevé jusqu\'à la fin de la partie'],
                      ['icon' => '🌀', 'name' => 'Confusion', 'desc' => 'Capacité de tromper les joueurs sur une bonne réponse en mauvaise réponse']
                  ],
                  'Magicien' => [
                      ['icon' => '✨', 'name' => 'Magie', 'desc' => 'Peut avoir une question bonus par partie'],
                      ['icon' => '💫', 'name' => 'Étoile', 'desc' => 'Peut annuler une mauvaise réponse non buzzer 1 fois par partie']
                  ],
                  'Magicienne' => [
                      ['icon' => '✨', 'name' => 'Magie', 'desc' => 'Peut avoir une question bonus par partie'],
                      ['icon' => '💫', 'name' => 'Étoile', 'desc' => 'Peut annuler une mauvaise réponse non buzzer 1 fois par partie']
                  ],
                  'Challenger' => [
                      ['icon' => '🔄', 'name' => 'Rotation', 'desc' => 'Fait changer les réponses des participants d\'emplacement au 2 sec'],
                      ['icon' => '⏳', 'name' => 'Temps', 'desc' => 'Diminue aux autres joueurs leur compte à rebours']
                  ],
                  'Historien' => [
                      ['icon' => '🪶', 'name' => 'Histoire', 'desc' => 'Voit un indice texte avant les autres'],
                      ['icon' => '⏰', 'name' => 'Chrono', 'desc' => '1 fois 2 sec de plus pour répondre']
                  ],
                  'IA Junior' => [
                      ['icon' => '🤖', 'name' => 'IA Assist', 'desc' => 'Voit une suggestion IA qui illumine pour la réponse 1 fois'],
                      ['icon' => '🎯', 'name' => 'Élimination', 'desc' => 'Peut éliminer 2 mauvaises réponses sur les 4'],
                      ['icon' => '↩️', 'name' => 'Reprise', 'desc' => 'Peut reprendre une réponse 1 fois']
                  ],
                  'Stratège' => [
                      ['icon' => '💰', 'name' => 'Bonus Pièces', 'desc' => 'Gagne +20% de pièces d\'intelligence sur une victoire'],
                      ['icon' => '👥', 'name' => 'Team', 'desc' => 'Peut créer un team (Ajouter 1 Avatar rare) en mode solo'],
                      ['icon' => '💎', 'name' => 'Réduction', 'desc' => 'Réduit le coût de déblocage des Avatars de 10%']
                  ],
                  'Sprinteur' => [
                      ['icon' => '⚡', 'name' => 'Vitesse', 'desc' => 'Peut reculer son temps de buzzer jusqu\'à 0.5s du plus rapide'],
                      ['icon' => '⏱️', 'name' => 'Réflexion', 'desc' => 'Peut utiliser 3 secondes de réflexion de plus 1 fois'],
                      ['icon' => '🔄', 'name' => 'Auto-Reset', 'desc' => 'Après chaque niveau se réactivent automatiquement']
                  ],
                  'Visionnaire' => [
                      ['icon' => '🔮', 'name' => 'Futur', 'desc' => 'Peut voir 5 questions "future" (prochaine question révélée)'],
                      ['icon' => '🛡️', 'name' => 'Contre', 'desc' => 'Peut contrer l\'attaque du Challenger'],
                      ['icon' => '🎯', 'name' => 'Certitude', 'desc' => 'Si 2 points dans une manche, seule la bonne réponse est sélectionnable']
                  ],
              ];
              $currentSkills = $avatarSkillsComplete[$params['avatar']] ?? [];
            @endphp
            @if(!empty($currentSkills))
              <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; margin-top: 10px;">
                @foreach ($currentSkills as $skill)
                  <div style="
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                    padding: 6px 0;
                    font-size: 0.85rem;
                    line-height: 1.3;
                    border-bottom: {{ $loop->last ? 'none' : '1px solid rgba(255,255,255,0.1)' }};
                  ">
                    <span style="font-size: 1.2rem; flex-shrink: 0;">{{ $skill['icon'] }}</span>
                    <span style="color: #FFD700; font-weight: 600; min-width: 80px;">{{ $skill['name'] }}</span>
                    <span style="opacity: 0.9; flex: 1;">{{ $skill['desc'] }}</span>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        @else
          <div class="avatar-slot">
            <div style="font-size: 2rem; margin-bottom: 10px;">⚔️</div>
            <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 5px;">Avatar Stratégique</div>
            <div style="opacity: 0.7; font-size: 0.9rem;">Cliquez pour choisir</div>
          </div>
        @endif
      </a>
    </div>

    <!-- DEBUG -->
    @php
        if (isset($params['opponent_info'])) {
            echo "<!-- DEBUG opponent_info: " . json_encode($params['opponent_info']) . " -->";
        } else {
            echo "<!-- DEBUG: opponent_info NOT SET -->";
        }
    @endphp
    
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
        @if(isset($params['opponent_info']) && !$params['opponent_info']['is_boss'])
          <!-- Photo de l'adversaire élève -->
          <img src="/images/avatars/students/{{ $params['opponent_info']['avatar'] }}.png" 
               alt="Avatar {{ $params['opponent_info']['name'] }}" 
               class="avatar-img"
               onerror="this.src='/images/avatars/students/default.png'"
               style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; margin: 20px auto; display: block;">
          
          <!-- Texte descriptif de l'adversaire -->
          <div style="padding: 0 20px 30px; text-align: center;">
            <div style="font-size: 1.1rem; font-weight: 600; opacity: 0.95; line-height: 1.5;">
              Votre adversaire {{ $params['opponent_info']['name'] }} {{ $params['opponent_info']['age'] }} ans élève de {{ $params['opponent_info']['next_boss'] }}
            </div>
          </div>
        @else
          <div style="padding: 40px 20px; text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 20px;">🎓</div>
            <div style="font-size: 1.3rem; font-weight: 600; margin-bottom: 10px;">Pas de Boss</div>
            <div style="opacity: 0.8; font-size: 1rem;">
              Le premier boss apparaît au niveau 10.<br>
              Continuez à vous entraîner !
            </div>
          </div>
        @endif
      </div>
    @endif
  </div>

  <!-- Bouton Démarrer -->
  <form action="{{ route('solo.prepare') }}" method="GET">
    <button type="submit" class="start-button">
      ▶️ Démarrer la Partie
    </button>
  </form>
</div>
@endsection
