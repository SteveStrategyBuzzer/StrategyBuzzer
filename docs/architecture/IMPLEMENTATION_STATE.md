# StrategyBuzzer — IMPLEMENTATION STATE

## RECOVERY-01 — État de reprise prouvé

**Date de récupération :** 17 août 2026  
**Dépôt :** `SteveStrategyBuzzer/StrategyBuzzer`  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Baseline propre auditée :** `db26047532cfdf5e030c348dba4455f8eb310971`  
**Dernier état moteur découvert avant ce checkpoint :** `6b82bcf2d9965e4efb16ae6bbf5d854ad18b83bf`  
**Portée :** `01_KernelBlueprint`, `02_KernelRotationPlanner`, frontière `03_Taxonomy`  
**Nouvelle logique moteur introduite par RECOVERY-01 :** AUCUNE

---

# 1. Verdict

```text
RECOVERY-01             ✅ TERMINÉ
POINT DE REPRISE         ✅ ÉTABLI
NOUVELLE LOGIQUE         ✅ AUCUNE

02_KernelRotationPlanner
au SHA db260475          ✅ dernière implémentation cohérente retrouvée avec le contrat v3.2

Série post-db260 v3.3    ❌ CONFLIT CONTRACTUEL

03_Taxonomy              ⛔ NE PAS REPRENDRE EN IMPLANTATION
                         tant que le conflit KRP n'est pas restauré/résolu
```

**Verdict d'architecture :**

> **REFUS — incohérence architecturale.**
>
> La série de commits v3.3 postérieure à `db260475` modifie des règles KRP verrouillées sans mettre à jour la source de vérité documentaire, cite des décisions `DEC-108` et `DEC-111` absentes du registre, et réintroduit des mécanismes explicitement superseded par les décisions officielles actives.

---

# 2. Ordre d'autorité pour la reprise

1. `docs/architecture/00_ArchitectureRegister.md` — décisions `OFFICIAL` non superseded ;
2. dernière spécification verrouillée du module ;
3. code au SHA audité ;
4. tests au SHA audité ;
5. historique Git / `.agents/memory/*` / `attached_assets/*` comme preuves de réalisation et de handoff, jamais comme autorité supérieure au contrat.

Principe conservé :

> **La spécification pilote le code. Le code ne réécrit pas la spécification.**

---

# 3. Contrat officiel encore actif au HEAD v3.3

Le post-db260 n'a pas modifié `00_ArchitectureRegister.md` ni fait passer `02_KernelRotationPlanner.md` à v3.3.

Le HEAD v3.3 contient toujours :

```text
docs/architecture/02_KernelRotationPlanner.md
Version : 3.2
Date    : 13 août 2026
Statut  : VERROUILLÉ
```

Décisions officielles déterminantes :

- `DEC-058` — `KernelBlueprintFactory` crée le Blueprint avant KRP ;
- `DEC-060` — `kernel_received_total` est de la traçabilité ; `cycle_target/cycle_completed` ne sont plus l'autorité de changement de Depth ;
- `DEC-063` — `CURRENT_KERNEL_RECEIVED` est le seul déclencheur de la prochaine rotation ;
- `DEC-068` — KRP n'écrit jamais `kernel_code` ;
- `DEC-082` — `DOMAIN_EXHAUSTED(depth, domain)` est un signal prospectif produit par Taxonomy ;
- `DEC-083` — `DEPTH_EXHAUSTED(depth)` est un signal prospectif produit par Taxonomy ;
- `DEC-092` — après `DEPTH_EXHAUSTED(10)`, la transition terminale est `PRODUCTION_ON_HOLD` ;
- `DEC-093` — `CURRENT_KERNEL_RECEIVED` est le seul incrémenteur de `kernel_received_total` ;
- `DEC-094` — DepthCycle officiel `2 → 4 → 6 → 7 → 8 → 9 → 10`, sans retour automatique à 2.

Le registre ne contient aucune définition de `DEC-108` ni `DEC-111`.

---

# 4. Baseline `db260475` — état cohérent retrouvé

## 4.1 KernelBlueprint

`app/Services/QuestionBank/KernelBlueprint.php`

La Partie 1 canonique est implantée :

- `blueprint_id` privé, Factory owner ;
- `depth + domain` privés, KRP owner ;
- `subdomain_active + subject_active + dominant_idea_active`, Taxonomy owner ;
- `kernel_code`, KernelCodeEngine owner ;
- write-once via méthodes `fill*()` ;
- écriture externe bloquée ;
- lecture publique contrôlée.

Le code indique lui-même que les Parties 2–6 futures ne sont pas encore implantées.

## 4.2 KRP au baseline

Au SHA `db260475`, KRP suit la transition figée :

```text
2  → 4
4  → 6
6  → 7
7  → 8
8  → 9
9  → 10
10 → null = PRODUCTION_ON_HOLD
```

`cycle_target/cycle_completed` ne pilotent plus cette transition.

`DEPTH_EXHAUSTED` est mémorisé prospectivement puis appliqué à la réception canonique du Blueprint courant.

