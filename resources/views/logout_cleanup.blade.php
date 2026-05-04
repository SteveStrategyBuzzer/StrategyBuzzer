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

    // Nettoyer le backup du formulaire profil pour éviter la restauration
    // d'anciennes données après reconnexion d'un autre compte ou migration
    sessionStorage.removeItem('profile_form_backup');

    // Rediriger immédiatement vers la page de login
    window.location.href = "{{ route('login') }}";
    </script>
</body>
</html>
