# AUDIT-02-00 — 02_KernelRotationPlanner — Code Audit v3.3

**Date :** 2026-08-20  
**Statut :** CLOSED — AUDIT SEULEMENT  
**Spécification cible :** `specifications/02_KernelRotationPlanner.md` v3.3  
**Décision de verrouillage :** DEC-114  
**Décisions structurantes :** DEC-094, DEC-107, DEC-108, DEC-111  
**Portée :** partie intellectuelle KRP + portes Factory/Taxonomy uniquement.

## 1. Conclusion

Le code actuel est encore majoritairement aligné sur KRP v3.2. Il possède plusieurs briques réutilisables, mais n'est pas conforme v3.3 sur la double autorité `DEPTH_EXHAUSTED / DepthNeedMatrix`, la fermeture de tour, la condition de `PRODUCTION_ON_HOLD`, la séquence Factory → KRP → Taxonomy et la politique de persistance/retry.

**UNRESOLVED bloquant : AUCUN.** Les corrections nécessaires sont déjà définies par la Bible v3.3 ; aucune nouvelle décision métier n'est requise.

## 2. KEEP

- `KernelBlueprintFactory::create()` existe et crée un nouveau Blueprint identifié.
- `KernelRotationPlanner::applyRotation()` écrit uniquement `depth + domain` via `KernelBlueprint::fillRotation()`.
- `DepthNeedMatrix` possède déjà `DEPTH_CYCLE = [2,4,6,7,8,9,10]`.
- `DepthNeedMatrix` possède déjà les cibles officielles `250/300/350/350/350/250/100`.
- `DepthNeedMatrix::nextRequiredDepth()` sait ignorer un Depth satisfait et reboucler après 10 vers 2.
- `DepthNeedMatrix::incrementCycleCompleted()` existe.
- `DepthTourState::DOMAIN_CYCLE` est correct : `geographie, histoire, faune, art, sport, cinema, cuisine, science`; `general` est exclu.
- KRP ne déduit pas `DOMAIN_EXHAUSTED` d'un `peekNext() == null`.
- Réception répétée d'un même `DOMAIN_EXHAUSTED` est déjà idempotente.
- Réception répétée du même `DEPTH_EXHAUSTED` pending est déjà idempotente.
- ReadyBank ne remet pas physiquement l'ancien objet Blueprint à KRP.

## 3. MODIFY

### M-02-01 — ordre Factory → KRP

Le chemin actuel résout la rotation avant `KernelBlueprintFactory::create()`. v3.3 exige qu'un nouveau Blueprint identifié existe avant l'entrée KRP.

Cible :

```text
lifecycle
→ Factory::create()
→ KRP lit RotationState + DepthNeedMatrix
→ KRP choisit depth + domain
→ fillRotation()
```

### M-02-02 — brancher DepthNeedMatrix dans la décision KRP

Le planner utilise encore un mapping v3.2 `10 => null`. Il doit utiliser `DepthNeedMatrix::nextRequiredDepth()` après fermeture d'un tour et au premier démarrage selon les besoins restants.

### M-02-03 — états Domain v3.3

Le stockage actuel utilise `ACTIF / DOMAIN_EXHAUSTED`. Le contrat v3.3 est `VISIBLE / ESTOMPÉ` par tour. L'état doit rester monotone pendant un tour et repartir `VISIBLE` seulement lors d'un nouveau tour du même Depth.

### M-02-04 — DEPTH_EXHAUSTED ferme le tour

Le code actuel stocke seulement un pending puis attend un `CURRENT_KERNEL_RECEIVED` pour transitionner. v3.3 exige qu'après persistance valide de la fermeture du tour :

```text
cycle_completed[depth] += 1
```

exactement une fois, puis que le prochain Depth nécessaire soit choisi via DepthNeedMatrix.

### M-02-05 — PRODUCTION_ON_HOLD

Le code actuel peut mettre HOLD simplement après Depth 10. v3.3 autorise HOLD uniquement si tous les `cycle_completed >= cycle_target` et qu'aucune transition n'est en attente de commit.

### M-02-06 — frontière KRP → Taxonomy

L'orchestrateur consulte actuellement Taxonomy avant `applyRotation()`, puis remplit Taxonomy et kernel_code dans le même `run()`. Pour la validation intellectuelle KRP, l'ordre contractuel doit être :

```text
nouveau Blueprint
→ KRP fillRotation(depth, domain)
→ persistance KRP
→ sortie du même Blueprint avec seulement blueprint_id + depth + domain
→ porte Taxonomy
```

KRP ne doit pas dépendre du résultat Taxonomy pour décider d'écrire sa rotation.

### M-02-07 — CURRENT_KERNEL_RECEIVED hors KRP

Le chemin actif appelle encore `KernelRotationPlanner::receiveKernelReceivedV2()`. v3.3 interdit que `CURRENT_KERNEL_RECEIVED` soit un appel direct à KRP. La réception/idempotence de l'événement doit rester dans la frontière lifecycle/orchestration ; KRP reçoit ensuite le nouveau Blueprint.

