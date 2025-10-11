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
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
        
        <div style="color: #666; margin: 20px 0;">
            Prochaine manche : <strong>{{ $params['nb_questions'] }} questions</strong>
        </div>
        
        <!-- Statistiques de la manche -->
        <div style="display: flex; justify-content: space-around; margin: 30px 0; padding: 20px; background: rgba(102, 126, 234, 0.1); border-radius: 15px;">
            <div style="text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">⚔️ Score</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;">{{ $params['player_score'] ?? 0 }}-{{ $params['opponent_score'] ?? 0 }}</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">❤️ Vies</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;">{{ $params['vies_restantes'] ?? 3 }}</div>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-around; margin: 20px 0; padding: 20px; background: rgba(102, 126, 234, 0.1); border-radius: 15px;">
            <div style="text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">📈 Efficacité manche</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;">{{ $params['round_efficiency'] ?? 0 }}%</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 5px;">🎯 Niveau adversaire</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;">{{ $params['niveau_adversaire'] ?? 1 }}</div>
            </div>
        </div>
        
        <form action="{{ route('solo.game') }}" method="GET">
            <button type="submit" class="next-button">
                🚀 Manche {{ $params['next_round'] }}
            </button>
        </form>
    </div>
</body>
</html>
