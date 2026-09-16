# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 2.3.0-dec-126
**Date :** 2026-09-16
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version remplace le périmètre de `2.1.0-kbp-v3.1` par **DEC-125 —
> OFFICIAL**. DEC-119/120 et les clauses compatibles de DEC-122/123/124
> restent actives. Les quatre contradictions historiques (position canonique
> suspecte, édition Quarantine ciblée, reprise hors Phase1 et routage toujours
> KBP) sont uniquement `SUPERSEDED BY DEC-125`; leur prose historique reste
> conservée dans le registre.

> **DEC-126 — OFFICIAL** complète ces décisions sans déplacer aucune
> responsabilité : elle fixe l’anglais comme langue de création intellectuelle
> canonique, le français comme langue architecturale et administrative, et la
> langue choisie par le joueur comme langue de présentation.

## 0. Autorité courante DEC-125

Un seul Blueprint canonique persistant est identifié définitivement par
`blueprint_id`. Cet identifiant est conservé entre phases normales uniquement.
Quand un slot est `SUSPICION` ou `EMPTY`, la position canonique est vide et une
copie de travail persistante, complète, non canonique, avec sept slots,
contenus disponibles et findings est créée. Les contenus rejetés et findings
restent dans la copie. Les sept slots de la copie sont visibles et éditables :
rouge = `SUSPICION`/`EMPTY`, vert = conforme et jamais encore modifié
manuellement, jaune = rempli/modifié manuellement jusqu’à ReadyBank (marqueur
de parcours, pas état de validation). Tous les slots restent éditables :
modifier un vert le jaunit et invalide immédiatement son ancien `PASS` aval.

Admin est une UI externe, pas une phase; elle ne supprime que des copies.
Toute copie complète recommence en Phase1. Elle accompagne les sept slots comme
contexte, mais chaque slot ne rejoue que ses contrôles/créations concernés :
contrôles techniques Phase1, validation intellectuelle ValidationPhase1,
Phase2 sur les slots autorisés, ValidationPhase2 pour les traductions et
ReadyBank pour la copie complète. Les slots conformes continuent, les échoués
restent vides. ReadyBank fusionne uniquement les slots réussis par
`blueprint_id + cognitive_type`, jamais un remplacement global; les non résolus
restent vides. Une copie peut cycler avec les findings les plus récents, sans
historique permanent de corrections; plusieurs copies prêtes sont permises.

Un renvoi met en file sans démarrer. L’ordre exact des clics est FIFO, un seul
traitement à la fois. À chaque arrivée ReadyBank, `CURRENT_KERNEL_RECEIVED`
choisit une seule direction : `GO` vers la première copie Quarantine prête si
la file n’est pas vide (KBP ne reçoit rien), sinon `GO` vers KBP. Jamais les
deux. KBP ne coordonne pas la circulation, ne reçoit aucun état Quarantine et
ne crée/ne retrouve un Blueprint que lorsque `GO` lui est destiné.
`blueprint_id` reste requis pour le suivi normal et les retours.

### Exigences d’implémentation ouvertes — pas des solutions approuvées

Sont explicitement **OPEN IMPLEMENTATION REQUIREMENTS** et non des solutions
techniques approuvées :

1. persistance de la copie complète courante;
2. file d’attente des clics Renvoie;
3. ordre exact des demandes;
4. détection des slots modifiés;
5. conservation du marqueur jaune jusqu’à ReadyBank;
6. invalidation immédiate d’un ancien PASS après modification;
7. protection contre les retours périmés;
8. idempotence de `CURRENT_KERNEL_RECEIVED`;
9. fusion atomique dans ReadyBank.

## 0.1 Autorité linguistique courante — DEC-126

Les identités et codes techniques actuels restent inchangés et indépendants de
la langue. Les valeurs intellectuelles `domain`, `subdomain_active`,
`subject_active` et `dominant_idea_active` sont canoniquement anglaises. Les
réservoirs Taxonomy produisent leurs nouvelles valeurs en anglais.

Phase1 crée en anglais, pour chacun des sept CognitiveSlots, la `question`, les
`choices`, la `correct_answer` et le `SV`. La valeur active est
`source_language = en`.

