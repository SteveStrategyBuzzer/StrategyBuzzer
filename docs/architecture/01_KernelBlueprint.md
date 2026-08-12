# Correctif — `01_KernelBlueprint.md`

## Version 1.5

**Date :** 12 août 2026
**Statut documentaire :** VERROUILLÉ
**Statut du module :** IMPLÉMENTÉ — CORRECTION TERMINALE
**Implantation autorisée :** OUI

> **Gel de contrat** — Ce document est verrouillé. Aucune modification n'est autorisée
> sans ouverture explicite d'un correctif versionné (v1.6 minimum) soumis à révision
> par l'architecte principal. Les sections héritées de v1.4 restent valides et sont
> conservées telles quelles. Les corrections B1 et B2 sont documentées en section 21.

---

# 1. Mission — Flow corrigé

```text
Depth
↓
Domaine réel de création
↓
Sous-domaine actif
↓
Sujet actif
↓
Idée dominante active validée par Taxonomy
↓
QuestionIntent
Attribution du kernel_code
↓
Phase 1
Création des contenus cognitifs par slot
↓
Validation Phase 1
├── verdict PASS → slot identifié OK
└── verdict FAIL → slot identifié FAIL
↓
Fin de la vérification de tous les slots concernés
├── aucun FAIL → poursuite vers Phase 2
└── un ou plusieurs FAIL
        ↓
   création d’une copie travaillable unique du Blueprint
        ↓
   QUARANTINE
↓
Phase 2
Création des traductions des slots identifiés OK
↓
Validation Phase 2
├── verdict PASS → slot identifié OK et ouvrable au gameplay
└── verdict FAIL → slot identifié FAIL et fermé au gameplay
↓
Fin de la vérification de tous les slots concernés
├── aucun FAIL → ReadyBank
└── un ou plusieurs FAIL
        ↓
   création d’une copie travaillable unique du Blueprint
        ↓
   QUARANTINE
↓
READY_BANK
Noyau canonique contenant l’état individuel de chacun de ses slots
```

La progression du Blueprint fonctionne individuellement pour chaque slot cognitif.

La validation produit un verdict :

```text
PASS
ou
FAIL
```

Le verdict `PASS` attribue au slot l’état :

```text
OK
```

Le verdict `FAIL` attribue au slot l’état :

```text
FAIL
```

La validation ne crée pas immédiatement une copie à chaque échec rencontré.

Elle doit d’abord terminer la vérification de l’ensemble des slots qui lui ont été remis.

À la fin de cette vérification :

* si tous les slots sont `OK`, le Blueprint poursuit son flow ;
* si un ou plusieurs slots sont `FAIL`, une seule copie travaillable du Blueprint est créée ;
* cette copie contient l’état complet du noyau ;
* chaque erreur est rattachée au slot concerné ;
* tous les points de correction identifiés sont joints à la copie ;
* la copie est transmise à Quarantine.

Lorsqu’un ou plusieurs slots échouent :

* les slots concernés reçoivent l’état `FAIL` ;
* les slots ayant reçu `PASS` sont identifiés `OK` ;
* les slots `OK` ne sont ni supprimés ni recréés inutilement ;
* le Blueprint canonique conserve l’état exact de tous ses slots ;
* le noyau n’est pas abandonné ;
* les slots `OK` peuvent poursuivre leur flow normal ;
* les slots `FAIL` demeurent fermés jusqu’à leur correction et leur nouvelle validation.

---

# 2.1 Pipeline officiel — Version corrigée

