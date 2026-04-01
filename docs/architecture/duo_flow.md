# Duo Flow — Architecture officielle (SOURCE DE VÉRITÉ)

## 🎯 Objectif

Définir le fonctionnement EXACT du mode Duo.
Ce document est la référence unique pour :
- Laravel
- Game Server (Node.js)
- Frontend
- Firestore

Aucune logique ne doit exister en dehors de ce flow.

---

## 🧠 Cycle de vie global

waiting → lobby → starting → playing → finished

---

## 🔄 Étapes détaillées

### 1. Invitation

- Envoi invitation
- acceptMatch()

Résultat :
- DuoMatch.status = lobby

---

### 2. Création du lobby (CRITIQUE)

Méthode :
createLobby()

Actions :
- création du lobby en cache
- appel GameServerService.createRoomAndGenerateTokens()

Résultat :

- roomId créé
- tokens générés UNE SEULE FOIS

Stockage :

lobby.game_server = {
    roomId,
    player_tokens,
    socket_url
}

---

### 3. Lobby

- joueurs rejoignent
- UI lit le lobby

IMPORTANT :

- les tokens sont lus uniquement depuis :
  lobby.game_server.player_tokens[playerId]

INTERDICTIONS :

❌ ne jamais générer un token ici  
❌ ne jamais appeler generatePlayerToken()  
❌ ne jamais modifier player_tokens  

---

### 4. startGame()

Méthode :
startGame()

Actions :

- validation joueurs
- vérification présence Firebase
- génération questions
- envoi au Game Server
- startGame Node.js

IMPORTANT :

❌ ne jamais recréer la room  
❌ ne jamais régénérer les tokens  

---

### 5. Gameplay

Responsabilité Game Server :

- gestion des phases
- buzz
- réponses
- score
- timing

Laravel :

- affichage
- récupération du token depuis lobby

---

### 6. Fin de partie

- Game Server termine la partie
- résultats stockés
- redistribution gains

---

## 🧩 Sources de vérité

| Élément            | Source |
|-------------------|--------|
| JWT tokens        | lobby cache |
| roomId            | lobby cache |
| état temps réel   | Game Server |
| questions         | Firestore / cache |
| résultats         | DB |

---

## 🔒 RÈGLES CRITIQUES

### JWT

- généré UNE SEULE FOIS dans createLobby()
- stocké dans :
  lobby.game_server.player_tokens
- jamais recréé ensuite

---

### Room Game Server

- créée UNE SEULE FOIS
- jamais recréée dans startGame()

---

### Laravel

- orchestration uniquement
- ne doit jamais recréer :
  - tokens
  - room
  - état temps réel

---

### Game Server (Node)

- autorité temps réel
- logique gameplay uniquement

---

## 🚨 Anti-patterns INTERDITS

❌ generatePlayerToken() dans controller  
❌ generatePlayerToken() dans startGame  
❌ recréer room après lobby  
❌ dépendre uniquement de duo_matches.room_id  
❌ token différent entre lobby et gameplay  

---

## ✅ Résultat attendu

- JWT stable
- aucune déconnexion
- Socket fiable
- gameplay synchronisé
- architecture scalable

---

## 🔥 Règle finale

SI un bug Duo apparaît :

1. vérifier lobby.game_server
2. vérifier tokens
3. vérifier roomId
4. vérifier socket

JAMAIS recréer quoi que ce soit.
