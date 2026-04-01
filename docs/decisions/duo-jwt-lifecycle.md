# Duo JWT Lifecycle Decision

Date: 2026-03-22 (Québec)

## Problème

- JWT expirait après 5 minutes
- Déconnexions fréquentes en mode Duo
- Incohérence entre durée lobby et gameplay

## Décision

- Le JWT doit être aligné sur la durée du lobby
- Durée cible: 20 minutes
- Le token doit rester valide pendant toute la session Duo

## Impact

- Amélioration stabilité gameplay
- Moins de reconnections
- Meilleure expérience utilisateur

## À faire

- Centraliser génération JWT
- Éviter duplication dans contrôleurs
- Harmoniser avec GameServerService
