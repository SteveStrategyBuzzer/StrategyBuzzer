# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.9.0-phase1-v1.0  
**Date :** 2026-08-30  
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version conserve `DEC-119 — OFFICIAL`, `02_KernelRotationPlanner v4.0`, `DEC-120 — OFFICIAL`, `03_Taxonomy v1.1`, `DEC-121` et `DEC-122`. Elle active maintenant `06_Phase1 v1.0` et `07_ValidationPhase1 v1.0` comme contrats de Build : création source de sept mécanismes cognitifs autonomes, validation officielle par slot et intégration au même KernelBlueprint.

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
| 01 KernelBlueprint | **100 % intellectuel v2.1** | **100 %** | à auditer contre DEC-122 | NON | **VERROUILLÉ — DEC-113 + DEC-122** |
| 02 KernelRotationPlanner | **100 % intellectuel v4.0** | **100 %** | implantation KRP seulement, par micro-blocs | NON | **VERROUILLÉ — DEC-119 OFFICIAL** |
| 03 Taxonomy | **100 % intellectuel v1.1** | **100 %** | non à poursuivre avant validation KRP | NON | **VERROUILLÉ — DEC-120 OFFICIAL** |
| 04 ValidationDominantIdeas | règles absorbées par Taxonomy v1.1 | contrat de règles | aucun moteur autonome | N/A | **SUPERSEDED comme étape autonome — DEC-101** |
| 05 QuestionIntent | **100 % intellectuel v2.2** | **100 %** | à réaligner sur construction progressive | NON | **VERROUILLÉ — DEC-121 + DEC-122** |
| 06 Phase1 | **100 % intellectuel v1.0** | **100 % BUILD-READY** | à auditer/réaligner | NON | **CONTRAT VERROUILLÉ — module actif** |
| 07 ValidationPhase1 | **100 % intellectuel v1.0** | **100 % BUILD-READY** | à auditer/réaligner après/avec 06 | NON | **CONTRAT VERROUILLÉ — frontière active** |
| 08 Phase2 | règles traductions DEC-122 | partiel v0.1 | non | non | traductions imbriquées + reprise ciblée verrouillées |
| 09 ValidationPhase2 | frontière DEC-122 | partiel v0.1 | non | non | signalement traduction + copie complète verrouillés |
| 10 Quarantine | copie complète DEC-122 | partiel v0.1 | non | non | copie rouge structurée + reprise ciblée verrouillées |
| 11 ReadyBank | fusion DEC-122 | partiel v0.1 | non | non | réconciliation canonique + masque joueur verrouillés |

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
QuestionIntent / KernelCodeEngine
  ↳ alloue uniquement VVVV
  ↳ assemble et verrouille le kernel_code dont DD-DO et SUB-SUJ-IDE ont été projetés progressivement par le KernelBlueprint
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

---

# 10. Cycles KRP v4.0

## DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

## DomainCycle

```text
Géographie
→ Histoire
→ Faune
→ Art
→ Sport
→ Cinéma
→ Cuisine
→ Science
```

`Général` n’est pas un domaine de création.

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
specifications/06_Phase1.md v1.0
specifications/07_ValidationPhase1.md v1.0
DEC-122
```

Phase1 reçoit le même Blueprint finalisé par QuestionIntent et remplit sept CognitiveSlots source autonomes dans un appel de création structuré. Aucun slot n’est le master des autres.

Règles terminales :

- question lisible en huit secondes ou moins;
- SV explicatif lisible en trente secondes ou moins;
- difficulté portée par la connaissance ou le raisonnement, jamais par la longueur;
- cohérence question/réponse/choix/SV jusqu’au sous-domaine;
- écriture atomique par CognitiveSlot;
- sept mécanismes cognitifs distincts;
- aucun `question_code`, `COG` ou `VAR`;
- ValidationPhase1 décide PASS ou SUSPICION par slot;
- source non PASS non traduite;
- copie Quarantine complète et reprise ciblée.

---

# 17. État opérationnel immédiat

Le module actif unique devient `06_Phase1 v1.0`.

KRP v4, Taxonomy v1.1 et QuestionIntent/KernelCodeEngine constituent les frontières amont déjà présentes sur la branche officielle. Ils ne doivent pas être réimplantés dans ce bloc.

Prochaine opération :

```text
ALIGN-AUDIT-06-v1.0
↓
audit du code Phase1 réel contre 06 v1.0
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
↓
si aucun UNRESOLVED architectural
↓
patch minimal Phase1
↓
tests contractuels 06 v1.0
↓
ALIGN-AUDIT-07-v1.0
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
05 → specifications/05_QuestionIntent.md v2.2 / DEC-121 + DEC-122
```

Le KernelBlueprint projette progressivement `DD-DO` lors des écritures KRP puis `SUB-SUJ-IDE` lors des écritures Taxonomy. QuestionIntent/KernelCodeEngine alloue uniquement le compteur base36 `VVVV`, indépendant par bassin `Depth + Domain`, puis assemble et verrouille le code final. Phase1 remplit ensuite les sept CognitiveSlots du même Blueprint. L’état cognitif joueur `00n→11o` reste externe au Blueprint.


## Référence canonique DEC-122

```text
01 → specifications/01_KernelBlueprint.md v2.1
06 → specifications/06_Phase1.md v1.0 — BUILD-READY
07 → specifications/07_ValidationPhase1.md v1.0 — BUILD-READY
08 → specifications/08_Phase2.md v0.1
09 → specifications/09_ValidationPhase2.md v0.1
10 → specifications/10_Quarantine.md v0.1
11 → specifications/11_ReadyBank.md v0.1
```

Le Blueprint canonique atteint ReadyBank. Une suspicion crée une copie complète Quarantine avec ciblage structuré affichable en rouge. La copie corrigée reprend uniquement les phases nécessaires puis fusionne avec le canonique dans ReadyBank pour remplacer/corriger/remplir les slots ciblés ou vides.