```text
1. KernelRotationPlanner
   │
   ├── crée le KernelBlueprint canonique
   ├── écrit le Depth actif
   └── écrit le Domaine réel actif
   ↓
2. Taxonomy
   │
   └── écrit :
          sub_domain
          subject
          dominant_idea
   ↓
3. QuestionIntent
   │
   └── écrit le kernel_code
   ↓
4. Phase 1
   │
   └── remplit individuellement les slots cognitifs
   ↓
5. Validation Phase 1
   │
   ├── vérifie l’ensemble des slots concernés
   │
   ├── pour chaque slot :
   │      ├── PASS → état du slot : OK
   │      └── FAIL → état du slot : FAIL
   │
   └── à la fin de la vérification complète :
          ├── aucun slot FAIL
          │      └── les slots OK poursuivent vers Phase 2
          │
          └── un ou plusieurs slots FAIL
                 ├── une seule copie travaillable du Blueprint est créée
                 ├── toutes les erreurs sont associées
                 │   à leurs slots respectifs
                 ├── tous les points de correction sont identifiés
                 └── la copie est envoyée à Quarantine
   ↓
6. Phase 2 — Traductions
   │
   └── traduit individuellement les slots identifiés OK
       par la Validation Phase 1
   ↓
7. Validation Phase 2
   │
   ├── vérifie l’ensemble des slots traduits concernés
   │
   ├── pour chaque slot :
   │      ├── PASS
   │      │      ├── état du slot : OK
   │      │      └── slot ouvrable au gameplay
   │      │
   │      └── FAIL
   │             ├── état du slot : FAIL
   │             └── slot fermé au gameplay
   │
   └── à la fin de la vérification complète :
          ├── aucun slot FAIL
          │      └── intégration dans ReadyBank
          │
          └── un ou plusieurs slots FAIL
                 ├── une seule copie travaillable du Blueprint est créée
                 ├── toutes les erreurs sont associées
                 │   à leurs slots respectifs
                 ├── tous les points de correction sont identifiés
                 └── la copie est envoyée à Quarantine
   ↓
8. ReadyBank
   │
   └── conserve le noyau canonique et l’état individuel
       de chacun de ses slots
```

## Règle de lot de validation

Chaque moteur de validation traite un ensemble de slots.

Il ne doit pas interrompre son analyse au premier `FAIL`.

Il doit :

1. vérifier tous les slots remis pour la passe courante ;
2. attribuer un verdict à chacun ;
3. dresser la liste complète des erreurs ;
4. déterminer tous les points de correction ;
5. produire une seule copie travaillable du Blueprint lorsque la passe contient au moins un `FAIL`.

Il est interdit de créer plusieurs copies de Quarantine pour plusieurs erreurs appartenant à la même passe de validation du même Blueprint.

---

# 2.2 Représentation Blueprint–Quarantine–ReadyBank

```text
┌──────────────────────────────────┐
│        KERNEL BLUEPRINT          │
│                                  │
│ Depth                            │
│ Domaine                          │
│ Sous-domaine                     │
│ Sujet                            │
│ Idée dominante                   │
│ kernel_code                      │
│                                  │
│ Slot cognitif 1 : OK / FAIL      │
│ Slot cognitif 2 : OK / FAIL      │
│ Slot cognitif 3 : OK / FAIL      │
│ Slot cognitif 4 : OK / FAIL      │
│ Slot cognitif 5 : OK / FAIL      │
│ Slot cognitif 6 : OK / FAIL      │
│ Slot cognitif 7 : OK / FAIL      │
│                                  │
│ Traductions par slot             │
└──────────────────────────────────┘
              │
              ├── Slots OK
              │       ↓
              │   poursuite du flow
              │       ↓
              │   READY_BANK
              │       ↓
              │   OPEN_GAMEPLAY
              │
              └── un ou plusieurs slots FAIL
                      ↓
              fin de la vérification
              de tous les slots
                      ↓
              une copie travaillable
              unique du Blueprint
                      ↓
                 QUARANTINE
                      ↓
              correction du Blueprint
              et de ses slots concernés
                      ↓
              retour systématique
                  à PHASE 1
                      ↓
              chaque moteur travaille
              les slots corrigés ou
              affectés de son environnement
                      ↓
              Validation Phase 1
                      ↓
                   Phase 2
                      ↓
              Validation Phase 2
                      ↓
              correspondance avec le
              Blueprint canonique
              du même kernel_code
                      ↓
              réintégration des slots
              corrigés ou modifiés
                      ↓
                 READY_BANK
                      ↓
                OPEN_GAMEPLAY
```

## Règle des erreurs multiples

Un même Blueprint peut contenir plusieurs slots `FAIL`.

La copie travaillable envoyée à Quarantine doit donc pouvoir contenir :

