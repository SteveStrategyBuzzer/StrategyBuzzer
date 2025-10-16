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

        .home-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .portrait-image {
            display: block;
        }

        .landscape-image {
            display: none;
        }

        .btn-create {
            position: absolute;
            background: rgba(255, 255, 255, 0.9);
            color: #003DA5;
            padding: 1rem 2.5rem;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .btn-create:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.4);
        }

        /* Portrait : bouton en bas à droite */
        @media (orientation: portrait) {
            .portrait-image {
                display: block;
            }
            .landscape-image {
                display: none;
            }
            .btn-create {
                bottom: 5%;
                right: 5%;
            }
        }

        /* Landscape : bouton en bas à droite */
        @media (orientation: landscape) {
            .portrait-image {
                display: none;
            }
            .landscape-image {
                display: block;
            }
            .btn-create {
                bottom: 8%;
                right: 5%;
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

    <!-- Image Portrait -->
    <img 
        src="{{ asset('images/master-home-portrait.png') }}" 
        alt="Soyez le Maître du Jeu" 
        class="home-image portrait-image"
    >
    
    <!-- Image Paysage -->
    <img 
        src="{{ asset('images/master-home-landscape.png') }}" 
        alt="Soyez le Maître du Jeu" 
        class="home-image landscape-image"
    >

    <a href="{{ route('master.create') }}" class="btn-create">
        Créer un Quizz
    </a>
</body>
</html>
