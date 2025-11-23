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
        
        <!-- Stats par manche (afficher toutes les manches de la partie) -->
        @if(!empty($params['round_summaries']))
        <div style="background: rgba(231, 76, 60, 0.1); padding: 20px; border-radius: 15px; margin: 25px 0;">
            <div style="font-size: 1.3rem; font-weight: 700; color: #333; margin-bottom: 15px;">📊 Statistiques par Manche</div>
            
            @foreach($params['round_summaries'] as $roundNum => $roundStats)
            <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #e74c3c;">
                <div style="font-weight: 700; color: #e74c3c; margin-bottom: 10px;">🏆 Manche {{ $roundNum }}</div>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 0.9rem;">
                    <div>
                        <span style="color: #666;">✅ Réussi:</span>
                        <strong style="color: #2ECC71;">{{ $roundStats['correct'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                    </div>
                    <div>
                        <span style="color: #666;">❌ Échec:</span>
                        <strong style="color: #E74C3C;">{{ $roundStats['wrong'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                    </div>
                    <div>
                        <span style="color: #666;">⏭️ Sans réponse:</span>
                        <strong style="color: #95a5a6;">{{ $roundStats['unanswered'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                    </div>
                    <div>
                        <span style="color: #666;">📈 Efficacité:</span>
                        <strong style="color: #e74c3c;">{{ number_format($roundStats['efficiency'] ?? 0, 1) }}%</strong>
                    </div>
                </div>
                
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; font-size: 0.85rem; color: #666;">
                    @php
                        $basePoints = $roundStats['points_earned'] ?? 0;
                        $bonusPoints = $roundStats['bonus_points'] ?? 0;
                        $pointsPossible = $roundStats['points_possible'] ?? 20;
                    @endphp
                    Points Gagnés: <strong style="color: #333;">{{ $basePoints }}</strong>@if($bonusPoints != 0)<strong style="color: {{ $bonusPoints > 0 ? '#2ECC71' : '#E74C3C' }}"> {{ $bonusPoints > 0 ? '+' : '' }}{{ $bonusPoints }}</strong>@endif / {{ $pointsPossible }}
                </div>
            </div>
            @endforeach
        </div>
        @endif
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Réussi</div>
                <div class="stat-value">{{ $params['total_correct'] }}</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Efficacité</div>
                <div class="stat-value">{{ number_format($params['party_efficiency'] ?? $params['global_efficiency'] ?? 0, 1) }}%</div>
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
        
        <!-- Skills utilisés -->
        @php
            $avatar = session('avatar', 'Aucun');
            $bonusResult = session('bonus_question_result', null);
            $cancelErrorUsed = in_array('cancel_error', session('used_skills', []));
        @endphp
        
        @if($avatar === 'Magicienne')
        <div style="background: rgba(155, 89, 182, 0.1); padding: 20px; border-radius: 15px; margin: 20px 0;">
            <div style="font-size: 1.2rem; font-weight: 700; color: #333; margin-bottom: 15px;">✨ Skills utilisés</div>
            
            @if($bonusResult)
                <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #666;">💫 Question Bonus</span>
                        @if($bonusResult['answered'])
                            <strong style="color: {{ $bonusResult['points'] > 0 ? '#2ECC71' : ($bonusResult['points'] < 0 ? '#E74C3C' : '#95a5a6') }}; font-size: 1.2rem;">
                                {{ $bonusResult['points'] > 0 ? '+' : '' }}{{ $bonusResult['points'] }} points
                            </strong>
                        @else
                            <strong style="color: #95a5a6; font-size: 1.2rem;">0 point</strong>
                        @endif
                    </div>
                </div>
            @endif
            
            @if($cancelErrorUsed)
                <div style="background: white; padding: 15px; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #666;">✨ Annule erreur</span>
                        <strong style="color: #2ECC71; font-size: 1.2rem;">Utilisé</strong>
                    </div>
                </div>
            @endif
            
            @if(!$bonusResult && !$cancelErrorUsed)
                <div style="background: white; padding: 15px; border-radius: 10px; text-align: center; color: #95a5a6;">
                    Aucun
                </div>
            @endif
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
            
            <a href="{{ route('solo.index') }}" class="btn btn-menu">← Solo</a>
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
        return enabled === null || enabled === 'true'; // Activé par défaut
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
