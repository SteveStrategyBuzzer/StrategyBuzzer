# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.2.0-recovery  
**Date :** 2026-08-19  
**Statut :** ACTIF — VÉRITÉ GLOBALE COURANTE  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version intègre la fermeture documentaire de `01_KernelBlueprint v2.0`. Elle ne redessine pas les modules suivants ; elle aligne uniquement l’état global sur la spécification canonique désormais verrouillée.

---

# 1. Hiérarchie des sources de vérité

```text
00_ConstitutionCognitive.md
↓
00_ArchitectureRegister.md
↓
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
↓
spécifications verrouillées concernées
↓
working/ du SEUL module actif
↓
code réel, seulement après verrouillage
```

`00_CURRENT_HANDOFF.md` indique le point exact de reprise mais n’a aucune autorité architecturale propre.

`archive/` n’est jamais une source active.

Principe :

```text
UNE RESPONSABILITÉ
=
UN PROPRIÉTAIRE
=
UNE IMPLÉMENTATION ACTIVE
=
UNE SOURCE DE VÉRITÉ
```

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
Audit du code
↓
Implantation
↓
Validation
```

Jamais :

```text
Code existant
↓
Architecture supposée
```

Une seule spécification ou un seul bloc d’implantation est travaillé à la fois.

Après verrouillage d’un module : audit code → micro-blocs d’implantation → validation terminale → module suivant.

---

# 3. Roadmap officielle

```text
01_KernelBlueprint
↓
02_KernelRotationPlanner
↓
03_Taxonomy
↓
04_ValidationDominantIdeas
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
↓
10_Quarantine
↓
11_ReadyBank
```

---

# 4. Tableau de bord courant

| Module | Architecture | Contrat | Implémentation | Validation | Statut actif |
|---|---:|---:|---:|---:|---|
| 01 KernelBlueprint | **100 %** | **100 %** | **20 % — code historique à auditer** | **20 % — ancienne preuve à rejouer** | **VERROUILLÉ v2.0 — AUDIT-01-00 NEXT** |
| 02 KernelRotationPlanner | v3.2 historique ; v3.3 à reconstruire | v3.3 à réécrire | ancienne implantation à auditer plus tard | à refaire contre v3.3 | **EN ATTENTE — 01 doit fermer implantation + validation** |
| 03 Taxonomy | **100 %** | **100 %** | **0 % à auditer/adapter dans son tour** | **0 % code** | **SPÉCIFICATION v1.0 VERROUILLÉE** |
| 04 ValidationDominantIdeas | brides actives | brides actives | non | non | À SPÉCIFIER dans son tour |
| 05 QuestionIntent | **100 %** | **100 %** | **100 %** | **100 %** | verrouillé selon certificat terminal récupéré |
| 06 Phase1 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 07 ValidationPhase1 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 08 Phase2 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 09 ValidationPhase2 | brides seulement | non verrouillé | à auditer plus tard | non | À VENIR |
| 10 Quarantine | frontière connue | non verrouillé | à auditer plus tard | non | À VENIR |
| 11 ReadyBank | frontière connue | non verrouillé | à auditer plus tard | non | À VENIR |

Le prochain travail autorisé est `AUDIT-01-00`.

---

# 5. Pipeline global actif

```text
KernelBlueprintFactory
↓
KernelBlueprint canonique
↓
KernelRotationPlanner
↓
Taxonomy
  ↳ Gemini utilise les règles ValidationDominantIdeas pendant la création des Dominant Ideas
↓
QuestionIntent
↓
Phase1
↓
ValidationPhase1
├── FAIL → Quarantine
↓ PASS
Phase2
↓
ValidationPhase2
├── FAIL → Quarantine
↓ PASS
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
KernelBlueprintFactory crée le Blueprint suivant
↓
KRP remplit la prochaine rotation sur ce NOUVEAU Blueprint
```

Correction majeure de frontière : **KRP ne crée pas le Blueprint.**

ReadyBank ne renvoie pas le Blueprint courant vers KRP pour réécriture.

---

# 6. KernelBlueprint — v2.0 VERROUILLÉ

Source canonique :

```text
specifications/01_KernelBlueprint.md
```

Certificat :

```text
certificates/01_KernelBlueprint/01_KernelBlueprint_CERTIFICAT_VERROUILLAGE.md
```

Architecture Register : DEC-113.

Le Blueprint est l’enveloppe canonique persistante d’un noyau. Il ne décide rien, ne crée aucun contenu intellectuel et ne possède aucun réservoir Taxonomy.

## 6.1 Création

```text
KernelBlueprintFactory
↓
création unique
↓
blueprint_id immuable
↓
Blueprint remis à KRP
```

Le Blueprint n’est jamais recyclé pour une nouvelle rotation.

## 6.2 Section 1 — Création intellectuelle

```text
blueprint_id            → KernelBlueprintFactory

