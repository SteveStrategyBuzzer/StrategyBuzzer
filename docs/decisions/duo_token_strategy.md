# Décision — Source des tokens Duo

## Choix

Source principale:
→ cache lobby (game_server.player_tokens)

Fallback:
→ DB (room_id) uniquement après startGame

## Pourquoi

- tokens générés dès createLobby
- room_id non garanti avant startGame
- évite undefined côté frontend

## Impact

- Lobby fiable
- Socket connecté dès l’entrée
- gameplay stable
