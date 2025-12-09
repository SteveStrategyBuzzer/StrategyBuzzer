<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Face à Face') }} - StrategyBuzzer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .splash-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0a1628;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .splash-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            animation: fadeIn 0.5s ease-out;
        }
        
        .splash-image.portrait {
            display: none;
        }
        
        .splash-image.landscape {
            display: block;
        }
        
        @media (orientation: portrait) {
            .splash-image.portrait {
                display: block;
            }
            .splash-image.landscape {
                display: none;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(1.05);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        
        .splash-container.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }
    </style>
</head>
<body>
    <div class="splash-container" id="splashContainer">
        <img src="{{ asset('images/league_splash_portrait.png') }}" alt="{{ __('Face à Face') }}" class="splash-image portrait">
        <img src="{{ asset('images/league_splash_landscape.png') }}" alt="{{ __('Face à Face') }}" class="splash-image landscape">
    </div>

    <script>
        const redirectUrl = '{{ $redirectUrl ?? route("league.team.management") }}';
        
        setTimeout(function() {
            document.getElementById('splashContainer').classList.add('fade-out');
            setTimeout(function() {
                window.location.href = redirectUrl;
            }, 500);
        }, 3000);
    </script>
</body>
</html>
