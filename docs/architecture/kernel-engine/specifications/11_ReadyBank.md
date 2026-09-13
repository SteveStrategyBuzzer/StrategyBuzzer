# STRATEGYBUZZER — 11_READYBANK

**Version :** 0.3
**Date :** 2026-09-13
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décision directrice :** DEC-125 — OFFICIAL (clauses compatibles de DEC-122)
**Implémentation :** À AUDITER  
**Validation terminale :** NON

> **Remplace :** v0.2 sur la fusion des copies, le signal de cycle et le
> routage. Les clauses contraires sont `SUPERSEDED BY DEC-125` avant leur
> remplacement; DEC-122 reste actif pour les clauses compatibles.

## 0. Règles actives DEC-125

ReadyBank reçoit les copies complètes et le Blueprint canonique. Une copie
prête contient les sept slots; les slots rouges (`SUSPICION`/`EMPTY`) et jaunes
(remplis/modifiés manuellement jusqu’à ReadyBank) sont des marqueurs de
parcours, tandis que le vert est conforme et jamais encore modifié
manuellement. Tous les slots restent éditables dans la copie; modifier un vert
le rend jaune et invalide immédiatement son ancien `PASS`. Les slots conformes
continuent et les slots échoués restent vides.

ReadyBank fusionne uniquement les slots réussis, par
`blueprint_id + cognitive_type`, sans remplacement global du canonique. Les
non résolus restent vides. La copie peut revenir plusieurs fois avec ses
findings les plus récents; aucune histoire permanente des corrections n’est
conservée; plusieurs copies prêtes sont permises.

Un renvoi enqueue sans démarrer. Les clics sont traités dans leur ordre exact
(FIFO), un à la fois. Chaque arrivée émet `CURRENT_KERNEL_RECEIVED` et choisit
une direction exclusive : si la file Quarantine prête n’est pas vide, `GO`
vers sa première copie et redémarrage Phase1, sans KBP; sinon `GO` vers KBP.
Jamais les deux. KBP n’est pas coordinateur de circulation, ne reçoit aucun
état Quarantine et ne crée/ne retrouve un Blueprint que si `GO` lui est
destiné. `blueprint_id` reste la clé du parcours normal et des retours.

**OPEN IMPLEMENTATION REQUIREMENTS — solutions non approuvées :**

1. persistance de la copie complète courante;
2. file d’attente des clics Renvoie;
3. ordre exact des demandes;
4. détection des slots modifiés;
5. conservation du marqueur jaune jusqu’à ReadyBank;
6. invalidation immédiate d’un ancien PASS après modification;
7. protection contre les retours périmés;
8. idempotence de `CURRENT_KERNEL_RECEIVED`;
9. fusion atomique dans ReadyBank.

---

# 1. Mission verrouillée

ReadyBank reçoit uniquement `blueprint_id`. Il relit alors le même agrégat
canonique persistant et y vérifie que la phase précédente est terminée avec le
statut terminal requis. Il devient le point unique
où une copie complète corrigée issue de Quarantine peut réconcilier cet
agrégat.

ReadyBank :

- conserve l’identité canonique;
- contrôle l’exploitabilité des CognitiveSlots et traductions;
- réconcilie une copie corrigée avec le bon Blueprint;
- expose au gameplay uniquement les contenus admissibles;
- ne recrée aucun contenu intellectuel.

# 2. Arrivée du canonique

> **CLAUSE v0.2 CI-DESSOUS — SUPERSEDED BY DEC-125 :** la réception d’un
> canonique avec slot suspect ou vide ne conserve pas cette position
> exploitable; sa copie complète porte le contenu rejeté et les findings.

## 2.1 Contrat de circulation et de propriété

`KernelBlueprint` est une structure persistante extérieure aux phases. Elle est
créée une seule fois par `KernelBlueprintFactory` (KBP), avec un unique
`blueprint_id`, puis progressivement remplie. Elle est la source de vérité
unique : aucune copie autoritaire et aucun objet `Blueprint` ne transitent entre
phases.

La seule valeur transmise entre phases est :

```text
blueprint_id
```

Chaque phase retrouve le `KernelBlueprint` persistant à partir de cet identifiant,
lit et écrit exclusivement les données relevant de son ownership, persiste sa
transaction, puis transmet le même `blueprint_id`. ReadyBank applique exactement ce contrat : il
ne reçoit, ne conserve ni ne transmet un objet canonique en mémoire. À son
déclenchement, il relit le même `KernelBlueprint` persistant, y vérifie l’état
terminal précédent,
et évalue l’admissibilité de ses slots.

Le canonique peut, à l’arrivée de ReadyBank, contenir :

- des slots valides;
- des slots soupçonnés;
- des slots vides parce qu’une création dépendante a été bloquée;
- des validations ou traductions encore attendues par une copie Quarantine.

ReadyBank ne « conserve » donc pas une nouvelle instance complète : il opère sur
l’unique agrégat canonique retrouvé par `blueprint_id`.

Un slot soupçonné, vide, bloqué ou non validé n’est jamais exploitable par le gameplay.

L’arrivée du canonique ne supprime pas et n’invalide pas la copie Quarantine correspondante.

# 3. Arrivée de la copie corrigée

> **CLAUSE v0.2 CI-DESSOUS — SUPERSEDED BY DEC-125 :** la copie complète est
> éditable sur ses sept slots selon les couleurs actives; elle ne se limite pas
> aux seuls chemins suspects.

