# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.5.0-krp-signal-boundary  
**Date :** 2026-08-23  
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version intègre `DEC-116` et `02_KernelRotationPlanner v3.5`. Elle remplace la v3.4 qui faisait lire à KRP une réalité Taxonomy persistée. La frontière correcte est maintenant active : **Taxonomy pousse un fait Domain vide à KRP; KRP reste seul décideur de rotation.**

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
| 02 KernelRotationPlanner | **100 % intellectuel v3.5** | **100 %** | travail local v3.3/v3.4 à réauditer | NON | **VERROUILLÉ — DEC-116** |
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

# 5. Pipeline intellectuel actif

```text
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint canonique
↓
KernelRotationPlanner
  ↳ lit RotationState + DepthNeedMatrix
  ↳ applique les faits Domain vides déjà reçus de Taxonomy
  ↳ décide seul depth + domain
  ↳ écrit uniquement depth + domain
↓
Taxonomy
  ↳ gère ses réservoirs
  ↳ écrit subdomain_active + subject_active + dominant_idea_active
  ↳ lorsqu’un Domain actif est réellement vide : pousse DOMAIN_EXHAUSTED(depth, domain)
↓
QuestionIntent
  ↳ écrit kernel_code
↓
FIN PHASE INTELLECTUELLE
```

---

# 6. Ownership global

## KernelBlueprintFactory

```text
propriétaire : blueprint_id
rôle : créer une nouvelle enveloppe
```

## Taxonomy

```text
propriétaire : ses Banks, occurrences, curseurs et contenu intellectuel
propriétaire : constat factuel de vacuité du Domain actif
NON propriétaire : prochain Domain
NON propriétaire : prochain Depth
NON propriétaire : fin de tour KRP
```

Signal factuel actif :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Il signifie uniquement : les Banks du Domain actif ne contiennent plus de contenu exploitable.

Taxonomy **n’envoie pas** `DEPTH_EXHAUSTED` dans le contrat actif.

## DepthNeedMatrix

```text
propriétaire : besoin quantitatif global par Depth
cycle_target
cycle_completed
cycle_remaining
```

## ReadyBank / CURRENT_KERNEL_RECEIVED

```text
rôle : déclencher le lifecycle du prochain noyau
NON propriétaire : décision de rotation
```

## KernelRotationPlanner

```text
AUTORITÉ UNIQUE DE ROTATION
```

KRP décide seul :

- conserver le même Domain ;
- sélectionner le prochain Domain ;
- fermer le tour ;
- incrémenter `cycle_completed` ;
- sélectionner le prochain Depth ;
- revenir vers Depth 2 après Depth 10 si nécessaire ;
- produire HOLD.

---

# 7. Communication Taxonomy → KRP

```text
Taxonomy travaille le territoire actif
↓
si contenu exploitable reste
→ aucun signal

si Domain réellement vide
→ DOMAIN_EXHAUSTED(depth, domain)
↓
KRP reçoit le fait
↓
KRP persiste VISIBLE → ESTOMPÉ
↓
aucune rotation immédiate
```

La garde avant émission est :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Le signal ne contient jamais le prochain Domain ou Depth.

---

# 8. Flow lifecycle canonique

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
KRP lit :
  RotationState
  DepthNeedMatrix
↓
KRP applique SON contrat de rotation
↓
fillRotation(depth, domain)
↓
Taxonomy reçoit le même Blueprint
```

La création d’un nouveau Blueprint n’implique pas automatiquement un changement de Domain.

---

# 9. KRP v3.5 — règles centrales

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

## Domain encore actif

Sans `DOMAIN_EXHAUSTED` valide reçu :

```text
Domain courant reste VISIBLE
↓
prochain Blueprint
↓
KRP conserve le même depth + domain
```

## Domain vide

```text
Taxonomy pousse DOMAIN_EXHAUSTED
↓
KRP persiste VISIBLE → ESTOMPÉ
↓
attente du prochain Blueprint
```

Au prochain Blueprint :

```text
KRP voit Domain courant ESTOMPÉ
↓
KRP choisit lui-même le prochain Domain VISIBLE
```

## Fin du tour

```text
8 Domaines ESTOMPÉ
↓
prochain Blueprint
↓
KRP ferme SON tour
↓
cycle_completed[depth] += 1 exactement une fois
↓
DepthNeedMatrix
↓
prochain Depth nécessaire
```

Taxonomy ne déclare pas la fin du tour.

## Depth 10

```text
Depth 10 terminé
↓
Matrix
↓
prochain Depth encore nécessaire
```

Retour possible à 2.

## HOLD

Seulement lorsque toutes les cibles globales sont satisfaites.

---

# 10. Cibles DepthNeedMatrix

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

# 11. Persistance KRP

Les transitions appartiennent à KRP :

```text
DOMAIN_EXHAUSTED reçu
→ VISIBLE → ESTOMPÉ

8 ESTOMPÉ au cycle suivant
→ OPEN → CLOSED
→ cycle_completed += 1
```

Elles sont persistées avant progression.

Politique d’échec technique :

```text
1 tentative + 3 retries
KRP-002 — DOMAIN_EXHAUSTED_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
BLOCKED après échec persistant
```

---

# 12. Blueprint — ownership Section intellectuelle

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

# 13. Taxonomy — état documentaire après DEC-116

`03_Taxonomy v1.0` contient des formulations superseded, notamment `DEPTH_EXHAUSTED` et une ownership de rotation trop large.

Boundary bridge actif :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-116.md
```

Quand le module 03 sera officiellement repris :

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

# 14. ValidationDominantIdeas

Gemini utilise les règles `ValidationDominantIdeas` **pendant** la création des Dominant Ideas à l’intérieur du travail Taxonomy. Ce n’est pas un moteur autonome postérieur relisant le Blueprint.

---

# 15. Phases 1–2

Leurs interfaces détaillées restent réservées et non spécifiées. Elles ne doivent pas être inventées dans KRP.

---

# 16. État opérationnel immédiat

Le Build Replit `IMPL-02-01` commencé contre v3.3/v3.4 est **PAUSED**.

Prochaine opération :

```text
DEC-116 + KRP v3.5
↓
réaudit ciblé des modifications locales déjà faites par Replit
↓
KEEP / REVERT / MODIFY
↓
reprise contrôlée IMPL-02-01
↓
tests contractuels v3.5
```

---

# 17. Sources actives KRP

```text
00_ArchitectureRegister.md — DEC-116
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
specifications/02_KernelRotationPlanner.md v3.5
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

Anciennes v3.4/v3.3/v3.2/ALIGN-02 : historiques/superseded.