Phase2 traduit cette source vers exactement neuf langues obligatoires, sans
langue facultative :

```text
fr, es, de, it, pt, ru, zh, ar, el
```

Chaque cible est traduite indépendamment et directement depuis la même source
anglaise. Les traductions en chaîne entre langues sont interdites.

L’identité persistante logique d’une traduction est :

```text
blueprint_id + cognitive_type + language_code + source_revision
```

`source_revision` versionne uniquement les quatre composantes anglaises du
CognitiveSlot. Elle augmente lorsqu’au moins une de ces composantes est
modifiée; toutes les traductions de la révision anglaise antérieure deviennent
alors périmées. Modifier une cible ne modifie jamais `source_revision`.

Chaque cible possède en plus une `translation_revision`, distincte de
`source_revision`. Toute modification réelle d’une composante cible crée une
nouvelle révision cible et invalide atomiquement l’ancien résultat de validation.
Les états Phase2 et ValidationPhase2 restent séparés.

Une correction Quarantaine complète peut réparer un `PERMANENT_FAILURE` du
cycle automatisé sous la même `source_revision`, en créant une nouvelle
`translation_revision` `CREATED + NOT_VALIDATED` avec indice jaune. Cet indice
et la protection contre les retours périmés demeurent actifs jusqu’à ReadyBank.

La Bible, les règles, les titres structurels « Domaine », « Sous-domaine »,
« Sujet » et « Idée dominante », les explications et l’interface Admin restent
en français. La langue d’affichage du jeu demeure choisie par le joueur.

Les données intellectuelles françaises déjà persistées ne sont pas converties
par cette décision documentaire. Elles sont seulement inventoriées pour un
travail technique ultérieur, après validation terminale du présent contrat.

---

# 1. Hiérarchie des sources de vérité

```text
00_ConstitutionCognitive.md
↓
00_ArchitectureRegister.md
↓
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
↓
spécification canonique verrouillée du module concerné
↓
boundary bridge explicitement déclaré si nécessaire
↓
code seulement après verrouillage
```

`00_CURRENT_HANDOFF.md` est un pointeur de reprise, jamais une autorité architecturale.

---

# 2. Méthode officielle

```text
Idée métier
↓
Architecture
↓
Spécification
↓
Architecture Register
↓
VERROUILLAGE
↓
Audit du code
↓
Implantation
↓
Validation
```

Une seule spécification est travaillée à la fois.

---

# 3. Roadmap officielle

```text
01_KernelBlueprint
↓
02_KernelRotationPlanner
↓
03_Taxonomy
  ↳ applique pendant la création Gemini les règles VDI / KEY_STRUCTURE / KEY_LEARNING_DIRECTION
↓
05_QuestionIntent
↓
06_Phase1
↓
07_ValidationPhase1
↓
08_Phase2
↓
09_ValidationPhase2
├── canonique poursuit → 11_ReadyBank
└── suspicion → copie complète → 10_Quarantine
                           ↓ correction ciblée
                    reprise 06/07 ou 08/09
                           ↓
                       11_ReadyBank
                           ↓
             fusion ciblée avec le canonique
```

---

# 4. Tableau de bord courant

