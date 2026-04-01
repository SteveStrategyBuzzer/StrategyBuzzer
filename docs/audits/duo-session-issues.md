# Audit Duo Session Issues

Date: 2026-03-22

## Problèmes identifiés

- JWT expiration trop courte (5 min)
- Génération du token à plusieurs endroits:
  - LobbyController
  - DuoController
  - GameServerService
- Flux lobby → gameplay non cohérent
- Risque de désynchronisation avec game-server

## Risques

- Expiration en plein match
- Déconnexion socket
- Perte de session joueur

## Conclusion

Le système actuel nécessite une centralisation et un alignement du cycle de vie des tokens avec le cycle de jeu.
