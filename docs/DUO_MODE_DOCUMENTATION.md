# STRATEGYBUZZER - MODE DUO
## Documentation Technique Complète

---

# TABLE DES MATIÈRES

1. Vue d'ensemble et flux de navigation
2. Page 1 : Lobby Duo
3. Page 2 : Matchmaking
4. Page 3 : Question (Buzz)
5. Page 4 : Answer (Réponse)
6. Page 5 : Result (Résultat)
7. Page 6 : Waiting (Attente)
8. Page 7 : Rankings (Classement)
9. Avatars Stratégiques & Skills
10. Système de Points & Divisions
11. Communication Vocale & Texto
12. Architecture Technique

---

# 1. VUE D'ENSEMBLE ET FLUX DE NAVIGATION

## Séquence Principale

```
LOBBY → MATCHMAKING → INTRO → [QUESTION → ANSWER → RESULT → WAITING] xN → FIN
```
*(où N = nombre de questions configuré)*

**Phases d'intro :** Fond noir (3s) + "Ladies and Gentlemen" (9s) = 12 secondes total

## Branches Possibles

| Départ | Action | Destination |
|--------|--------|-------------|
| Lobby | Matchmaking aléatoire | duo_matchmaking.blade.php |
| Lobby | Invitation envoyée | lobby.show (Room générique) |
| Lobby | Invitation acceptée | lobby.show (Room générique) |
| Room | 2 joueurs prêts | duo_question.blade.php |

## Boucle de Jeu (Questions configurables)

| Étape | Page | Durée |
|-------|------|-------|
| 1 | Intro (fond noir) | 3 secondes |
| 2 | Ladies and Gentlemen | 9 secondes |
| 3 | duo_question.blade.php | 8 secondes |
| 4 | duo_answer.blade.php | 10 secondes |
| 5 | duo_result.blade.php | Variable |
| 6 | duo_waiting.blade.php | Sync joueurs |

## Format de Match

- **Best of 3** manches
- **10, 20, 30, 40 ou 50 questions** par manche (configurable)
- **Tiebreaker** si égalité

---

# 2. PAGE 1 : LOBBY DUO

**Fichier :** `resources/views/duo_lobby.blade.php`

## Layout

```
┌─────────────────────────────────────────────┐
│ [← Retour]          MODE DUO                │
├─────────────────────────────────────────────┤
│ ⚠️ ACCÈS LIMITÉ (si niveau < 11)            │
│ Progression: Niveau X / 10                  │
├─────────────────────────────────────────────┤
│ 🎮 SALON OUVERT (si actif)                  │
│ X/2 joueurs | [REJOINDRE →]                 │
├─────────────────────────────────────────────┤
│ 🎯 SÉLECTIONNER DIVISION                    │
│ [🥉 Bronze] [🥈 Argent] [🥇 Or] ...         │
├─────────────────────────────────────────────┤
│ 🎯 MATCHMAKING                              │
│ [REJOINDRE LA FILE D'ATTENTE]               │
├─────────────────────────────────────────────┤
│ 👥 INVITER UN AMI                           │
│ [Code: SB-____] [INVITER] [📒 Carnet]       │
├─────────────────────────────────────────────┤
│ 📬 INVITATIONS REÇUES                       │
│ [ACCEPTER] [REFUSER]                        │
├─────────────────────────────────────────────┤
│ 🏆 CLASSEMENT                               │
│ [Voir classement complet →]                 │
└─────────────────────────────────────────────┘
```

## Fonctionnalités

| Fonction | Description | Condition |
|----------|-------------|-----------|
| Sélection division | Jouer jusqu'à +2 divisions | Niveau ≥ 11 |
| Matchmaking aléatoire | File d'attente Redis | Niveau ≥ 11 |
| Invitation par code | Format SB-XXXX | Niveau ≥ 11 |
| Carnet contacts | Joueurs rencontrés | Toujours |
| Voir invitations | Accepter/Refuser | Toujours |

## Variables PHP

