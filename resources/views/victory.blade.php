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
        
        <!-- Stats par manche (afficher toutes les manches de la partie) -->
        @if(!empty($params['round_summaries']))
        <div style="background: rgba(46, 204, 113, 0.1); padding: 20px; border-radius: 15px; margin: 25px 0;">
            <div style="font-size: 1.3rem; font-weight: 700; color: #333; margin-bottom: 15px;">📊 Statistiques par Manche</div>
            
            @foreach($params['round_summaries'] as $roundNum => $roundStats)
            <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #11998e;">
                <div style="font-weight: 700; color: #11998e; margin-bottom: 10px;">🏆 Manche {{ $roundNum }}</div>
                
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
                        <strong style="color: #11998e;">{{ number_format($roundStats['efficiency'] ?? 0, 1) }}%</strong>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        @php
                            $basePoints = $roundStats['points_earned'] ?? 0;
                            $bonusPoints = $roundStats['bonus_points'] ?? 0;
                        @endphp
                        <span style="color: #666;">🎯 Points Gagnés:</span>
                        <strong style="color: #333;">{{ $basePoints }}</strong>@if($bonusPoints != 0)<strong style="color: {{ $bonusPoints > 0 ? '#2ECC71' : '#E74C3C' }}"> {{ $bonusPoints > 0 ? '+' : '' }}{{ $bonusPoints }}</strong>@endif / 20
                    </div>
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
                <div class="stat-label">Efficacité Max de la Partie</div>
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
        
        @if($params['new_level'] <= 100)
        @php
            $unlocks = [];
            if ($params['new_level'] == 10) {
                $unlocks[] = 'Mode Duo';
            }
        @endphp
        
        @if(count($unlocks) > 0)
        <div class="challenge-section" style="background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%); padding: 20px; border-radius: 15px; margin: 20px 0;">
            <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px;">🎉 Félicitation !</h2>
            <p style="color: white; font-size: 1.2rem;">
                @foreach($unlocks as $index => $unlock)
                    @if($index > 0), @endif
                    Vous avez débloqué le <strong>{{ $unlock }}</strong>
                @endforeach
            </p>
        </div>
        @endif
        
        <div class="challenge-section">
            <h2 class="challenge-title">Prochain adversaire</h2>
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