* un seul slot `FAIL` ;
* plusieurs slots `FAIL` de Phase 1 ;
* plusieurs slots `FAIL` de Phase 2 ;
* des erreurs liées entre plusieurs slots ;
* des corrections ayant un impact sur des slots initialement `OK`.

Après correction, la copie complète retourne à Phase 1.

Chaque moteur traite ensuite uniquement les slots corrigés ou affectés qui appartiennent à son environnement.

---

# 2.4 Position de Quarantine — Version 1.4

Quarantine reçoit une copie travaillable du Blueprint lorsque la validation complète d’une passe contient un ou plusieurs slots `FAIL`.

Une seule copie est produite pour le Blueprint à la fin de la passe de validation concernée.

Cette copie conserve le contexte complet du noyau :

* le Depth ;
* le domaine ;
* le sous-domaine ;
* le sujet ;
* l’idée dominante ;
* le `kernel_code` ;
* les blocs cognitifs ;
* les traductions existantes ;
* l’état individuel de chaque slot ;
* les erreurs rattachées à chaque slot ;
* les points de correction identifiés ;
* les dépendances entre les erreurs.

## Portée des corrections

Les corrections ne sont pas obligatoirement limitées aux seuls slots identifiés `FAIL`.

Quarantine peut déterminer qu’une correction exige également de modifier :

* un slot `OK` directement dépendant du slot en échec ;
* plusieurs slots appartenant au même bloc cognitif ;
* une question et ses réponses ;
* une réponse et son Saviez-vous ;
* un contenu source et ses traductions ;
* toute autre donnée dont la cohérence est affectée.

Les slots `OK` non concernés par la correction sont conservés comme références et ne doivent pas être recréés inutilement.

Lorsqu’un slot précédemment `OK` est modifié dans la copie travaillable :

* son ancien état `OK` est retiré ;
* il redevient un slot modifié à vérifier ;
* il doit repasser les validations applicables ;
* il ne peut pas conserver artificiellement son ancien statut `OK`.

## Règle de progression du Blueprint canonique

L’envoi d’une copie à Quarantine ne bloque pas automatiquement les slots `OK` du Blueprint canonique.

```text
Slot OK
→ poursuit le pipeline normal

Slot FAIL
→ reste fermé
→ est représenté dans la copie Quarantine
```

Le Blueprint canonique peut donc contenir simultanément :

* des slots ouverts au gameplay ;
* des slots en cours de traduction ;
* des slots identifiés `FAIL` ;
* des slots représentés dans une copie en correction ;
* des slots en attente de réintégration.

La copie Quarantine ne remplace pas le Blueprint canonique pendant sa correction.

---

# 2.4.1 Retour de Quarantine

Toute copie travaillable corrigée retourne systématiquement à :

```text
PHASE 1
```

Cette règle s’applique quelle que soit l’étape où l’erreur a été détectée.

Le retour ne dépend pas :

* du moteur ayant produit le contenu fautif ;
* du type d’erreur ;
* de l’étape de détection ;
* du fait que l’erreur soit cognitive ou linguistique.

Le retour ne s’effectue jamais directement vers :

* Phase 2 ;
* Validation Phase 2 ;
* ReadyBank.

## Échec détecté en Validation Phase 1

```text
Un ou plusieurs slots cognitifs FAIL
↓
fin de la vérification de tous les slots
↓
copie travaillable unique du Blueprint
↓
Quarantine
↓
correction de tous les slots concernés
↓
Phase 1
↓
Validation Phase 1
↓
Phase 2
↓
Validation Phase 2
↓
ReadyBank
```

## Échec détecté en Validation Phase 2 — Erreur de traduction

```text
Un ou plusieurs slots de traduction FAIL
↓
fin de la vérification de tous les slots
↓
copie travaillable unique du Blueprint
↓
Quarantine
↓
correction des traductions concernées
↓
Phase 1
↓
Validation Phase 1
↓
Phase 2
↓
Validation Phase 2
↓
ReadyBank
```

## Échec détecté en Validation Phase 2 — Erreur du contenu source

```text
Un ou plusieurs slots FAIL
↓
fin de la vérification de tous les slots
↓
copie travaillable unique du Blueprint
↓
Quarantine
↓
correction du contenu source
et des contenus dépendants
↓
Phase 1
↓
Validation Phase 1
↓
Phase 2
↓
Validation Phase 2
↓
ReadyBank
```

