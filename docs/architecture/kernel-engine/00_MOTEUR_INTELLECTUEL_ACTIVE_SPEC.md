# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.8.0-krp-v4-taxonomy-v1.1  
**Date :** 2026-08-25  
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version aligne la direction globale sur `DEC-119 — OFFICIAL` (`02_KernelRotationPlanner v4.0`) et `DEC-120 — OFFICIAL` (`03_Taxonomy v1.1`). `DEC-115` à `DEC-118` sont `REJECTED` et restent conservées uniquement comme historique.

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
certificat documentaire applicable
↓
code seulement après verrouillage et audit
```

`00_CURRENT_HANDOFF.md` est le pointeur opérationnel de reprise. Il doit refléter les sources officielles, mais il ne crée aucune règle architecturale.

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
Implantation d’un seul module
↓
Validation
```

Une seule spécification et un seul module sont travaillés à la fois.

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

| Module | Référence officielle | Architecture | Implantation active | Statut |
|---|---|---:|---:|---|
| 01 KernelBlueprint | v2.0 — DEC-113 | verrouillée | intacte | stable |
| 02 KernelRotationPlanner | **v4.0 — DEC-119** | **verrouillée** | **oui, KRP seulement** | module actif |
| 03 Taxonomy | **v1.1 — DEC-120** | **verrouillée** | non, après validation KRP | attente |
| 04 ValidationDominantIdeas | brides actives | à spécifier | non | à venir |
| 05 QuestionIntent | historique verrouillé | historique | non | attente |
| 06 à 11 | frontières partielles | à spécifier/valider | non | à venir |

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

KRP et Taxonomy ne sont jamais implantés ni activés simultanément dans un même bloc.

---

# 6. Pipeline intellectuel actif

```text
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint canonique
↓
KernelRotationPlanner
  ↳ consomme le fait terminal Taxonomy déjà en attente
  ↳ exécute ses moteurs internes DOMAIN_EXHAUSTED et DEPTH_EXHAUSTED
  ↳ lit RotationState + DepthNeedMatrix
  ↳ décide seul depth + domain
  ↳ écrit uniquement depth + domain
↓
KRP FIN
↓
Taxonomy
  ↳ gère ses réservoirs et son contenu intellectuel
  ↳ écrit subdomain_active + subject_active + dominant_idea_active
  ↳ consomme le même IdeaSlot écrit
  ↳ persiste et expose seulement le fait terminal de consommation prévu par v1.1
↓
Taxonomy FIN
↓
QuestionIntent
  ↳ écrit kernel_code
↓
FIN PHASE INTELLECTUELLE
```

Le fait terminal produit à la frontière Taxonomy n’active pas KRP. Il demeure en attente jusqu’au prochain cycle lifecycle.

---

# 7. Ownership global

## KernelBlueprintFactory

```text
propriétaire : blueprint_id
rôle : créer une nouvelle enveloppe avant KRP
```

## Taxonomy

```text
propriétaire : Banks, occurrences, curseurs et contenu intellectuel
propriétaire : persistance du fait terminal de consommation
NON propriétaire : DOMAIN_EXHAUSTED interne KRP
NON propriétaire : DEPTH_EXHAUSTED interne KRP
NON propriétaire : rotation Domain ou Depth
NON propriétaire : fin de tour KRP
```

Taxonomy n’ordonne aucune transition KRP.

## ReadyBank / CURRENT_KERNEL_RECEIVED

```text
rôle : déclencher le lifecycle du prochain noyau
NON propriétaire : décision de rotation
```

## DepthNeedMatrix

```text
propriétaire KRP : besoin quantitatif global par Depth
cycle_target
cycle_completed
cycle_remaining
```

## KernelRotationPlanner

```text
AUTORITÉ UNIQUE DE ROTATION
```

KRP décide seul :

- consommer le fait terminal en attente ;
- exécuter `DOMAIN_EXHAUSTED` en interne ;
- rendre le Domain concerné `ESTOMPÉ` de façon idempotente ;
- sélectionner le prochain Domain `VISIBLE` ;
- exécuter `DEPTH_EXHAUSTED` en interne lorsque le tour est terminé ;
- fermer le tour et incrémenter `cycle_completed` exactement une fois ;
- consulter `DepthNeedMatrix` ;
- sélectionner le prochain Depth encore nécessaire ;
- produire HOLD uniquement lorsque tous les besoins sont satisfaits.

---

# 8. Frontière terminale Taxonomy v1.1

Taxonomy ne transmet ni commande de rotation ni événement métier interne KRP.

Dans sa fermeture de sortie :

