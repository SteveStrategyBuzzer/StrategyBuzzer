# ADR_006_asset_storage_separation

## Contexte
Le repo StrategyBuzzer est trop lourd à cause d'assets visuels, archives, exports et fichiers générés.

## Décision
Séparer définitivement :
- le code
- les assets lourds
- les backups

## Conséquences
- Git reste centré sur le code
- les visuels restent sauvegardés hors repo
- les déploiements deviennent plus propres
- les clones et pulls deviennent plus sûrs