## Travail des moteurs après le retour

Le retour systématique à Phase 1 ne signifie pas que tout le Blueprint doit être recréé.

Chaque moteur doit travailler uniquement :

* les slots corrigés appartenant à son environnement ;
* les slots directement affectés par une correction précédente ;
* les dépendances qui doivent être régénérées ;
* les slots dont l’ancien statut `OK` a été invalidé par une modification.

Exemple :

```text
Une traduction est corrigée dans Quarantine
↓
la copie retourne à Phase 1
↓
Phase 1 constate que le contenu cognitif source
n’a pas été modifié
↓
Phase 1 ne recrée pas ce contenu
↓
Validation Phase 1 confirme les slots concernés
↓
Phase 2 travaille la traduction corrigée
↓
Validation Phase 2 vérifie la traduction
```

Le passage par Phase 1 est obligatoire.

La recréation de tous les slots ne l’est pas.

---

# 2.4.2 Réintégration dans le noyau canonique

Après correction, la copie retraverse obligatoirement :

```text
Phase 1
↓
Validation Phase 1
↓
Phase 2
↓
Validation Phase 2
```

La réintégration n’est possible qu’après la fin complète de ce flow.

## Correspondance obligatoire

StrategyBuzzer traite un Blueprint à la fois.

La copie corrigée doit être rattachée au Blueprint canonique dont elle provient.

La correspondance principale repose sur :

```text
kernel_code de la copie corrigée
=
kernel_code du Blueprint canonique
```

La réintégration doit également confirmer :

* la même identité de Blueprint ;
* le même Depth ;
* le même domaine ;
* le même sous-domaine ;
* le même sujet ;
* la même idée dominante ;
* les mêmes identifiants de slots ;
* la continuité de version attendue.

Il est interdit d’imbriquer une copie corrigée dans un Blueprint possédant un autre `kernel_code`.

## Contenu réintégré

La réintégration ne concerne pas obligatoirement uniquement les slots initialement `FAIL`.

Elle concerne tous les slots qui ont été :

* corrigés ;
* recréés ;
* modifiés pour préserver la cohérence ;
* régénérés à la suite d’une dépendance ;
* retraduits ;
* revalidés.

Les slots du Blueprint canonique qui n’ont pas été modifiés restent inchangés.

## Transformation des états

Pour chaque slot corrigé ou modifié :

```text
FAIL ou ancien OK invalidé
↓
CORRECTED
↓
Phase 1
↓
Validation Phase 1 : PASS
↓
état OK Phase 1
↓
Phase 2
↓
Validation Phase 2 : PASS
↓
état OK final
↓
OPEN_GAMEPLAY
```

Un slot ne devient ouvert au gameplay qu’après avoir franchi de nouveau toutes les étapes applicables.

---

# 2.5 Position de ReadyBank — Version corrigée

ReadyBank conserve le noyau canonique exploitable par le gameplay.

Chaque slot possède son propre état de disponibilité.

ReadyBank peut donc contenir un noyau dont :

* certains slots sont ouverts au gameplay ;
* certains slots sont fermés en raison d’un `FAIL` ;
* certains slots sont en correction dans Quarantine ;
* certains slots attendent leur réintégration.

## États de disponibilité

```text
CLOSED_EMPTY
CLOSED_IN_PROGRESS
CLOSED_FAIL
CLOSED_QUARANTINE
OPEN_GAMEPLAY
```

## CLOSED_EMPTY

Le slot existe, mais ne contient pas encore son contenu obligatoire.

## CLOSED_IN_PROGRESS

Le slot est en cours de création, de traduction ou de vérification.

## CLOSED_FAIL

Le slot a reçu un verdict `FAIL`.

Il ne peut pas être sélectionné par le gameplay.

## CLOSED_QUARANTINE

Le slot est représenté dans une copie travaillable actuellement prise en charge par Quarantine.

## OPEN_GAMEPLAY

Le slot :

