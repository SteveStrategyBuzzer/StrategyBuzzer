# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.4.0-krp-ownership  
**Date :** 2026-08-23  
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version intègre `DEC-115` et `02_KernelRotationPlanner v3.4`. Elle remplace la frontière v3.3 où Taxonomy produisait des signaux d’épuisement vers KRP.

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
working/ du seul module actif / boundary bridge explicitement déclaré
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
| 02 KernelRotationPlanner | **100 % intellectuel v3.4** | **100 %** | travail v3.3 local à réauditer | NON | **VERROUILLÉ — DEC-115** |
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
  ↳ lit RotationState + DepthNeedMatrix + réalité Taxonomy disponible
  ↳ décide seul depth + domain
  ↳ écrit uniquement depth + domain
↓
Taxonomy
  ↳ gère ses réservoirs
  ↳ écrit subdomain_active + subject_active + dominant_idea_active
  ↳ expose la réalité de ce qui lui reste à exploiter
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
propriétaire : vérité de ce qu’il reste réellement à exploiter
NON propriétaire : rotation Domain/Depth
NON propriétaire : fin de tour KRP
```

Taxonomy n’émet plus de commande contractuelle :

```text
DOMAIN_EXHAUSTED
DEPTH_EXHAUSTED
```

vers KRP.

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
- estomper le Domain courant ;
- sélectionner le prochain Domain ;
- fermer le tour ;
- incrémenter cycle_completed ;
- sélectionner le prochain Depth ;
- revenir vers Depth 2 après Depth 10 si nécessaire ;
- produire HOLD.

---

# 7. Flow lifecycle canonique

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
  réalité Taxonomy du territoire précédent/courant
↓
KRP applique SON contrat de rotation
↓
fillRotation(depth, domain)
↓
Taxonomy reçoit le même Blueprint
```

La création d’un nouveau Blueprint n’implique pas automatiquement un changement de Domain.

---

# 8. KRP v3.4 — règles centrales

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

## Domain encore exploitable

```text
Taxonomy expose : contenu restant
↓
KRP garde le même depth + domain
```

## Domain épuisé en réalité

```text
Taxonomy expose : aucun contenu exploitable restant
↓
KRP persiste VISIBLE → ESTOMPÉ
↓
KRP choisit le prochain Domain
```

## Fin du tour

```text
8 Domaines ESTOMPÉ
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

# 9. Cibles DepthNeedMatrix

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

# 10. Persistance KRP

Les transitions appartiennent à KRP :

```text
VISIBLE → ESTOMPÉ
OPEN → CLOSED
cycle_completed += 1
```

Elles sont persistées avant progression.

Politique d’échec technique :

```text
1 tentative + 3 retries
KRP-002 — DOMAIN_ROTATION_STATE_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
BLOCKED après échec persistant
```

---

# 11. Blueprint — ownership Section intellectuelle

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

# 12. Taxonomy — état documentaire après DEC-115

`03_Taxonomy v1.0` contient encore des formulations maintenant superseded où Taxonomy produit `DOMAIN_EXHAUSTED/DEPTH_EXHAUSTED`.

Ces formulations ne sont plus actives.

Boundary bridge temporaire autoritatif pour la frontière :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-115.md
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

Aucune implantation Taxonomy ne doit être poursuivie depuis la frontière v1.0 superseded.

---

# 13. ValidationDominantIdeas

La position reste inchangée : Gemini utilise les règles `ValidationDominantIdeas` **pendant** la création des Dominant Ideas à l’intérieur du travail Taxonomy. Ce n’est pas un moteur autonome postérieur relisant le Blueprint.

---

# 14. Phases 1–2

Leurs interfaces détaillées restent réservées et non spécifiées. Elles ne doivent pas être inventées dans KRP.

---

# 15. État opérationnel immédiat

Le Build Replit `IMPL-02-01` démarré contre v3.3 est **PAUSED**.

Il ne doit pas être repris tel quel.

Prochaine opération :

```text
DEC-115 + KRP v3.4
↓
réaudit ciblé des modifications locales déjà faites par Replit
↓
KEEP / REVERT / MODIFY
↓
reprise contrôlée IMPL-02-01
↓
tests contractuels v3.4
```

---

# 16. Sources actives KRP

```text
00_ArchitectureRegister.md — DEC-115
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
specifications/02_KernelRotationPlanner.md v3.4
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

Anciennes v3.3/v3.2/ALIGN-02 : historiques/superseded.
