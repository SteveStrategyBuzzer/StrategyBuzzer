# Audit — Bug Socket Duo

## Symptômes

- matchRoomId undefined
- matchPlayerToken undefined
- aucune connexion Socket.IO

## Cause racine

- room_id absent DB
- tokens non utilisés depuis cache
- statut incorrect (playing trop tôt)

## Fix

- statut lobby
- lecture tokens cache
- persistance room_id
- guards matchmaking

## Résultat attendu

- Lobby synchronisé
- Socket connecté
- gameplay fluide