Le chemin canonique retrouvé est :

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
Outbox
↓
KRP reçoit/comptabilise le Blueprint exactement une fois
↓
kernel_received_total +1
↓
applique la transition DEPTH_EXHAUSTED pending si nécessaire
↓
Orchestration du Blueprint suivant
```

## 4.3 Validation historique retrouvée

Le handoff du 14 août documente :

```text
PostgreSQL / Neon #159  : 9/9 PASS
PostgreSQL strict #159B : 6/6 PASS
TOTAL                    : 15/15 PASS
```

Couverture documentée :

- `FOR UPDATE` réel ;
- concurrence ;
- single-active Blueprint ;
- idempotence CKR ;
- transition `4 → 6` ;
- `Depth 10 → PRODUCTION_ON_HOLD` ;
- idempotence `DOMAIN_EXHAUSTED` ;
- idempotence `DEPTH_EXHAUSTED` ;
- rollback ;
- preuve `IMPASSE-KRP-001`.

RECOVERY-01 n'a pas réexécuté PHPUnit : aucun résultat courant n'est inventé.

---

# 5. Delta complet `db260475 → 6b82bcf2`

La série v3.3 commence directement après `db260475` et contient neuf commits :

| Ordre | Commit | Changement principal | Verdict Recovery |
|---:|---|---|---|
| 1 | `d482821b` | réécriture `KernelRotationPlanner` sous un prétendu contrat v3.3 | ❌ CONFLIT |
| 2 | `900ffa95` | Orchestrator réduit à Factory → KRP → depth/domain | ⚠️ MIXTE / non enregistré |
| 3 | `15529c09` | commande rotate réduite au module 02 | ⚠️ suit le changement précédent |
| 4 | `2bb88895` | CKR retiré du chemin direct de comptabilisation/transition KRP | ❌ CONFLIT |
| 5 | `bb87f8a2` | commande Outbox alignée sur cette nouvelle frontière CKR | ❌ dépend du conflit |
| 6 | `a2f36539` | tests Orchestrator réécrits pour v3.3 | ⚠️ tests du nouveau comportement |
| 7 | `203e2151` | CKR laissé rejouable selon nouveaux états KRP | ⚠️ comportement non enregistré |
| 8 | `0d9cc046` | tests KRP réécrits pour cycle_target/cycle_completed + wrap 10→2 | ❌ CONFLIT DIRECT |
| 9 | `6b82bcf2` | tests CKR réécrits pour nouvelle frontière v3.3 | ❌ valide la dérive |

Aucun de ces neuf commits ne modifie le registre d'architecture ni ne remplace officiellement `02_KernelRotationPlanner.md v3.2`.

---

# 6. Conflits précis introduits par v3.3

## CONFLIT A — décisions inexistantes

Le code v3.3 affirme :

```text
Contrat actif v3.3 (DEC-094 / DEC-108 / DEC-111)
```

Or :

```text
DEC-094 : existe
DEC-108 : ABSENTE du Architecture Register
DEC-111 : ABSENTE du Architecture Register
```

Un commentaire de code ne peut pas créer une décision d'architecture.

## CONFLIT B — `cycle_target/cycle_completed` réactivés comme autorité

Le KRP v3.3 appelle :

```text
DepthNeedMatrix::incrementCycleCompleted()
DepthNeedMatrix::nextRequiredDepth()
```

Cela réactive exactement le mécanisme que `DEC-060` a retiré du chemin décisionnel.

## CONFLIT C — changement de sens de `DEPTH_EXHAUSTED`

Contrat officiel :

```text
DEPTH_EXHAUSTED(depth)
= épuisement réel de tous les bassins Domaines du Depth
→ prochain Depth au prochain CURRENT_KERNEL_RECEIVED
```

v3.3 :

```text
DEPTH_EXHAUSTED
= fin d'UN tour
→ cycle_completed +1
→ possibilité de revenir au même Depth selon target
```

Ce n'est pas la même sémantique.

## CONFLIT D — retour automatique Depth 10 → Depth 2

Contrat officiel :

```text
2 → 4 → 6 → 7 → 8 → 9 → 10
DEPTH_EXHAUSTED(10)
→ PRODUCTION_ON_HOLD
```

Interdiction explicite :

```text
10 → 2 automatique
```

Les tests v3.3 exigent au contraire un wrap `10 → 2` lorsque des cibles restent ouvertes.

## CONFLIT E — CURRENT_KERNEL_RECEIVED

Contrat officiel :

```text
CURRENT_KERNEL_RECEIVED
= seul déclencheur de la prochaine rotation
+ seul incrémenteur de kernel_received_total
+ point où une transition DEPTH_EXHAUSTED pending devient effective
```

v3.3 déprécie `receiveKernelReceivedV2()` comme mécanisme de transition KRP et retire son appel du processeur Outbox.

Le signal autorise seulement la création du Blueprint suivant ; la décision KRP est recalculée ensuite via la matrix.

C'est une modification de contrat non enregistrée.

## CONFLIT F — états inventés/non enregistrés

v3.3 ajoute notamment :

```text
VISIBLE
ESTOMPÉ
BLOCKED
AWAITING_DEPTH_EXHAUSTED
retry de persistance KRP
```

Ces concepts peuvent être techniquement plausibles, mais ils n'ont pas été inscrits comme décisions officielles avant leur implantation.

Ils ne sont donc pas validés par RECOVERY-01.

---

# 7. Ce qui peut être conservé conceptuellement de la série v3.3

Un point de v3.3 correspond à une frontière déjà verrouillée :

```text
KernelRotationPlanner
↓
reçoit Blueprint existant
↓
choisit depth + domain
↓
écrit depth + domain
↓
FIN DE SA RESPONSABILITÉ
```

Donc l'intention de **ne pas faire exécuter Taxonomy ou KernelCodeEngine par KRP lui-même** est correcte.

Cependant les commits `900ffa95` et `15529c09` ne doivent pas être conservés aveuglément : ils changent aussi le rôle de `KernelPipelineOrchestrator`, alors que `02_KernelRotationPlanner.md v3.2` décrit encore cet orchestrateur comme coordinateur des moteurs successifs.

Cette séparation devra être remise en conformité explicitement pendant le bloc de restauration, sans importer les mécanismes v3.3 en conflit.

---

# 8. IMPASSE-KRP-001

Au baseline `db260475`, l'impasse documentée est :

```text
Factory crée Blueprint CREATED_UNENGAGED
↓
transaction KRP validée
↓
Taxonomy appelée ensuite
↓
exception Taxonomy
↓
Blueprint CREATED_UNENGAGED durable
↓
single-active guard bloque le run suivant
```

Cette impasse devait être résolue à la frontière de `03_Taxonomy`, sans inventer :

- auto-recovery ;
- cleanup métier ;
- timeout ;
- retry métier ;
- nouvel état ;
- nouveau signal ;
- contournement du single-active guard.

La série v3.3 a changé la frontière avant que le contrat 03 soit verrouillé. RECOVERY-01 ne valide donc pas cette résolution implicite.

---

# 9. État officiel de reprise

| Brique | État |
|---|---|
| Architecture Register | ✅ autorité retrouvée |
| 01 KernelBlueprint Partie 1 | ✅ implantée |
| 02 KRP v3.2 au baseline `db260475` | ✅ dernière version cohérente + validation historique retrouvée |
| série KRP v3.3 post-db260 | ❌ non conforme au contrat officiel actuel |
| tests v3.3 | ❌ ne peuvent pas servir de preuve de conformité puisqu'ils ont été réécrits pour la dérive |
| IMPASSE-KRP-001 | 🟡 ouverte selon le contrat baseline |
| 03 Taxonomy | ⛔ ne pas implanter tant que 02 n'est pas restauré/stabilisé |

---

# 10. Bloc suivant autorisé — RESTORE-02

Objectif : **restaurer le module 02 sur son contrat officiel sans reconstruire l'architecture à partir du code v3.3.**

Séquence obligatoire :

```text
1. prendre db260475 comme référence comportementale KRP prouvée
2. comparer chaque fichier modifié par la série v3.3
3. classer chaque changement :
   CONSERVER / RESTAURER / RÉÉCRIRE / SUPPRIMER
