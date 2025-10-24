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
    <audio id="ambientMusic" preload="auto" loop></audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Système de musique d'ambiance globale StrategyBuzzer
    (function() {
        const ambientMusic = document.getElementById('ambientMusic');
        if (!ambientMusic) return;
        
        // Mapping des IDs de musique vers les fichiers
        const musicFiles = {
            'strategybuzzer': '/sounds/strategybuzzer_ambient.mp3',
            'fun_01': '/sounds/fun_01.mp3',
            'chill': '/sounds/chill.mp3',
            'punchy': '/sounds/punchy.mp3'
        };
        
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
        
        // Vérifier si la musique est activée dans les paramètres du profil
        function isMusicEnabled() {
            const enabled = localStorage.getItem('ambient_music_enabled');
            return enabled === null || enabled === 'true'; // Par défaut activé
        }
        
        // Obtenir la musique choisie (par défaut strategybuzzer)
        function getSelectedMusic() {
            return localStorage.getItem('ambient_music_id') || 'strategybuzzer';
        }
        
        // Charger la source audio selon le choix
        function loadMusicSource() {
            const musicId = getSelectedMusic();
            const musicFile = musicFiles[musicId] || musicFiles['strategybuzzer'];
            
            // Ne recharger que si la source a changé
            if (ambientMusic.src !== window.location.origin + musicFile) {
                const wasPlaying = !ambientMusic.paused;
                const savedTime = parseFloat(localStorage.getItem('ambientMusicTime_' + musicId) || '0');
                
                ambientMusic.src = musicFile;
                ambientMusic.load();
                
                ambientMusic.addEventListener('loadedmetadata', function onLoaded() {
                    if (savedTime > 0 && savedTime < ambientMusic.duration) {
                        ambientMusic.currentTime = savedTime;
                    }
                    if (wasPlaying && isMusicEnabled() && !isGameplayPage() && !isResultPage) {
                        ambientMusic.play().catch(err => console.log('Erreur lecture:', err));
                    }
                    ambientMusic.removeEventListener('loadedmetadata', onLoaded);
                }, { once: true });
            }
        }
        
        // Charger la source initiale
        loadMusicSource();
        
        // Écouter les changements de musique ou d'activation
        window.addEventListener('storage', function(e) {
            if (e.key === 'ambient_music_id') {
                loadMusicSource();
            } else if (e.key === 'ambient_music_enabled') {
                if (e.newValue === 'true' && !isGameplayPage() && !isResultPage) {
                    ambientMusic.play().catch(err => console.log('Erreur lecture:', err));
                } else {
                    ambientMusic.pause();
                }
            }
        });
        
        // Vérifier si la session musicale a été démarrée (flag défini sur la page profil)
        function isMusicSessionStarted() {
            return localStorage.getItem('music_session_started') === 'true';
        }
        
        // Si on est sur une page de gameplay, arrêter la musique
        if (isGameplayPage() || isResultPage) {
            ambientMusic.pause();
        } else {
            // Pages non-gameplay : jouer la musique d'ambiance SI activée ET session démarrée
            const savedTime = parseFloat(localStorage.getItem('ambientMusicTime_' + getSelectedMusic()) || '0');
            
            ambientMusic.addEventListener('loadedmetadata', function() {
                if (savedTime > 0 && savedTime < ambientMusic.duration) {
                    ambientMusic.currentTime = savedTime;
                }
                
                // Jouer la musique SEULEMENT si activée ET session démarrée après connexion
                if (isMusicEnabled() && isMusicSessionStarted()) {
                    ambientMusic.play().catch(err => {
                        console.log('Lecture automatique bloquée. Attente interaction utilisateur.');
                        
                        // Si le navigateur bloque la lecture auto, jouer au premier clic
                        document.addEventListener('click', function playOnClick() {
                            if (isMusicEnabled() && isMusicSessionStarted()) {
                                ambientMusic.play().catch(e => console.log('Erreur lecture:', e));
                            }
                            document.removeEventListener('click', playOnClick);
                        }, { once: true });
                    });
                }
            });
            
            // Sauvegarder la position de lecture toutes les secondes
            setInterval(() => {
                const musicId = getSelectedMusic();
                localStorage.setItem('ambientMusicTime_' + musicId, ambientMusic.currentTime.toString());
            }, 1000);
            
            // Sauvegarder la position avant de quitter la page
            window.addEventListener('beforeunload', () => {
                const musicId = getSelectedMusic();
                localStorage.setItem('ambientMusicTime_' + musicId, ambientMusic.currentTime.toString());
            });
        }
        
        // Exposer une fonction globale pour contrôler la musique depuis le profil
        window.toggleAmbientMusic = function(enabled) {
            localStorage.setItem('ambient_music_enabled', enabled.toString());
            if (enabled && isMusicSessionStarted() && !isGameplayPage() && !isResultPage) {
                ambientMusic.play().catch(err => console.log('Erreur lecture:', err));
            } else {
                ambientMusic.pause();
            }
        };
        
        // Exposer une fonction pour démarrer la musique (appelée depuis le menu)
        window.startAmbientMusicSession = function() {
            // Si la session est déjà démarrée, ne rien faire (la musique continue déjà)
            if (isMusicSessionStarted()) {
                console.log('Session musicale déjà active, musique continue normalement');
                return;
            }
            
            // Sinon, démarrer la session pour la première fois
            localStorage.setItem('music_session_started', 'true');
            console.log('Démarrage de la session musicale');
            
            if (isMusicEnabled() && !isGameplayPage() && !isResultPage) {
                ambientMusic.play().catch(err => {
                    console.log('Lecture automatique bloquée, attente interaction utilisateur');
                    // Fallback : jouer au prochain clic
                    document.addEventListener('click', function playOnClick() {
                        if (isMusicEnabled()) {
                            ambientMusic.play().catch(e => console.log('Erreur lecture:', e));
                        }
                        document.removeEventListener('click', playOnClick);
                    }, { once: true });
                });
            }
        };
        
        // Exposer une fonction pour changer la musique
        window.changeAmbientMusic = function(musicId) {
            localStorage.setItem('ambient_music_id', musicId);
            loadMusicSource();
        };
    })();
    </script>
</body>
</html>