* contient son contenu cognitif ;
* a reçu `PASS` à la Validation Phase 1 ;
* est identifié `OK` après cette validation ;
* possède ses traductions obligatoires ;
* a reçu `PASS` à la Validation Phase 2 ;
* est identifié `OK` après cette validation ;
* est intégré dans le Blueprint canonique de ReadyBank ;
* peut être sélectionné par le gameplay.

## Réouverture après correction

```text
CLOSED_FAIL
↓
CLOSED_QUARANTINE
↓
CORRECTED
↓
retour systématique à Phase 1
↓
Validation Phase 1 : PASS
↓
Phase 2
↓
Validation Phase 2 : PASS
↓
réintégration dans le Blueprint canonique
↓
OPEN_GAMEPLAY
```

La copie corrigée ne crée pas un nouveau noyau ReadyBank.

Elle complète ou met à jour le noyau canonique portant le même `kernel_code`.

---

# 3.1 Responsabilités exclusives — Version corrigée

Le KernelBlueprint doit également :

21. permettre la progression individuelle de chaque slot cognitif ;

22. conserver l’état individuel de chacun de ses slots ;

23. distinguer le verdict produit par une validation de l’état attribué au slot :

```text
Verdict PASS
→ état OK

Verdict FAIL
→ état FAIL
```

24. permettre aux slots `OK` de poursuivre le pipeline lorsqu’un autre slot est `FAIL` ;

25. attendre la fin de la vérification de tous les slots concernés avant la création d’une copie Quarantine ;

26. permettre la création d’une seule copie travaillable par Blueprint et par passe de validation contenant au moins un `FAIL` ;

27. associer chaque erreur au slot concerné dans cette copie ;

28. associer tous les points de correction identifiés à cette copie ;

29. permettre à une même copie de contenir plusieurs slots `FAIL` ;

30. conserver les slots `OK` non concernés par les corrections ;

31. permettre la modification d’un slot initialement `OK` lorsque la cohérence du noyau l’exige ;

32. retirer l’état `OK` de tout slot modifié pendant la correction ;

33. imposer le retour systématique de toute copie corrigée à Phase 1 ;

34. permettre à chaque moteur de retravailler les slots corrigés ou affectés appartenant à son environnement ;

35. empêcher la recréation inutile des slots `OK` non affectés ;

36. imposer la retraversée de Phase 1, Validation Phase 1, Phase 2 et Validation Phase 2 ;

37. vérifier la correspondance entre le `kernel_code` de la copie corrigée et celui du Blueprint canonique ;

38. empêcher l’intégration d’une copie dans un autre noyau ;

39. réintégrer tous les slots corrigés, modifiés ou régénérés ;

40. préserver les slots canoniques qui n’ont pas été affectés ;

41. remplacer l’état `FAIL` par `OPEN_GAMEPLAY` uniquement après les deux validations obligatoires ;

42. permettre à ReadyBank de contenir différents états de disponibilité dans un même noyau ;

43. empêcher toute ouverture gameplay d’un slot qui n’a pas terminé son flow complet ;

44. empêcher la duplication du noyau pendant la réintégration.

---

# États individuels des slots — Version 1.4

## Flow initial

```text
EMPTY
↓
FILLED_PHASE1
↓
VALIDATION_PHASE1
├── PASS
│      ↓
│   OK_PHASE1
│      ↓
│   TRANSLATION_IN_PROGRESS
│      ↓
│   TRANSLATED
│      ↓
│   VALIDATION_PHASE2
│      ├── PASS
│      │      ↓
│      │   OK_PHASE2
│      │      ↓
│      │   OPEN_GAMEPLAY
│      │
│      └── FAIL
│             ↓
│          FAIL_PHASE2
│             ↓
│          CLOSED_QUARANTINE
│
└── FAIL
       ↓
    FAIL_PHASE1
       ↓
    CLOSED_QUARANTINE
```

## Création de la copie Quarantine

```text
Fin de la vérification complète
↓
présence d’au moins un FAIL
↓
une copie travaillable unique du Blueprint
↓
tous les slots FAIL identifiés
↓
toutes les erreurs associées
↓
tous les points de correction associés
↓
QUARANTINE
```

## Retour après correction

