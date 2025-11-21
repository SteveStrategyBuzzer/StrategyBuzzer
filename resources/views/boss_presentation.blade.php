@extends('layouts.app')

@section('content')
<style>
  body { 
    background: linear-gradient(135deg, #003DA5 0%, #001A52 100%);
    color: #fff;
    overflow: hidden;
    margin: 0;
    padding: 0;
  }

  .boss-presentation-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 20px;
  }

  .vs-header {
    font-size: 3rem;
    font-weight: 900;
    text-align: center;
    margin-bottom: 30px;
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #FF6347 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 30px rgba(255, 215, 0, 0.5);
    animation: pulse 2s ease-in-out infinite;
  }

  .battle-layout {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 40px;
    width: 100%;
    max-width: 1200px;
    align-items: center;
  }

  .player-column, .boss-column {
    background: rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
  }

  .column-header {
    text-align: center;
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 20px;
    color: #FFD700;
  }

  .avatar-display {
    width: 150px;
    height: 150px;
    margin: 0 auto 20px auto;
    border-radius: 15px;
    overflow: hidden;
    border: 4px solid #FFD700;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
  }

  .avatar-display img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .skills-list {
    background: rgba(0,0,0,0.2);
    border-radius: 12px;
    padding: 15px;
    margin-top: 15px;
  }

  .skill-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    margin-bottom: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    font-size: 0.9rem;
  }

  .skill-icon {
    font-size: 1.3rem;
  }

  .skill-name {
    font-weight: 600;
    color: #FFD700;
  }

  .skill-description {
    font-size: 0.85rem;
    opacity: 0.9;
  }

  .vs-divider {
    font-size: 4rem;
    font-weight: 900;
    color: #FF4500;
    text-shadow: 0 0 30px rgba(255, 69, 0, 0.8);
    animation: pulse 1.5s ease-in-out infinite;
  }

  .radar-container {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    padding: 20px;
    background: rgba(0,0,0,0.2);
    border-radius: 15px;
  }

  .boss-level {
    text-align: center;
    font-size: 1.2rem;
    font-weight: 600;
    color: #FF4500;
    margin-bottom: 15px;
  }

  .continue-button {
    position: fixed;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
    color: #003DA5;
    padding: 15px 40px;
    border-radius: 12px;
    border: none;
    font-size: 1.3rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4);
    transition: all 0.3s ease;
  }

  .continue-button:hover {
    transform: translateX(-50%) translateY(-3px);
    box-shadow: 0 15px 40px rgba(255, 215, 0, 0.6);
  }

  @keyframes pulse {
    0%, 100% {
      transform: scale(1);
    }
    50% {
      transform: scale(1.05);
    }
  }

  /* Responsive pour mobile */
  @media (max-width: 768px) {
    .battle-layout {
      grid-template-columns: 1fr;
      gap: 20px;
    }
    
    .vs-divider {
      font-size: 2.5rem;
    }
    
    .vs-header {
      font-size: 2rem;
      margin-bottom: 20px;
    }
    
    .column-header {
      font-size: 1.3rem;
    }
    
    .avatar-display {
      width: 120px;
      height: 120px;
    }

    .radar-container {
      max-width: 300px;
    }
  }
</style>

