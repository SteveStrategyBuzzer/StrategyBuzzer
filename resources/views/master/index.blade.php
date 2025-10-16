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
            position: relative;
        }

        .image-link {
            width: 100%;
            height: 100%;
            display: block;
            cursor: pointer;
            position: relative;
        }

        .home-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .portrait-image {
            display: block;
        }

        .landscape-image {
            display: none;
        }

        /* Portrait */
        @media (orientation: portrait) {
            .portrait-image {
                display: block;
            }
            .landscape-image {
                display: none;
            }
        }

        /* Landscape */
        @media (orientation: landscape) {
            .portrait-image {
                display: none;
            }
            .landscape-image {
                display: block;
            }
        }

    </style>
</head>
<body>
    <a href="{{ route('master.create') }}" class="image-link">
        <!-- Image Portrait -->
        <img 
            src="{{ asset('images/master-home-portrait.png') }}" 
            alt="Soyez le Maître du Jeu - Cliquer pour créer un quizz" 
            class="home-image portrait-image"
        >
        
        <!-- Image Paysage -->
        <img 
            src="{{ asset('images/master-home-landscape.png') }}" 
            alt="Soyez le Maître du Jeu - Cliquer pour créer un quizz" 
            class="home-image landscape-image"
        >
    </a>
</body>
</html>
