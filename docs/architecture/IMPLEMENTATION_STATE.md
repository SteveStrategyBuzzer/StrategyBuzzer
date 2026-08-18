# StrategyBuzzer — IMPLEMENTATION STATE

## État canonique après RECOVERY-01 + RESTORE-02

**Date :** 17 août 2026  
**Dépôt :** `SteveStrategyBuzzer/StrategyBuzzer`  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Baseline moteur restaurée :** `db26047532cfdf5e030c348dba4455f8eb310971`  
**Commit de restauration :** `052eef096ed7ef539f27ca9d84e0c50f4e88799b`

---

# 1. État global

```text
RECOVERY-01             ✅ TERMINÉ
RESTORE-02              ✅ TERMINÉ
NOUVELLE LOGIQUE        ✅ AUCUNE introduite par Recovery/Restore

01_KernelBlueprint      ✅ Partie 1 implantée au niveau de la baseline
02_KernelRotationPlanner
                        ✅ code restauré à la dernière baseline officielle prouvée
                        🟡 nouveau réalignement documentaire requis avant toute évolution
03_Taxonomy             ⛔ aucune nouvelle implantation autorisée avant ALIGN-02
```

---

# 2. Autorité de reprise

Ordre d'autorité :

1. `docs/architecture/00_ArchitectureRegister.md` — décisions `OFFICIAL` non superseded ;
2. dernière spécification verrouillée du module ;
3. code au SHA audité ;
4. tests au SHA audité ;
5. historique Git, mémoires techniques et notes de recherche comme preuves secondaires.

Principe :

> **La spécification pilote le code. Le code ne crée jamais rétroactivement son propre contrat.**

---

# 3. RECOVERY-01 — conclusion

La baseline utilisateur initialement annoncée était :

```text
db260475
working tree clean
branch = replit/intellectual-engine-current-2026-08-16
```

L'audit a retrouvé à cette baseline :

- `01_KernelBlueprint` Partie 1 implantée ;
- `02_KernelRotationPlanner` v3.2 implanté ;
- validation PostgreSQL historique documentée le 14 août : `15/15 PASS` ;
- `IMPASSE-KRP-001` explicitement ouverte à la frontière Taxonomy ;
- handoff historique : prochaine brique documentaire = `03_Taxonomy`.

Mais la branche distante contenait ensuite neuf commits supplémentaires, jusqu'à :

```text
6b82bcf2d9965e4efb16ae6bbf5d854ad18b83bf
```

Ces neuf commits avaient réécrit huit fichiers du module 02 et de ses tests.

---

# 4. Série post-db260 v3.3 — verdict

Commits audités :

```text
d482821b  Align KernelRotationPlanner with active v3.3 rotation contract
900ffa95  Stop module 02 orchestrator at depth and domain assignment
15529c09  Keep rotate command inside module 02 boundary
2bb88895  Route CURRENT_KERNEL_RECEIVED through Blueprint creation boundary
bb87f8a2  Align outbox command with Blueprint creation boundary
a2f36539  Rewrite module 02 orchestrator tests for v3.3 boundary
203e2151  Keep CKR replayable while KRP cannot start next Blueprint
0d9cc046  Rewrite KernelRotationPlanner tests for active v3.3 contract
6b82bcf2  Rewrite CKR boundary tests for module 02 v3.3
```

Le compare GitHub `db260475 → 6b82bcf2` a confirmé :

```text
9 commits
8 fichiers modifiés
aucun document d'architecture modifié
```

Le code v3.3 déclarait notamment :

```text
Contrat actif v3.3 (DEC-094 / DEC-108 / DEC-111)
```

Or `DEC-108` et `DEC-111` n'existent pas dans `00_ArchitectureRegister.md` au HEAD audité.

Le document officiel restait :

```text
02_KernelRotationPlanner.md
Version : 3.2
Statut  : VERROUILLÉ
```

Verdict :

> **REFUS — incohérence architecturale.**

La série post-db260 ne pouvait pas être utilisée comme nouvelle source de vérité sans réconciliation contractuelle.

---

# 5. RESTORE-02 — opération réalisée

Les huit fichiers modifiés par la série v3.3 ont été restaurés **par leurs blobs Git exacts de `db260475`**, sans réécriture manuelle :

```text
app/Console/Commands/QuestionsKernelProcessOutboxCommand.php
app/Console/Commands/QuestionsKernelRotateCommand.php
app/Services/QuestionBank/Rotation/KernelPipelineOrchestrator.php
app/Services/QuestionBank/Rotation/KernelRotationPlanner.php
app/Services/QuestionBank/Rotation/ProcessKernelPipelineOutbox.php
tests/Unit/QuestionBank/Rotation/KernelPipelineOrchestratorTest.php
tests/Unit/QuestionBank/Rotation/KernelRotationPlannerV3Test.php
tests/Unit/QuestionBank/Rotation/ProcessKernelPipelineOutboxTest.php
```

Restauration atomique :

```text
commit 052eef096ed7ef539f27ca9d84e0c50f4e88799b
Restore KRP v3.2 verified contract after recovery audit
```

La branche a été avancée en fast-forward, sans force push.

Validation structurelle après restauration :

```text
compare ced7c7ca → 052eef09
= exactement 8 fichiers restaurés

compare db260475 → 052eef09
= aucun changement moteur/test restant
= seul docs/architecture/IMPLEMENTATION_STATE.md diffère
```

Conclusion :

> Le périmètre moteur/test touché par v3.3 est revenu bit pour bit à `db260475`.

