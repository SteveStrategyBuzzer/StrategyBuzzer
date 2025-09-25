<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuzzSound</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <header>
        <h1>BuzzSound</h1>
    </header>

    <main class="container">
        @yield('content')
    </main>

    <footer>
        <p>&copy; 2025 BuzzSound. Tous droits réservés.</p>
        <p>
            <a href="{{ route('privacy.policy') }}">Politique de Confidentialité</a> |
            <a href="{{ route('data.deletion.policy') }}">Suppression des données</a>
        </p>
    </footer>
</body>
</html>

