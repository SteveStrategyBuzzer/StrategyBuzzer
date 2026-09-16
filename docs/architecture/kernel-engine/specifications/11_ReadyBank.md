# STRATEGYBUZZER — 11_READYBANK

**Version :** 0.3
**Date :** 16 septembre 2026
**Statut :** RÈGLES OFFICIELLES VERROUILLÉES — MODULE À COMPLÉTER  
**Décisions :** DEC-122 + DEC-125 + invariant linguistique DEC-126
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission verrouillée

ReadyBank reçoit ou détecte un relais minimal composé de `blueprint_id`, de la
confirmation que la phase précédente est terminée et de son statut terminal.
Il relit alors le même agrégat canonique persistant. Il devient le point unique
où une copie complète corrigée issue de Quarantine peut réconcilier cet
agrégat.

ReadyBank :

- conserve l’identité canonique;
- contrôle l’exploitabilité des CognitiveSlots et traductions;
- réconcilie une copie corrigée avec le bon Blueprint;
- expose au gameplay uniquement les contenus admissibles;
- ne recrée aucun contenu intellectuel.

ReadyBank ne convertit aucune langue. Il contrôle que la source canonique
admissible est anglaise (`source_language = en`) et que les traductions
requises sont rattachées aux neuf codes `fr, es, de, it, pt, ru, zh, ar, el`
selon le contrat Phase2/ValidationPhase2. L’interface et la sélection de la
langue du joueur restent externes.

Avant toute fusion ou exposition, ReadyBank recalcule depuis l’état persistant
courant le prédicat de chaque traduction, puis celui du CognitiveSlot. Une
traduction doit être complète, `CREATED + PASS`, rattachée aux
`source_revision` et `translation_revision` courantes, sans finding bloquant ni
opération technique en attente. Un slot exige sa source anglaise Phase1 PASS et
ses neuf traductions obligatoires admissibles.

Aucune donnée legacy, absence, substitution, duplication, langue de fallback,
langue facultative, décision fournisseur non persistée ou PASS d’une autre
langue ne satisfait implicitement ce prédicat. L’indice jaune est compatible
avec l’admissibilité, mais seul ReadyBank le retire après sa décision terminale
portant sur la révision exacte.

La décision de fusion et publication est atomique par CognitiveSlot :

```text
source anglaise PASS
+ neuf traductions admissibles
→ fusion et publication du CognitiveSlot

sinon
→ aucune publication du CognitiveSlot
```

ReadyBank ne publie jamais un slot avec huit langues, une cible de fallback ou
une traduction rouge, manquante, périmée ou non PASS. Une panne purement
technique maintient la position non publiable sans colorer le contenu ni exiger
à elle seule une copie Quarantaine.

# 2. Arrivée du canonique

## 2.1 Contrat de circulation et de propriété

`KernelBlueprint` est une structure persistante extérieure aux phases. Elle est
créée une seule fois par `KernelBlueprintFactory` (KBP), avec un unique
`blueprint_id`, puis progressivement remplie. Elle est la source de vérité
unique : aucune copie autoritaire et aucun objet `Blueprint` ne transitent entre
phases.

Le seul relais inter-phase autorisé est :

```text
blueprint_id + phase précédente terminée + statut terminal
```

Chaque phase retrouve le `KernelBlueprint` persistant à partir de cet identifiant,
lit et écrit exclusivement les données relevant de son ownership, persiste sa
transaction, puis signale sa fin. ReadyBank applique exactement ce contrat : il
ne reçoit, ne conserve ni ne transmet un objet canonique en mémoire. À son
déclenchement, il vérifie le relais, relit le même `KernelBlueprint` persistant
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

Après avoir relu le canonique, ReadyBank fusionne atomiquement les seules
corrections admissibles de la copie avec ce même agrégat.

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
canonique, puis émet son signal terminal. Le relais suivant ne reçoit que
`blueprint_id`, la fin de ReadyBank et ce statut terminal; il relira à son tour
le même agrégat. Aucune phase ne transporte une version sérialisée, mutée ou
complète du canonique.

## 4.3 Échecs de Phase1

Une erreur technique de Phase1 (création, fournisseur, persistance ou
infrastructure empêchant la création) termine en `CREATION_FAILED`. Elle ne
produit pas un contenu intellectuel à valider.

