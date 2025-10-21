<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StrategyBuzzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.png') }}">
</head>
<body>

    <main class="container">
        @yield('content')
    </main>

    <!-- Musique d'ambiance StrategyBuzzer (joue partout sauf en gameplay) -->
    <audio id="ambientMusic" preload="auto" loop>
        <source src="{{ asset('sounds/strategybuzzer_ambient.mp3') }}" type="audio/mpeg">
    </audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Système de musique d'ambiance globale StrategyBuzzer
    (function() {
        const ambientMusic = document.getElementById('ambientMusic');
        if (!ambientMusic) return;
        
        // Pages de jeu où la musique d'ambiance NE doit PAS jouer
        const gameplayPages = [
            '/solo/game',
            '/solo/answer', 
            '/solo/next',
            '/duo/game',
            '/duo/answer',
            '/league-individual/game',
            '/league-individual/answer',
            '/league-team/game',
            '/league-team/answer'
        ];
        
        // Vérifier si on est sur une page de gameplay
        function isGameplayPage() {
            const currentPath = window.location.pathname;
            return gameplayPages.some(page => currentPath.includes(page));
        }
        
        // Variable pour indiquer si c'est la page de résultat (game_result)
        const isResultPage = @json(isset($params) && isset($params['is_correct']));
        
        // Si on est sur une page de gameplay, arrêter la musique
        if (isGameplayPage() || isResultPage) {
            // Sauvegarder la position actuelle
            const currentTime = parseFloat(localStorage.getItem('ambientMusicTime') || '0');
            ambientMusic.currentTime = currentTime;
            
            // Ne pas jouer la musique
            ambientMusic.pause();
            
            // Continuer à sauvegarder la position pour la reprendre plus tard
            setInterval(() => {
                localStorage.setItem('ambientMusicTime', ambientMusic.currentTime.toString());
            }, 1000);
        } else {
            // Pages non-gameplay : jouer la musique d'ambiance
            
            // Restaurer la position de lecture depuis localStorage
            const savedTime = parseFloat(localStorage.getItem('ambientMusicTime') || '0');
            
            // Attendre que les métadonnées soient chargées pour définir currentTime
            ambientMusic.addEventListener('loadedmetadata', function() {
                if (savedTime > 0 && savedTime < ambientMusic.duration) {
                    ambientMusic.currentTime = savedTime;
                }
                
                // Jouer la musique automatiquement
                ambientMusic.play().catch(err => {
                    console.log('Lecture automatique bloquée. Attente interaction utilisateur.');
                    
                    // Si le navigateur bloque la lecture auto, jouer au premier clic
                    document.addEventListener('click', function playOnClick() {
                        ambientMusic.play().catch(e => console.log('Erreur lecture:', e));
                        document.removeEventListener('click', playOnClick);
                    }, { once: true });
                });
            });
            
            // Sauvegarder la position de lecture toutes les secondes
            setInterval(() => {
                localStorage.setItem('ambientMusicTime', ambientMusic.currentTime.toString());
            }, 1000);
            
            // Sauvegarder la position avant de quitter la page
            window.addEventListener('beforeunload', () => {
                localStorage.setItem('ambientMusicTime', ambientMusic.currentTime.toString());
            });
        }
    })();
    </script>
</body>
</html>
