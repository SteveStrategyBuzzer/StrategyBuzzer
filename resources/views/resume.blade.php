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
  
  /* Force 2 colonnes même sur mobile portrait */
  @media (max-width: 768px) {
    .avatars-section {
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }
    
    .avatar-img {
      width: 120px;
      height: 120px;
    }
    
    .avatar-title {
      font-size: 0.75rem;
    }
    
    .avatar-name {
      font-size: 1.1rem;
    }
  }

  /* Section Skills en dessous des avatars */
  .skills-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
  }

  @media (max-width: 768px) {
    .skills-section {
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }
  }

  .radar-container-box {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(220, 53, 69, 0.3);
    border-radius: 20px;
    padding: 20px;
    text-align: center;
  }

  .strategic-avatar-box {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(40, 167, 69, 0.3);
    border-radius: 20px;
    padding: 20px;
    text-align: center;
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

  /* Mobile portrait optimization - maximise largeur écran */
  @media (max-width: 400px) {
    .resume-container {
      margin: 10px auto;
      padding: 6px;
    }

    .info-grid {
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      margin-bottom: 20px;
    }

    .info-card {
      padding: 10px 6px;
    }

    .info-label {
      font-size: 0.7rem;
      margin-bottom: 4px;
    }

    .info-value {
      font-size: 1rem;
    }

    .avatars-section {
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      margin-bottom: 20px;
    }

    .avatar-card {
      padding: 10px 6px;
      border-radius: 12px;
    }

    .avatar-title {
      font-size: 0.75rem;
      letter-spacing: 1px;
      margin-bottom: 8px;
    }

    .avatar-img {
      width: 100px;
      height: 100px;
      border-width: 2px;
      margin-bottom: 10px;
    }

    .avatar-name {
      font-size: 1rem;
      margin-bottom: 8px;
    }

    .skills-section {
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      margin-bottom: 20px;
    }

    .strategic-avatar-box,
    .radar-container-box {
      padding: 10px 6px;
      border-radius: 12px;
    }

    .strategic-avatar-box > a > div:first-child {
      font-size: 1rem !important;
      margin-bottom: 12px !important;
    }

    .skill-wrapper {
      margin-bottom: 8px;
    }

    .skill-name {
      font-size: 0.75rem;
      margin-bottom: 3px;
    }

    .skill-desc {
      font-size: 0.65rem;
      line-height: 1.2;
    }

    .radar-container-box > div:first-child {
      font-size: 0.85rem !important;
      margin-bottom: 10px !important;
    }

    #radarChart {
      max-width: 160px !important;
    }

    .start-button {
      padding: 14px 40px;
      font-size: 1.1rem;
      margin-top: 15px;
    }

    /* Réduire aussi les textes descriptifs */
    .avatar-card > div:last-of-type {
      font-size: 0.75rem !important;
      margin-top: 6px !important;
      line-height: 1.2 !important;
    }
  }
</style>

<!-- Bouton Retour Solo -->
<a href="{{ route('solo.index') }}" class="menu-button">
  ← {{ __('SOLO') }}
</a>

