@extends('layouts.app')

@section('content')
<style>
  body { 
    background: #003DA5; 
    color: #fff; 
    overflow: hidden;
    margin: 0;
    padding: 0;
  }
  
  .header-menu {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    background: white;
    color: #003DA5;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s ease;
  }
  .header-menu:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,255,255,0.3);
    color: #003DA5;
  }
  
  .gallery-header {
    text-align: center;
    padding: 20px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(0,61,165,0.95);
    z-index: 100;
  }
  
  .gallery-header h1 {
    font-size: 1.8rem;
    margin: 0 0 5px 0;
  }
  
  .gallery-header p {
    font-size: 0.95rem;
    margin: 0;
    opacity: 0.9;
  }
  
  /* MODE PORTRAIT - Vertical Scroll (Mobile Portrait, Tablet Portrait) */
  @media (orientation: portrait), (max-aspect-ratio: 1/1) {
    .gallery-container {
      display: block !important;
      position: absolute;
      top: 120px;
      left: 0;
      right: 0;
      bottom: 0;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 0 15px 30px 15px;
    }
    
    .carousel-container {
      display: none !important;
    }
    
    .section-block {
      margin-bottom: 40px;
    }
    
    .boss-card-portrait {
      display: block;
      margin: 0 auto 20px auto;
      max-width: 280px;
    }
    
    .boss-card-landscape {
      display: none !important;
    }
    
    .students-grid-portrait {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }
    
    .student-column {
      display: none !important;
    }
  }
  
  /* MODE PAYSAGE - Horizontal Swipe (Mobile Landscape, Desktop) */
  @media (orientation: landscape), (min-aspect-ratio: 1/1) {
    .gallery-container {
      display: none !important;
    }
    
    .carousel-container {
      display: flex !important;
      position: absolute;
      top: 90px;
      left: 0;
      right: 0;
      bottom: 40px;
      overflow-x: auto;
      overflow-y: hidden;
      scroll-snap-type: x mandatory;
      -webkit-overflow-scrolling: touch;
    }
    
    .carousel-section {
      min-width: 100vw;
      width: 100vw;
      height: 100%;
      scroll-snap-align: start;
      display: flex !important;
      align-items: center;
      justify-content: center;
      padding: 15px;
      box-sizing: border-box;
    }
    
    .landscape-layout {
      display: flex !important;
      flex-direction: row;
      gap: 15px;
      align-items: center;
      justify-content: center;
      height: auto;
      max-height: 100%;
    }
    
    .student-column {
      display: flex !important;
      flex-direction: column;
      gap: 10px;
      justify-content: center;
    }
    
    .student-column .opponent-card {
      flex-shrink: 0;
      width: 110px;
      height: auto;
    }
    
    .boss-card-landscape {
      display: block !important;
      flex-shrink: 0;
      width: 160px;
    }
    
    .boss-card-portrait {
      display: none !important;
    }
    
    .students-grid-portrait {
      display: none !important;
    }
  }
  
  /* Carte Adversaire */
  .opponent-card {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    border: 3px solid transparent;
  }
  
  .opponent-card:hover:not(.locked) {
    transform: scale(1.05);
    background: rgba(255,255,255,0.2);
    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
  }
  
  .opponent-card.current {
    border-color: #FFD700;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    background: rgba(255, 215, 0, 0.15);
  }
  
  .opponent-card.locked {
    cursor: not-allowed;
  }
  
  /* Carte Boss */
  .boss-card {
    background: linear-gradient(135deg, rgba(255,0,0,0.3), rgba(139,0,0,0.3));
    border: 4px solid #FF4500;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
  }
  
  .boss-card:hover:not(.locked) {
    transform: scale(1.05);
    box-shadow: 0 0 30px rgba(255, 69, 0, 0.8);
  }
  
  .boss-card.current {
    border-color: #FFD700;
    box-shadow: 0 0 30px rgba(255, 215, 0, 0.7);
  }
  
  .boss-card.locked {
    cursor: not-allowed;
  }
  
  .avatar-wrapper {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    position: relative;
    margin-bottom: 6px;
  }
  
  .boss-card .avatar-wrapper {
    aspect-ratio: 1;
    border-radius: 12px;
  }
  
  .avatar-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  
  .lock-icon {
    position: absolute;
    bottom: 5px;
    right: 5px;
    font-size: 1.5rem;
    opacity: 0.9;
    z-index: 10;
  }
  
  .opponent-name {
    font-weight: 700;
    font-size: 0.85rem;
    margin-bottom: 3px;
    color: #fff;
  }
  
  .boss-card .opponent-name {
    font-size: 1.1rem;
    margin-bottom: 5px;
  }
  
  .opponent-level {
    font-size: 0.75rem;
    color: #FFD700;
    font-weight: 600;
  }
  
  .boss-card .opponent-level {
    font-size: 0.9rem;
  }
  
  .boss-label {
    background: #FF4500;
    color: white;
    padding: 3px 10px;
    border-radius: 5px;
    font-size: 0.7rem;
    font-weight: 700;
    margin-top: 5px;
    display: inline-block;
  }
  
  .current-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #FFD700;
    color: #003DA5;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    z-index: 20;
  }
  
  .section-indicator {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 100;
  }
  
  @media (orientation: portrait) {
    .section-indicator { display: none; }
  }
  
  .indicator-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.4);
    transition: all 0.3s ease;
  }
  
  .indicator-dot.active {
    background: #FFD700;
    transform: scale(1.3);
  }
