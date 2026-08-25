# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.7.0-krp-delta-boundary  
**Date :** 2026-08-23  
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version intègre `DEC-118` et `02_KernelRotationPlanner v3.7`. La frontière active est finale pour KRP : **Taxonomy communique seulement un changement réel de besoin, dans sa fermeture de sortie; KRP ne s’active qu’au prochain nouveau Blueprint.**

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
| 02 KernelRotationPlanner | **100 % intellectuel v3.7** | **100 %** | travail local antérieur à réauditer | NON | **VERROUILLÉ — DEC-118** |
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
  ↳ consomme les faits Domain vides déjà en attente
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
  ↳ si et seulement si le Domain vient de passer « exploitable → vide » : émet DOMAIN_EXHAUSTED(depth,domain)
↓
Taxonomy FIN
↓
QuestionIntent
  ↳ écrit kernel_code
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
propriétaire : constat du changement réel vers Domain vide
peut émettre : DOMAIN_EXHAUSTED(depth,domain)
signification : « ce Domain est vide »
NON propriétaire : rotation
NON propriétaire : fin de tour KRP
NON propriétaire : prochain Depth
```

Taxonomy n’émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

## Frontière de communication

```text
conserve le fait DOMAIN_EXHAUSTED après Taxonomy FIN
n’active pas KRP
ne décide aucune rotation
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

- conserver le même Domain ;
- rendre un Domain `ESTOMPÉ` à partir d’un fait en attente ;
- exclure les Domaines `ESTOMPÉ` de ses rotations du tour courant ;
- sélectionner le prochain Domain ;
- fermer le tour ;
- incrémenter `cycle_completed` ;
- sélectionner le prochain Depth ;
- revenir vers Depth 2 après Depth 10 si nécessaire ;
- produire HOLD.

---

# 8. Frontière Taxonomy delta-only

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

Si le Domain reste exploitable :

```text
AUCUN SIGNAL
```

Si cette consommation provoque :

```text
ENCORE EXPLOITABLE → VIDE
```

alors :

```text
DOMAIN_EXHAUSTED(depth,domain)
```

est émis une seule fois pour cette occurrence.

Aucun `AVAILABLE` n’est nécessaire.

---

# 9. Application différée par KRP

```text
Taxonomy FIN
↓
DOMAIN_EXHAUSTED si changement réel
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
VISIBLE → ESTOMPÉ
↓
Domain abstrait/exclu des rotations restantes du tour
```

KRP choisit ensuite seul la rotation.

---

# 10. Cycles KRP v3.7

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

## Sans fait d’épuisement

```text
Domain courant VISIBLE
→ même depth + domain au prochain Blueprint
```

## Avec fait d’épuisement

```text
DOMAIN_EXHAUSTED en attente
→ KRP : VISIBLE → ESTOMPÉ
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
fait DOMAIN_EXHAUSTED consommé
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

# 14. Taxonomy — état documentaire après DEC-118

`03_Taxonomy v1.0` reste utile pour ses détails intellectuels internes mais sa frontière KRP est superseded.

Boundary bridge actif :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-118.md
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

# 15. ValidationDominantIdeas

Gemini utilise les règles `ValidationDominantIdeas` **pendant** la création des Dominant Ideas à l’intérieur du travail Taxonomy. Ce n’est pas un moteur autonome postérieur relisant le Blueprint.

---

# 16. Phases 1–2

Leurs interfaces détaillées restent réservées et non spécifiées. Elles ne doivent pas être inventées dans KRP.

---

# 17. État opérationnel immédiat

Le Build Replit `IMPL-02-01` commencé contre des versions précédentes est **PAUSED**.

Prochaine opération :

```text
DEC-118 + KRP v3.7
↓
RÉAUDIT-02-v3.7 des modifications locales déjà faites par Replit
↓
KEEP / REVERT / MODIFY / MISSING
↓
reprise contrôlée IMPL-02-01
↓
tests contractuels v3.7
```

---

# 18. Sources actives KRP

```text
00_ArchitectureRegister.md — DEC-118
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
specifications/02_KernelRotationPlanner.md v3.7
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

Anciennes v3.6/v3.5/v3.4/v3.3/v3.2/ALIGN-02 : historiques/superseded.