4. restaurer les invariants officiels :
   - cycle_target/cycle_completed = zéro autorité de transition
   - DEPTH_EXHAUSTED = épuisement réel du Depth
   - transition appliquée au CKR
   - Depth 10 → PRODUCTION_ON_HOLD
   - CKR = comptabilisation idempotente + déclencheur
5. conserver uniquement les séparations de responsabilité réellement compatibles
6. remettre les tests sur le contrat officiel
7. exécuter validation disponible
8. seulement après validation : autoriser le passage à 03_Taxonomy
```

Aucune modification de Taxonomy n'est autorisée dans RESTORE-02.

---

# 11. Interdictions actives

Ne pas :

- inventer `DEC-108` ou `DEC-111` rétroactivement ;
- faire du code v3.3 la source de vérité ;
- garder un wrap automatique `10 → 2` ;
- réactiver `cycle_target/cycle_completed` comme autorité KRP ;
- changer le sens de `DEPTH_EXHAUSTED` ;
- supprimer la comptabilisation CKR officielle ;
- passer à Taxonomy pour masquer le conflit de la brique 02 ;
- modifier une brique suivante pour rendre les tests 02 verts ;
- déclarer le KRP FINI tant que le code courant n'est pas revenu en conformité et revalidé.

---

# 12. Statut terminal de RECOVERY-01

```text
SPEC AUDIT db260             ✅
CODE AUDIT db260             ✅
TEST INVENTORY db260         ✅
HISTORIQUE VALIDATION        ✅ 15/15 PG documentés
DELTA db260 → 6b82           ✅ 9 commits audités
CONTRAT v3.3 OFFICIEL        ❌ ABSENT
DÉRIVE v3.3                  ✅ PROUVÉE
CHECKPOINT                   ✅ MIS À JOUR
TESTS RERUN                  ⚪ NON EXÉCUTÉS
NOUVELLE LOGIQUE RECOVERY    ✅ AUCUNE

NEXT
→ RESTORE-02 — correction contrôlée du module 02 avant toute reprise de Taxonomy.
```