La copie Quarantine est non canonique. Elle ne constitue pas un transport du
`KernelBlueprint` entre phases et ne peut jamais devenir sa source de vérité.
Elle est une pièce de reprise ciblée, persistée séparément, que ReadyBank
identifie puis confronte à l’agrégat canonique relu par `blueprint_id`.

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

> **CLAUSE v0.2 CI-DESSOUS — SUPERSEDED BY DEC-125 :** ReadyBank ne remplace
> pas globalement le canonique, mais la fusion réussie est définie par
> `blueprint_id + cognitive_type`; les positions non résolues restent vides.

Après avoir relu le canonique, ReadyBank fusionne les seules corrections
admissibles de la copie avec ce même agrégat. La garantie d’atomicité de cette
fusion reste une exigence d’implantation ouverte; aucune solution technique
n’est approuvée par cette clause.

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

## 4.1 Frontière de persistance canonique

ReadyBank manipule le seul agrégat canonique `KernelBlueprint`, toujours relu
par `blueprint_id`.
`kernel_blueprint_runs` conserve sa Section 1 immuable et
`kernel_blueprint_cognitive_slots` conserve séparément ses sept slots.

Chaque slot est identifié par `(blueprint_id, cognitive_type)` et une
contrainte unique garantit une seule occurrence de chaque type par Blueprint.
Les écritures et réconciliations sont atomiques par slot; aucune opération ne
réécrit un frame global.

`question_intents.frame_en` est legacy et non autoritaire. ReadyBank ne
l’utilise pas comme source de vérité canonique. Les traductions restent
imbriquées dans leur slot, tandis que le masque joueur, le mélange des choix
et les autres données joueur restent externes au Blueprint.

## 4.2 Admissibilité et signal terminal

Un slot n’est admissible qu’après lecture de son état persistant et vérification
de toutes les conditions requises par son type : contenu source complet,
validation requise obtenue et traduction demandée elle-même admissible. Un slot
vide, bloqué, soupçonné, non validé ou dont la traduction est absente/non validée
reste physiquement présent mais exclu du gameplay.

ReadyBank persiste le résultat de son contrôle ou de sa réconciliation dans le
canonique, puis émet son signal terminal. La frontière suivante transmet
uniquement `blueprint_id`; son destinataire relira le même agrégat et y
vérifiera la fin de ReadyBank ainsi que son statut terminal. Aucune phase ne
transporte une version sérialisée, mutée ou complète du canonique.

## 4.3 Échecs de Phase1

Une erreur technique de Phase1 (création, fournisseur, persistance ou
infrastructure empêchant la création) termine en `CREATION_FAILED`. Elle ne
produit pas un contenu intellectuel à valider.

Un contenu intellectuel produit et techniquement persistable, mais douteux ou
incomplet au regard des règles métier, passe par `ValidationPhase1`, puis prend
le statut `SUSPICION` si cette validation le conclut. `CREATION_FAILED` et
`SUSPICION` sont donc des branches distinctes et ne doivent jamais être
confondues par ReadyBank ou par leurs relais.

## 4.4 Modes et scénarios de test externes

Les modes production et test sont entièrement externes au `KernelBlueprint`.
Aucun champ de mode, de target ou de test n’est stocké dans le Blueprint ni
déduit de son contenu.

Un scénario de test reste entièrement extérieur au Blueprint et à KBP. Il
demande à KBP un vrai `KernelBlueprint` PostgreSQL isolé avec ses sept slots
structurels vides; KBP retourne uniquement `blueprint_id`. La phase demandée
recharge ce Blueprint et les phases précédentes établissent leurs propres
préconditions selon leurs contrats, sans préparation intellectuelle par KBP.

Le scénario définit uniquement la première phase, les transmissions successives
de `blueprint_id` permises et le point d’arrêt. L’appelant observe ensuite le
résultat persistant et demande à KBP le nettoyage avec la référence technique
de création. Il ne constitue ni un Harness architectural ni une variante
métier de Phase1.

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

> **CLAUSE v0.2 CI-DESSOUS — SUPERSEDED BY DEC-125 :** `CURRENT_KERNEL_RECEIVED`
> ne va pas toujours à KRP/KBP. ReadyBank choisit la direction exclusive
> selon la file Quarantine prête.

ReadyBank peut produire les faits définis par son contrat lifecycle vers KRP, mais :

- la fusion Quarantine ne recrée jamais le Blueprint;
- elle ne relance jamais KRP sur ce Blueprint;
- elle ne modifie jamais Depth ou Domain;
- elle ne réinitialise jamais `VVVV`;
- elle ne compte jamais une copie comme un nouveau noyau canonique.

## 8.1 Routage exclusif DEC-125

À chaque arrivée terminale de ReadyBank, le signal
`CURRENT_KERNEL_RECEIVED` est idempotent et ne démarre qu’une direction :

```text
file Quarantine prête non vide
→ GO vers la première copie (FIFO)
→ reprise Phase1
→ KBP ne reçoit rien

file Quarantine prête vide
→ GO vers KBP
→ KBP crée ou retrouve le Blueprint demandé
```

Le renvoi précédent a seulement mis la copie en file; il ne l’a pas démarrée.
L’ordre exact des clics est conservé et un seul traitement est actif à la fois.
ReadyBank n’effectue aucun remplacement global : seuls les slots réussis
portant le même `blueprint_id` et le même `cognitive_type` sont fusionnés.

# 9. Invariants verrouillés

- un seul canonique;
- copie corrigée complète;
- fusion uniquement dans ReadyBank;
- identité identique obligatoire;
- fusion sélective des seuls slots conformes;
- un slot vert modifié manuellement n’est plus un slot valide hors cible;
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