| Module | Architecture | Contrat | Implémentation | Validation | Statut |
|---|---:|---:|---:|---:|---|
| 01 KernelBlueprint | **100 % intellectuel v3.2** | **100 %** | à auditer contre DEC-125 et clauses compatibles DEC-122/123/124 | NON | **VERROUILLÉ — DEC-125** |
| 02 KernelRotationPlanner | **100 % intellectuel v4.0 + langue DEC-126** | **100 %** | alignement technique ultérieur | NON | **VERROUILLÉ — DEC-119 + DEC-126** |
| 03 Taxonomy | **100 % intellectuel v1.1 + langue DEC-126** | **100 %** | alignement technique ultérieur | NON | **VERROUILLÉ — DEC-120 + DEC-126** |
| 04 ValidationDominantIdeas | règles absorbées par Taxonomy v1.1 | contrat de règles | aucun moteur autonome | N/A | **SUPERSEDED comme étape autonome — DEC-101** |
| 05 QuestionIntent | **100 % intellectuel v2.3 + langue DEC-126** | **100 %** | alignement technique ultérieur | NON | **VERROUILLÉ — DEC-125 + DEC-126** |
| 06 Phase1 | **100 % intellectuel v1.2** | langue source verrouillée; contrat fonctionnel antérieur conservé | alignement technique ultérieur | NON | **DOCUMENTAIRE — DEC-125 + DEC-126** |
| 07 ValidationPhase1 | **100 % intellectuel v1.2** | langue source verrouillée; contrat fonctionnel antérieur conservé | alignement technique ultérieur | NON | **DOCUMENTAIRE — DEC-125 + DEC-126** |
| 08 Phase2 | source/cibles DEC-126 + frontières DEC-125 | partiel v0.3 | non | non | décisions fonctionnelles détaillées ouvertes |
| 09 ValidationPhase2 | source/cibles DEC-126 + frontières DEC-125 | partiel v0.3 | non | non | décisions fonctionnelles détaillées ouvertes |
| 10 Quarantine | copie complète DEC-125 | partiel v0.2 | non | non | sept slots éditables + reprise Phase1 verrouillées |
| 11 ReadyBank | fusion DEC-125 | partiel v0.3 | non | non | fusion sélective + routage FIFO verrouillés |

---

# 5. Invariant global d’exécution

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

```text
KRP ACTIVE
→ KRP FIN
→ Taxonomy ACTIVE
→ Taxonomy FIN
→ modules suivants
...
→ ReadyBank
→ Factory
→ KRP ACTIVE à nouveau
```

KRP et Taxonomy ne sont jamais actifs simultanément.

---

# 6. Pipeline intellectuel actif

```text
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint canonique
↓
KernelRotationPlanner
  ↳ consomme les faits terminaux Taxonomy déjà en attente
  ↳ exécute ses moteurs internes DOMAIN_EXHAUSTED et DEPTH_EXHAUSTED
  ↳ lit RotationState + DepthNeedMatrix
  ↳ décide seul depth + domain
  ↳ écrit uniquement depth + domain
↓
KRP FIN
↓
Taxonomy
  ↳ gère ses réservoirs
  ↳ écrit subdomain_active + subject_active + dominant_idea_active
  ↳ consomme le même IdeaSlot écrit
  ↳ si et seulement si la dernière Dominant Idea du dernier Subject du Domain vient d’être consommée : persiste le fait terminal destiné à KRP
↓
Taxonomy FIN
↓
QuestionIntent
  ↳ attribue VVVV
  ↳ construit, persiste et verrouille seul le kernel_code complet
↓
FIN PHASE INTELLECTUELLE
```

Le fait Taxonomy n’active pas KRP. Il reste en attente jusqu’au prochain cycle déclenché après ReadyBank.

---

# 7. Ownership global

## KernelBlueprintFactory

```text
propriétaire : blueprint_id
rôle : créer une nouvelle enveloppe
```

## Taxonomy

```text
propriétaire : Banks, occurrences, curseurs et contenu intellectuel
propriétaire : constat et persistance du fait terminal de consommation
peut transmettre : fait terminal rattaché au depth + domain
NON propriétaire : moteur DOMAIN_EXHAUSTED
NON propriétaire : moteur DEPTH_EXHAUSTED
NON propriétaire : rotation
NON propriétaire : fin de tour KRP
NON propriétaire : prochain Depth
```

Taxonomy ne possède et n’émet ni moteur `DOMAIN_EXHAUSTED` ni `DEPTH_EXHAUSTED` dans le contrat actif.

## Frontière de communication

```text
conserve le fait terminal après Taxonomy FIN
n’active pas KRP
ne décide aucune rotation
alimente ultérieurement le moteur interne KRP DOMAIN_EXHAUSTED
```

## ReadyBank / CURRENT_KERNEL_RECEIVED

```text
rôle : déclencher le lifecycle du prochain noyau
NON propriétaire : décision de rotation
```

## DepthNeedMatrix

```text
propriétaire : besoin quantitatif global par Depth
cycle_target
cycle_completed
cycle_remaining
```

## KernelRotationPlanner

```text
AUTORITÉ UNIQUE DE ROTATION
```

KRP décide seul :