depth                   → KernelRotationPlanner
domain                  → KernelRotationPlanner

subdomain_active        → Taxonomy
subject_active          → Taxonomy
dominant_idea_active    → Taxonomy

kernel_code             → QuestionIntent, propriétaire fonctionnel
                          KernelCodeEngine = mécanisme d’implantation
```

Écritures structurelles normales :

```text
fillRotation(depth, domain)
fillTaxonomy(subdomain, subject, dominantIdea)
fillKernelCode(kernelCode)
```

Section 1 est write-once dans le chemin normal.

## 6.3 Section 2 — création gameplay

Sept CognitiveSlots permanents :

```text
qcm_recognition
qcm_reasoning
qcm_deceptive_trap
tf_recognition_true
tf_recognition_false
tf_reasoning_true
tf_reasoning_false
```

Chaque conteneur porte le payload gameplay `question + réponse(s) + Saviez-vous` selon le contrat futur de Phase1.

## 6.4 Section 3 — traduction

Chaque CognitiveSlot possède un contrat de traduction correspondant 1:1.

Phase2 définit plus tard le schéma linguistique détaillé sans redessiner l’enveloppe permanente.

## 6.5 Lifecycle structurel

```text
CREATED_UNENGAGED
↓ première écriture Taxonomy réussie
ENGAGED_IN_PIPELINE
```

`PRODUCTION_ON_HOLD` n’est pas un état du Blueprint.

## 6.6 Frontière ReadyBank

```text
ReadyBank reçoit Blueprint courant
↓
CURRENT_KERNEL_RECEIVED
↓
KernelBlueprintFactory crée Blueprint suivant
↓
KRP reçoit ce nouveau Blueprint
```

## 6.7 Consommation Taxonomy

DEC-106 :

```text
IdeaSlot sélectionné
=
dominant_idea_active écrit
=
IdeaSlot CONSUMED
```

`CONSUMED` suit uniquement une écriture `fillTaxonomy(...)` réussie.

---

# 7. KernelRotationPlanner — frontière active v3.3 à spécifier plus tard

`02` n’est pas encore ouvert au travail parce que 01 doit d’abord terminer implantation + validation.

Sa future v3.3 devra conserver :

```text
Depth + Domain seulement
```

Il écrit uniquement :

```text
Blueprint.depth
Blueprint.domain
```

Il ne crée pas le Blueprint, ne choisit pas Subdomain/Subject/Dominant Idea et ne lit pas les Banks Taxonomy.

## 7.1 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → retour vers le prochain Depth encore nécessaire
```

## 7.2 Domaines de création

```text
Géographie
Histoire
Faune
Art
Sport
Cinéma
Cuisine
Science
```

`Général` n’est pas un domaine de création intellectuelle.

## 7.3 DepthNeedMatrix

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100