```php
$stats         // PlayerDuoStat
$division      // Division actuelle
$rankings      // Top 10 de la division
$duoFullUnlocked // bool (niveau ≥ 11)
$choixNiveau   // Niveau Solo actuel
$activeLobbyCode // Code salon actif
$activeLobby   // Données salon actif
```

---

# 3. PAGE 2 : MATCHMAKING

**Fichier :** `resources/views/duo_matchmaking.blade.php`

## Layout

```
┌─────────────────────────────────────────────┐
│                                             │
│            ⟳ (spinner animé)                │
│                                             │
│         RECHERCHE D'ADVERSAIRE              │
│            Division Bronze                  │
│                                             │
├─────────────────────────────────────────────┤
│ (Quand trouvé:)                             │
│                                             │
│   [👤 Vous]      VS      [👤 Adversaire]    │
│   Niveau X               Niveau Y           │
│                                             │
│   Mode: Best of 3                           │
│   Questions: 10/20/30/40/50 (configurable)  │
│   Thème: Culture Générale                   │
│                                             │
├─────────────────────────────────────────────┤
│              [Annuler]                      │
└─────────────────────────────────────────────┘
```

## Fonctionnalités

| Fonction | Description |
|----------|-------------|
| Animation recherche | Spinner + texte division |
| Affichage VS | Avatars + niveaux côte à côte |
| Infos match | Mode, questions, thème |
| Annulation | Retour au lobby |

## Variables PHP

```php
$division      // Division sélectionnée
$player_level  // Niveau du joueur
```

---

# 4. PAGE 3 : QUESTION (BUZZ)

**Fichier :** `resources/views/duo_question.blade.php`

## Layout 3 Colonnes

```
┌─────────────────────────────────────────────────────────┐
│ ⚡ Connexion              Question 1/N                   │
├─────────────────────────────────────────────────────────┤
│              🧠 Culture générale                         │
│    Quel est le plus grand océan du monde ?              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ┌─────────┐      ┌──────────────┐      ┌─────────┐     │
│ │  MOI    │      │              │      │ADVERSAIRE│    │
│ │  👤     │      │     ⏱️        │      │   👤     │    │
│ │ Pseudo  │      │      8       │      │  Pseudo  │    │
│ │ 0 pts   │      │              │      │  0 pts   │    │
│ └─────────┘      └──────────────┘      └─────────┘     │
│                                                         │
│ [Skills]                              [Skills adv.]     │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│               ┌─────────────────┐                       │
│               │   🔴 BUZZ!      │                       │
│               │  (Espace/Clic)  │                       │
│               └─────────────────┘                       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

## Composants

| Zone | Contenu |
|------|---------|
| Header | Connexion status, Question X/N (N configurable) |
| Thème | Emoji + nom thème |
| Question | Texte de la question |
| Colonne gauche | Avatar joueur, pseudo, score (cyan) |
| Colonne centre | Chronomètre 8s (220px cercle) |
| Colonne droite | Avatar adversaire, pseudo, score (rouge) |
| Footer | Buzzer géant |

## États du Buzzer

| État | Couleur | Comportement |
|------|---------|--------------|
| ready | 🔴 Rouge animé | Actif, cliquable |
| pressed | ✅ Vert pulsant | Joueur a buzzé |
| disabled | Grisé | Adversaire a buzzé / timeout |

## Chronomètre

- **Durée :** 8 secondes
- **Animation :** pulse-glow
- **Sync :** Socket.IO `phaseEndsAt`

## Variables PHP

```php
$match_id           // ID du match
$room_id            // ID de la room Socket.IO
$lobby_code         // Code du lobby
$jwt_token          // Token JWT pour Socket.IO
$skills             // Skills du joueur
$strategic_avatar   // Avatar stratégique équipé
$playerAvatarPath   // Chemin avatar joueur
$opponentAvatarPath // Chemin avatar adversaire
$opponentName       // Pseudo adversaire
$playerScore        // Score joueur
$opponentScore      // Score adversaire
$totalQuestions     // 10, 20, 30, 40 ou 50 (configurable)
$currentQuestion    // 1 à $totalQuestions
$theme              // Thème actuel
$themeDisplay       // Thème avec emoji
```

## Événements Socket.IO

| Direction | Événement | Description |
|-----------|-----------|-------------|
| IN | phase_changed | Mise à jour phase |
| IN | question_ready | Question chargée |
| IN | buzz_registered | Qui a buzzé |
| IN | timer_sync | Sync chrono |
| OUT | buzz | Enregistrer buzz |
| OUT | ready | Joueur prêt |

---

# 5. PAGE 4 : ANSWER (RÉPONSE)

**Fichier :** `resources/views/duo_answer.blade.php`

## Layout - VUE BUZZ WINNER

```
┌─────────────────────────────────────────────────────────┐
│ ⚡ Connexion        Question 1/N • À vous de répondre    │
├─────────────────────────────────────────────────────────┤
│              🧠 Culture générale                         │
│    Quel est le plus grand océan du monde ?              │
├─────────────────────────────────────────────────────────┤
│                      ⏱️ 10                               │
├─────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────────────────┐ │
│ │ A. Océan Atlantique                                 │ │
│ └─────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ B. Océan Pacifique                                  │ │
│ └─────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ C. Océan Indien                                     │ │
│ └─────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ D. Océan Arctique                                   │ │
│ └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

