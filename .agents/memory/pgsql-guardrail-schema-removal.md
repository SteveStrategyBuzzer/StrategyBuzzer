---
name: Garde anti-rollback pgsql & retraits de schéma
description: migrate:rollback/fresh interdits sur Neon par un garde projet ; procédure convenue pour retirer proprement des colonnes vides sur ordre explicite
---

Un garde projet (AppServiceProvider) intercepte les commandes artisan destructrices (`migrate:rollback`, `fresh`, `reset`…) et les BLOQUE sur toute connexion non-sqlite — protection des données joueurs. Ne jamais tenter de le désactiver.

**Procédure convenue (2026-08-11) pour un retrait de schéma ORDONNÉ par le user** (colonnes/contraintes VIDES issues d'une tâche annulée) :
1. script PHP bootstrappé (pas de commande artisan) ;
2. `DB::transaction` + garde `count() === 0` sur la table — ABORT si des données sont apparues ;
3. ALTER/DROP ciblés équivalents au `down()` ;
4. DELETE de la ligne dans `migrations` + suppression du fichier de migration du dépôt.

**Why :** le garde vise les commandes de masse ; ordre explicite + table vide + transaction = seul chemin légitime. Pour toute donnée réelle → STOP/BLOCKER avant destruction, jamais de retrait silencieux.