<div class="resume-container">
  <!-- Titre -->
  <div class="title-section">
    <h1>
      @if($params['has_boss'] ?? false)
        ⚔️ {{ __('Boss Challenge') }}
      @else
        🧾 {{ __('Descriptif de la Partie') }}
      @endif
    </h1>
  </div>

  <!-- Informations de la partie -->
  <div class="info-grid">
    <div class="info-card">
      <div class="info-label">{{ __('Thème') }}</div>
      <div class="info-value">{{ ucfirst($params['theme']) }} {{ $params['theme_icon'] ?? '' }}</div>
    </div>
    
    <div class="info-card">
      <div class="info-label">{{ __('Questions Par Manche') }}</div>
      <div class="info-value">{{ $params['nb_questions'] }}</div>
    </div>
  </div>

  <!-- Alerte si conflit d'avatar -->
  @if($params['avatar_conflict'] ?? false)
    <div style="background: rgba(220, 53, 69, 0.2); border: 2px solid #dc3545; border-radius: 12px; padding: 15px; margin-bottom: 30px; text-align: center;">
      <strong>⚠️ {{ __('Attention') }} :</strong> {{ __('Vous ne pouvez pas utiliser le même avatar stratégique que le Boss ! Votre avatar a été réinitialisé.') }}
    </div>
  @endif

  <!-- Avatars côte à côte -->
  <div class="avatars-section">
    <!-- Avatar Joueur (Gauche) -->
    <div class="avatar-card player">
      @php
        $playerName = $params['player_pseudonym'] ?? 'Joueur';
        $niveauProgression = $params['niveau_progression'] ?? 1;
      @endphp
      <div class="avatar-title">{{ $playerName }}</div>
      
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
        <div class="avatar-name">Niv: {{ $niveauProgression }}</div>
      </a>
    </div>

    <!-- Avatar Boss (Droite) - Uniquement si niveau >= 10 -->
    @if($params['has_boss'] ?? false)
      <div class="avatar-card boss">
        <div class="avatar-title">{{ $params['boss_name'] }}</div>
        <img src="{{ asset($params['boss_avatar']) }}?v={{ time() }}" 
             alt="{{ $params['boss_name'] }}" 
             class="avatar-img">
        @php
          $opponentInfo = $params['opponent_info'] ?? null;
          $opponentName = $opponentInfo['name'] ?? 'Adversaire';
          $opponentAge = $opponentInfo['age'] ?? 0;
        @endphp
        <div class="avatar-name">{{ $opponentName }}    Niv: {{ $params['niveau_joueur'] }}</div>
        <div style="font-size: 0.95rem; opacity: 0.9; margin-top: 10px; line-height: 1.4;">
          {{ __('Votre adversaire') }}, {{ $opponentAge }} {{ __('ans élève du') }} "{{ $params['boss_name'] }}"
        </div>
        
        @if(!empty($params['boss_skills']))
          <div class="skills-list" style="margin-top: 20px;">
            <div class="skills-title">⚔️ {{ __('Compétences du Boss') }}</div>
            @foreach ($params['boss_skills'] as $skill)
              <div class="skill-item">{{ $skill }}</div>
            @endforeach
          </div>
        @endif
      </div>
    @else
      <div class="avatar-card" style="border-color: rgba(255,255,255,0.3);">
        @if(isset($params['opponent_info']) && !$params['opponent_info']['is_boss'])
          @php
            $nextBoss = $params['opponent_info']['next_boss'] ?? 'Le Maître';
            $opponentName = $params['opponent_info']['name'] ?? 'Adversaire';
          @endphp
          <div class="avatar-title">{{ $opponentName }}</div>
          
          <!-- Photo de l'adversaire élève -->
          <img src="/images/avatars/students/{{ $params['opponent_info']['avatar'] }}.png" 
               alt="Avatar {{ $params['opponent_info']['name'] }}" 
               class="avatar-img"
               onerror="this.src='/images/avatars/students/default.png'">
          
          <!-- Format symétrique nom + niveau -->
          <div class="avatar-name">{{ $params['opponent_info']['name'] }}    Niv: {{ $params['niveau_joueur'] }}</div>
          
          <!-- Texte descriptif de l'adversaire -->
          <div style="font-size: 0.95rem; opacity: 0.9; margin-top: 10px; line-height: 1.4;">
            Votre adversaire, {{ $params['opponent_info']['age'] }} ans, élève de "{{ $nextBoss }}"
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

  <!-- Section Skills : Avatar Stratégique (gauche) et Radar Boss (droite) -->
  <div class="skills-section">
    <!-- Avatar Stratégique (Gauche) -->
    <div class="strategic-avatar-box">
      <a href="{{ route('avatar', ['from' => 'resume']) }}" style="text-decoration: none; color: inherit;">
        @if($params['avatar'] !== 'Aucun')
          <div style="font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; color: #FFD700;">⚔️ {{ $params['avatar'] }}</div>
          
          @php
            // Mapping complet des skills avec descriptions
            $avatarSkillsComplete = [
                'Mathématicien' => [
                    ['icon' => '🔢', 'name' => 'Calcul Rapide', 'description' => 'Active automatiquement']
                ],
                'Scientifique' => [
                    ['icon' => '🧪', 'name' => 'Acidifier', 'description' => 'Élimine 2 mauvaises réponses (1x par partie)']
                ],
                'Explorateur' => [
                    ['icon' => '👁️', 'name' => 'Voir choix', 'description' => 'Voit le choix de l\'adversaire (1x par partie)']
                ],
                'Défenseur' => [
                    ['icon' => '🛡️', 'name' => 'Protection', 'description' => 'Active automatiquement']
                ],
                'Comédien' => [
                    ['icon' => '🎯', 'name' => 'Précision', 'description' => 'Active automatiquement'],
                    ['icon' => '🌀', 'name' => 'Confusion', 'description' => 'Active automatiquement']
                ],
                'Comédienne' => [
                    ['icon' => '🎯', 'name' => 'Précision', 'description' => 'Active automatiquement'],
                    ['icon' => '🌀', 'name' => 'Confusion', 'description' => 'Active automatiquement']
                ],
                'Magicien' => [
                    ['icon' => '✨', 'name' => 'Cancel une mauvaise réponse', 'description' => 'Elle vous la transforme en sans réponse'],
                    ['icon' => '💫', 'name' => 'Question Bonus', 'description' => 'Gagnez une question supplémentaire']
                ],
                'Magicienne' => [
                    ['icon' => '✨', 'name' => 'Cancel une mauvaise réponse', 'description' => 'Elle vous la transforme en sans réponse'],
                    ['icon' => '💫', 'name' => 'Question Bonus', 'description' => 'Gagnez une question supplémentaire']
                ],
                'Challenger' => [
                    ['icon' => '🔄', 'name' => 'Rotation', 'description' => 'Active automatiquement'],
                    ['icon' => '⏳', 'name' => 'Temps', 'description' => 'Active automatiquement']
                ],
                'Historien' => [
                    ['icon' => '🪶', 'name' => 'Savoir sans temps', 'description' => 'Répondre sans buzzer (+1 max)'],
                    ['icon' => '📜', 'name' => "L'histoire corrige", 'description' => 'Récupérer les points après erreur']
                ],
                'IA Junior' => [
                    ['icon' => '🤖', 'name' => 'IA Assist', 'description' => 'Active automatiquement'],
                    ['icon' => '🎯', 'name' => 'Élimination', 'description' => 'Active automatiquement']
                ],
                'Stratège' => [
                    ['icon' => '💰', 'name' => 'Bonus Pièces', 'description' => '25% gain supplémentaire'],
                    ['icon' => $params['teammate_skill_icon'] ?? '👥', 'name' => 'Team', 'description' => 'Choix d\'un Avatar Stratégique Rare'],
                    ['icon' => '🏷️', 'name' => 'Réduction', 'description' => 'Boutique']
                ],
                'Sprinteur' => [
                    ['icon' => '⚡', 'name' => 'Vitesse', 'description' => 'Active automatiquement'],
                    ['icon' => '⏱️', 'name' => 'Réflexion', 'description' => 'Active automatiquement']
                ],
                'Visionnaire' => [
                    ['icon' => '👁️', 'name' => 'Prémonition', 'description' => 'Cliquez pour voir un résumé', 'skill_id' => 'premonition'],
                    ['icon' => '🏰', 'name' => 'Forteresse', 'description' => 'Immunité contre Challenger'],
                    ['icon' => '🎯', 'name' => 'Réponse Sécurisée', 'description' => 'Sur 2 pts, bonne réponse cliquable']
                ],
            ];
            $currentSkills = $avatarSkillsComplete[$params['avatar']] ?? [];
          @endphp
          
          @if(!empty($currentSkills))
            <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 10px;">
              @foreach ($currentSkills as $skill)
                @if(isset($skill['skill_id']) && $skill['skill_id'] === 'preview_questions')
                  {{-- Skill Visionnaire Preview - Cliquable --}}
                  <div class="visionnaire-preview-skill" 
                       onclick="showVisionnairePreview()" 
                       style="background: rgba(138,43,226,0.25); border: 2px solid #8a2be2; border-radius: 12px; padding: 12px; text-align: left; cursor: pointer; transition: all 0.3s ease;"
                       onmouseover="this.style.transform='scale(1.02)'; this.style.boxShadow='0 0 15px rgba(138,43,226,0.5)';"
                       onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                    <div style="font-size: 1.1rem; font-weight: 600; color: #8a2be2; margin-bottom: 5px;">
                      <span style="font-size: 1.5rem;">{{ $skill['icon'] }}</span> {{ $skill['name'] }}
                      <span style="float: right; font-size: 0.8rem; background: #8a2be2; color: white; padding: 2px 8px; border-radius: 10px;">
                        {{ $params['visionnaire_previews_remaining'] ?? 5 }}/5
                      </span>
                    </div>
                    <div style="font-size: 0.85rem; opacity: 0.9; line-height: 1.3;">
                      {{ $skill['description'] }}
                    </div>
                  </div>
                @else
                  <div style="background: rgba(255,215,0,0.15); border: 2px solid #FFD700; border-radius: 12px; padding: 12px; text-align: left;">
                    <div style="font-size: 1.1rem; font-weight: 600; color: #FFD700; margin-bottom: 5px;">
                      <span style="font-size: 1.3rem;">{{ $skill['icon'] }}</span> {{ $skill['name'] }}
                    </div>
                    <div style="font-size: 0.85rem; opacity: 0.9; line-height: 1.3;">
                      {{ $skill['description'] }}
                    </div>
                  </div>
                @endif
              @endforeach
            </div>
          @endif
        @else
          <div style="padding: 30px 10px;">
            <div style="font-size: 2rem; margin-bottom: 10px;">⚔️</div>
            <div style="font-size: 1rem; opacity: 0.7;">Aucun avatar stratégique</div>
          </div>
        @endif
      </a>
    </div>

    <!-- Radar Boss (Droite) - Seulement si c'est un boss -->
    <div class="radar-container-box">
      @if($params['has_boss'] ?? false)
        <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 15px;">📊 Compétences du Boss</div>
        <canvas id="radarChart" style="max-width: 350px; margin: 0 auto;"></canvas>
      @else
        <div style="padding: 30px 10px;">
          <div style="font-size: 2rem; margin-bottom: 10px;">📊</div>
          <div style="font-size: 1rem; opacity: 0.7;">Pas de diagramme pour élève</div>
        </div>
      @endif
    </div>
  </div>

  <!-- Bouton Démarrer -->
  <form action="{{ route('solo.prepare') }}" method="GET">
    <button type="submit" class="start-button">
      ▶️ Démarrer la Partie
    </button>
  </form>