## Layout - VUE NON-WINNER

```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│                  ⏳ EN ATTENTE...                        │
│                                                         │
│       [Pseudo adversaire] répond à la question...       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

## Chronomètre

- **Durée :** 10 secondes
- **Visible par :** Buzz winner uniquement

## Variables PHP

```php
$match_id           // ID du match
$room_id            // ID room Socket.IO
$lobby_code         // Code lobby
$jwt_token          // Token JWT
$isBuzzWinner       // bool - Ce joueur a-t-il buzzé?
$question           // Données question (si dispo)
$answers            // 4 réponses possibles
$opponentName       // Pseudo adversaire
```

## Événements Socket.IO

| Direction | Événement | Description |
|-----------|-----------|-------------|
| IN | answer_result | Résultat de la réponse |
| IN | phase_changed | Changement de phase |
| OUT | answer | Soumettre réponse |

---

# 6. PAGE 5 : RESULT (RÉSULTAT)

**Fichier :** `resources/views/duo_result.blade.php`

## Layout

```
┌─────────────────────────────────────────────────────────┐
│               MANCHE 1 • Question 1/N                    │
├─────────────────────────────────────────────────────────┤
│                   ✅ CORRECT!                            │
│                    +2 pts                                │
├─────────────────────────────────────────────────────────┤
│ ┌─────────┐          VS          ┌─────────┐           │
│ │  👤     │                      │  👤     │           │
│ │ 2 pts   │                      │ 0 pts   │           │
│ │ (cyan)  │                      │ (rouge) │           │
│ └─────────┘                      └─────────┘           │
├─────────────────────────────────────────────────────────┤
│ ✅ La bonne réponse était: Océan Pacifique              │
├─────────────────────────────────────────────────────────┤
│ 💡 Le saviez-vous?                                       │
│ Le Pacifique couvre 46% de la surface océanique...      │
├─────────────────────────────────────────────────────────┤
│ 🎯 SKILLS DISPONIBLES                                    │
│ [💡 Skill1] [🧪 Skill2] [👁️ Skill3]                     │
├─────────────────────────────────────────────────────────┤
│                [🔊] [💬]                                 │
├─────────────────────────────────────────────────────────┤
│                   [ GO → ]                               │
└─────────────────────────────────────────────────────────┘
```

## Composants

| Zone | Contenu |
|------|---------|
| Header | Manche X, Question Y/N (N configurable) |
| Résultat | ✅ CORRECT / ❌ FAUX + points |
| Score battle | Avatars + scores côte à côte |
| Bonne réponse | Toujours affichée |
| Le saviez-vous | Anecdote IA (Gemini) |
| Skills | Grille compétences disponibles |
| Communication | 🔊 Micro + 💬 Texto |
| Action | Bouton GO → |

## Couleurs

| Élément | Correct | Incorrect |
|---------|---------|-----------|
| Bordure | rgba(78, 205, 196, 0.5) | rgba(255, 107, 107, 0.5) |
| Fond | rgba(78, 205, 196, 0.1) | rgba(255, 107, 107, 0.1) |
| Texte | #4ECDC4 | #FF6B6B |

## Variables PHP

```php
$wasCorrect        // bool
$pointsEarned      // +2, +1, 0, -2
$correctAnswer     // Texte bonne réponse
$didYouKnow        // Anecdote IA
$playerScore       // Score joueur
$opponentScore     // Score adversaire
$skills            // Skills disponibles
$currentQuestion   // Question actuelle
$totalQuestions    // 10, 20, 30, 40 ou 50 (configurable)
```

---

# 7. PAGE 6 : WAITING (ATTENTE)

**Fichier :** `resources/views/duo_waiting.blade.php`

## Layout

```
┌─────────────────────────────────────────────────────────┐
│               Adversaire: [Pseudo]                       │
├─────────────────────────────────────────────────────────┤
│ ┌─────────┐          VS          ┌─────────┐           │
│ │  MOI    │                      │ADVERSAIRE│           │
│ │ 2 pts   │                      │  0 pts   │           │
│ └─────────┘                      └─────────┘           │
├─────────────────────────────────────────────────────────┤
│ 🎯 SKILLS DE [Avatar]                                    │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 💡 Illumine si chiffre         ∞ utilisations       │ │
│ │ Met en évidence si réponse contient un chiffre      │ │
│ └─────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 🧪 Acidifie erreur             0/1 utilisé          │ │
│ │ Marque visuellement une mauvaise réponse            │ │
│ └─────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────┤
│ 📊 STATS DU MATCH                                        │
│ Score: 2 | Vies: ❤️❤️❤️ | Bonnes: 1 | Erreurs: 0        │
├─────────────────────────────────────────────────────────┤
│                [🔊] [💬]                                 │
├─────────────────────────────────────────────────────────┤
│           En attente de l'adversaire...                  │
│ OU        [ GO → Question suivante ]                     │
└─────────────────────────────────────────────────────────┘
```

## Composants

| Zone | Contenu |
|------|---------|
| Header | Nom adversaire |
| Score battle | Avatars + scores |
| Skills | Liste détaillée avec utilisations |
| Stats | Score, vies, bonnes, erreurs |
| Communication | 🔊 Micro + 💬 Texto |
| Action | Attente sync OU bouton GO |

## Variables PHP

```php
$params['match_id']        // ID match
$params['room_code']       // Code room
$params['current_question'] // Question actuelle
$params['total_questions']  // 10, 20, 30, 40 ou 50
$params['player_info']     // {name, score}
$params['opponent_info']   // {name, score}
$params['last_answer']     // Dernière réponse
$params['correct_answer']  // Bonne réponse
$params['was_correct']     // bool
$params['did_you_know']    // Anecdote
$params['skills']          // Skills array
$params['avatar_name']     // Nom avatar
$params['stats']           // Stats du match
```

---

# 8. PAGE 7 : RANKINGS (CLASSEMENT)

**Fichier :** `resources/views/duo_rankings.blade.php`

## Layout

```
┌─────────────────────────────────────────────────────────┐
│ [← Retour]           CLASSEMENT DUO                      │
├─────────────────────────────────────────────────────────┤
│ [🥉 Bronze] [🥈 Argent] [🥇 Or] [💎 Platine] ...        │
├─────────────────────────────────────────────────────────┤
│ #1  👤 Player1    15V-3D  (83%)     450 pts             │
│ #2  👤 Player2    12V-4D  (75%)     380 pts             │
│ #3  👤 Player3    10V-5D  (67%)     320 pts             │
│ ...                                                      │
│ #15 👤 [MOI]       5V-5D  (50%)     150 pts  ← surligné │
│ ...                                                      │
└─────────────────────────────────────────────────────────┘
```

## Fonctionnalités

| Fonction | Description |
|----------|-------------|
| Onglets divisions | Filtrer par division |
| Classement complet | Tous les joueurs |
| Position joueur | Surligné si présent |
| Stats affichées | V-D, %, points |

---

# 9. AVATARS STRATÉGIQUES & SKILLS

## Tiers et Prix

| Tier | Prix | Nombre |
|------|------|--------|
| Rare 🎯 | 500 💰 | 4 avatars |
| Épique ⭐ | 1000 💰 | 4 avatars |
| Légendaire 👑 | 1500 💰 | 4 avatars |

## Catalogue Complet (12 avatars)

### RARE (1 skill chacun)

| Avatar | Skill | Icône | Effet | Auto |
|--------|-------|-------|-------|------|
| Mathématicien | Illumine si chiffre | 💡 | Surligne bonne réponse si contient chiffre | ✅ |
| Scientifique | Acidifie erreur | 🧪 | Marque une mauvaise réponse (1x) | ❌ |
| Explorateur | Voit choix adverse | 👁️ | Voir réponse choisie par adversaire | ✅ |
| Défenseur | Annule attaque | 🛡️ | Bloque prochaine attaque (1x) | ✅ |

### ÉPIQUE (2 skills chacun)

| Avatar | Skill 1 | Skill 2 |
|--------|---------|---------|
| Comédienne | 🎭 Score - (faux score) | 🔄 Trompe réponse (inverse) |
| Magicienne | ❓ Question bonus (1x) | ✨ Annule erreur (1x) |
| Challenger | 🔀 Mélange réponses | ⏱️ Diminue temps |
| Historien | 📜 Indice texte | ⏳ +2s réponse |

### LÉGENDAIRE (3 skills chacun)

| Avatar | Skill 1 | Skill 2 | Skill 3 |
|--------|---------|---------|---------|
| IA Junior | 🤖 Suggestion IA (80%) | ❌ Élimine 2 | 🔁 Rejouer (1x) |
| Stratège | 💰 +20% pièces | 👥 Créer team | 🏷️ -10% coût |
| Sprinteur | ⚡ Buzzer rapide | ⏰ +3s réflexion | 🔄 Auto-réactivation |
| Visionnaire | 👁️ 5 Q° futures | 🛡️ Contre Challenger | 🔒 2 pts sécurisés |

## Types de Skills

| Type | Description |
|------|-------------|
| personal | Affecte le joueur uniquement |
| attack | Affecte l'adversaire |
| defense | Protège contre attaques |

## Triggers

| Trigger | Moment d'activation |
|---------|---------------------|
| on_question | Début de question |
| on_answer | Phase réponse |
| on_result | Affichage résultat |
| on_error | Après erreur |
| on_victory | Victoire du match |
| match_start | Début de match |
| always | Toujours actif |

---

# 10. SYSTÈME DE POINTS & DIVISIONS

## Attribution des Points (par question)

| Situation | Points | Condition |
|-----------|--------|-----------|
| Correct rapide | +2 | > 3 secondes restantes |
| Correct moyen | +1 | 1-3 secondes restantes |
| Correct lent | 0 | < 1 seconde restante |
| Incorrect | -2 | Mauvaise réponse |
| Timeout | 0 | Aucune réponse |

## Divisions

| Division | Emoji | Récompense victoire | Frais accès (+) |
|----------|-------|---------------------|-----------------|
| Bronze | 🥉 | 10 💰 | - |
| Argent | 🥈 | 15 💰 | 30 💰 |
| Or | 🥇 | 25 💰 | 50 💰 |
| Platine | 💎 | 50 💰 | 100 💰 |
| Diamant | 💠 | 100 💰 | 200 💰 |
| Légende | 👑 | 250 💰 | 500 💰 |

## Règle d'Accès

- Peut jouer jusqu'à **2 divisions au-dessus** de la sienne
- Frais d'accès = 2x récompense de la division

---

# 11. COMMUNICATION VOCALE & TEXTO

## Disponibilité par Page

| Page | Audio actif | Boutons visibles |
|------|-------------|------------------|
| duo_question.blade.php | ✅ | ❌ |
| duo_answer.blade.php | ✅ | ❌ |
| duo_result.blade.php | ✅ | ✅ |
| duo_waiting.blade.php | ✅ | ✅ |

## Boutons UI

| Bouton | État actif | État muté |
|--------|------------|-----------|
| 🔊 | Vert (#4CAF50) | - |
| 🔇 | - | Rouge (#FF6B6B) |
| 💬 | - | Ouvre chat texto |

## Technologie VoiceChat

| Composant | Technologie |
|-----------|-------------|
| Communication | WebRTC peer-to-peer |
| Signaling | Firebase Firestore |
| STUN | Google STUN servers |
| TURN | OpenRelay TURN servers |
| Max participants | 5 (League Team) |

## Fichier : `public/js/VoiceChat.js`

```javascript
class VoiceChat {
    sessionId       // ID session Firebase
    localUserId     // ID utilisateur local
    remoteUserIds   // IDs utilisateurs distants
    isMuted         // État micro (défaut: true)
    isConnected     // État connexion
    peerConnections // Map des connexions WebRTC
}
```

---

# 12. ARCHITECTURE TECHNIQUE

## Stack

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Frontend   │ ←→  │   Laravel   │ ←→  │ Game Server │
│  (Blade)    │     │  (Backend)  │     │ (Socket.IO) │
└─────────────┘     └─────────────┘     └─────────────┘
                          ↓                    ↓
                    ┌───────────┐        ┌───────────┐
                    │PostgreSQL │        │   Redis   │
                    │ (données) │        │  (état)   │
                    └───────────┘        └───────────┘
```

