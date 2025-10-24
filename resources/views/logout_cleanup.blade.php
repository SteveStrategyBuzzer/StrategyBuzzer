<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déconnexion - StrategyBuzzer</title>
</head>
<body>
    <script>
    // Réinitialiser le flag de session musicale lors de la déconnexion
    localStorage.removeItem('music_session_started');
    
    // Rediriger immédiatement vers la page de login
    window.location.href = "{{ route('login') }}";
    </script>
</body>
</html>