</style>

<a href="{{ route('solo.index') }}" class="header-menu">← Retour Solo</a>

<div class="gallery-header">
  <h1>Galerie d'Adversaires</h1>
  <p>Votre Niveau : <strong>{{ $playerLevel }}</strong> | Sélectionnez un adversaire</p>
</div>

@php
  $sections = [
    ['levels' => range(1, 9), 'boss' => 10, 'boss_name' => 'Le Stratège', 'boss_slug' => 'le-stratege'],
    ['levels' => range(11, 19), 'boss' => 20, 'boss_name' => 'Le Prodige', 'boss_slug' => 'le-prodige'],
    ['levels' => range(21, 29), 'boss' => 30, 'boss_name' => 'Le Maître', 'boss_slug' => 'le-maitre'],
    ['levels' => range(31, 39), 'boss' => 40, 'boss_name' => 'Le Sage', 'boss_slug' => 'le-sage'],
    ['levels' => range(41, 49), 'boss' => 50, 'boss_name' => 'Le Champion', 'boss_slug' => 'le-champion'],
    ['levels' => range(51, 59), 'boss' => 60, 'boss_name' => 'Le Légende', 'boss_slug' => 'le-legende'],
    ['levels' => range(61, 69), 'boss' => 70, 'boss_name' => 'Le Titan', 'boss_slug' => 'le-titan'],
    ['levels' => range(71, 79), 'boss' => 80, 'boss_name' => 'Le Virtuose', 'boss_slug' => 'le-virtuose'],
    ['levels' => range(81, 89), 'boss' => 90, 'boss_name' => 'Le Génie', 'boss_slug' => 'le-genie'],
    ['levels' => range(91, 99), 'boss' => 100, 'boss_name' => 'Cerveau Ultime', 'boss_slug' => 'cerveau-ultime'],
  ];
@endphp