## Services Backend

| Service | Fichier | Rôle |
|---------|---------|------|
| DuoController | app/Http/Controllers/DuoController.php | Routes et vues |
| DuoMatchmakingService | app/Services/DuoMatchmakingService.php | File d'attente |
| GameServerService | app/Services/GameServerService.php | JWT, rooms |
| SkillCatalog | app/Services/SkillCatalog.php | Catalogue skills |
| AvatarCatalog | app/Services/AvatarCatalog.php | Catalogue avatars |

## Services Frontend

| Service | Fichier | Rôle |
|---------|---------|------|
| DuoSocketClient | public/js/DuoSocketClient.js | Client Socket.IO |
| VoiceChat | public/js/VoiceChat.js | WebRTC voice |

## Phases Socket.IO (TypeScript)

```typescript
type Phase =
  | "INTRO"
  | "BUZZ_WINDOW"
  | "QUESTION_DISPLAY"
  | "ANSWER_SELECTION"
  | "REVEAL"
  | "ROUND_SCOREBOARD"
  | "TIEBREAKER_CHOICE"
  | "TIEBREAKER_QUESTION"
  | "MATCH_END";
```

## Timings

| Phase | Durée |
|-------|-------|
| INTRO | ~2s |
| BUZZ_WINDOW | 8s |
| ANSWER_SELECTION | 10s |
| REVEAL | ~3s |
| ROUND_SCOREBOARD | Variable |