- avancer au prochain Domain `VISIBLE` à chaque nouveau Blueprint ;
- consommer le fait terminal en attente dans son moteur interne `DOMAIN_EXHAUSTED` ;
- rendre le Domain concerné `ESTOMPÉ` ;
- exclure les Domaines `ESTOMPÉ` de ses rotations du tour courant ;
- sélectionner le prochain Domain ;
- fermer le tour ;
- incrémenter `cycle_completed` ;
- sélectionner le prochain Depth ;
- revenir vers Depth 2 après Depth 10 si nécessaire ;
- produire HOLD.

---

# 8. Frontière terminale Taxonomy v1.1

Taxonomy ne communique pas un état à chaque noyau.

Dans sa fermeture de sortie :

```text
triplet exact prêt
↓
écriture Blueprint réussie
↓
consommation immédiate du même IdeaSlot
↓
évaluation de l’état final du Domain
```

Si du contenu exploitable reste :

```text
AUCUN FAIT TERMINAL
```

Si cette consommation utilise avec succès la dernière Dominant Idea du dernier Subject encore exploitable de l’occurrence :

```text
FAIT TERMINAL DE DOMAIN
```

Ce fait est persisté et transmis une seule fois pour cette occurrence. Il transporte au minimum l’identité `depth + domain` nécessaire à KRP, mais ne constitue aucune commande de rotation.

Aucun `AVAILABLE` n’est nécessaire.

---

# 9. Application différée par KRP

> **CLAUSE ANTÉRIEURE — SUPERSEDED BY DEC-125, PORTÉE LIMITÉE :** le chemin
> direct `CURRENT_KERNEL_RECEIVED → Factory` ne s’applique que lorsque la file
> Quarantine prête est vide. ReadyBank choisit d’abord une éventuelle copie.

```text
Taxonomy FIN
↓
fait terminal de consommation
↓
fait en attente
↓
KRP INACTIF
↓
... pipeline ...
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
Factory crée NOUVEAU Blueprint
↓
KRP ACTIVE
↓
consomme le fait
↓
moteur interne DOMAIN_EXHAUSTED
↓
VISIBLE → ESTOMPÉ
↓
Domain abstrait/exclu des rotations restantes du tour
```

KRP choisit ensuite seul la rotation.

**Remplacement actif DEC-125 :** à chaque arrivée ReadyBank, le signal choisit
exclusivement `GO` vers la première copie Quarantine prête en FIFO (reprise
Phase1, rien vers KBP), ou `GO` vers KBP si la file est vide. Les deux
destinations ne sont jamais activées.

---

# 10. Cycles KRP v4.0

## DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

## DomainCycle

| Identité stable | Valeur intellectuelle anglaise |
|---|---|
| GEO | Geography |
| HIS | History |
| FAU | Wildlife |
| ART | Art |
| SPO | Sports |
| CIN | Cinema |
| CUI | Cuisine |
| SCI | Science |

Le DomainCycle conserve son ordre et ses codes techniques actuels. Les valeurs
anglaises sont intellectuelles et ne deviennent jamais des clés de rotation.

`General` est absent de cette table parce qu’il n’est pas un Domaine canonique
de création. C’est un mode Shuffle gameplay qui sélectionne et mélange des
questions issues de ces huit Domaines. Aucun Blueprint, réservoir Taxonomy ou
contenu Phase1 n’est créé sous `General`.

## Rotation normale

```text
nouveau Blueprint
→ KRP avance au prochain Domain VISIBLE du DomainCycle
→ les Domaines ESTOMPÉ sont ignorés
```

## Avec fait terminal en attente

```text
fait terminal Taxonomy en attente
→ KRP consomme le fait
→ moteur interne DOMAIN_EXHAUSTED
→ VISIBLE → ESTOMPÉ
→ KRP exclut le Domain du tour
→ KRP choisit le prochain Domain VISIBLE
```

## Fin de tour

```text
8 Domaines ESTOMPÉ
→ KRP ferme SON tour
→ cycle_completed[depth] += 1 exactement une fois
→ DepthNeedMatrix
→ prochain Depth nécessaire
```

Taxonomy ne déclare pas la fin du tour.

## Depth 10

