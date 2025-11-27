<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Maître du Jeu') }} - StrategyBuzzer</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(180deg, #0a1628 0%, #1a2a4a 30%, #0d3d6e 60%, #0a1628 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            font-family: 'Roboto', sans-serif;
            position: relative;
            padding: 20px;
        }

        /* Étoiles en arrière-plan */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(2px 2px at 20px 30px, white, transparent),
                radial-gradient(2px 2px at 40px 70px, rgba(255,255,255,0.8), transparent),
                radial-gradient(1px 1px at 90px 40px, white, transparent),
                radial-gradient(2px 2px at 130px 80px, rgba(255,255,255,0.6), transparent),
                radial-gradient(1px 1px at 160px 120px, white, transparent),
                radial-gradient(2px 2px at 200px 50px, rgba(255,255,255,0.7), transparent),
                radial-gradient(1px 1px at 250px 90px, white, transparent),
                radial-gradient(2px 2px at 300px 60px, rgba(255,255,255,0.5), transparent),
                radial-gradient(1px 1px at 350px 30px, white, transparent),
                radial-gradient(2px 2px at 80px 150px, rgba(255,255,255,0.6), transparent),
                radial-gradient(1px 1px at 180px 180px, white, transparent),
                radial-gradient(2px 2px at 280px 140px, rgba(255,255,255,0.8), transparent);
            background-repeat: repeat;
            background-size: 400px 200px;
            opacity: 0.6;
            pointer-events: none;
            z-index: 0;
        }

        .content-wrapper {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            width: 100%;
            max-width: 600px;
        }

        /* Titre principal */
        .title-section {
            text-align: center;
            margin-top: 30px;
            animation: fadeInDown 1s ease-out;
        }

        .title-small {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(18px, 4vw, 28px);
            color: #87CEEB;
            text-transform: uppercase;
            letter-spacing: 8px;
            text-shadow: 0 0 20px rgba(135, 206, 235, 0.8);
            margin-bottom: 5px;
        }

        .title-main {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(48px, 12vw, 80px);
            font-weight: 900;
            color: #FFD700;
            text-transform: uppercase;
            letter-spacing: 4px;
            text-shadow: 
                0 0 30px rgba(255, 215, 0, 0.8),
                0 0 60px rgba(255, 215, 0, 0.4),
                2px 2px 4px rgba(0, 0, 0, 0.5);
            line-height: 1;
        }

        .title-sub {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(28px, 7vw, 48px);
            font-weight: 700;
            color: #FFD700;
            text-transform: uppercase;
            letter-spacing: 6px;
            text-shadow: 
                0 0 20px rgba(255, 215, 0, 0.6),
                0 0 40px rgba(255, 215, 0, 0.3);
            margin-top: 5px;
        }

        /* Section centrale avec silhouette */
        .center-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            width: 100%;
        }

        /* Podium lumineux */
        .podium {
            position: relative;
            width: 200px;
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .podium-glow {
            position: absolute;
            bottom: 0;
            width: 180px;
            height: 100px;
            background: radial-gradient(ellipse at center bottom, rgba(0, 200, 255, 0.6) 0%, rgba(0, 150, 255, 0.3) 40%, transparent 70%);
            filter: blur(10px);
        }

        .podium-base {
            position: absolute;
            bottom: 20px;
            width: 140px;
            height: 60px;
            background: linear-gradient(180deg, #00a8ff 0%, #0066cc 50%, #003366 100%);
            border-radius: 10px 10px 20px 20px;
            box-shadow: 
                0 10px 40px rgba(0, 168, 255, 0.5),
                inset 0 2px 10px rgba(255, 255, 255, 0.3);
        }

        /* Silhouette du présentateur */
        .presenter {
            position: absolute;
            bottom: 60px;
            width: 100px;
            height: 150px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .presenter-silhouette {
            width: 80px;
            height: 140px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            clip-path: polygon(
                50% 0%, 
                65% 10%, 
                60% 25%, 
                70% 30%, 
                90% 45%, 
                75% 50%, 
                70% 100%, 
                30% 100%, 
                25% 50%, 
                10% 45%, 
                30% 30%, 
                40% 25%, 
                35% 10%
            );
            opacity: 0.9;
        }

        /* Cercle Question */
        .question-circle {
            position: absolute;
            bottom: 100px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(180deg, #0088cc 0%, #004466 100%);
            border: 3px solid rgba(255, 255, 255, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 0 30px rgba(0, 136, 204, 0.5),
                inset 0 0 20px rgba(255, 255, 255, 0.1);
            animation: pulse 2s ease-in-out infinite;
        }

        .question-text {
            font-size: 14px;
            color: #fff;
            text-align: center;
            line-height: 1.2;
        }

        .question-number {
            font-size: 18px;
            font-weight: bold;
            color: #FFD700;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Logo StrategyBuzzer */
        .logo-section {
            margin: 20px 0;
            animation: fadeIn 1.5s ease-out;
        }

        .logo-text {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(24px, 6vw, 36px);
            font-weight: 700;
            background: linear-gradient(90deg, #FFD700 0%, #FFA500 50%, #FFD700 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: none;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.5));
        }

        /* Section du bas */
        .bottom-section {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeInUp 1s ease-out;
        }

        .tagline {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(12px, 3vw, 18px);
            color: #FFD700;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .access-btn {
            display: inline-block;
            padding: 12px 40px;
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(90deg, #0088cc 0%, #00aaff 50%, #0088cc 100%);
            border: none;
            border-radius: 30px;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            box-shadow: 
                0 5px 20px rgba(0, 136, 204, 0.5),
                0 0 40px rgba(0, 170, 255, 0.3);
            transition: all 0.3s ease;
            animation: glowPulse 2s ease-in-out infinite;
        }

        .access-btn:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 8px 30px rgba(0, 136, 204, 0.7),
                0 0 60px rgba(0, 170, 255, 0.5);
        }

        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 5px 20px rgba(0, 136, 204, 0.5), 0 0 40px rgba(0, 170, 255, 0.3); }
            50% { box-shadow: 0 5px 30px rgba(0, 136, 204, 0.7), 0 0 60px rgba(0, 170, 255, 0.5); }
        }

        /* Compte à rebours */
        .countdown {
            margin-top: 15px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDown {
            from { 
                opacity: 0;
                transform: translateY(-30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive - Paysage */
        @media (orientation: landscape) {
            body {
                flex-direction: row;
                padding: 20px 40px;
            }

            .content-wrapper {
                flex-direction: row;
                justify-content: space-around;
                max-width: 100%;
            }

            .title-section {
                margin-top: 0;
                text-align: left;
            }

            .title-main {
                font-size: clamp(36px, 8vw, 60px);
            }

            .title-sub {
                font-size: clamp(24px, 5vw, 40px);
            }

            .center-section {
                flex: 0;
            }

            .bottom-section {
                margin-bottom: 0;
                text-align: right;
            }

            .tagline {
                font-size: clamp(10px, 2vw, 14px);
            }
        }

        /* Petits écrans */
        @media (max-height: 600px) and (orientation: portrait) {
            .title-section {
                margin-top: 10px;
            }

            .podium {
                transform: scale(0.8);
            }

            .question-circle {
                width: 80px;
                height: 80px;
            }

            .bottom-section {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <!-- Titre -->
        <div class="title-section">
            <div class="title-small">{{ __('SOYEZ LE') }}</div>
            <div class="title-main">{{ __('MAÎTRE') }}</div>
            <div class="title-sub">{{ __('DU JEU') }}</div>
        </div>

        <!-- Section centrale -->
        <div class="center-section">
            <div class="podium">
                <div class="podium-glow"></div>
                <div class="presenter">
                    <div class="presenter-silhouette"></div>
                </div>
                <div class="podium-base"></div>
            </div>
            <div class="question-circle">
                <span class="question-number">{{ __('Question :num sur :total', ['num' => 1, 'total' => 20]) }}</span>
            </div>
        </div>

        <!-- Logo -->
        <div class="logo-section">
            <div class="logo-text">STRATEGYBUZZER</div>
        </div>

        <!-- Bas de page -->
        <div class="bottom-section">
            <div class="tagline">
                {{ __('CRÉEZ VOS PROPRES QUESTIONS') }}<br>
                {{ __('ET DÉFIEZ VOS AMIS') }} !
            </div>
            <a href="{{ route('master.create') }}" class="access-btn">
                {{ __('Accédez') }}
            </a>
            <div class="countdown">
                {{ __('Redirection automatique dans') }} <span id="timer">5</span>s
            </div>
        </div>
    </div>

    <script>
        let seconds = 5;
        const timerEl = document.getElementById('timer');
        
        const countdown = setInterval(function() {
            seconds--;
            timerEl.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = "{{ route('master.create') }}";
            }
        }, 1000);
    </script>
</body>
</html>
