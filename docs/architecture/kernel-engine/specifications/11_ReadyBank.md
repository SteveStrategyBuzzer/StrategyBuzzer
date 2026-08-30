# STRATEGYBUZZER — 11_READYBANK

**Version :** 0.2  
**Date :** 29 août 2026  
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission verrouillée

ReadyBank reçoit le Blueprint canonique après son parcours normal et devient le point unique où une copie complète corrigée issue de Quarantine peut rejoindre ce canonique.

ReadyBank :

- conserve l’identité canonique;
- contrôle l’exploitabilité des CognitiveSlots et traductions;
- réconcilie une copie corrigée avec le bon Blueprint;
- expose au gameplay uniquement les contenus admissibles;
- ne recrée aucun contenu intellectuel.

# 2. Arrivée du canonique

Le Blueprint canonique poursuit toutes les phases jusqu’à ReadyBank.

Il peut arriver avec :

- des slots valides;
- des slots soupçonnés;
- des slots vides parce qu’une création dépendante a été bloquée;
- des validations ou traductions encore attendues par une copie Quarantine.

ReadyBank conserve le Blueprint complet.

Un slot soupçonné, vide, bloqué ou non validé n’est jamais exploitable par le gameplay.

L’arrivée du canonique ne supprime pas et n’invalide pas la copie Quarantine correspondante.

# 3. Arrivée de la copie corrigée

La copie complète corrigée doit correspondre exactement au canonique par :

```text
blueprint_id
+ kernel_code
```

Elle transporte également :

- les chemins soupçonnés;
- les valeurs avant correction;
- les valeurs corrigées;
- les slots précédemment vides maintenant remplis;
- les validations obtenues;
- la traçabilité de sa reprise ciblée.

Une copie ne correspondant pas à la même identité est refusée.

# 4. Réconciliation contrôlée

ReadyBank fusionne atomiquement la copie corrigée avec le canonique.

Opérations autorisées :

1. **REMPLACER** un slot explicitement soupçonné par sa version corrigée et validée;
2. **CORRIGER** un ou plusieurs champs ciblés d’un slot;
3. **REMPLIR / IMPRIMER** dans le canonique un slot resté vide et désormais créé/validé;
4. conserver tous les slots valides non ciblés;
5. conserver l’identité et la traçabilité du Blueprint.

Interdictions :

- aucun remplacement global aveugle du Blueprint;
- aucune modification de `blueprint_id`;
- aucune modification de `kernel_code`;
- aucun écrasement d’un slot valide hors cible;
- aucune fusion sans validation requise;
- aucune création métier directement dans ReadyBank.

Après fusion, le résultat demeure le Blueprint canonique original, complété ou corrigé.

La copie ne devient jamais un deuxième canonique.

# 5. Exploitabilité gameplay

Le gameplay peut lire uniquement :

- un CognitiveSlot source admissible;
- sa traduction admissible dans la langue demandée;
- une question, réponse, choix et SV validés;
- une identité canonique complète.

Un Blueprint présent physiquement dans ReadyBank peut contenir des parties non exploitables. La sélection gameplay doit exclure ces parties jusqu’à leur réconciliation réussie.

## 5.1 Correspondance canonique des choix QCM

Pour les trois cognitifs QCM du Blueprint :

```text
QCM_RECOGNITION
QCM_REASONING
QCM_TRAP
```

la persistance conserve toujours :

```text
choices.a = bonne réponse
choices.b = distracteur 1
choices.c = distracteur 2
choices.d = distracteur 3
correct_answer_key = a
```

Cette correspondance est conservée lors de la livraison au gameplay, des
copies Quarantine, des corrections et de la réconciliation ReadyBank.

Au moment de présenter une question, le gameplay peut mélanger l’ordre des
quatre choix. Il conserve pour chaque choix affiché sa clé canonique et
enregistre l’identité canonique du choix joué, jamais la seule lettre affichée.
Le mélange n’est jamais persisté et ne modifie jamais le Blueprint.

Cette règle ne change pas la polarité Vrai/Faux :

```text
type *_TRUE  → réponse canonique a = VRAI
type *_FALSE → réponse canonique b = FAUX
```

# 6. Vue de vérification du Blueprint

ReadyBank doit permettre une projection administrative complète montrant :

- identité intellectuelle;
- `blueprint_id`;
- `kernel_code`;
- sept CognitiveSlots;
- question, réponse, choix et SV source;
- traductions question/réponse/choix/SV par langue;
- slots soupçonnés;
- slots vides ou bloqués;
- validations;
- corrections Quarantine;
- résultat de fusion.

Les champs soupçonnés peuvent être affichés en rouge à partir des chemins persistés.

# 7. Historique cognitif durable du joueur

L’état joueur ne fait pas partie du `kernel_code` canonique et ne modifie jamais le Blueprint partagé.

Le masque seul ne suffit pas à identifier quel cognitif exact a été joué. L’historique durable conserve donc au minimum :

```text
player_id
+ identité conceptuelle DD-DO-SUB-SUJ-IDE
+ cognitive_type exact
+ played_at
+ game_id si disponible
```