```text
Depth 10 terminé
→ Matrix
→ prochain Depth encore nécessaire
```

Retour possible à 2.

## HOLD

Seulement lorsque toutes les cibles globales sont satisfaites.

---

# 11. Cibles DepthNeedMatrix

```text
2  = 250
4  = 300
6  = 350
7  = 350
8  = 350
9  = 250
10 = 100
```

```text
cycle_remaining[depth]
= max(0, cycle_target[depth] - cycle_completed[depth])
```

---

# 12. Persistance KRP

Transitions KRP :

```text
fait terminal Taxonomy consommé
→ moteur interne DOMAIN_EXHAUSTED
→ VISIBLE → ESTOMPÉ

8 ESTOMPÉ
→ OPEN → CLOSED
→ cycle_completed += 1
```

Politique d’échec technique :

```text
1 tentative + 3 retries
KRP-002 — DOMAIN_ROTATION_STATE_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
BLOCKED après échec persistant
```

---

# 13. Blueprint — ownership Section intellectuelle

```text
blueprint_id            → KernelBlueprintFactory
depth                   → KernelRotationPlanner
domain                  → KernelRotationPlanner
subdomain_active        → Taxonomy
subject_active          → Taxonomy
dominant_idea_active    → Taxonomy
kernel_code             → QuestionIntent
```

KRP sort avec :

```text
blueprint_id = rempli
depth = rempli
domain = rempli
Taxonomy slots = null
kernel_code = null
```

---

# 14. Taxonomy — état documentaire officiel

`03_Taxonomy v1.1` est verrouillée par `DEC-120 — OFFICIAL`.

Sa frontière KRP corrigée est active comme contrat documentaire :

```text
Taxonomy termine sa consommation
↓
Taxonomy persiste le fait terminal destiné à KRP
↓
ce fait demeure en attente
↓
au prochain nouveau Blueprint, KRP consomme ce fait
↓
KRP déclenche son moteur interne DOMAIN_EXHAUSTED
↓
Domain VISIBLE → ESTOMPÉ
```

L’implantation Taxonomy demeure séparée et ne commence qu’après l’implantation et la validation terminale de KRP v4.0.

---

# 15. ValidationDominantIdeas

Gemini utilise les règles `ValidationDominantIdeas` **pendant** la création des Dominant Ideas à l’intérieur du travail Taxonomy. Ce n’est pas un moteur autonome postérieur relisant le Blueprint.

---

# 16. Phase1 et ValidationPhase1 actives

Références exclusives :

```text
specifications/06_Phase1.md v1.2
specifications/07_ValidationPhase1.md v1.2
DEC-125 + DEC-126
```

Phase1 reçoit le même Blueprint finalisé par QuestionIntent et remplit sept CognitiveSlots source autonomes dans un appel de création structuré. Aucun slot n’est le master des autres.

Règles terminales :

- texte de la question lisible en huit secondes ou moins;
- réponse et distracteurs QCM sous forme d’unités courtes représentant chacune une seule idée (mot, nom propre, valeur courte ou expression courte);
- choix QCM de même catégorie sémantique, forme grammaticale et concision comparables;
- SV explicatif lisible en trente secondes ou moins;
- difficulté portée par la connaissance ou le raisonnement, jamais par la longueur;
- cohérence question/réponse/choix/SV jusqu’au sous-domaine;
- écriture atomique par CognitiveSlot;
- sept mécanismes cognitifs distincts;
- aucun `question_code`, `COG` ou `VAR`;
- ValidationPhase1 décide PASS ou SUSPICION par slot;
- source non PASS non traduite;
- copie Quarantine complète, reprise Phase1, puis contrôles concernés par slot.

---

# 17. État opérationnel immédiat

Le contrat documentaire actif devient `06_Phase1 v1.2`, sous DEC-125 et
DEC-126. Son alignement technique reste un travail ultérieur distinct.

KRP v4, Taxonomy v1.1 et QuestionIntent constituent les frontières amont déjà présentes sur la branche officielle. `KernelCodeEngine`, s'il existe, est uniquement un mécanisme interne de QuestionIntent. Ils ne doivent pas être réimplantés dans ce bloc.

Prochaine opération :

