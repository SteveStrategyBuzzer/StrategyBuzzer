---
name: Isolation PostgreSQL des tests
description: Règles durables pour exécuter les tests Laravel sur une base PostgreSQL jetable sans risquer une base persistante.
---

# Isolation PostgreSQL des tests Laravel

**Règle :** les tests Laravel qui peuvent migrer ou écrire s'exécutent uniquement
dans une base PostgreSQL dédiée, créée avec un nom aléatoire strictement préfixé
pour une seule commande, puis supprimée même si la migration ou PHPUnit échoue.
Un lancement PHPUnit direct doit échouer avant le bootstrap Laravel.

**Why:** `RefreshDatabase` peut exécuter `migrate:fresh`, donc une simple erreur de
variable d'environnement peut effacer une base persistante. SQLite ne valide pas
fidèlement les migrations PostgreSQL historiques, notamment les contraintes et
index partiels. La base jetable doit donc être PostgreSQL sans être la base de
développement, de production, `postgres` ou une base système.

**How to apply:** passer par le lanceur isolé du projet. Conserver trois contrôles :
bootstrap PHPUnit avant Laravel, validation de la configuration Laravel dans le
TestCase de base, puis comparaison SQL avec `current_database()`. Neutraliser
`DATABASE_URL` et fournir séparément la base jetable afin qu'aucune URL persistante
ne puisse reprendre la priorité. Le nettoyage doit être dans un `finally` sans
`exit()` à l'intérieur du `try`, car un arrêt explicite peut court-circuiter le
nettoyage.