## 4. MISSING

### X-02-01 — persistance/retry DEC-111

Il manque la politique contractuelle :

```text
1 tentative initiale + 3 retries
KRP-002 — DOMAIN_EXHAUSTED_PERSIST_FAILED
KRP-003 — DEPTH_EXHAUSTED_PERSIST_FAILED
BLOCKED après échec persistant
aucune progression depuis un état non commité
```

### X-02-02 — idempotence durable de fermeture de tour

Le même tour ne doit pouvoir incrémenter `cycle_completed` qu'une seule fois, y compris après replay/restart.

### X-02-03 — sortie KRP testable vers Taxonomy

Il manque une sortie contractuellement observable où le même Blueprint ressort avec :

```text
blueprint_id = rempli
depth = rempli
domain = rempli
subdomain_active = null
subject_active = null
dominant_idea_active = null
kernel_code = null
```

## 5. REMOVE du chemin actif

- mapping v3.2 `DEPTH_CYCLE_NEXT` avec `10 => null` comme autorité de progression ;
- `CURRENT_KERNEL_RECEIVED → KernelRotationPlanner::receiveKernelReceivedV2()` comme frontière active ;
- dépendance de l'écriture KRP à `TaxonomyNavigatorInterface::peekNext()` ;
- tests qui exigent `Depth 10 → PRODUCTION_ON_HOLD` sans consultation des besoins globaux.

Ces éléments peuvent rester dans l'historique Git mais ne doivent plus définir le chemin actif v3.3.

## 6. Matrice de conformité avant patch

| Exigence Bible v3.3 | Code actuel | Verdict |
|---|---|---|
| Nouveau Blueprint déjà créé avant KRP | résolution KRP avant Factory | MODIFY |
| RotationState lu/persisté | présent | KEEP |
| DepthNeedMatrix autorité quantitative | service présent mais non branché à la décision | MODIFY |
| Cycle 2→4→6→7→8→9→10→besoin suivant | 10→HOLD | MODIFY |
| DomainCycle 8 domaines, Général exclu | conforme | KEEP |
| `VISIBLE→ESTOMPÉ` sur signal Taxonomy | sémantique ancienne ACTIF/DOMAIN_EXHAUSTED | MODIFY |
| signal Domain répété = NO-OP | conforme | KEEP |
| `DEPTH_EXHAUSTED` = fin d'un tour | pending v3.2 | MODIFY |
| `cycle_completed += 1` exactement une fois | mécanisme disponible mais non utilisé | MISSING |
| HOLD seulement si toutes cibles atteintes | non | MODIFY |
| persistance 1+3 retries + KRP-002/003 | absent | MISSING |
| écrit seulement depth+domain | conforme | KEEP |
| CKR n'appelle pas directement KRP | violation active | REMOVE/MODIFY |
| sortie même Blueprint vers Taxonomy | ordre actuel inverse | MISSING/MODIFY |

## 7. Tests historiques

À conserver/adapter : DomainCycle, write-once `fillRotation`, signal Domain idempotent, verrouillage/persistance/concurrence utiles.

À remplacer car v3.2 :

- tests imposant transitions fixes sans DepthNeedMatrix ;
- tests imposant `10 → PRODUCTION_ON_HOLD` ;
- tests qui font dépendre `applyRotation()` d'un territoire Taxonomy obtenu avant la rotation ;
- tests où `CURRENT_KERNEL_RECEIVED` déclenche directement la transition KRP.

## 8. Tests contractuels v3.3 requis après patch

1. Factory crée un nouveau Blueprint avant appel KRP.
2. KRP choisit le premier Depth encore nécessaire via DepthNeedMatrix.
3. KRP respecte le DomainCycle officiel et Général est absent.
4. KRP écrit uniquement `depth + domain`.
5. `DOMAIN_EXHAUSTED` : `VISIBLE→ESTOMPÉ`, replay = NO-OP.
6. `DEPTH_EXHAUSTED` ferme le tour et incrémente `cycle_completed` exactement une fois.
7. prochain Depth satisfait ignoré.
8. après Depth 10, retour vers 2 ou autre Depth encore nécessaire.
9. HOLD seulement lorsque toutes les cibles sont satisfaites.
10. échec de persistance Domain : 1+3 puis KRP-002/BLOCKED.
11. échec de persistance Depth : 1+3 puis KRP-003/BLOCKED.
12. le même Blueprint ressort de KRP avec seulement `blueprint_id + depth + domain`, prêt pour Taxonomy.
13. aucun appel direct `CURRENT_KERNEL_RECEIVED → KRP` dans le chemin actif.

## 9. Décision audit

```text
AUDIT-02-00 = CLOSED
↓
IMPL-02-01 = autorisé
```

`IMPL-02-01` doit aligner le chemin intellectuel KRP v3.3 uniquement. Aucune logique Phase1/Phase2 n'est autorisée.
