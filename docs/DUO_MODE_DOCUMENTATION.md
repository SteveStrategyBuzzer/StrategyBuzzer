# STRATEGYBUZZER - MODE DUO
## Documentation Technique Complète

---

# TABLE DES MATIÈRES

1. Vue d'ensemble et flux de navigation
2. Page 1 : Duo (Menu principal)
3. Page 2 : Lobby Duo (Synchronisation)
4. Page 3 : Matchmaking
5. Page 4 : Question (Buzz)
6. Page 5 : Answer (Réponse)
7. Page 6 : Result (Résultat + Sync)
8. Page 7 : Rankings (Classement)
9. Avatars Stratégiques & Skills
10. Système de Points, Divisions & Pièces
11. Communication Vocale & Texto
12. Architecture Technique

---

# 1. VUE D'ENSEMBLE ET FLUX DE NAVIGATION

## Séquence Principale

```
DUO (Menu) → LOBBY (Sync) → MATCHMAKING → INTRO → [QUESTION → ANSWER → RESULT] xN → FIN
```
*(où N = nombre de questions configuré : 10, 20, 30, 40 ou 50)*

**Phases d'intro :** Fond noir (3s) + "Ladies and Gentlemen" (9s) = 12 secondes total

**Note :** La page Result inclut la synchronisation des joueurs (ancien Waiting fusionné) avec bouton GO

## Branches Possibles

| Départ | Action | Destination |
|--------|--------|-------------|
| Page Duo | Matchmaking aléatoire | duo_matchmaking.blade.php |
| Page Duo | Invitation envoyée | Lobby Duo (synchronisation) |
| Page Duo | Invitation acceptée | Lobby Duo (synchronisation) |
| Lobby Duo | 2 joueurs synchronisés | duo_question.blade.php |

## Boucle de Jeu (Questions configurables)

| Étape | Page | Durée | Contenu |
|-------|------|-------|---------|
| 1 | Intro (fond noir) | 3 secondes | Écran noir |
| 2 | Ladies and Gentlemen | 9 secondes | Animation d'intro |
| 3 | duo_question.blade.php | 8 secondes | Question + Avatars + Buzzer |
| 4 | duo_answer.blade.php | 10 secondes | 4 choix ou Vrai/Faux + Skills |
| 5 | duo_result.blade.php | Variable | Points + Résultat + Skills + "Le saviez-vous" + GO |

## Format de Match

- **Best of 3** manches
- **10, 20, 30, 40 ou 50 questions** par manche (configurable)
- **Tiebreaker** si égalité
- **Pièces de Compétence** gagnées selon performance

---

# 2. PAGE 1 : DUO (MENU PRINCIPAL)

**Fichier :** `resources/views/duo.blade.php`

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
| Voir classement | Accès page Rankings | Toujours |

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

# 3. PAGE 2 : LOBBY DUO (SYNCHRONISATION)

**Fichier :** `resources/views/duo_lobby.blade.php`

## Rôle

Le Lobby Duo est l'endroit où les 2 joueurs se synchronisent avant de démarrer le gameplay. C'est une salle d'attente où les joueurs confirment qu'ils sont prêts.

## Layout

```
┌─────────────────────────────────────────────┐
│                 LOBBY DUO                    │
├─────────────────────────────────────────────┤
│                                             │
│   [👤 Joueur 1]      [👤 Joueur 2]          │
│     ✅ Prêt           ⏳ En attente          │
│                                             │
├─────────────────────────────────────────────┤
│             [🔊] [💬]                        │
│        Communication vocale active          │
├─────────────────────────────────────────────┤
│     Synchronisé: Connexion établie          │
├─────────────────────────────────────────────┤
│              [DÉMARRER]                      │
│     (Actif quand 2 joueurs prêts)           │
└─────────────────────────────────────────────┘
```

## Fonctionnalités

| Fonction | Description |
|----------|-------------|
| Affichage joueurs | Avatars + noms des 2 joueurs |
| Statut synchronisation | ⏳ En attente / ✅ Prêt |
| Communication | Boutons micro et texto visibles |
| Démarrage | Bouton actif quand 2 joueurs synchronisés |

## Variables PHP

```php
$lobby_code    // Code du lobby
$player1       // Données joueur 1
$player2       // Données joueur 2
$isReady       // Statut de synchronisation
```

---

# 4. PAGE 3 : MATCHMAKING

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

# 5. PAGE 4 : QUESTION (BUZZ)

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
| Colonne gauche | Avatar joueur + pseudo + score (cyan) + Emplacement Avatar Stratégique + Skills |
| Colonne centre | Chronomètre 8s (220px cercle) |
| Colonne droite | Avatar adversaire + pseudo + score (rouge) + Emplacement Avatar Stratégique adverse |
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

# 6. PAGE 5 : ANSWER (RÉPONSE)

**Fichier :** `resources/views/duo_answer.blade.php`

