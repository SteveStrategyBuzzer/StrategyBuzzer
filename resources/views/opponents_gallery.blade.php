@extends('layouts.app')

@section('content')
<style>
  body { background: #003DA5; color: #fff; overflow-x: hidden; }
  .container-gallery { max-width: 1200px; margin: 20px auto; padding: 0 16px; }
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
  
  .gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 20px;
    margin-top: 30px;
  }
  
  .opponent-card {
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    border: 3px solid transparent;
  }
  
  .opponent-card:hover:not(.locked) {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.2);
    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
  }
  
  .opponent-card.current {
    border-color: #FFD700;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    background: rgba(255, 215, 0, 0.15);
  }
  
  .opponent-card.locked {
    opacity: 0.6;
    cursor: not-allowed;
  }
  
  .opponent-card.boss {
    background: linear-gradient(135deg, rgba(255,0,0,0.2), rgba(139,0,0,0.2));
    border: 3px solid #FF4500;
  }
  
  .opponent-card.boss:hover:not(.locked) {
    box-shadow: 0 0 30px rgba(255, 69, 0, 0.6);
  }
  
  .avatar-wrapper {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    position: relative;
    margin-bottom: 8px;
  }
  
  .avatar-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  
  .lock-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    opacity: 0.9;
  }
  
  .opponent-name {
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 4px;
    color: #fff;
  }
  
  .opponent-level {
    font-size: 0.85rem;
    color: #FFD700;
    font-weight: 600;
  }
  
  .boss-label {
    background: #FF4500;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-top: 4px;
    display: inline-block;
  }
  
  .section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 40px 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(255,255,255,0.3);
  }
  
  .current-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background: #FFD700;
    color: #003DA5;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  }
</style>

<a href="{{ route('solo.index') }}" class="header-menu">← Retour Solo</a>

<div class="container-gallery">
  <h1 class="display-4 text-center">Galerie d'Adversaires</h1>
  <p class="lead text-center" style="margin-top:6px">
    Votre Niveau : <strong>{{ $playerLevel }}</strong> | 
    Cliquez sur un adversaire pour le sélectionner
  </p>

  @php
    $ageGroups = [
      8 => range(1, 9),
      10 => range(11, 19),
      12 => range(21, 29),
      14 => range(31, 39),
      16 => range(41, 49),
      18 => range(51, 59),
      20 => range(61, 69),
      22 => range(71, 79),
      24 => range(81, 89),
      26 => range(91, 99),
    ];
  @endphp

  @foreach($ageGroups as $age => $levels)
    <div class="section-title">Étudiants {{ $age }} ans (Niveaux {{ $levels[0] }}-{{ end($levels) }})</div>
    <div class="gallery-grid">
      @foreach($levels as $level)
        @php
          $opponent = $opponents[$level] ?? null;
          $isLocked = $level > $playerLevel;
          $isCurrent = $level == $playerLevel;
          $isBoss = in_array($level, [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]);
        @endphp
        
        @if($opponent)
          <div class="opponent-card {{ $isLocked ? 'locked' : '' }} {{ $isCurrent ? 'current' : '' }} {{ $isBoss ? 'boss' : '' }}"
               onclick="{{ $isLocked ? '' : 'selectOpponent(' . $level . ')' }}">
            
            @if($isCurrent)
              <div class="current-badge">ACTUEL</div>
            @endif
            
            <div class="avatar-wrapper">
              <img src="{{ asset('images/avatars/students/' . $opponent['avatar'] . '.png') }}" 
                   alt="{{ $opponent['name'] }}">
              @if($isLocked)
                <div class="lock-icon">🔒</div>
              @endif
            </div>
            
            <div class="opponent-name">{{ $opponent['name'] }}</div>
            <div class="opponent-level">Niveau {{ $level }}</div>
            @if($isBoss)
              <div class="boss-label">BOSS</div>
            @endif
          </div>
        @endif
      @endforeach
    </div>
  @endforeach

  <div class="section-title">Boss - Niveau 100</div>
  <div style="text-align:center; padding:30px; background:rgba(255,69,0,0.1); border-radius:12px; border:2px solid #FF4500;">
    <div style="font-size:3rem; margin-bottom:15px;">🏆</div>
    <div style="font-size:1.5rem; font-weight:700; color:#FFD700; margin-bottom:10px;">Cerveau Ultime</div>
    <div style="font-size:1rem; color:#fff; opacity:0.9;">
      Le Boss Final vous attend au niveau 100 !
      @if($playerLevel >= 100)
        <br><span style="color:#FFD700;">✅ Débloqué</span>
      @else
        <br><span style="opacity:0.7;">🔒 Atteignez le niveau 100 pour le défier</span>
      @endif
    </div>
  </div>
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
</script>
@endsection