<!-- MODE PORTRAIT : Vertical Scroll -->
<div class="gallery-container">
  @foreach($sections as $section)
    <div class="section-block">
      @php
        $bossLevel = $section['boss'];
        $bossData = $opponents[$bossLevel] ?? ['name' => $section['boss_name']];
        $isBossLocked = $bossLevel > $playerLevel;
        $isBossCurrent = $bossLevel == $playerLevel;
      @endphp
      
      <!-- Boss en haut -->
      <div class="boss-card boss-card-portrait {{ $isBossLocked ? 'locked' : '' }} {{ $isBossCurrent ? 'current' : '' }}"
           onclick="{{ $isBossLocked ? '' : 'selectOpponent(' . $bossLevel . ')' }}">
        @if($isBossCurrent)
          <div class="current-badge">ACTUEL</div>
        @endif
        <div class="avatar-wrapper">
          <img src="{{ asset('images/avatars/bosses/' . $section['boss_slug'] . '.png') }}" 
               alt="{{ $section['boss_name'] }}">
          @if($isBossLocked)
            <div class="lock-icon">🔒</div>
          @endif
        </div>
        <div class="opponent-name">{{ $section['boss_name'] }}</div>
        <div class="opponent-level">Niveau {{ $bossLevel }}</div>
        <div class="boss-label">BOSS</div>
      </div>
      
      <!-- Grille 3x3 des étudiants -->
      <div class="students-grid-portrait">
        @foreach($section['levels'] as $level)
          @php
            $opponent = $opponents[$level] ?? null;
            $isLocked = $level > $playerLevel;
            $isCurrent = $level == $playerLevel;
          @endphp
          
          @if($opponent)
            <div class="opponent-card {{ $isLocked ? 'locked' : '' }} {{ $isCurrent ? 'current' : '' }}"
                 onclick="{{ $isLocked ? '' : 'selectOpponent(' . $level . ')' }}">
              @if($isCurrent)
                <div class="current-badge">ACTUEL</div>
              @endif
              <div class="avatar-wrapper">
                @php
                  // Vérifier si c'est un boss (a slug) ou un étudiant (a avatar)
                  $imagePath = isset($opponent['slug']) 
                    ? asset('images/avatars/bosses/' . $opponent['slug'] . '.png')
                    : asset('images/avatars/students/' . $opponent['avatar'] . '.png');
                @endphp
                <img src="{{ $imagePath }}" alt="{{ $opponent['name'] }}">
                @if($isLocked)
                  <div class="lock-icon">🔒</div>
                @endif
              </div>
              <div class="opponent-name">{{ $opponent['name'] }}</div>
              <div class="opponent-level">Niv. {{ $level }}</div>
            </div>
          @endif
        @endforeach
      </div>
    </div>
  @endforeach
</div>

