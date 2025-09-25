README - Dossier /tests pour StrategyBuzzer

Ce dossier contient la structure de tests Laravel de base :

1. /Feature/ExampleTest.php
   → Exemple de test fonctionnel basé sur les routes.

2. /Unit/ExampleTest.php
   → Exemple de test unitaire (logique indépendante).

3. CreatesApplication.php
   → Classe nécessaire au fonctionnement des tests Laravel.

=== Exécution des tests ===

1. Ouvre ton terminal dans le projet Laravel.
2. Assure-toi que PHPUnit est installé (`composer install`).
3. Lance :

   php artisan test

OU plus directement :

   ./vendor/bin/phpunit

=== Ajouter tes propres tests ===

→ Crée de nouveaux fichiers dans /tests/Feature ou /tests/Unit
→ Exemples possibles :
   - BuzzButtonTest.php
   - QuizTransitionTest.php
   - AudioFeedbackTest.php

Garde cette structure claire pour faciliter les tests à l’avenir.