```text
triplet exact prêt
↓
écriture Blueprint réussie
↓
consommation immédiate du même IdeaSlot
↓
persistance du fait terminal prévu par Taxonomy v1.1
```

KRP consomme ultérieurement ce fait terminal et en déduit sa propre transition interne `DOMAIN_EXHAUSTED`.

---

# 9. Application différée par KRP

```text
Taxonomy FIN
↓
fait terminal persistant
↓
KRP INACTIF
↓
... pipeline ...
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
Factory crée un NOUVEAU Blueprint
↓
KRP ACTIVE
↓
consomme le fait terminal
↓
DOMAIN_EXHAUSTED interne
↓
VISIBLE → ESTOMPÉ
↓
rotation KRP
```

Le transport du fait terminal appartient à la frontière contractuelle Taxonomy v1.1; sa transformation en transition de rotation appartient exclusivement à KRP v4.0.

---

# 10. Cycles KRP v4.0

## DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → 2
```

KRP saute tout Depth dont le besoin global est déjà satisfait.

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

À chaque nouveau Blueprint, KRP sélectionne le prochain Domain `VISIBLE` du cycle. Un Domain `ESTOMPÉ` est ignoré pour le reste du tour courant.

## Fin de tour

```text
dernier Domain VISIBLE épuisé
↓
DOMAIN_EXHAUSTED interne
↓
KRP ferme SON tour exactement une fois
↓
cycle_completed[depth] += 1 exactement une fois
↓
DEPTH_EXHAUSTED interne
↓
DepthNeedMatrix
↓
prochain Depth nécessaire
```

Taxonomy ne déclare ni `DOMAIN_EXHAUSTED` ni `DEPTH_EXHAUSTED` comme commandes adressées à KRP.

## Depth 10

```text
Depth 10 terminé
↓
recherche cyclique depuis Depth 2
↓
prochain Depth dont cycle_remaining > 0
```

## HOLD

HOLD est produit uniquement lorsque toutes les cibles globales sont satisfaites.

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

# 12. Persistance et idempotence KRP

```text
fait terminal consommé
→ DOMAIN_EXHAUSTED interne
→ VISIBLE → ESTOMPÉ exactement une fois

dernier Domain VISIBLE épuisé
→ tour OPEN → CLOSED exactement une fois
→ cycle_completed += 1 exactement une fois
→ DEPTH_EXHAUSTED interne
```

Les replays ne doivent ni répéter une transition ni doubler un compteur.

Politique d’échec technique :

```text
1 tentative + 3 retries
KRP-002 — DOMAIN_ROTATION_STATE_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
BLOCKED après échec persistant
```

---

# 13. Blueprint — ownership de la section intellectuelle

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

Sa frontière KRP corrigée est active comme contrat documentaire. Son implantation demeure interdite tant que l’implantation et la validation terminale de KRP v4.0 ne sont pas terminées.

Les références Taxonomy antérieures incompatibles restent historiques et ne dirigent aucun code actif.

---

# 15. ValidationDominantIdeas

Gemini utilise les règles `ValidationDominantIdeas` pendant la création des Dominant Ideas à l’intérieur du travail Taxonomy. Ce n’est pas un moteur autonome postérieur relisant le Blueprint.

---

# 16. Phases 1–2

Leurs interfaces détaillées restent réservées et non spécifiées. Elles ne doivent pas être inventées dans KRP.

---

# 17. État opérationnel immédiat

```text
KRP v4.0 verrouillé — DEC-119
↓
audit KRP déjà effectué
↓
implantation KRP seulement, par micro-blocs
↓
tests contractuels KRP
↓
validation terminale KRP
↓
Taxonomy v1.1 seulement après
```

Le code de sauvegarde `ace19555` n’est ni une source architecturale ni une source d’implantation. Il ne doit pas être consulté, récupéré, cherry-pické ou copié.

Le premier bloc doit être strictement borné avant patch. `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` sont des responsabilités internes KRP distinctes et peuvent être implantées dans des micro-blocs séparés.

---

# 18. Sources actives

```text
00_ArchitectureRegister.md
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
00_CURRENT_HANDOFF.md
specifications/01_KernelBlueprint.md v2.0 — DEC-113
specifications/02_KernelRotationPlanner.md v4.0 — DEC-119
specifications/03_Taxonomy.md v1.1 — DEC-120
certificats documentaires applicables
```

`DEC-115` à `DEC-118` : `REJECTED`, historique seulement.  
`DEC-114` et les versions KRP antérieures : superseded/historiques.  
Aucune ancienne référence ne peut diriger l’implantation active.