<!-- MODE PAYSAGE : Horizontal Carousel -->
<div class="carousel-container" id="carousel">
  @foreach($sections as $index => $section)
    <div class="carousel-section" data-section="{{ $index }}">
      <div class="landscape-layout">
        @php
          $levels = $section['levels'];
          $col1 = [$levels[0], $levels[1], $levels[2]]; // 1, 2, 3
          $col2 = [$levels[3], $levels[4], $levels[5]]; // 4, 5, 6
          $col3 = [$levels[6], $levels[7], $levels[8]]; // 7, 8, 9
          $bossLevel = $section['boss'];
          $bossData = $opponents[$bossLevel] ?? ['name' => $section['boss_name']];
          $isBossLocked = $bossLevel > $playerLevel;
          $isBossCurrent = $bossLevel == $playerLevel;
        @endphp
        
        <!-- Colonne 1 (niveaux 1-2-3) -->
        <div class="student-column">
          @foreach($col1 as $level)
            @php
              $opponent = $opponents[$level] ?? null;
              $isLocked = $level > $playerLevel;
              $isCurrent = $level == $playerLevel;
            @endphp
            @if($opponent)
              <div class="opponent-card {{ $isLocked ? 'locked' : '' }} {{ $isCurrent ? 'current' : '' }}"
                   onclick="{{ $isLocked ? '' : 'selectOpponent(' . $level . ')' }}">
                @if($isCurrent)
                  <div class="current-badge">ACTUEL</div>
                @endif
                <div class="avatar-wrapper">
                  @php
                    $cardImagePath = isset($opponent['slug']) 
                      ? asset('images/avatars/bosses/' . $opponent['slug'] . '.png')
                      : asset('images/avatars/students/' . $opponent['avatar'] . '.png');
                  @endphp
                  <img src="{{ $cardImagePath }}" alt="{{ $opponent['name'] }}">
                  @if($isLocked)
                    <div class="lock-icon">🔒</div>
                  @endif
                </div>
                <div class="opponent-name">{{ $opponent['name'] }}</div>
                <div class="opponent-level">{{ $level }}</div>
              </div>
            @endif
          @endforeach
        </div>
        
        <!-- Colonne 2 (niveaux 4-5-6) -->
        <div class="student-column">
          @foreach($col2 as $level)
            @php
              $opponent = $opponents[$level] ?? null;
              $isLocked = $level > $playerLevel;
              $isCurrent = $level == $playerLevel;
            @endphp
            @if($opponent)
              <div class="opponent-card {{ $isLocked ? 'locked' : '' }} {{ $isCurrent ? 'current' : '' }}"
                   onclick="{{ $isLocked ? '' : 'selectOpponent(' . $level . ')' }}">
                @if($isCurrent)
                  <div class="current-badge">ACTUEL</div>
                @endif
                <div class="avatar-wrapper">
                  @php
                    $cardImagePath = isset($opponent['slug']) 
                      ? asset('images/avatars/bosses/' . $opponent['slug'] . '.png')
                      : asset('images/avatars/students/' . $opponent['avatar'] . '.png');
                  @endphp
                  <img src="{{ $cardImagePath }}" alt="{{ $opponent['name'] }}">
                  @if($isLocked)
                    <div class="lock-icon">🔒</div>
                  @endif
                </div>
                <div class="opponent-name">{{ $opponent['name'] }}</div>
                <div class="opponent-level">{{ $level }}</div>
              </div>
            @endif
          @endforeach
        </div>
        
        <!-- Colonne 3 (niveaux 7-8-9) -->
        <div class="student-column">
          @foreach($col3 as $level)
            @php
              $opponent = $opponents[$level] ?? null;
              $isLocked = $level > $playerLevel;
              $isCurrent = $level == $playerLevel;
            @endphp
            @if($opponent)
              <div class="opponent-card {{ $isLocked ? 'locked' : '' }} {{ $isCurrent ? 'current' : '' }}"
                   onclick="{{ $isLocked ? '' : 'selectOpponent(' . $level . ')' }}">
                @if($isCurrent)
                  <div class="current-badge">ACTUEL</div>
                @endif
                <div class="avatar-wrapper">
                  @php
                    $cardImagePath = isset($opponent['slug']) 
                      ? asset('images/avatars/bosses/' . $opponent['slug'] . '.png')
                      : asset('images/avatars/students/' . $opponent['avatar'] . '.png');
                  @endphp
                  <img src="{{ $cardImagePath }}" alt="{{ $opponent['name'] }}">
                  @if($isLocked)
                    <div class="lock-icon">🔒</div>
                  @endif
                </div>
                <div class="opponent-name">{{ $opponent['name'] }}</div>
                <div class="opponent-level">{{ $level }}</div>
              </div>
            @endif
          @endforeach
        </div>
        
        <!-- Boss à droite -->
        <div class="boss-card boss-card-landscape {{ $isBossLocked ? 'locked' : '' }} {{ $isBossCurrent ? 'current' : '' }}"
             onclick="{{ $isBossLocked ? '' : 'selectOpponent(' . $bossLevel . ')' }}"
             style="width: 140px; min-width: 140px;">
          @if($isBossCurrent)
            <div class="current-badge">ACTUEL</div>
          @endif
          <div class="avatar-wrapper">
            <img src="{{ asset('images/avatars/bosses/' . $section['boss_slug'] . '.png') }}" 
                 alt="{{ $section['boss_name'] }}">
            @if($isBossLocked)
              <div class="lock-icon">🔒</div>
            @endif
          </div>
          <div class="opponent-name">{{ $section['boss_name'] }}</div>
          <div class="opponent-level">Niv. {{ $bossLevel }}</div>
          <div class="boss-label">BOSS</div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<!-- Indicateurs de section (paysage uniquement) -->
<div class="section-indicator">
  @for($i = 0; $i < 10; $i++)
    <div class="indicator-dot" data-index="{{ $i }}"></div>
  @endfor
</div>

<script>
  function selectOpponent(level) {
    fetch('{{ route('solo.select-opponent', '') }}/' + level, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        window.location.href = '{{ route('solo.index') }}';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Erreur lors de la sélection de l\'adversaire');
    });
  }
  
  // Gestion indicateurs de section en mode paysage
  const carousel = document.getElementById('carousel');
  if (carousel) {
    carousel.addEventListener('scroll', () => {
      const scrollLeft = carousel.scrollLeft;
      const sectionWidth = carousel.clientWidth;
      const currentSection = Math.round(scrollLeft / sectionWidth);
      
      document.querySelectorAll('.indicator-dot').forEach((dot, index) => {
        if (index === currentSection) {
          dot.classList.add('active');
        } else {
          dot.classList.remove('active');
        }
      });
    });
    
    // Initialiser premier indicateur
    document.querySelector('.indicator-dot[data-index="0"]')?.classList.add('active');
  }
</script>
@endsection
