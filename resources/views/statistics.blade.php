<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - StrategyBuzzer</title>
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
            padding: 40px 20px;
        }
        
        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            font-size: 3rem;
            margin-bottom: 40px;
            text-align: center;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        
        .mode-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .mode-title {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 900;
        }
        
        .stat-subtitle {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        .no-data {
            text-align: center;
            color: #999;
            font-size: 1.2rem;
            padding: 40px;
        }
        
        .back-button {
            display: inline-block;
            padding: 15px 40px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 30px auto;
            text-align: center;
            display: block;
            width: fit-content;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }
            
            .mode-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="stats-container">
        <h1>📊 Vos Statistiques</h1>
        
        <!-- Solo Mode Stats -->
        <div class="mode-section">
            <h2 class="mode-title">🎯 Mode Solo</h2>
            
            @if($params['global_stats']['solo'])
                <div class="stats-grid">
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="stat-label">🎯 Efficacité Brute</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['solo']->efficacite_brute, 1) }}%</div>
                        <div class="stat-subtitle">Points / Questions</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="stat-label">🙋 Participation</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['solo']->taux_participation, 1) }}%</div>
                        <div class="stat-subtitle">Buzzes / Total</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="stat-label">✅ Précision</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['solo']->taux_precision, 1) }}%</div>
                        <div class="stat-subtitle">Correct / Buzzes</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="stat-label">⭐ Performance</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['solo']->ratio_performance, 1) }}%</div>
                        <div class="stat-subtitle">Points / Max Possible</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 20px; background: #f7f9fc; border-radius: 15px;">
                    <p style="color: #333; font-size: 1.1rem; text-align: center;">
                        <strong>{{ $params['global_stats']['solo']->total_questions }}</strong> questions jouées &nbsp;•&nbsp; 
                        <strong>{{ $params['global_stats']['solo']->correct_answers }}</strong> correctes &nbsp;•&nbsp; 
                        <strong>{{ $params['global_stats']['solo']->wrong_answers }}</strong> incorrectes
                    </p>
                </div>
            @else
                <div class="no-data">
                    Aucune donnée disponible. Jouez des parties en mode Solo pour voir vos statistiques !
                </div>
            @endif
        </div>
        
        <!-- Duo Mode Stats -->
        <div class="mode-section">
            <h2 class="mode-title">🤝 Mode Duo</h2>
            
            @if($params['global_stats']['duo'])
                <div class="stats-grid">
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="stat-label">🎯 Efficacité Brute</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['duo']->efficacite_brute, 1) }}%</div>
                        <div class="stat-subtitle">Points / Questions</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="stat-label">🙋 Participation</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['duo']->taux_participation, 1) }}%</div>
                        <div class="stat-subtitle">Buzzes / Total</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="stat-label">✅ Précision</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['duo']->taux_precision, 1) }}%</div>
                        <div class="stat-subtitle">Correct / Buzzes</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="stat-label">⭐ Performance</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['duo']->ratio_performance, 1) }}%</div>
                        <div class="stat-subtitle">Points / Max Possible</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 20px; background: #f7f9fc; border-radius: 15px;">
                    <p style="color: #333; font-size: 1.1rem; text-align: center;">
                        <strong>{{ $params['global_stats']['duo']->total_questions }}</strong> questions jouées &nbsp;•&nbsp; 
                        <strong>{{ $params['global_stats']['duo']->correct_answers }}</strong> correctes &nbsp;•&nbsp; 
                        <strong>{{ $params['global_stats']['duo']->wrong_answers }}</strong> incorrectes
                    </p>
                </div>
            @else
                <div class="no-data">
                    Aucune donnée disponible. Jouez des parties en mode Duo pour voir vos statistiques !
                </div>
            @endif
        </div>
        
        <!-- League Mode Stats -->
        <div class="mode-section">
            <h2 class="mode-title">🏆 Mode Ligue</h2>
            
            @if($params['global_stats']['league'])
                <div class="stats-grid">
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="stat-label">🎯 Efficacité Brute</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['league']->efficacite_brute, 1) }}%</div>
                        <div class="stat-subtitle">Points / Questions</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="stat-label">🙋 Participation</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['league']->taux_participation, 1) }}%</div>
                        <div class="stat-subtitle">Buzzes / Total</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="stat-label">✅ Précision</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['league']->taux_precision, 1) }}%</div>
                        <div class="stat-subtitle">Correct / Buzzes</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="stat-label">⭐ Performance</div>
                        <div class="stat-value">{{ number_format($params['global_stats']['league']->ratio_performance, 1) }}%</div>
                        <div class="stat-subtitle">Points / Max Possible</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 20px; background: #f7f9fc; border-radius: 15px;">
                    <p style="color: #333; font-size: 1.1rem; text-align: center;">
                        <strong>{{ $params['global_stats']['league']->total_questions }}</strong> questions jouées &nbsp;•&nbsp; 
                        <strong>{{ $params['global_stats']['league']->correct_answers }}</strong> correctes &nbsp;•&nbsp; 
                        <strong>{{ $params['global_stats']['league']->wrong_answers }}</strong> incorrectes
                    </p>
                </div>
            @else
                <div class="no-data">
                    Aucune donnée disponible. Jouez des parties en mode Ligue pour voir vos statistiques !
                </div>
            @endif
        </div>
        
        <a href="{{ route('menu') }}" class="back-button">← Retour au Menu</a>
    </div>
</body>
</html>