L’identité conceptuelle est utilisée afin qu’un changement de `VVVV` ne remette jamais l’historique à zéro.

La persistance doit empêcher une deuxième consommation logique du même cognitif par le même joueur et la même identité conceptuelle :

```text
UNIQUE (
  player_id,
  conceptual_identity,
  cognitive_type
)
```

Le `kernel_code` physique effectivement joué demeure conservable pour la traçabilité, mais il ne remplace pas l’identité conceptuelle utilisée par la règle de non-répétition.

## 7.1 Types cognitifs suivis

```text
QCM_RECOGNITION
QCM_REASONING
QCM_TRAP
TRUE_FALSE_RECOGNITION_TRUE
TRUE_FALSE_RECOGNITION_FALSE
TRUE_FALSE_REASONING_TRUE
TRUE_FALSE_REASONING_FALSE
```

L’historique détaillé permet de savoir précisément lequel de ces sept types a été joué.

Aucun `question_code`, segment `COG` ou segment `VAR` n’est nécessaire pour cette identification.

## 7.2 Masque dérivé à trois familles

Le masque est une projection rapide calculée depuis l’historique détaillé. Il n’est pas la source de vérité.

Format visuel :

```text
[QR][RF][QTR]
```

Premier caractère — famille `QCM_RECOGNITION / QCM_REASONING` :

```text
0 = aucun utilisé
1 = l’un des deux a été utilisé
```

Deuxième caractère — famille `TRUE_FALSE_RECOGNITION_TRUE / TRUE_FALSE_RECOGNITION_FALSE / TRUE_FALSE_REASONING_TRUE / TRUE_FALSE_REASONING_FALSE` :

```text
0 = aucun utilisé
1 = l’un des quatre a été utilisé
```

Troisième caractère — `QCM_TRAP` :

```text
n = non utilisé
o = utilisé
```

États possibles :

```text
00n = aucun groupe utilisé
10n = groupe QR utilisé
01n = groupe RF utilisé
00o = QCM_TRAP utilisé
11n = QR + RF utilisés
10o = QR + QCM_TRAP utilisés
01o = RF + QCM_TRAP utilisés
11o = trois familles utilisées; identité fermée pour ce joueur
```

Le joueur reçoit au maximum :

- un cognitif parmi `QCM_RECOGNITION/QCM_REASONING`;
- un cognitif parmi les quatre Vrai/Faux;
- `QCM_TRAP` une seule fois.

Maximum total : trois cognitifs, un par famille.

## 7.3 Sélection gameplay

Avant de sélectionner un cognitif, le gameplay lit l’historique détaillé du joueur pour l’identité conceptuelle demandée.

Il doit :

1. exclure toute famille déjà consommée;
2. identifier le `cognitive_type` exact déjà joué;
3. choisir uniquement un slot admissible dans une famille encore disponible;
4. enregistrer atomiquement la consommation réussie;
5. recalculer le masque dérivé;
6. fermer l’identité pour ce joueur lorsque le masque atteint `11o`.

Le masque accélère le filtrage des familles. L’historique détaillé garantit l’exactitude et l’audit.

## 7.4 Projection gameplay

Une interface peut afficher :

```text
kernel_code + masque joueur
06-HIS-TIT-RAP-EVA-0000-00n
```

Cette chaîne est une projection gameplay propre au joueur, jamais le `kernel_code` persistant du Blueprint.

# 8. Frontière avec KRP

ReadyBank peut produire les faits définis par son contrat lifecycle vers KRP, mais :

- la fusion Quarantine ne recrée jamais le Blueprint;
- elle ne relance jamais KRP sur ce Blueprint;
- elle ne modifie jamais Depth ou Domain;
- elle ne réinitialise jamais `VVVV`;
- elle ne compte jamais une copie comme un nouveau noyau canonique.

# 9. Invariants verrouillés

- un seul canonique;
- copie corrigée complète;
- fusion uniquement dans ReadyBank;
- identité identique obligatoire;
- fusion ciblée et atomique;
- slots valides hors cible inchangés;
- slots suspects non exploitables;
- slots vides remplissables après reprise;
- aucune copie comptée comme nouveau noyau;
- état joueur externe au Blueprint;
- historique détaillé par joueur, identité conceptuelle et `cognitive_type`;
- masque dérivé, jamais source de vérité;
- maximum trois familles cognitives;
- aucun reset par `VVVV`;
- aucun `question_code`, `COG` ou `VAR` requis.

# 10. Statut restant

Restent à spécifier :

- schéma SQL persistant exact de l’historique joueur;
- transactions et verrous de fusion;
- états détaillés d’exploitabilité;
- signal lifecycle exact vers KRP;
- conservation/archivage des copies fusionnées;
- interface administrative;
- durée et politique d’archivage de l’historique joueur;
- comportement multijoueur lorsque plusieurs historiques doivent être combinés.

La présente version verrouille les responsabilités de réconciliation, l’identification exacte du cognitif joué et la frontière gameplay sans déclarer ReadyBank terminé.
