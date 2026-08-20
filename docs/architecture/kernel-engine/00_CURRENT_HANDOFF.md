# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-20  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Module actif :** `02_KernelRotationPlanner`  
**Bloc actif :** `AUDIT-02-00`  
**Dernière décision structurante :** `DEC-114`

> Ce fichier est un pointeur de reprise. Il n’a aucune autorité architecturale propre. En cas de contradiction : `00_ArchitectureRegister.md + spécification canonique verrouillée + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md` priment.

---

# 1. État canonique KRP

```text
specifications/02_KernelRotationPlanner.md
Version : 3.3
Statut : VERROUILLÉ — PARTIE INTELLECTUELLE
Architecture intellectuelle : 100 %
Contrat intellectuel : 100 %
Implémentation : À AUDITER
Validation code : NON
DEC : DEC-114
```

Certificat :

```text
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

---

# 2. Vérité KRP à reconstruire avant tout audit/code

## Frontière

```text
CURRENT_KERNEL_RECEIVED
↓
lifecycle/orchestration externe
↓
KernelBlueprintFactory
↓
NOUVEAU Blueprint + blueprint_id
↓
KernelRotationPlanner
↓
RotationState + DepthNeedMatrix
↓
sélection depth + domain
↓
fillRotation(depth, domain)
↓
persistance
↓
FIN KRP
↓
porte vers Taxonomy
```

KRP n’écrit que :

```text
depth
domain
```

KRP ne crée pas Blueprint, Subdomain, Subject, Dominant Idea ou `kernel_code`.

---

# 3. Décisions KRP obligatoires

## DEC-094

Double autorité :

```text
Taxonomy / DEPTH_EXHAUSTED
= fin réelle du tour intellectuel

DepthNeedMatrix
= besoin quantitatif global

KRP
= combine les deux
```

Cibles de tours :

```text
2  = 250
4  = 300
6  = 350
7  = 350
8  = 350
9  = 250
10 = 100
```

DepthCycle :

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

Retour `10→2` possible si un besoin subsiste.

## DEC-107

`DOMAIN_EXHAUSTED` n’est valide qu’après garde Taxonomy :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Sinon `TAX-003` bloque le signal avant KRP.

KRP applique :

```text
VISIBLE → ESTOMPÉ
```

Aucun retour `ESTOMPÉ→VISIBLE` dans le même tour.

## DEC-108

```text
DEPTH_EXHAUSTED(depth)
= FIN DU TOUR
≠ fin définitive du besoin global du Depth
```

Après commit valide :

```text
cycle_completed[depth] += 1
```

exactement une fois.

## DEC-111

- transition persistée avant progression ;
- répétition après commit = `NO-OP` ;
- 1 tentative + 3 retries ;
- `KRP-002` / `KRP-003` en échec persistant ;
- aucune rotation nouvelle depuis un état non commité.

---

# 4. DomainCycle de création

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

---

# 5. Porte Taxonomy

Sortie normale KRP :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

Ce même nouveau Blueprint est recevable par Taxonomy.

Le retour informationnel Taxonomy :

```text
DOMAIN_EXHAUSTED
DEPTH_EXHAUSTED
```

est distinct du déclenchement de création du Blueprint suivant.

---

# 6. PRODUCTION_ON_HOLD

Seulement si :

```text
pour tous les Depths :
cycle_completed[depth] >= cycle_target[depth]
```

et aucune transition KRP n’attend de commit.

La simple fermeture du Depth 10 ne suffit pas.

---

# 7. Documents KRP à NE PLUS utiliser comme vérité

```text
docs/architecture/02_KernelRotationPlanner.md
→ HISTORIQUE v3.2

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED
```

La source unique est :

```text
specifications/02_KernelRotationPlanner.md v3.3
```

---

# 8. Extension future Phases 1–2

KRP est **complet pour la partie intellectuelle**.

Les éventuelles interfaces requises plus tard par Phase1/Phase2 sont :

```text
RÉSERVÉES
NON SPÉCIFIÉES
NON BLOQUANTES pour AUDIT-02-00
```

Une extension future exige :

```text
spécification propriétaire Phase concernée
↓
nouvelle version KRP
↓
nouvelle DEC
```

Aucune logique Phase1/Phase2 ne doit être inventée pendant l’audit intellectuel KRP.

---

# 9. Prochaine opération EXACTE — AUDIT-02-00

Mission :

```text
auditer le code KRP réel contre specifications/02_KernelRotationPlanner.md v3.3
```

Inspecter uniquement ce qui est nécessaire à KRP et ses portes :

```text
KernelRotationPlanner
RotationState
DepthNeedMatrix
Factory → KRP
DOMAIN_EXHAUSTED
DEPTH_EXHAUSTED
KRP → Blueprint.fillRotation(depth, domain)
KRP → porte Taxonomy
PRODUCTION_ON_HOLD
persistance / idempotence
```

Classer chaque élément :

```text
KEEP
MODIFY
REMOVE
MISSING
UNRESOLVED
```

Aucun patch avant la fermeture de l’audit.

---

# 10. Tests futurs KRP — principe simple

Les tests KRP doivent prouver KRP, pas les modules aval :

1. reçoit un nouveau Blueprint déjà créé ;
2. lit RotationState et DepthNeedMatrix ;
3. choisit le bon Depth + Domain ;
4. écrit uniquement `depth + domain` ;
5. respecte `VISIBLE→ESTOMPÉ` ;
6. traite les répétitions en `NO-OP` ;
7. ferme un tour sur `DEPTH_EXHAUSTED` et incrémente une fois ;
8. revient vers un Depth encore nécessaire après 10 ;
9. met HOLD seulement quand tous les besoins sont satisfaits ;
10. laisse une porte valide vers Taxonomy.

Ne pas faire dépendre la validation KRP de l’exécution complète de Taxonomy, QuestionIntent, Phase1 ou Phase2.

---

# 11. DO NOT REDO

Ne pas :

- réouvrir ALIGN-02 ;
- reconstruire KRP depuis v3.2 ;
- remettre `DEPTH_EXHAUSTED` comme fin définitive ;
- supprimer `cycle_target/cycle_completed` du chemin décisionnel ;
- arrêter définitivement après Depth 10 si des besoins restent ;
- faire recevoir directement l’ancien Blueprint à KRP depuis ReadyBank ;
- faire écrire Taxonomy ou kernel_code par KRP ;
- inventer les Phases 1–2 pendant AUDIT-02-00.

---

# 12. Reprise prochain chat

Lire :

1. `START_HERE.md`
2. `00_ConstitutionCognitive.md`
3. `00_ArchitectureRegister.md`
4. `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md`
5. `00_CURRENT_HANDOFF.md`
6. `specifications/01_KernelBlueprint.md` pour la frontière d’entrée
7. `specifications/02_KernelRotationPlanner.md` v3.3
8. `specifications/03_Taxonomy.md` pour la frontière de sortie

Puis reprendre directement :

```text
AUDIT-02-00
```