```text
QUARANTINE
↓
CORRECTED
↓
retour systématique à Phase 1
↓
travail des slots corrigés ou affectés
↓
Validation Phase 1
↓
Phase 2
↓
Validation Phase 2
↓
correspondance avec le kernel_code canonique
↓
réintégration
↓
OPEN_GAMEPLAY
```

## Règle absolue

Un slot ne passe jamais directement de :

```text
FAIL
```

à :

```text
OPEN_GAMEPLAY
```

Toute copie corrigée revient à Phase 1.

Chaque slot corrigé ou affecté doit retraverser toutes les étapes nécessaires avant sa réintégration dans le noyau canonique.

---

# Architecture Register — Mise à jour Version 1.4

## DEC-027 — Progression individuelle des slots

**Statut :** OFFICIAL

**Précision ajoutée :**

La validation traite tous les slots concernés avant de produire une copie Quarantine.

Une seule copie travaillable est créée à la fin de la passe lorsqu’un ou plusieurs slots sont `FAIL`.

---

## DEC-028 — Retour ciblé depuis Quarantine

**Ancien statut :** OFFICIAL
**Nouveau statut :** SUPERSEDED

**Ancienne décision :**

Une copie corrigée retournait au moteur propriétaire du contenu fautif.

**Motif du remplacement :**

Toutes les copies corrigées doivent retraverser le même point d’entrée contrôlé.

**Remplacée par :** DEC-030

---

## DEC-029 — Réintégration limitée au slot initialement FAIL

**Ancien statut :** OFFICIAL
**Nouveau statut :** SUPERSEDED

**Ancienne décision :**

La réintégration remplaçait uniquement le slot précédemment identifié `FAIL`.

**Motif du remplacement :**

La correction peut nécessiter la modification de plusieurs slots, y compris de slots initialement `OK`.

**Remplacée par :** DEC-031

---

## DEC-030 — Retour systématique à Phase 1

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL

**Décision :**

Toute copie travaillable corrigée provenant de Quarantine retourne systématiquement à Phase 1.

Cette règle s’applique aux erreurs détectées :

* en Validation Phase 1 ;
* en Validation Phase 2 ;
* dans un contenu cognitif ;
* dans une traduction ;
* dans une dépendance entre plusieurs slots.

La copie retraverse ensuite :

```text
Phase 1
↓
Validation Phase 1
↓
Phase 2
↓
Validation Phase 2
↓
ReadyBank
```

Chaque moteur travaille uniquement les slots corrigés ou affectés appartenant à son environnement.

---

## DEC-031 — Réintégration de tous les slots modifiés

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL

**Décision :**

La copie corrigée est réintégrée dans le Blueprint canonique portant le même `kernel_code`.

La réintégration peut concerner :

* les slots initialement `FAIL` ;
* les slots initialement `OK` mais modifiés pendant la correction ;
* les slots dépendants qui ont dû être régénérés ;
* les traductions corrigées ;
* tous les contenus dont l’ancien statut a été invalidé.

Les slots canoniques non modifiés restent inchangés.

---

## DEC-032 — Une copie par passe de validation

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL

**Décision :**

Un moteur de validation termine l’analyse de tous les slots qui lui ont été remis avant de produire une copie Quarantine.

Lorsqu’un ou plusieurs slots sont `FAIL`, il crée une seule copie travaillable du Blueprint contenant :

* tous les slots en échec ;
* toutes les erreurs détectées ;
* tous les points de correction ;
* le contexte complet du noyau.

Il est interdit de créer une copie distincte pour chaque slot `FAIL` appartenant à la même passe.

---

## DEC-033 — Distinction PASS et OK

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL

**Décision :**

`PASS` est le verdict produit par un moteur de validation.

`OK` est l’état attribué au slot après un verdict `PASS`.

```text
PASS
→ OK
```

`FAIL` constitue à la fois le verdict d’échec et l’état de fermeture du slot jusqu’à sa correction.

---

# 20. Statut — Version 1.5