## Layout - VUE BUZZ WINNER (4 choix)

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
├─────────────────────────────────────────────────────────┤
│ 🎯 Skills disponibles (si applicable)                   │
└─────────────────────────────────────────────────────────┘
```

## Layout - VUE BUZZ WINNER (Vrai/Faux)

```
┌─────────────────────────────────────────────────────────┐
│ ⚡ Connexion        Question 1/N • À vous de répondre    │
├─────────────────────────────────────────────────────────┤
│    Le soleil tourne autour de la Terre.                 │
├─────────────────────────────────────────────────────────┤
│                      ⏱️ 10                               │
├─────────────────────────────────────────────────────────┤
│ ┌────────────────────┐    ┌────────────────────┐       │
│ │      ✅ VRAI        │    │      ❌ FAUX        │       │
│ └────────────────────┘    └────────────────────┘       │
└─────────────────────────────────────────────────────────┘
```

## Layout - VUE NON-BUZZER

```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│                  ⏳ EN ATTENTE...                        │
│                                                         │
│       [Pseudo adversaire] répond à la question...       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

## Points selon vitesse de buzz

| Buzz | Points (si correct) |
|------|---------------------|
| Plus rapide (> 3s restantes) | +2 pts |
| Moins rapide (1-3s restantes) | +1 pt |
| Lent (< 1s restante) | 0 pt |
| Erreur | -2 pts |

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

# 7. PAGE 6 : RESULT (RÉSULTAT + SYNC)

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
│ │ 2 pts   │    RÉSULTAT EN       │ 0 pts   │           │
│ │ (cyan)  │      COURS           │ (rouge) │           │
│ └─────────┘                      └─────────┘           │
├─────────────────────────────────────────────────────────┤
│ ✅ Bonne réponse: Océan Pacifique                       │
│ ❌ Mauvaise réponse sélectionnée: Océan Atlantique      │
├─────────────────────────────────────────────────────────┤
│ 🎯 SKILLS DISPONIBLES                                    │
│ [💡 Skill1] [🧪 Skill2] [👁️ Skill3]                     │
├─────────────────────────────────────────────────────────┤
│ 💡 Le saviez-vous?                                       │
│ Le Pacifique couvre 46% de la surface océanique...      │
├─────────────────────────────────────────────────────────┤
│ Statut: [Vous ✅] [Adversaire ⏳]                        │
├─────────────────────────────────────────────────────────┤
│        [🔊] [💬]           [ GO → ]    [🚪 Sortie]       │
└─────────────────────────────────────────────────────────┘
```

## Composants

| Zone | Contenu |
|------|---------|
| Header | Manche X, Question Y/N (N configurable) |
| Points obtenus | +2 pts, +1 pt, 0 pt, -2 pts |
| Résultat | ✅ CORRECT / ❌ FAUX (résultat en cours) |
| Score battle | Avatars + scores côte à côte |
| Bonne/Mauvaise réponse | Toujours affichées |
| Skills | Grille compétences disponibles |
| Le saviez-vous | Anecdote IA (Gemini) |
| Statut sync | État prêt des 2 joueurs (⏳/✅) |
| Actions | 🔊 Micro + 💬 Texto + GO (sync 2 joueurs) + 🚪 Sortie |

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

# 8. PAGE 7 : RANKINGS (CLASSEMENT)

**Fichier :** `resources/views/duo_rankings.blade.php`

**Accessible depuis :** Page Duo (Menu principal) via bouton "Voir classement complet"

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

Les avatars stratégiques offrent des compétences (skills) qui peuvent :
- **Altérer ou augmenter les temps de chrono** (ajouter/retirer des secondes)
- **Affecter le comportement des réponses** (déplacer les choix, mélanger)
- **Modifier le pointage** (bonus/malus de points)
- **Autres effets spéciaux** (voir adversaire, bloquer attaques, etc.)

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

# 10. SYSTÈME DE POINTS, DIVISIONS & PIÈCES

## Attribution des Points (par question)

| Situation | Points | Condition |
|-----------|--------|-----------|
| Correct rapide | +2 | > 3 secondes restantes |
| Correct moyen | +1 | 1-3 secondes restantes |
| Correct lent | 0 | < 1 seconde restante |
| Incorrect | -2 | Mauvaise réponse |
| Timeout | 0 | Aucune réponse |

## Pièces de Compétence

Les joueurs gagnent des **Pièces de Compétence** selon leur performance :
- Utilisées pour acheter des avatars stratégiques
- Gagnées selon le résultat du match et la division

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
| duo_lobby.blade.php (Synchronisation) | ✅ | ✅ |
| duo_question.blade.php | ✅ | ❌ |
| duo_answer.blade.php | ✅ | ❌ |
| duo_result.blade.php | ✅ | ✅ |
| Pages fin de manche | ✅ | ✅ |
| Pages fin de partie | ✅ | ✅ |

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
| duo.blade.php | Vue | Page Duo (Menu principal) |
| duo_lobby.blade.php | Vue | Lobby Duo (Synchronisation joueurs) |
| duo_matchmaking.blade.php | Vue | Recherche adversaire |
| duo_question.blade.php | Vue | Page buzz (8s) + Avatars + Skills |
| duo_answer.blade.php | Vue | Page réponse (10s) + 4 choix ou Vrai/Faux |
| duo_result.blade.php | Vue | Page résultat + Skills + Sync joueurs + Le saviez-vous |
| duo_rankings.blade.php | Vue | Classement par division |
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
