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

  .preparation-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #003DA5 0%, #001A52 100%);
  }

  .announcement-text {
    font-size: 2.5rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 60px;
    text-shadow: 0 4px 20px rgba(0,0,0,0.5);
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #FF6347 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: pulse 1.5s ease-in-out infinite;
  }

  .countdown-container {
    position: relative;
    width: 400px;
    height: 400px;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .countdown-display {
    font-size: 20rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 0 10px 40px rgba(255,215,0,0.7),
                 0 0 60px rgba(255,165,0,0.5),
                 0 0 100px rgba(255,99,71,0.3);
    animation: countdownPulse 1s ease-in-out infinite;
  }

  @keyframes countdownPulse {
    0%, 100% {
      transform: scale(1);
      text-shadow: 0 10px 40px rgba(255,215,0,0.7),
                   0 0 60px rgba(255,165,0,0.5),
                   0 0 100px rgba(255,99,71,0.3);
    }
    50% {
      transform: scale(1.05);
      text-shadow: 0 10px 50px rgba(255,215,0,0.9),
                   0 0 80px rgba(255,165,0,0.7),
                   0 0 120px rgba(255,99,71,0.5);
    }
  }

  @keyframes pulse {
    0%, 100% {
      transform: scale(1);
    }
    50% {
      transform: scale(1.05);
    }
  }

  /* Responsive pour mobile portrait */
  @media (max-width: 767px) and (orientation: portrait) {
    .announcement-text {
      font-size: 1.8rem;
      margin-bottom: 40px;
      padding: 0 20px;
    }
    
    .countdown-container {
      width: 300px;
      height: 300px;
    }
    
    .countdown-display {
      font-size: 12rem;
    }
  }

  /* Responsive pour mobile landscape */
  @media (max-width: 767px) and (orientation: landscape) {
    .announcement-text {
      font-size: 1.5rem;
      margin-bottom: 30px;
      padding: 0 20px;
    }
    
    .countdown-container {
      width: 250px;
      height: 250px;
    }
    
    .countdown-display {
      font-size: 10rem;
    }
  }

  /* Responsive pour tablette portrait */
  @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
    .announcement-text {
      font-size: 2rem;
      margin-bottom: 50px;
    }
    
    .countdown-container {
      width: 350px;
      height: 350px;
    }
    
    .countdown-display {
      font-size: 15rem;
    }
  }
</style>

<div class="preparation-container">
  <div class="announcement-text">🎙️ Ladies and Gentlemen, Are You Ready?</div>
  
  <div class="countdown-container">
    <div class="countdown-display" id="countdown">--</div>
  </div>
</div>

<!-- Audio de l'annonce -->
<audio id="readyAudio" preload="auto">
  <source src="{{ asset('sounds/ready_announcement.mp3') }}" type="audio/mpeg">
</audio>

<script>
// Nettoyer le localStorage de la musique de gameplay au début d'une nouvelle partie
localStorage.removeItem('gameplayMusicTime');

const audio = document.getElementById('readyAudio');
const countdownDisplay = document.getElementById('countdown');
let audioDuration = 0;
let updateInterval = null;
let hasStarted = false;
let hasRedirected = false;
let fallbackTimeout = null;

// Définir le volume au maximum pour être entendu sur mobile
audio.volume = 1.0;

// Attendre que l'audio soit chargé
audio.addEventListener('loadedmetadata', function() {
    audioDuration = audio.duration;
    
    // Afficher le compte à rebours initial
    countdownDisplay.textContent = Math.ceil(audioDuration);
    
    // Jouer l'audio immédiatement sans délai pour mobile
    audio.play().then(() => {
        hasStarted = true;
        startCountdownSync();
        if (fallbackTimeout) clearTimeout(fallbackTimeout); // Annuler le fallback
        
        // NOUVEAU : Lancer la génération proactive des questions en arrière-plan
        fetch("{{ route('solo.generate-batch') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                round: {{ session('current_round', 1) }}
            })
        }).then(response => response.json())
          .then(data => {
              console.log('[PROACTIVE] Batch generation started:', data);
          })
          .catch(err => {
              console.error('[PROACTIVE] Batch generation failed:', err);
          });
    }).catch(e => {
        console.log('Audio play failed, trying on user interaction:', e);
        // Sur mobile, jouer au premier clic
        document.addEventListener('click', function playOnClick() {
            audio.play().then(() => {
                hasStarted = true;
                startCountdownSync();
                if (fallbackTimeout) clearTimeout(fallbackTimeout); // Annuler le fallback
                
                // NOUVEAU : Lancer la génération proactive (même code que ci-dessus)
                fetch("{{ route('solo.generate-batch') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        round: {{ session('current_round', 1) }}
                    })
                }).then(response => response.json())
                  .then(data => {
                      console.log('[PROACTIVE] Batch generation started (after click):', data);
                  })
                  .catch(err => {
                      console.error('[PROACTIVE] Batch generation failed (after click):', err);
                  });
            }).catch(err => console.log('Audio still failed:', err));
            document.removeEventListener('click', playOnClick);
        }, { once: true });
        
        // Fallback: rediriger après 6 secondes si l'audio ne joue toujours pas
        fallbackTimeout = setTimeout(() => {
            if (!hasStarted && !hasRedirected) {
                hasRedirected = true;
                window.location.href = "{{ route('solo.game') }}";
            }
        }, 6000);
    });
});

// Rediriger immédiatement quand l'audio se termine
audio.addEventListener('ended', function() {
    if (!hasRedirected) {
        hasRedirected = true;
        clearInterval(updateInterval);
        window.location.href = "{{ route('solo.game') }}";
    }
});

// Synchroniser le compte à rebours avec audio.currentTime
function startCountdownSync() {
    updateInterval = setInterval(() => {
        const remaining = audioDuration - audio.currentTime;
        
        if (remaining > 0) {
            // Arrondir à l'unité supérieure pour un compte à rebours propre
            countdownDisplay.textContent = Math.ceil(remaining);
        } else {
            countdownDisplay.textContent = '0';
        }
    }, 100); // Mise à jour toutes les 100ms pour une synchronisation fluide
}

// Fallback sécurisé : si l'audio ne démarre jamais, rediriger après 10 secondes
setTimeout(() => {
    if (!hasStarted && !hasRedirected) {
        console.log('Fallback: audio never started, redirecting');
        hasRedirected = true;
        window.location.href = "{{ route('solo.game') }}";
    }
}, 10000);
</script>
@endsection