| Section                   | Avancement |
| ------------------------- | ---------: |
| Mission                   |      100 % |
| Position dans le pipeline |      100 % |
| Responsabilités           |      100 % |
| Interdictions             |      100 % |
| Entrées                   |      100 % |
| Sorties                   |      100 % |
| Slots Blueprint lus       |      100 % |
| Slots Blueprint écrits    |      100 % |
| Données internes          |      100 % |
| États internes            |      100 % |
| Mécanismes                |      100 % |
| Communication             |      100 % |
| Contrats                  |      100 % |
| Transitions               |      100 % |
| Cas limites               |      100 % |
| Persistance               |      100 % |
| Validation                |      100 % |
| Tests                     |      100 % |
| Architecture Register     |      100 % |

```text
Architecture :      100 %
Contrat :           100 %
Implémentation :    100 %
Validation :        100 %

Statut :
VERROUILLÉ v1.5

Version documentaire :
1.5 — VERROUILLÉ

Implantation :
AUTORISÉE
```

## Motif du passage à la Version 1.4

La Version 1.4 corrige les dérives de la Version 1.3 :

* une validation termine la vérification de tous les slots avant d’agir ;
* une seule copie travaillable est créée par passe de validation ;
* une copie peut contenir plusieurs slots `FAIL` ;
* `PASS` est un verdict et `OK` est l’état du slot ;
* les corrections ne sont pas limitées aux slots initialement `FAIL` ;
* tout slot `OK` modifié perd son ancien statut et doit être revalidé ;
* toutes les copies corrigées retournent systématiquement à Phase 1 ;
* chaque moteur retravaille ensuite les slots corrigés ou affectés de son environnement ;
* la copie retraverse l’intégralité du flow ;
* tous les slots modifiés peuvent être réintégrés ;
* la réintégration s’effectue uniquement dans le Blueprint canonique portant le même `kernel_code`.

---

# 21. Correction Terminale — Version 1.5 (12 août 2026)

## B1 — Atomicité de création de Blueprint

### Problème identifié

`KernelBlueprintFactory::create()` effectuait une vérification applicative `SELECT EXISTS`
suivie d'un `INSERT` sans transaction, sans verrou, et sans contrainte DB.

Deux appels simultanés pouvaient tous deux passer le `EXISTS`, tous deux insérer, et
créer deux Blueprints actifs simultanément — violation directe de DEC-067.

### Solution appliquée

1. **Nouvelle migration** `2026_08_12_000001_add_one_active_blueprint_unique_index.php` :
   index unique partiel PostgreSQL sur l'expression constante `(1)` limité aux états actifs.

   ```sql
   CREATE UNIQUE INDEX IF NOT EXISTS one_active_blueprint_idx
   ON kernel_blueprint_runs ((1))
   WHERE execution_state IN ('CREATED_UNENGAGED', 'ENGAGED_IN_PIPELINE')
   ```

   L'index sur une constante ne peut contenir qu'une seule entrée → au plus une ligne
   active en DB, garanti atomiquement par PostgreSQL.

2. **`KernelBlueprintFactory::create()`** : `INSERT` enveloppé dans un `try/catch
   UniqueConstraintViolationException`. La même `RuntimeException` que le chemin
   applicatif est levée — aucun comportement externe nouveau.

3. **Test de concurrence** `tests/Concurrent/kernel_blueprint_factory_concurrent.php` :
   20 workers forkés, chacun tente `create()` simultanément après barrière.
   Critères : exactement 1 SUCCESS, 19 refus, 1 Blueprint actif en DB.

### Portée

- La vérification applicative `EXISTS` est conservée (chemin rapide, séquentiel).
- La contrainte DB est le filet atomique concurrentiel.
- Sur SQLite (PHPUnit), la migration est un NO-OP ; seul le chemin applicatif protège.

---

## B2 — Immutabilité write-once de KernelBlueprint

### Problème identifié

Les 7 propriétés de `KernelBlueprint` étaient `public` : tout module pouvait écrire
`$blueprint->depth = 99` directement, contournant le modèle d'ownership des slots.

Les méthodes `fill*()` étaient ré-entrantes : aucun garde → suréciture silencieuse possible.

### Solution appliquée

**`app/Services/QuestionBank/KernelBlueprint.php`** :

- Les 7 propriétés passent de `public` à `private`.
- `__get(string $name)` → lecture publique transparente (`$bp->depth` fonctionne).
- `__set(string $name, mixed $value)` → lève `LogicException` sur toute écriture directe.
- `__isset(string $name)` → `true` si la propriété existe et est non-null.
- `initializeBlueprintId(string $id): void` → write-once avec garde (remplace l'ancienne
  écriture directe `$blueprint->blueprint_id = $id` dans la Factory).
