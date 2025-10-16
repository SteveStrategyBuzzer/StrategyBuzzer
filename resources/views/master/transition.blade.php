<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maître du Jeu - StrategyBuzzer</title>
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
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .transition-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(1.1);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Portrait */
        @media (orientation: portrait) {
            .transition-image {
                object-fit: cover;
            }
        }

        /* Landscape */
        @media (orientation: landscape) {
            .transition-image {
                object-fit: contain;
            }
        }
    </style>
</head>
<body>
    <img 
        src="{{ asset('images/master-transition-portrait.png') }}" 
        alt="Soyez le Maître du Jeu" 
        class="transition-image"
    >

    <script>
        // Redirection automatique après 5 secondes
        setTimeout(function() {
            window.location.href = "{{ route('master.create') }}";
        }, 5000);
    </script>
</body>
</html>