cycle_remaining = max(0, cycle_target - cycle_completed)
```

Double autorité :

- Taxonomy constate la fin intellectuelle réelle du Tour ;
- DepthNeedMatrix conserve le besoin quantitatif global ;
- KRP combine les deux pour sélectionner le prochain Depth nécessaire.

## 7.4 Horloge du Tour

```text
VISIBLE
↓ DOMAIN_EXHAUSTED valide et persisté
ESTOMPÉ
```

Après les 8 Domaines :

```text
Taxonomy → DEPTH_EXHAUSTED(depth)
↓
KRP persiste fermeture
↓
cycle_completed[depth] += 1 exactement une fois
↓
prochain Depth encore nécessaire
```

`DEPTH_EXHAUSTED` = fin d’un Tour, pas satisfaction globale du Depth.

`PRODUCTION_ON_HOLD` seulement lorsque toutes les cibles globales sont satisfaites et qu’aucune transition n’attend de persistance.

## 7.5 Persistance KRP

- signal déjà appliqué → NO-OP ;
- 1 tentative initiale + 3 retries ;
- `KRP-002` / `KRP-003` en échec terminal ;
- aucune nouvelle rotation engagée tant que la transition requise n’est pas commitée.

Décisions structurantes futures : DEC-094, DEC-095 frontière, DEC-108, DEC-111.

---

# 8. Taxonomy — v1.0 VERROUILLÉE

Source canonique : `specifications/03_Taxonomy.md`.

Taxonomy reçoit `depth + domain` fixé et possède ses réservoirs/progression internes.

## 8.1 Bassin

```text
Depth + occurrence du Tour + Domain
```

Occurrence interne à Taxonomy, aucun nouveau slot Blueprint.

Un seul Subdomain officiel par occurrence.

## 8.2 Territoire

Un travail intellectuel viable crée :

```text
1 Subdomain
+
1..50 Subjects PASS
```

50 = maximum, jamais quota.

Subject PASS persistant ; Subject FAIL éphémère dans l’appel courant.

## 8.3 Dominant Ideas

Subjects préparés en lots équilibrés, capacité actuelle 10 par appel.

Gemini utilise les règles VDI pendant la création.

Préparation réussie :

```text
1..5 Dominant Ideas PASS
```

`0 PASS` = anomalie.

LOOKBACK-2 cyclique traverse `10 → nouveau 2`.

Identité PASS :

```text
Depth + Domain + Subdomain + Subject + Dominant Idea
```

## 8.4 Consommation

DEC-106 s’applique immédiatement après `fillTaxonomy(...)` réussi.

## 8.5 Épuisement

Avant `DOMAIN_EXHAUSTED` :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Sinon `TAX-003`.

Après 8 Domaines : `DEPTH_EXHAUSTED(depth)`.

Taxonomy ne modifie jamais `cycle_target/cycle_completed`.

## 8.6 Gemini

- 1 tentative + 3 retries ;
- erreur technique → aucun effet métier ;
- 3 opérations intellectuelles consécutives ayant épuisé leurs tentatives → `INTELLECTUAL_CREATION_UNAVAILABLE` + `BLOCKED` ;
- une réussite remet le compteur à zéro.

---

# 9. ValidationDominantIdeas — à spécifier dans son tour

Frontières déjà actives :

- VDI possède les règles intellectuelles de création/contrôle des Dominant Ideas ;
- Gemini utilise ces règles dans le travail orchestré par Taxonomy ;
- VDI ne lit/écrit pas directement Blueprint ;
- VDI ne possède pas les Banks Taxonomy ;
- Taxonomy fournit contexte/mémoires/exclusions ;
- ne jamais restaurer « génération libre puis deuxième moteur autonome VDI ».

---

# 10. QuestionIntent — verrouillé selon certificat récupéré

Entrée canonique :

```text
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

Seule écriture Blueprint :

```text
kernel_code
```

Identité de forme récupérée :

```text
DD-DO-SUB-SUJ-IDE-VVVV
```

suffixe base36 sur 4 caractères.

QuestionIntent ne modifie aucun des cinq champs intellectuels amont.

---

# 11. Frontières aval connues

## Phase1

Remplit les 7 CognitiveSlots. Son payload métier détaillé est à spécifier avec 06.

## ValidationPhase1

Valide la création Phase1. Ne pas inventer son mode détaillé avant 07.

## Phase2

Remplit la couche de traduction correspondante. Détail à spécifier avec 08.

## ValidationPhase2

Valide les traductions selon 09.

## Quarantine

Station de correction contrôlée, pas quatrième section du Blueprint. Contrat détaillé futur 10.

## ReadyBank

Destination terminale :

```text
ReadyBank reçoit/stocke noyau prêt
↓
CURRENT_KERNEL_RECEIVED
↓
KernelBlueprintFactory crée prochain Blueprint
↓
KRP remplit depth + domain du nouveau Blueprint
```

ReadyBank ne choisit ni ne valide la consommation Taxonomy.

---

# 12. Administration opérationnelle

Admin/Ops est extérieure aux moteurs 01→11. Elle observe/persiste les incidents et les reprises autorisées sans posséder de contenu intellectuel normal.

Référence : `cross-module/AdminOperations_BRIDES_ACTIVE.md`.

---

# 13. Frontières interdites