- `fillRotation()`, `fillTaxonomy()`, `fillKernelCode()` → garde write-once au début de
  chaque méthode ; un second appel lève `LogicException`.

Pourquoi pas `readonly` ? Les propriétés `readonly` PHP 8.2 ne peuvent pas être
initialisées à `null` puis écrites plus tard. Le pattern write-once via `__set` est
la seule approche compatible PHP 8.2 qui préserve le schéma null-initial.

### Mises à jour des consommateurs (purement mécaniques)

| Fichier                                              | Changement |
| ---------------------------------------------------- | ---------- |
| `KernelBlueprintFactory`                             | `$blueprint->blueprint_id = $id` → `initializeBlueprintId($id)` |
| `tests/Unit/.../KernelCodeEngineTest.php`            | `makeBlueprint()` → `initializeBlueprintId()` + `fill*()` ; 3 tests inline migrés ; `test_missing_field_throws` → `test_missing_rotation_throws` + `test_missing_taxonomy_throws` |
| `tests/Unit/.../KernelRotationPlannerV2Test.php`     | `makeBlueprint()` → `initializeBlueprintId()` |
| `tests/Concurrent/kernel_code_concurrent.php`        | 3 blocs de construction directe → `initializeBlueprintId()` + `fill*()` |

### Mises à jour de KernelBlueprintPart1Test

Retraits (états désormais inatteignables via l'API publique) :
- `test_isRotationFilled_false_when_only_depth_set`
- `test_isRotationFilled_false_when_only_domain_set`
- `test_isTaxonomyFilled_false_when_only_subdomain_set`

Ajouts (contrat write-once) :
- `test_direct_write_throws_logic_exception`
- `test_blueprint_id_direct_write_throws`
- `test_initializeBlueprintId_write_once_throws_on_second_call`
- `test_fillRotation_write_once_throws_on_second_call`
- `test_fillTaxonomy_write_once_throws_on_second_call`
- `test_fillKernelCode_write_once_throws_on_second_call`
- `test_read_via_magic_get_works_before_fill`
- `test_read_via_magic_get_works_after_fill`

---

## DEC-034 — Immutabilité write-once de KernelBlueprint

**Version :** 1.0
**Date :** 12 août 2026
**Statut :** OFFICIAL

**Décision :**

Toutes les propriétés de `KernelBlueprint` sont privées.

La lecture publique passe par `__get()` (comportement transparent).

L'écriture directe externe est interceptée par `__set()` et lève `LogicException`.

Chaque slot ne peut être attribué qu'une seule fois via la méthode `fill*()` de son propriétaire.
Un second appel à `fill*()` sur un slot déjà rempli lève `LogicException`.

**Pourquoi :**

Le modèle d'ownership des slots (DEC-059) exige que chaque moteur soit le seul
à écrire dans les slots qui lui appartiennent. Sans protection runtime, n'importe
quel module pouvait contourner ce contrat par écriture directe silencieuse.

---

## DEC-035 — Atomicité DB de la création de Blueprint

**Version :** 1.0
**Date :** 12 août 2026
**Statut :** OFFICIAL

**Décision :**

L'unicité du Blueprint actif (DEC-067) est garantie par deux niveaux :

1. Vérification applicative `SELECT EXISTS` (chemin rapide, séquentiel).
2. Index unique partiel PostgreSQL `one_active_blueprint_idx` sur `(1) WHERE
   execution_state IN ('CREATED_UNENGAGED', 'ENGAGED_IN_PIPELINE')` (atomique,
   protège contre la concurrence).

Sur conflit DB, `UniqueConstraintViolationException` est capturée et convertie en
la même `RuntimeException` que le chemin applicatif.

**Pourquoi :**

La vérification applicative seule n'est pas atomique. Deux connexions simultanées
peuvent passer le `EXISTS` simultanément avant que l'une des deux n'insère.
L'index partiel sur une constante garantit physiquement qu'une seule ligne active
peut exister dans la table, quel que soit le nombre de connexions concurrentes.
