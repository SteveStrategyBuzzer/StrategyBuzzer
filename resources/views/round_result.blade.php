<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat de la Manche - StrategyBuzzer</title>
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
        
        .result-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            max-width: 700px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .round-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 30px;
        }
        
        .score-display {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin: 40px 0;
        }
        
        .score-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 40px;
            border-radius: 15px;
            font-size: 3rem;
            font-weight: 900;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .score-label {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 10px;
            opacity: 0.9;
        }
        
        .match-status {
            font-size: 1.5rem;
            margin: 30px 0;
            color: #333;
        }
        
        .next-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 50px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .next-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }
        
        .winner-emoji {
            font-size: 4rem;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="result-container">
        @php
            $playerWon = $params['player_rounds_won'] > $params['opponent_rounds_won'];
            $tied = $params['player_rounds_won'] === $params['opponent_rounds_won'];
        @endphp
        
        <div class="round-title">
            ⚔️ Manche {{ $params['round_number'] }} terminée !
        </div>
        
        @if($playerWon && $params['player_rounds_won'] >= 1)
            <div class="winner-emoji">🎉</div>
        @elseif($tied && $params['player_rounds_won'] >= 1)
            <div class="winner-emoji">😌</div>
        @elseif(!$playerWon && $params['opponent_rounds_won'] >= 1)
            <div class="winner-emoji">😤</div>
        @endif
        
        <div class="score-display">
            <div>
                <div class="score-card" style="background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);">
                    {{ $params['player_rounds_won'] }}
                </div>
                <div class="score-label">VOUS</div>
            </div>
            
            <div style="font-size: 3rem; color: #667eea; align-self: center;">-</div>
            
            <div>
                <div class="score-card" style="background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);">
                    {{ $params['opponent_rounds_won'] }}
                </div>
                <div class="score-label">ADVERSAIRE</div>
            </div>
        </div>
        
        <div class="match-status">
            @if($playerWon)
                ✅ Vous menez la partie !
            @elseif($tied)
                ⚡ Égalité parfaite !
            @else
                💪 Votre adversaire mène !
            @endif
        </div>
        
        <!-- Informations de base -->
        <div style="background: rgba(102, 126, 234, 0.1); padding: 15px; border-radius: 15px; margin: 20px 0;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left;">
                <div>
                    <span style="color: #666; font-size: 0.9rem;">🎯 Thème :</span>
                    <strong style="color: #333; display: block; font-size: 1.1rem;">{{ $params['theme'] ?? 'Général' }}</strong>
                </div>
                <div>
                    <span style="color: #666; font-size: 0.9rem;">📊 Niveau :</span>
                    <strong style="color: #333; display: block; font-size: 1.1rem;">{{ $params['niveau_adversaire'] ?? 1 }}</strong>
                </div>
                <div>
                    <span style="color: #666; font-size: 0.9rem;">⚔️ Manches gagnées :</span>
                    <strong style="color: #333; display: block; font-size: 1.1rem;">{{ $params['player_rounds_won'] }}-{{ $params['opponent_rounds_won'] }}</strong>
                </div>
                <div>
                    <span style="color: #666; font-size: 0.9rem;">❤️ Vies :</span>
                    <strong style="color: #333; display: block; font-size: 1.1rem;">{{ $params['vies_restantes'] ?? config('game.life_max', 3) }}</strong>
                </div>
            </div>
        </div>
        
        <!-- Score de la manche -->
        <div style="background: rgba(78, 205, 196, 0.1); padding: 15px; border-radius: 15px; margin: 20px 0;">
            <div style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">⚔️ Pointage manche {{ $params['round_number'] }}</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #667eea;">
                {{ $params['player_score'] ?? 0 }} - {{ $params['opponent_score'] ?? 0 }}
            </div>
        </div>
        
        <!-- Efficacité manche -->
        <div style="background: rgba(243, 156, 18, 0.1); padding: 15px; border-radius: 15px; margin: 20px 0;">
            <div style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">📈 Efficacité manche {{ $params['round_number'] }}</div>
            <div style="font-size: 1.8rem; font-weight: 800; color: #f39c12;">{{ $params['round_efficiency'] ?? 0 }}%</div>
        </div>
        
        <!-- Statistiques globales (toutes manches) -->
        <div style="background: rgba(46, 204, 113, 0.1); padding: 20px; border-radius: 15px; margin: 20px 0;">
            <div style="font-size: 1.2rem; font-weight: 700; color: #333; margin-bottom: 15px;">📊 Statistiques globales</div>
            
            <div style="display: grid; gap: 10px;">
                <div style="background: white; padding: 12px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #666;">✅ Réussi</span>
                    <strong style="color: #2ECC71; font-size: 1.3rem;">{{ $params['total_correct'] ?? 0 }} / {{ $params['total_questions_played'] ?? 0 }}</strong>
                </div>
                
                <div style="background: white; padding: 12px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #666;">❌ Échec</span>
                    <strong style="color: #E74C3C; font-size: 1.3rem;">{{ $params['total_incorrect'] ?? 0 }} / {{ $params['total_questions_played'] ?? 0 }}</strong>
                </div>
                
                <div style="background: white; padding: 12px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #666;">⏭️ Sans réponse</span>
                    <strong style="color: #95a5a6; font-size: 1.3rem;">{{ $params['total_unanswered'] ?? 0 }} / {{ $params['total_questions_played'] ?? 0 }}</strong>
                </div>
            </div>
            
            <div style="margin-top: 15px; padding: 15px; background: white; border-radius: 10px;">
                <div style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">📈 Efficacité globale de la partie</div>
                <div style="font-size: 2rem; font-weight: 800; color: #667eea;">{{ $params['global_efficiency'] ?? 0 }}%</div>
            </div>
        </div>
        
        <div style="color: #666; margin: 20px 0;">
            Prochaine manche : <strong>{{ $params['nb_questions'] }} questions</strong>
        </div>
        
        <form action="{{ route('solo.game') }}" method="GET">
            <button type="submit" class="next-button">
                🚀 Manche {{ $params['next_round'] }}
            </button>
        </form>
    </div>
</body>
</html>