```text
ALIGN-AUDIT-06-v1.2
↓
audit du code Phase1 réel contre 06 v1.2
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
↓
si aucun UNRESOLVED architectural
↓
patch minimal Phase1
↓
tests contractuels 06 v1.2
↓
ALIGN-AUDIT-07-v1.2
```

---

# 18. Sources actives KRP

```text
00_ArchitectureRegister.md — DEC-119 OFFICIAL / DEC-120 OFFICIAL
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
specifications/02_KernelRotationPlanner.md v4.0
specifications/03_Taxonomy.md v1.1
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

DEC-115 à DEC-118 : REJECTED, historique seulement. Anciennes versions KRP : historiques/superseded.


## Référence canonique QuestionIntent

```text
01 → specifications/01_KernelBlueprint.md v3.2 / DEC-125
05 → specifications/05_QuestionIntent.md v2.3 / DEC-125 + DEC-126
```

KRP écrit uniquement `depth + domain` et Taxonomy uniquement son triplet métier. QuestionIntent attribue le compteur base36 `VVVV`, indépendant par bassin `Depth + Domain`, puis construit, persiste et verrouille le `kernel_code` complet. Il n'existe aucune projection progressive du code. `KernelCodeEngine`, s'il existe techniquement, demeure interne à QuestionIntent et sans ownership autonome. Phase1 remplit ensuite les sept CognitiveSlots du même Blueprint. L’état cognitif joueur `00n→11o` reste externe au Blueprint.


## Référence canonique DEC-122

```text
01 → specifications/01_KernelBlueprint.md v3.2 — DEC-125
06 → specifications/06_Phase1.md v1.2 — CONTRAT DOCUMENTAIRE
07 → specifications/07_ValidationPhase1.md v1.2 — CONTRAT DOCUMENTAIRE
08 → specifications/08_Phase2.md v0.3 — MODULE À COMPLÉTER
09 → specifications/09_ValidationPhase2.md v0.3 — MODULE À COMPLÉTER
10 → specifications/10_Quarantine.md v0.2
11 → specifications/11_ReadyBank.md v0.3
```

## Inventaire documentaire des surfaces persistantes à auditer ultérieurement

Cet inventaire identifie des surfaces; il ne constate, ne compte, ne convertit
et ne réécrit aucune donnée :

- `kernel_blueprint_runs` : `domain_code`, `subdomain_active`,
  `subject_active`, `dominant_idea_active`;
- `taxonomy_v11_subdomains.subdomain_name`;
- `taxonomy_v11_subjects.subject_name`;
- `taxonomy_v11_ideas.idea_value`;
- `taxonomy_v11_generation_memory` : candidats, PASS et FAIL JSON pouvant
  contenir les mêmes valeurs intellectuelles;
- `kernel_blueprint_cognitive_slots.source` et `translations`;
- `kernel_quarantine_work_copies` : `domain_code`, `subdomain_active`,
  `subject_active`, `dominant_idea_active`;
- `kernel_quarantine_work_copy_slots.source` et `translations`;
- `question_intents.frame_en`, stockage legacy non autoritaire;
- `question_translations`, stockage relationnel legacy de l’ancienne banque.

La présence réelle, la langue et le volume des lignes de ces surfaces restent à
auditer lors du travail technique autorisé après le PASS documentaire terminal.

> **Ancienne formulation ci-dessous — SUPERSEDED BY DEC-125 :** elle décrivait
> une reprise uniquement ciblée et la poursuite de la position suspecte.

Le Blueprint canonique atteint ReadyBank. Une suspicion crée une copie complète Quarantine avec ciblage structuré affichable en rouge. La copie corrigée reprend uniquement les phases nécessaires puis fusionne avec le canonique dans ReadyBank pour remplacer/corriger/remplir les slots ciblés ou vides.

**Remplacement actif :** la position canonique suspecte est vidée; contenu et
findings restent dans la copie complète. Celle-ci repart en Phase1, puis les
slots conformes continuent et les échoués restent vides. ReadyBank fusionne
seulement les slots réussis par `blueprint_id + cognitive_type`; son arrivée
émet `CURRENT_KERNEL_RECEIVED` vers la première copie prête en FIFO, sinon
vers KBP, jamais les deux.