Un contenu intellectuel produit et techniquement persistable, mais douteux ou
incomplet au regard des règles métier, passe par `ValidationPhase1`, puis prend
le statut `SUSPICION` si cette validation le conclut. `CREATION_FAILED` et
`SUSPICION` sont donc des branches distinctes et ne doivent jamais être
confondues par ReadyBank ou par leurs relais.

## 4.4 Modes externes, fixture et Harness

Les modes production et test sont entièrement externes au `KernelBlueprint`.
Aucun champ de mode, de target ou de test n’est stocké dans le Blueprint ni
déduit de son contenu.

Pour une fixture de test, le Harness demande à KBP le scénario ciblé. KBP crée
atomiquement un vrai `KernelBlueprint` PostgreSQL isolé, déjà préparé pour la
phase visée : les préconditions existent dès sa création et ses vrais sept slots
sont présents. KBP retourne uniquement son `blueprint_id`. Cette fixture n’est
ni un mock, ni un tableau, ni de la mémoire, ni une copie Quarantine. Elle
n’est pas récupérable par les workers de production non ciblés, mais reste
accessible à la vraie phase autorisée par `blueprint_id`.

Le Harness déclenche ensuite la vraie phase avec fournisseur simulé, intercepte
son signal de fin, bloque la cascade, observe le résultat puis nettoie. Il
n’écrit aucune précondition, donnée intellectuelle ou validation. Il ne crée pas
de variante métier de Phase1 : en production Phase1 relaie vers la validation;
en test elle relaie vers le récepteur terminal externe.

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


# 11. Contrat DEC-125 — réconciliation Quarantaine et direction suivante

## 11.1 Fusion atomique

ReadyBank réconcilie une copie Quarantaine uniquement après vérification de son `blueprint_id`, de son `kernel_code`, de son identité de copie, de sa version réclamée et de son droit de terminaison.

La clé de réemboîtement d’un slot est exclusivement :

```text
blueprint_id + cognitive_type
```

Chaque slot admissible est fusionné atomiquement. Un slot encore `EMPTY`, `SUSPICION`, non validé ou dont les traductions requises ne sont pas admissibles reste vide dans le canonique. L’échec d’un slot n’empêche pas la fusion des autres slots admissibles.

La fusion termine une version exactement une fois. Un retour ancien, un token expiré ou une version déjà terminée ne modifie rien.

## 11.2 Jaune

Un slot modifié manuellement reste identifié comme jaune pendant toute sa reprise. ReadyBank retire cette indication uniquement après sa décision terminale :

- fusion réussie : la correction devient le contenu canonique;
- échec : la position canonique reste vide et la copie retourne en Quarantaine avec le slot rouge.

## 11.3 Direction exclusive après CURRENT_KERNEL_RECEIVED

ReadyBank dirige le prochain GO; il ne retient pas un signal.

```text
CURRENT_KERNEL_RECEIVED
→ demandes Quarantaine READY présentes
   → une seule direction QUARANTINE
   → plus ancienne demande
   → aucun GO KBP

→ aucune demande Quarantaine READY
   → une seule direction KBP
```

Cinq demandes prêtes produisent cinq directions Quarantaine successives avant qu’une direction KBP puisse être choisie. Chaque décision est persistée et idempotente.

La branche Quarantaine reprend un Blueprint existant à Phase1. Elle ne crée aucun Blueprint, ne déclenche aucune Rotation et ne modifie aucun compteur Rotation.

La branche KBP crée le nouveau Blueprint canonique. KBP transmet ensuite son `blueprint_id` à Rotation; Rotation applique seulement à ce moment sa sélection et sa progression normales.


# 12. Indice de reprise des slots jaunes

## Indice de reprise persistant des slots jaunes

Pour chaque slot jaune, ReadyBank vérifie l’indice de reprise persistant avant toute fusion. Cet indice doit correspondre simultanément :

- au même `blueprint_id`;
- au même `cognitive_type`;
- à la version de copie réclamée;
- à la révision manuelle évaluée;
- à la reprise active;
- aux validations propriétaires terminées.

ReadyBank ne retire le jaune qu’après sa décision terminale sur cette combinaison exacte. Une divergence rend le retour périmé et produit un NO-OP atomique sur le canonique.

Une fusion réussie termine l’indice et installe la révision corrigée dans le slot canonique. Une fusion refusée maintient le slot canonique vide et renvoie la copie vers Quarantaine avec un nouvel état de travail, sans permettre à une ancienne reprise de se terminer ensuite.
