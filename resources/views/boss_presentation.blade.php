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
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30px;
    width: 100%;
    max-width: 600px;
  }

  .boss-section {
    background: rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
    width: 100%;
    text-align: center;
  }

  .boss-header {
    text-align: center;
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 20px;
    color: #FFD700;
  }

  .avatar-display {
    width: 180px;
    height: 180px;
    margin: 0 auto 20px auto;
    border-radius: 15px;
    overflow: hidden;
    border: 4px solid #FF4500;
    box-shadow: 0 0 20px rgba(255, 69, 0, 0.5);
  }

  .avatar-display img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
    <!-- Section Boss -->
    <div class="boss-section">
      <div class="boss-header">{{ $bossData['name'] }}</div>
      
      <div class="avatar-display">
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
