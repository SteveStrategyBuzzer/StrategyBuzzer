<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Défaite - StrategyBuzzer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .defeat-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 25px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
        }
        
        .defeat-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        
        .defeat-title {
            font-size: 3rem;
            font-weight: 900;
            color: #764ba2;
            margin-bottom: 15px;
        }
        
        .defeat-message {
            font-size: 1.5rem;
            color: #666;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 900;
        }
        
        .retry-section {
            background: #f7f9fc;
            padding: 30px;
            border-radius: 20px;
            margin: 30px 0;
        }
        
        .retry-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 18px 45px;
            font-size: 1.2rem;
            font-weight: 700;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: white;
        }
        
        .btn-retry {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .btn-retry:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }
        
        .btn-menu {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-menu:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }
        
        @media (max-width: 600px) {
            .defeat-title {
                font-size: 2.2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="defeat-container">
        <div class="defeat-icon">😔</div>
        
        <h1 class="defeat-title">Défaite</h1>
        
        <p class="defeat-message">
            L'adversaire a remporté la partie<br>
            Niveau {{ $params['current_level'] }}
        </p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Réussi</div>
                <div class="stat-value">{{ $params['total_correct'] }}</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Efficacité</div>
                <div class="stat-value">{{ $params['global_efficiency'] }}%</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Échec</div>
                <div class="stat-value">{{ $params['total_incorrect'] }}</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Sans réponse</div>
                <div class="stat-value">{{ $params['total_unanswered'] }}</div>
            </div>
        </div>
        
        @if(isset($params['stats_metrics']) && $params['stats_metrics'])
        <div class="stats-grid" style="margin-top: 30px; border-top: 2px dashed #e0e0e0; padding-top: 30px;">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-label">🎯 Efficacité Brute</div>
                <div class="stat-value">{{ number_format($params['stats_metrics']['efficacite_brute'], 1) }}%</div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.8;">Points / Questions</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="stat-label">🙋 Participation</div>
                <div class="stat-value">{{ number_format($params['stats_metrics']['taux_participation'], 1) }}%</div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.8;">Buzzes / Total</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="stat-label">✅ Précision</div>
                <div class="stat-value">{{ number_format($params['stats_metrics']['taux_precision'], 1) }}%</div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.8;">Correct / Buzzes</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="stat-label">⭐ Performance</div>
                <div class="stat-value">{{ number_format($params['stats_metrics']['ratio_performance'], 1) }}%</div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.8;">Points / Max Possible</div>
            </div>
        </div>
        @endif
        
        <!-- Affichage des vies restantes -->
        @if(!$params['is_guest'])
        <div class="lives-section" style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 15px; margin: 20px 0;">
            <h3 style="color: #856404; font-size: 1.3rem; margin-bottom: 10px;">
                ❤️ Vies restantes : {{ $params['remaining_lives'] ?? 0 }} / {{ config('game.life_max', 3) }}
            </h3>
            @if(!$params['has_lives'])
                <p style="color: #721c24; background: #f8d7da; padding: 15px; border-radius: 10px; margin-top: 10px;">
                    ⏰ Vous n'avez plus de vies !<br>
                    <strong>Prochaine vie dans : <span id="countdown-timer">{{ $params['cooldown_time'] }}</span></strong>
                </p>
            @endif
        </div>
        @endif
        
        <div class="retry-section">
            @if($params['is_guest'] || $params['has_lives'])
                <h2 class="retry-title">Ne baissez pas les bras !</h2>
                <p style="color: #666; font-size: 1.1rem;">
                    Réessayez et montrez votre vraie valeur !
                </p>
            @else
                <h2 class="retry-title">Plus de vies disponibles</h2>
                <p style="color: #666; font-size: 1.1rem;">
                    Revenez dans <span id="countdown-timer-2">{{ $params['cooldown_time'] }}</span> pour continuer à jouer !
                </p>
            @endif
        </div>
        
        <div class="action-buttons">
            @if($params['is_guest'] || $params['has_lives'])
                <form action="{{ route('solo.start') }}" method="POST" style="display: inline;">
                    @csrf
                    <input type="hidden" name="nb_questions" value="{{ session('nb_questions', 30) }}">
                    <input type="hidden" name="theme" value="{{ session('theme', 'general') }}">
                    <input type="hidden" name="niveau_joueur" value="{{ $params['current_level'] }}">
                    <button type="submit" class="btn btn-retry">🔄 Réessayer</button>
                </form>
            @else
                <button type="button" class="btn btn-retry" disabled style="opacity: 0.5; cursor: not-allowed;">
                    🔄 Réessayer (Plus de vies)
                </button>
            @endif
            
            <a href="{{ route('menu') }}" class="btn btn-menu">Menu</a>
        </div>
    </div>
    
    @if(!$params['is_guest'] && !$params['has_lives'] && $params['next_life_regen'])
    <script>
    (function() {
        const timer1 = document.getElementById('countdown-timer');
        const timer2 = document.getElementById('countdown-timer-2');
        const targetIso = '{{ $params['next_life_regen'] }}';
        
        if (!targetIso || (!timer1 && !timer2)) return;
        
        const fmt = (ms) => {
            if (ms < 0) ms = 0;
            const totalSec = Math.floor(ms / 1000);
            const h = Math.floor(totalSec / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            return `${String(h).padStart(2,'0')}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
        };
        
        const tick = () => {
            const target = new Date(targetIso).getTime();
            const now = Date.now();
            const diffMs = target - now;
            
            const timeText = fmt(diffMs);
            if (timer1) timer1.textContent = timeText;
            if (timer2) timer2.textContent = timeText;
            
            // Si le temps est écoulé, recharger la page pour régénérer une vie
            if (diffMs <= 0) {
                window.location.reload();
            }
        };
        
        tick();
        setInterval(tick, 1000);
    })();
    </script>
    @endif
    
    <!-- Musique d'ambiance du gameplay (fin de partie) -->
    <audio id="gameplayAmbient" preload="auto" loop>
        <source src="{{ asset('sounds/gameplay_ambient.mp3') }}" type="audio/mpeg">
    </audio>
    
    <script>
    // Vérifier si la musique de gameplay est activée
    function isGameplayMusicEnabled() {
        const enabled = localStorage.getItem('gameplay_music_enabled');
        return enabled === 'true';
    }
    
    // Continuer la musique d'ambiance du gameplay SEULEMENT si activée
    const gameplayAmbient = document.getElementById('gameplayAmbient');
    gameplayAmbient.volume = 0.5; // -6 dB ≈ 50% de volume
    
    if (isGameplayMusicEnabled()) {
        const savedTime = parseFloat(localStorage.getItem('gameplayMusicTime') || '0');
        gameplayAmbient.addEventListener('loadedmetadata', function() {
            if (savedTime > 0 && savedTime < gameplayAmbient.duration) {
                gameplayAmbient.currentTime = savedTime;
            }
            
            gameplayAmbient.play().catch(e => {
                console.log('Gameplay music autoplay blocked:', e);
                document.addEventListener('click', function playGameplayMusic() {
                    gameplayAmbient.play().catch(err => console.log('Audio play failed:', err));
                    document.removeEventListener('click', playGameplayMusic);
                }, { once: true });
            });
        });
    }
    
    // Nettoyer le localStorage après quelques secondes (fin de partie)
    setTimeout(() => {
        localStorage.removeItem('gameplayMusicTime');
    }, 5000);
    </script>
</body>
</html>
