<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            '/solo/prepare',
            '/solo/game',
            '/solo/answer',
            '/solo/timeout',
            '/solo/next',
            '/solo/victory',
            '/solo/defeat',
            '/duo/game',
            '/duo/answer',
            '/league-individual/game',
            '/league-individual/answer',
            '/league-team/game',
            '/league-team/answer',
            '/game_preparation',
            '/game_question',
            '/game_answer',
            '/game_result',
            '/victory',
            '/defeat'
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
            
            // Utiliser canplay au lieu de loadedmetadata pour s'assurer que l'audio est prêt
            ambientMusic.addEventListener('canplay', function onCanPlay() {
                // Restaurer la position sauvegardée
                if (savedTime > 0) {
                    // Vérifier que savedTime est valide (pas après la fin de la piste)
                    if (!isNaN(ambientMusic.duration) && savedTime < ambientMusic.duration) {
                        ambientMusic.currentTime = savedTime;
                        console.log('Musique restaurée à:', savedTime + 's');
                    } else if (isNaN(ambientMusic.duration) || ambientMusic.duration === Infinity) {
                        // Si duration pas encore connue, forcer quand même
                        ambientMusic.currentTime = savedTime;
                        console.log('Musique restaurée à:', savedTime + 's (duration inconnue)');
                    }
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
                // Retirer l'événement après la première exécution
                ambientMusic.removeEventListener('canplay', onCanPlay);
            });
            
            // Sauvegarder la position de lecture toutes les 250ms pour plus de précision
            setInterval(() => {
                if (!ambientMusic.paused) {
                    const musicId = getSelectedMusic();
                    localStorage.setItem('ambientMusicTime_' + musicId, ambientMusic.currentTime.toString());
                }
            }, 250);
            
            // Sauvegarder la position avant de quitter la page (plusieurs événements pour compatibilité)
            const savePosition = () => {
                const musicId = getSelectedMusic();
                localStorage.setItem('ambientMusicTime_' + musicId, ambientMusic.currentTime.toString());
            };
            
            window.addEventListener('beforeunload', savePosition);
            window.addEventListener('pagehide', savePosition);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    savePosition();
                }
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

    <!-- Détection automatique de la langue du navigateur -->
    <script>
    (function() {
        @auth
        const userLang = @json(auth()->user()->preferred_language ?? null);
        const supportedLanguages = @json(array_keys(config('languages.supported', ['fr' => []])));
        
        // Si l'utilisateur n'a pas de langue définie, détecter automatiquement
        if (!userLang || userLang === 'fr') {
            const browserLang = (navigator.language || navigator.userLanguage || 'fr').split('-')[0].toLowerCase();
            
            // Vérifier si la langue du navigateur est supportée
            if (supportedLanguages.includes(browserLang) && browserLang !== 'fr') {
                // Proposer de changer automatiquement
                const confirmChange = confirm(
                    `Votre navigateur est en ${browserLang}. Voulez-vous utiliser StrategyBuzzer dans cette langue ?\n\n` +
                    `Your browser is in ${browserLang}. Do you want to use StrategyBuzzer in this language?`
                );
                
                if (confirmChange) {
                    // Envoyer une requête pour sauvegarder la langue
                    const formData = new FormData();
                    formData.append('language', browserLang);
                    formData.append('_token', '{{ csrf_token() }}');
                    
                    fetch('{{ route("profile.update") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            location.reload(); // Recharger la page pour appliquer la langue
                        } else {
                            console.error('Failed to save language preference');
                        }
                    })
                    .catch(err => console.error('Erreur sauvegarde langue:', err));
                }
            }
        }
        @endauth
    })();
    </script>
<!-- Toast Notification System -->
<div id="toastContainer" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 99999; pointer-events: none;"></div>

<style>
.custom-toast {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    padding: 16px 28px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 215, 0, 0.2);
    border: 1px solid rgba(255, 215, 0, 0.3);
    font-size: 16px;
    font-weight: 500;
    text-align: center;
    animation: toastSlideIn 0.4s ease-out, toastFadeOut 0.4s ease-in 2.6s forwards;
    pointer-events: auto;
    max-width: 90vw;
}
.custom-toast.success {
    border-color: rgba(76, 217, 100, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(76, 217, 100, 0.3);
}
.custom-toast.error {
    border-color: rgba(255, 59, 48, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 59, 48, 0.3);
}
.custom-toast.warning {
    border-color: rgba(255, 204, 0, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 204, 0, 0.3);
}
@keyframes toastSlideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes toastFadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
</style>

<script>
window.showToast = function(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = 'custom-toast ' + type;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, duration);
};
</script>
</body>
</html>