## Flux de Phase Typique

```
INTRO → BUZZ_WINDOW → ANSWER_SELECTION → REVEAL → ROUND_SCOREBOARD
   ↑                                                        │
   └──────────────── (Question suivante) ←──────────────────┘
```

---

# ANNEXE : LISTE DES FICHIERS

| Fichier | Type | Description |
|---------|------|-------------|
| duo_lobby.blade.php | Vue | Lobby principal |
| duo_matchmaking.blade.php | Vue | Recherche adversaire |
| duo_question.blade.php | Vue | Page buzz (8s) |
| duo_answer.blade.php | Vue | Page réponse (10s) |
| duo_result.blade.php | Vue | Page résultat |
| duo_waiting.blade.php | Vue | Salle d'attente |
| duo_rankings.blade.php | Vue | Classement |
| duo_splash.blade.php | Vue | Splash screen |
| duo_resume.blade.php | Vue | Reprise match |
| duo_game.blade.php | Vue | (Legacy) |
| DuoController.php | Contrôleur | Logique métier |
| DuoSocketClient.js | JS | Client Socket.IO |
| VoiceChat.js | JS | WebRTC voice |
| SkillCatalog.php | Service | Catalogue skills |
| AvatarCatalog.php | Service | Catalogue avatars |

---

*Document généré le 13 janvier 2026*
*StrategyBuzzer - Mode Duo v2.0*
