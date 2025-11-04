<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Victoire ! - StrategyBuzzer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .victory-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 25px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
        }
        
        .trophy-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .victory-title {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }
        
        .level-up {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 30px;
            font-weight: 700;
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
        
        .challenge-section {
            background: #f7f9fc;
            padding: 30px;
            border-radius: 20px;
            margin: 30px 0;
        }
        
        .challenge-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .opponent-name {
            font-size: 2.5rem;
            color: #667eea;
            font-weight: 900;
            margin: 15px 0;
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
        }
        
        .btn-yes {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .btn-yes:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(17, 153, 142, 0.4);
        }
        
        .btn-no {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-no:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }
        
        @media (max-width: 600px) {
            .victory-title {
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
    <div class="victory-container">
        <div class="trophy-icon">🏆</div>
        
        <h1 class="victory-title">VICTOIRE !</h1>
        
        <p class="level-up">
            Niveau {{ $params['current_level'] }} complété !<br>
            <strong>Niveau {{ $params['new_level'] }} débloqué 🎉</strong>
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
        
        @if($params['new_level'] <= 100)
        <div class="challenge-section">
            <h2 class="challenge-title">Voulez-vous challenger</h2>
            <div class="opponent-name">{{ $params['next_opponent_name'] }}</div>
            <p style="color: #666; font-size: 1.1rem;">Niveau {{ $params['new_level'] }}</p>
        </div>
        
        <div class="action-buttons">
            <form action="{{ route('solo.start') }}" method="POST" style="display: inline;">
                @csrf
                <input type="hidden" name="nb_questions" value="{{ session('nb_questions', 30) }}">
                <input type="hidden" name="theme" value="{{ session('theme', 'general') }}">
                <input type="hidden" name="niveau_joueur" value="{{ $params['new_level'] }}">
                <button type="submit" class="btn btn-yes">OUI ⚔️</button>
            </form>
            
            <a href="{{ route('solo.index') }}" class="btn btn-no">NON</a>
        </div>
        @else
        <div class="challenge-section">
            <h2 class="challenge-title">🎊 FÉLICITATIONS ! 🎊</h2>
            <p style="color: #333; font-size: 1.2rem; margin: 20px 0;">
                Vous avez atteint le niveau maximum !<br>
                Vous êtes un maître absolu de StrategyBuzzer !
            </p>
        </div>
        
        <div class="action-buttons">
            <a href="{{ route('solo.index') }}" class="btn btn-yes">← Retour Solo</a>
        </div>
        @endif
    </div>
    
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
