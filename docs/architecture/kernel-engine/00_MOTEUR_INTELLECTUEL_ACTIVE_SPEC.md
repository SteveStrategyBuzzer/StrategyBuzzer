# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.6.0-krp-sequential-boundary  
**Date :** 2026-08-23  
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version intègre `DEC-117` et `02_KernelRotationPlanner v3.6`. Elle verrouille la règle : **un seul module métier actif à la fois**. Taxonomy émet le fait `DOMAIN_EXHAUSTED(depth,domain)` à la fin de son travail; KRP ne l'applique qu'à sa prochaine activation après ReadyBank → Factory → nouveau Blueprint.

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

| Module | Architecture | Contrat | Implémentation | Validation | Statut |
|---|---:|---:|---:|---:|---|
| 01 KernelBlueprint | 100 % intellectuel | 100 % | Section 1 alignée | terminale globale différée | contrat d’entrée disponible |
| 02 KernelRotationPlanner | **100 % intellectuel v3.6** | **100 %** | travail local v3.3/v3.4/v3.5 à réauditer | NON | **VERROUILLÉ — DEC-117** |
| 03 Taxonomy | détails internes historiques utiles | frontière KRP v1.0 superseded | non à poursuivre maintenant | NON | **À RÉÉCRIRE v1.1 dans son tour** |
| 04 ValidationDominantIdeas | brides actives | règles utilisées par Gemini | non | non | à spécifier |
| 05 QuestionIntent | historique verrouillé | historique verrouillé | historique | historique | verrouillé selon certificat |
| 06 Phase1 | brides seulement | non verrouillé | non | non | à venir |
| 07 ValidationPhase1 | brides seulement | non verrouillé | non | non | à venir |
| 08 Phase2 | brides seulement | non verrouillé | non | non | à venir |
| 09 ValidationPhase2 | brides seulement | non verrouillé | non | non | à venir |
| 10 Quarantine | frontière connue | non verrouillé | à auditer plus tard | non | à venir |
| 11 ReadyBank | frontière lifecycle connue | non verrouillé | à auditer plus tard | non | à venir |

---

# 5. Invariant d'exécution global

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

Séquence :

```text
KRP ACTIVE
↓
KRP FIN
↓
Taxonomy ACTIVE
↓
Taxonomy FIN
↓
QuestionIntent / autres modules selon pipeline
...
↓
ReadyBank
↓
Factory
↓
KRP ACTIVE à nouveau
```

État interdit :

```text
KRP ACTIVE + Taxonomy ACTIVE
```

---

# 6. Pipeline intellectuel actif

```text
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint canonique
↓
KernelRotationPlanner
  ↳ consomme les faits Domain vides en attente
  ↳ lit RotationState + DepthNeedMatrix
  ↳ décide seul depth + domain
  ↳ écrit uniquement depth + domain
↓
KRP FIN
↓
Taxonomy
  ↳ gère ses réservoirs
  ↳ écrit subdomain_active + subject_active + dominant_idea_active
  ↳ si le Domain devient réellement vide : émet DOMAIN_EXHAUSTED(depth,domain) à la fin de son travail
↓
Taxonomy FIN
↓
QuestionIntent
  ↳ écrit kernel_code
↓
FIN PHASE INTELLECTUELLE
```

Le fait Taxonomy n'active pas KRP. Il reste en attente jusqu'au prochain cycle déclenché après ReadyBank.

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
propriétaire : constat de vacuité du Domain qu'il vient de travailler
peut émettre : DOMAIN_EXHAUSTED(depth,domain)
signification : « ce Domain est vide »
NON propriétaire : rotation
NON propriétaire : fin de tour KRP
NON propriétaire : prochain Depth
```

Taxonomy n'émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

## Frontière de communication

```text
conserve le fait DOMAIN_EXHAUSTED après Taxonomy FIN
n'active pas KRP
ne décide aucune rotation
```

Le mécanisme technique n'est pas contractuel.

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

- conserver le même Domain ;
- rendre un Domain `ESTOMPÉ` à partir d'un fait en attente ;
- exclure les Domaines `ESTOMPÉ` de ses rotations du tour courant ;
- sélectionner le prochain Domain ;
- fermer le tour ;
- incrémenter `cycle_completed` ;
- sélectionner le prochain Depth ;
- revenir vers Depth 2 après Depth 10 si nécessaire ;
- produire HOLD.

---

# 8. Communication Taxonomy → KRP

À la fin de Taxonomy :

```text
Taxonomy FIN
↓
si Domain vide : DOMAIN_EXHAUSTED(depth,domain)
↓
fait en attente
↓
KRP INACTIF
```

Signification :

```text
CE DOMAIN EST VIDE
```

Aucun autre effet métier immédiat.

Au prochain cycle seulement :

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
Factory crée NOUVEAU Blueprint
↓
KRP ACTIVE
↓
consomme le fait en attente
↓
VISIBLE → ESTOMPÉ
↓
Domain exclu des rotations restantes du tour
```

---

# 9. Flow lifecycle canonique

```text
noyau courant termine
↓
ReadyBank reçoit le noyau
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle
↓
Factory crée NOUVEAU Blueprint
↓
KRP reçoit ce Blueprint
↓
KRP charge RotationState
↓
KRP consomme faits DOMAIN_EXHAUSTED en attente
↓
KRP consulte DepthNeedMatrix si nécessaire
↓
KRP applique SON contrat de rotation
↓
fillRotation(depth,domain)
↓
KRP FIN
↓
Taxonomy peut devenir ACTIVE
```

---

# 10. KRP v3.6 — règles centrales

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

`Général` n'est pas un domaine de création.

## Domain sans fait d'épuisement

```text
Domain = VISIBLE
→ même depth + domain au prochain Blueprint
```

## Domain avec fait d'épuisement en attente

```text
DOMAIN_EXHAUSTED
→ à la prochaine activation KRP : VISIBLE → ESTOMPÉ
→ ESTOMPÉ = Domain abstrait/exclu des rotations restantes du tour
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
fait DOMAIN_EXHAUSTED consommé
→ VISIBLE → ESTOMPÉ

8 ESTOMPÉ
→ OPEN → CLOSED
→ cycle_completed += 1
```

Politique d'échec technique :

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

# 14. Taxonomy — état documentaire après DEC-117

`03_Taxonomy v1.0` reste historique sur sa frontière KRP.

Boundary bridge actif :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-117.md
```

Quand le module 03 sera repris :

```text
reconstruction complète
↓
03_Taxonomy v1.1
↓
nouvelle DEC
↓
verrouillage
```

---

# 15. ValidationDominantIdeas

Gemini utilise les règles `ValidationDominantIdeas` pendant la création des Dominant Ideas à l'intérieur du travail Taxonomy. Ce n'est pas un moteur autonome postérieur relisant le Blueprint.

---

# 16. Phases 1–2

Leurs interfaces détaillées restent réservées et non spécifiées. Elles ne doivent pas être inventées dans KRP.

---

# 17. État opérationnel immédiat

Le Build Replit `IMPL-02-01` reste **PAUSED**.

Prochaine opération :

```text
DEC-117 + KRP v3.6
↓
réaudit ciblé des modifications locales déjà faites
↓
KEEP / REVERT / MODIFY / MISSING
↓
reprise contrôlée IMPL-02-01
↓
tests contractuels v3.6
```

---

# 18. Sources actives KRP

```text
00_ArchitectureRegister.md — DEC-117
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
specifications/02_KernelRotationPlanner.md v3.6
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

Anciennes v3.5/v3.4/v3.3/v3.2/ALIGN-02 : historiques/superseded.