</div>

<!-- Chart.js pour le radar du boss -->
@if($params['has_boss'] ?? false)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
@php
  // Récupérer les données radar du boss depuis config/opponents.php
  $niveau = $params['niveau_joueur'];
  $bossOpponents = config('opponents.boss_opponents', []);
  $bossData = $bossOpponents[$niveau] ?? null;
@endphp

@if($bossData && isset($bossData['radar']))
const radarData = {
  labels: {!! json_encode(array_keys($bossData['radar'])) !!},
  datasets: [{
    label: '{{ $bossData['name'] }}',
    data: {!! json_encode(array_values($bossData['radar'])) !!},
    fill: true,
    backgroundColor: 'rgba(220, 53, 69, 0.2)',
    borderColor: '#dc3545',
    borderWidth: 2,
    pointBackgroundColor: '#dc3545',
    pointBorderColor: '#fff',
    pointBorderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6
  }]
};

const config = {
  type: 'radar',
  data: radarData,
  options: {
    responsive: true,
    maintainAspectRatio: true,
    scales: {
      r: {
        beginAtZero: true,
        max: 100,
        ticks: {
          stepSize: 20,
          color: '#FFD700',
          backdropColor: 'transparent',
          font: {
            size: 10,
            weight: 'bold'
          }
        },
        grid: {
          color: 'rgba(255, 255, 255, 0.2)',
          lineWidth: 1
        },
        pointLabels: {
          color: '#FFD700',
          font: {
            size: 11,
            weight: 'bold'
          }
        }
      }
    },
    plugins: {
      legend: {
        display: false
      }
    }
  }
};

const radarChart = new Chart(
  document.getElementById('radarChart'),
  config
);
@endif
</script>
@endif
@endsection
