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
        
        <div class="retry-section">
            <h2 class="retry-title">Ne baissez pas les bras !</h2>
            <p style="color: #666; font-size: 1.1rem;">
                Réessayez et montrez votre vraie valeur !
            </p>
        </div>
        
        <div class="action-buttons">
            <form action="{{ route('solo.start') }}" method="POST" style="display: inline;">
                @csrf
                <input type="hidden" name="nb_questions" value="{{ session('nb_questions', 30) }}">
                <input type="hidden" name="theme" value="{{ session('theme', 'general') }}">
                <input type="hidden" name="niveau_joueur" value="{{ $params['current_level'] }}">
                <button type="submit" class="btn btn-retry">🔄 Réessayer</button>
            </form>
            
            <a href="{{ route('menu') }}" class="btn btn-menu">Menu</a>
        </div>
    </div>
</body>
</html>
