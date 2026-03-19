Projet: StrategyBuzzer
Contexte: Replit = source de vérité, GitHub = miroir officiel, VM Debian = prod.
Pipeline obligatoire: Replit -> GitHub -> VM.
Objectif: auditer et corriger proprement le flux Duo invitation -> lobby -> room -> socket sync.

Résumé du bug réel observé en prod VM
- Les 2 joueurs arrivent dans le lobby
- Presence Firebase/Firestore peut indiquer 2 joueurs online
- Mais côté navigateur Windows dans le lobby:
  - window.matchRoomId = undefined
  - window.matchLobbyCode = undefined
  - window.matchPlayerToken = undefined
- Donc le bloc Socket.IO du lobby n’est pas injecté et aucune synchro temps réel ne démarre.

Faits confirmés par audit VM
1) Dans app/Http/Controllers/LobbyController.php, les variables socket du lobby sont injectées seulement si:
   if ($duoMatch && $duoMatch->room_id)

2) On a élargi le filtre de statut pour inclure aussi 'playing', car le code ne regardait pas ce statut.

3) Exemple réel inspecté:
   duo_matches.id = 222
   lobby_code = N5S84M
   status = lobby
   room_id = null
   started_at = null

4) Mais le cache lobby:N5S84M contient déjà:
   - game_server.roomId = 7215b424-8a07-44bd-8949-c0a0d8014f54
   - game_server.player_tokens[3]
   - game_server.player_tokens[4]
   - game_server.socket_url = http://127.0.0.1:3001

5) Donc la room existe bien côté cache lobby / game_server, mais elle n’est pas persistée dans duo_matches.room_id.

6) On a aussi constaté que, à l’instant inspecté, cache lobby:N5S84M contenait seulement:
   lobby['players'] => user 3
   alors que des tokens existaient pour user 3 et 4.
   Donc possible désynchronisation entre:
   - cache lobby players
   - duo_matches
   - création / entrée du joueur invité

7) Ancien bug déjà identifié:
   app/Services/DuoMatchmakingService.php mettait trop tôt:
   - status = 'playing'
   - started_at = now()
   sans room_id persistant.
   On a corrigé temporairement en VM pour:
   - status = 'lobby'
   - started_at = null

8) Autres corrections déjà faites temporairement en VM:
   - LobbyController accepte status 'playing' dans sa recherche de DuoMatch
   - LobbyService régénère un token frais pour le joueur qui rejoint
   - JS lobby / duo question / duo answer utilise window.location.origin au lieu d’une config socket cassée / localhost côté navigateur

Hypothèse forte
Le flux métier source a un défaut de conception:
- createLobby() crée bien room + tokens dans le cache
- mais ne persiste pas room_id dans duo_matches
et/ou
- le joueur invité n’est pas hydraté correctement dans lobby['players']
et/ou
- le match, le cache lobby et la logique d’acceptation divergent après invitation

Audit demandé
Je veux un audit complet et une correction cohérente du flux:
invitation -> acceptMatch -> createLobby -> joinLobby -> show lobby -> start game

Je veux que tu identifies précisément:
1. Où room_id devrait être écrit dans duo_matches mais ne l’est pas
2. Où le cache lobby et la table duo_matches divergent
3. Où lobby['players'] devrait contenir les 2 joueurs mais ne reflète qu’une partie de l’état réel
4. Quel doit être l’ordre exact et unique de ces étapes:
   - acceptation du match
   - création du lobby
   - création de la room game-server
   - génération des tokens
   - persistance room_id dans duo_matches
   - ajout des 2 joueurs dans lobby['players']
   - affichage du lobby
   - connexion socket des 2 joueurs
   - lancement du countdown / start game

Cible fonctionnelle obligatoire
Je veux une logique robuste et simple:
- Dès qu’une invitation Duo est acceptée, un lobby unique existe
- Une seule room game-server est créée
- room_id est persisté immédiatement dans duo_matches
- les tokens des 2 joueurs sont disponibles
- les 2 joueurs sont présents dans lobby['players']
- LobbyController peut injecter pour les 2 joueurs:
  - matchRoomId
  - matchLobbyCode
  - matchPlayerToken
  - gameServerUrl
- Ensuite seulement on lance countdown et gameplay

Ce que je veux comme réponse
1. Analyse précise de la cause racine
2. Liste exacte des fichiers à modifier
3. Diff complet et propre
4. Explication du nouvel enchaînement logique
5. Vérifications précises à faire ensuite sur VM

Important
- Ne pas proposer une rustine frontend seulement
- Corriger le flux métier source-of-truth côté Replit
- Respecter strictement l’architecture Replit -> GitHub -> VM
- Chercher une correction propre, durable et cohérente, pas un workaround
