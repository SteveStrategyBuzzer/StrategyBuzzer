---
name: Test DB isolation & $_SERVER precedence
description: Pourquoi phpunit <env> seul ne suffit pas à isoler les tests de la base prod quand une var DB est injectée dans l'environnement réel du process.
---

# Isolation tests ↔ base prod : précédence $_SERVER

**Règle :** quand une variable DB (`DB_CONNECTION`, `DATABASE_URL`, …) existe dans
l'environnement réel du process (ex. injectée par Replit comme env partagée/secret),
il faut la forcer dans `phpunit.xml` à la fois en `<env force="true">` **ET** en
`<server force="true">`. Sinon les tests retombent sur la vraie base Postgres.

**Why:**
- Laravel `env()` résout via phpdotenv avec un ordre d'adaptateurs : il lit
  `$_SERVER` **AVANT** `$_ENV`. (vérifié : `$_SERVER["DB_CONNECTION"]` gagne.)
- PHPUnit `<env name=... force="true">` n'écrit que `$_ENV` + `putenv()`, **pas**
  `$_SERVER`. Donc une var présente dans `$_SERVER` (car injectée par la plateforme)
  l'emporte et l'override `<env>` est silencieusement ignoré.
- Symptôme observé : après avoir mis `DB_CONNECTION=pgsql` en env Replit partagée,
  `config('database.default')` valait `pgsql` PENDANT les tests malgré
  `<env DB_CONNECTION=sqlite force>` → le garde-fou `TestCase::setUp()` se déclenchait
  (il a bien protégé Neon).
- Les vars seulement présentes dans `.env` (pas dans le process réel, ex. `APP_ENV`)
  n'ont pas ce problème : `$_SERVER` ne les contient pas, donc `<env>` suffit.

**How to apply :** dans `phpunit.xml`, dupliquer chaque override DB critique en
`<server ... force="true">` en plus du `<env ... force="true">`. Garder le garde-fou
`Tests\TestCase::setUp()` (refuse de tourner si `config('database.default') !== 'sqlite'`)
comme dernière ligne de défense. Toute NOUVELLE var DB ajoutée à l'env runtime doit
être ajoutée aux DEUX blocs.

**Runtime :** l'app DOIT tourner sur `DB_CONNECTION=pgsql` (env partagée Replit →
connexion `pgsql` → `DATABASE_URL`/Neon). NE PAS détourner la connexion `sqlite`
via une `url` Postgres : ça masque le driver réel et aveugle le garde-fou
anti-effacement `CommandStarting`.
