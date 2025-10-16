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

        .header-menu {
            position: absolute;
            top: 20px;
            left: 20px;
            background: white;
            color: #003DA5;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            z-index: 10;
            transition: all 0.3s ease;
        }

        .header-menu:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <a href="{{ route('menu') }}" class="header-menu">← Menu</a>

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