---

# 6. Contrat KRP actuellement restauré

Décisions officielles encore présentes dans le registre :

- `DEC-058` — Blueprint créé par `KernelBlueprintFactory` avant KRP ;
- `DEC-060` — `kernel_received_total` = traçabilité ; `cycle_target/cycle_completed` ne sont pas l'autorité de transition ;
- `DEC-063` — `CURRENT_KERNEL_RECEIVED` = déclencheur de la prochaine rotation ;
- `DEC-068` — KRP n'écrit pas `kernel_code` ;
- `DEC-082` — `DOMAIN_EXHAUSTED` prospectif, autorité Taxonomy ;
- `DEC-083` — `DEPTH_EXHAUSTED` prospectif, autorité Taxonomy ;
- `DEC-092` — terminal Depth 10 → `PRODUCTION_ON_HOLD` ;
- `DEC-093` — CKR seul incrémenteur de `kernel_received_total` ;
- `DEC-094` — DepthCycle `2 → 4 → 6 → 7 → 8 → 9 → 10`, sans retour automatique à 2.

À la baseline restaurée :

```text
2  → 4
4  → 6
6  → 7
7  → 8
8  → 9
9  → 10
10 → PRODUCTION_ON_HOLD
```

`cycle_target/cycle_completed` restent présents dans `DepthNeedMatrix` comme surface legacy, mais ne pilotent pas la transition KRP restaurée.

---

# 7. Validation disponible

Validation historique retrouvée :

```text
PostgreSQL / Neon #159  : 9/9 PASS
PostgreSQL strict #159B : 6/6 PASS
TOTAL                    : 15/15 PASS
```

Couvre notamment :

- verrouillage `FOR UPDATE` réel ;
- concurrence ;
- single-active Blueprint ;
- idempotence CKR ;
- transition `4 → 6` ;
- terminal `10 → PRODUCTION_ON_HOLD` ;
- idempotence `DOMAIN_EXHAUSTED` ;
- idempotence `DEPTH_EXHAUSTED` ;
- rollback ;
- preuve `IMPASSE-KRP-001`.

Validation pendant RESTORE-02 :

```text
GitHub compare structurel  ✅
GitHub CI status            aucun check configuré
PHPUnit rerun               non exécuté depuis cet environnement
```

Aucun résultat de test nouveau n'est inventé.

---

# 8. Point qui reste à réaligner avant de continuer

Les matériaux de conception plus récents contiennent une intention importante :

```text
Module 02 / KernelRotationPlanner
→ reçoit un Blueprint créé
→ écrit uniquement depth + domain
→ s'arrête
```

Cette frontière est compatible avec la responsabilité propre de KRP, mais la série v3.3 a mélangé cette séparation correcte avec d'autres changements non verrouillés :

- réactivation de `cycle_target/cycle_completed` comme autorité ;
- modification du sens de `DEPTH_EXHAUSTED` ;
- modification du chemin CKR ;
- nouveaux états techniques ;
- nouveaux retries ;
- références DEC inexistantes ;
- tests réécrits pour imposer ces nouveaux comportements.

Il est donc interdit de réappliquer v3.3 en bloc.

---

# 9. Prochain bloc obligatoire — ALIGN-02

Objectif : **reconstruire la prochaine version du contrat 02 à partir des décisions réellement verrouillées, sans laisser le code choisir l'architecture.**

À traiter séparément :

```text
A. Frontière exacte du module 02
   Factory → Blueprint → KRP → fillRotation(depth, domain) → FIN 02

B. Rôle du KernelPipelineOrchestrator global
   distinguer orchestration du module 02 et orchestration du pipeline complet

C. CURRENT_KERNEL_RECEIVED
   propriété, comptabilisation, idempotence, moment exact de création du Blueprint suivant

D. DepthNeedMatrix
   confirmer quelles données sont cibles/traçabilité et lesquelles ont autorité

E. DEPTH_EXHAUSTED
   sémantique exacte : épuisement intellectuel réel vs fin de tour

F. Depth 10
   comportement terminal exact

G. États techniques
   aucun nouvel état sans décision officielle

H. Tests
   réécrire uniquement après verrouillage du contrat
```

Règle :

```text
ALIGN-02 = SPECIFICATION / RÉCONCILIATION
aucune nouvelle logique moteur
```

Seulement après verrouillage de cette réconciliation :

```text
implantation ciblée
↓
validation
↓
03_Taxonomy
```

---

# 10. Interdictions actives

Ne pas :

- prendre les commits v3.3 annulés comme source de vérité ;
- inventer `DEC-108` ou `DEC-111` ;
- réintroduire un comportement simplement parce qu'un ancien test l'attend ;
- modifier Taxonomy pour rendre KRP cohérent ;
- contourner `IMPASSE-KRP-001` avant le contrat inter-module ;
- déclarer une nouvelle version 02 verrouillée avant ALIGN-02 ;
- déclarer des tests verts sans exécution réelle.

---

# 11. Statut courant

```text
RECOVERY-01             ✅ FINI
RESTORE-02              ✅ FINI
CODE KRP COURANT        ✅ restauré à db260475
TESTS KRP COURANTS      ✅ restaurés à db260475
VALIDATION HISTORIQUE   ✅ 15/15 PostgreSQL retrouvée
VALIDATION NOUVELLE     ⚪ non exécutée
DÉRIVE v3.3             ✅ retirée du code courant
ALIGN-02                ▶ PROCHAINE ÉTAPE
03_Taxonomy             ⛔ EN ATTENTE
```