```text
KRP ne crée pas le Blueprint
KRP ne crée pas Subdomain
KRP ne choisit pas Subject
KRP ne choisit pas Dominant Idea
KRP n’infère pas DOMAIN_EXHAUSTED depuis ses compteurs

Taxonomy ne choisit pas prochain Domain/Depth
Taxonomy ne modifie pas cycle_target/cycle_completed
Taxonomy n’écrit pas kernel_code
Taxonomy ne consomme pas une autre Idea que celle écrite
Taxonomy ne crée pas plusieurs Subdomains dans une occurrence

VDI ne lit/écrit pas directement Blueprint
VDI ne possède pas les Banks Taxonomy

QuestionIntent ne modifie pas les cinq dimensions intellectuelles amont

ReadyBank ne recycle pas le Blueprint courant vers KRP
ReadyBank ne choisit/valide pas la consommation Taxonomy
```

---

# 14. Points ouverts — NE PAS INVENTER

1. `AUDIT-01-00` puis micro-blocs d’implantation/validation de `01_KernelBlueprint v2.0` ;
2. seulement après fermeture totale de 01 : réécriture complète + verrouillage de `02_KernelRotationPlanner v3.3` ;
3. détail interne complet de `04_ValidationDominantIdeas` dans son tour ;
4. contrats complets 06→11 dans leur ordre officiel.

Fermés :

- `01_KernelBlueprint v2.0` Architecture 100 % / Contrat 100 % ;
- ownership Factory → nouveau Blueprint → KRP ;
- ReadyBank → CURRENT_KERNEL_RECEIVED → nouveau Blueprint ;
- Taxonomy v1.0 ;
- occurrence de bassin par Tour ;
- 1 Subdomain par occurrence ;
- SubjectBank max 50 ;
- `1..5 PASS` par Subject préparé ;
- VDI utilisé par Gemini ;
- anti-doublon + LOOKBACK-2 ;
- consommation exacte immédiate ;
- `DOMAIN_EXHAUSTED` + TAX-003 ;
- `DEPTH_EXHAUSTED` = fin d’un Tour ;
- `cycle_target/cycle_completed` = besoins globaux ;
- retries Gemini ;
- QuestionIntent verrouillé selon certificat terminal récupéré.

---

# 15. Architecture Register structurant

```text
DEC-094  fin d’un Tour Taxonomy vs besoin global DepthNeedMatrix/KRP
DEC-095  occurrence de bassin par Tour
DEC-096  un Subdomain par occurrence
DEC-097  création atomique Subdomain + SubjectBank
DEC-098  SubjectBank max 50
DEC-099  Subject PASS persistant / FAIL éphémère
DEC-100  lots équilibrés
DEC-101  VDI utilisé par Gemini
DEC-102  1..5 Dominant Ideas PASS
DEC-103  identité anti-doublon contextualisée
DEC-104  LOOKBACK-2 cyclique
DEC-105  FAIL Bank DI persistante
DEC-106  consommation exacte immédiate
DEC-107  DOMAIN_EXHAUSTED + TAX-003
DEC-108  DEPTH_EXHAUSTED = fin d’un Tour
DEC-109  retry Gemini 1+3
DEC-110  BLOCKED après échecs intellectuels consécutifs
DEC-111  persistance/idempotence KRP
DEC-112  Taxonomy v1.0 verrouillée
DEC-113  KernelBlueprint v2.0 verrouillé
```

`DEC-088` reste `SUPERSEDED` par `DEC-094`.

---

# 16. Références actives

```text
START_HERE.md
00_ConstitutionCognitive.md
00_ArchitectureRegister.md
00_CURRENT_HANDOFF.md
00_DOCUMENTATION_MAP.md

specifications/01_KernelBlueprint.md
specifications/03_Taxonomy.md

working/01_KernelBlueprint/01_KernelBlueprint_RECONSTRUCTION_ACTIVE.md  [CLOSED/PROMOTED pointer]
working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
working/04_ValidationDominantIdeas/04_ValidationDominantIdeas_BRIDES_ACTIVE.md

certificates/01_KernelBlueprint/
certificates/03_Taxonomy/
certificates/05_QuestionIntent/

cross-module/AdminOperations_BRIDES_ACTIVE.md
```

Aucun document d’`archive/` ne doit être utilisé pour réintroduire une architecture supersédée.

---

# 17. Prochain bloc exact

```text
AUDIT-01-00
```

Audit code uniquement. Aucun patch pendant ce bloc.

Après audit : définir les micro-blocs `IMPL-01-XX`, les fermer un par un avec tests, diff et commit, puis produire la validation terminale de 01.

Seulement ensuite ouvrir `02_KernelRotationPlanner`.