<div class="boss-presentation-container">
  <div class="vs-header">⚔️ BOSS BATTLE ⚔️</div>
  
  <div class="battle-layout">
    <!-- Colonne Joueur -->
    <div class="player-column">
      <div class="column-header">Vous</div>
      
      @if(session('avatar_strategique'))
        <div class="avatar-display">
          @php
            $avatarName = session('avatar_strategique');
            $avatarSlug = strtolower(str_replace(' ', '-', $avatarName));
          @endphp
          <img src="{{ asset('images/avatars/' . $avatarSlug . '.png') }}" 
               alt="{{ $avatarName }}"
               onerror="this.src='{{ asset('images/avatars/default.png') }}'">
        </div>
        
        <div style="text-align: center; font-weight: 700; font-size: 1.2rem; color: #FFD700;">
          {{ session('avatar_strategique') }}
        </div>
        
        <div class="skills-list">
          <div style="font-weight: 700; margin-bottom: 10px; color: #FFD700;">✨ Skills Actifs</div>
          
          @php
            $avatarSkills = [
              'Magicienne' => [
                ['icon' => '✨', 'name' => 'Magie', 'desc' => 'Question bonus par partie'],
                ['icon' => '🌟', 'name' => 'Étoile', 'desc' => 'Annule une mauvaise réponse'],
              ],
              'Boxeur' => [
                ['icon' => '🥊', 'name' => 'K.O.', 'desc' => 'Double les points d\'une bonne réponse'],
              ],
              'Footballeur' => [
                ['icon' => '⚽', 'name' => 'Tir au but', 'desc' => 'Rejouer une question manquée'],
              ],
            ];
            
            $currentSkills = $avatarSkills[session('avatar_strategique')] ?? [];
          @endphp
          
          @if(count($currentSkills) > 0)
            @foreach($currentSkills as $skill)
              <div class="skill-item">
                <span class="skill-icon">{{ $skill['icon'] }}</span>
                <div>
                  <div class="skill-name">{{ $skill['name'] }}</div>
                  <div class="skill-description">{{ $skill['desc'] }}</div>
                </div>
              </div>
            @endforeach
          @else
            <div style="opacity: 0.7; text-align: center;">Aucun skill disponible</div>
          @endif
        </div>
      @else
        <div style="text-align: center; opacity: 0.7; padding: 40px 20px;">
          Aucun avatar sélectionné
        </div>
      @endif
    </div>
    
    <!-- Divider VS -->
    <div class="vs-divider">VS</div>
    
    <!-- Colonne Boss -->
    <div class="boss-column">
      <div class="column-header">{{ $bossData['name'] }}</div>
      
      <div class="avatar-display" style="border-color: #FF4500;">
        <img src="{{ asset('images/avatars/bosses/' . $bossData['slug'] . '.png') }}" 
             alt="{{ $bossData['name'] }}">
      </div>
      
      <div class="boss-level">🔥 BOSS NIVEAU {{ $niveau }} 🔥</div>
      
      <div class="radar-container">
        <canvas id="radarChart"></canvas>
      </div>
    </div>
  </div>
  
  <button class="continue-button" onclick="continueToGame()">
    🎮 Commencer le Combat
  </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const radarData = {
  labels: {!! json_encode(array_keys($bossData['radar'])) !!},
  datasets: [{
    label: '{{ $bossData['name'] }}',
    data: {!! json_encode(array_values($bossData['radar'])) !!},
    fill: true,
    backgroundColor: 'rgba(255, 69, 0, 0.2)',
    borderColor: '#FF4500',
    borderWidth: 3,
    pointBackgroundColor: '#FF4500',
    pointBorderColor: '#fff',
    pointBorderWidth: 2,
    pointRadius: 5,
    pointHoverRadius: 7
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
            size: 11,
            weight: 'bold'
          }
        },
        grid: {
          color: 'rgba(255, 255, 255, 0.2)',
          lineWidth: 2
        },
        pointLabels: {
          color: '#FFD700',
          font: {
            size: 13,
            weight: 'bold'
          }
        },
        angleLines: {
          color: 'rgba(255, 255, 255, 0.2)',
          lineWidth: 1
        }
      }
    },
    plugins: {
      legend: {
        display: true,
        position: 'bottom',
        labels: {
          color: '#FFD700',
          font: {
            size: 14,
            weight: 'bold'
          },
          padding: 15
        }
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        titleColor: '#FFD700',
        bodyColor: '#fff',
        borderColor: '#FF4500',
        borderWidth: 2,
        padding: 12,
        displayColors: true,
        callbacks: {
          label: function(context) {
            return context.dataset.label + ': ' + context.parsed.r + '/100';
          }
        }
      }
    }
  }
};

const radarChart = new Chart(
  document.getElementById('radarChart'),
  config
);

function continueToGame() {
  window.location.href = "{{ route('solo.preparation') }}";
}

// Auto-continuer après 10 secondes
setTimeout(() => {
  continueToGame();
}, 10000);
</script>
@endsection
