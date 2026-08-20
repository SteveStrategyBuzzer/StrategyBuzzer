# 02_KernelRotationPlanner — Spécification canonique

**Version :** 3.3  
**Date :** 2026-08-20  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Architecture intellectuelle :** 100 %  
**Contrat intellectuel :** 100 %  
**Implémentation :** À AUDITER  
**Validation terminale code :** NON  
**Décision de verrouillage :** DEC-114

> Cette v3.3 est l’unique contrat canonique de `02_KernelRotationPlanner` pour la partie intellectuelle. Elle remplace comme vérité active la v3.2 historique, `02_KernelRotationPlanner_v3.3_ALIGNMENT.md` et `working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md`.
>
> Les interfaces éventuelles nécessaires aux futures Phases 1 et 2 restent **RÉSERVÉES / NON SPÉCIFIÉES**. Elles ne sont pas nécessaires à la fermeture intellectuelle de KRP. Elles devront être définies dans leur propre tour de spécification et, si elles affectent KRP, produire une nouvelle version (3.4+ ou majeure) et une nouvelle DEC. Elles ne peuvent pas modifier silencieusement le contrat intellectuel verrouillé ici.

---

# 1. Mission

`KernelRotationPlanner` (KRP) est l’autorité de rotation qui choisit le prochain couple :

```text
depth + domain
```

à écrire dans un **nouveau KernelBlueprint déjà créé**.

Il combine deux autorités distinctes :

1. l’état réel d’épuisement intellectuel du tour courant, communiqué par Taxonomy ;
2. le besoin quantitatif global par Depth, porté par `DepthNeedMatrix`.

KRP ne crée aucun contenu intellectuel. Il organise uniquement le cadran de production.

---

# 2. Position canonique

```text
CURRENT_KERNEL_RECEIVED
↓
frontière lifecycle/orchestration externe
↓
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint + blueprint_id
↓
KernelRotationPlanner
↓
lecture RotationState + DepthNeedMatrix
↓
sélection depth + domain
↓
Blueprint.fillRotation(depth, domain)
↓
persistance de la position KRP
↓
FIN KRP
↓
porte de sortie vers Taxonomy
```

Règle absolue :

```text
ReadyBank/CURRENT_KERNEL_RECEIVED
NE remet JAMAIS l’ancien Blueprint à KRP.
```

Le Blueprint reçu par KRP est toujours une nouvelle enveloppe créée par Factory.

---

# 3. Responsabilités

KRP doit :

1. recevoir un KernelBlueprint canonique déjà identifié par `blueprint_id` ;
2. charger l’état persistant `RotationState` ;
3. consulter `DepthNeedMatrix` ;
4. connaître le `DepthCycle` officiel ;
5. connaître le `DomainCycle` officiel ;
6. tenir l’état `VISIBLE / ESTOMPÉ` des Domaines du tour actif ;
7. recevoir et appliquer `DOMAIN_EXHAUSTED` valide ;
8. recevoir et appliquer `DEPTH_EXHAUSTED` valide ;
9. maintenir `cycle_completed[depth]` ;
10. calculer le besoin restant par rapport à `cycle_target[depth]` ;
11. choisir le prochain Depth encore nécessaire ;
12. choisir le prochain Domain `VISIBLE` du tour ;
13. écrire **uniquement** `depth + domain` dans le Blueprint ;
14. persister ses transitions avant de les considérer commitées ;
15. rendre les répétitions de signaux idempotentes ;
16. laisser le même Blueprint prêt à être transmis à Taxonomy ;
17. produire `PRODUCTION_ON_HOLD` uniquement quand tous les besoins globaux sont satisfaits et qu’aucune transition n’est en attente.

---

# 4. Interdictions

KRP ne doit jamais :

- créer le KernelBlueprint ;
- générer ou modifier `blueprint_id` ;
- recevoir directement l’ancien Blueprint depuis ReadyBank ;
- considérer `CURRENT_KERNEL_RECEIVED` comme un appel direct à KRP ;
- écrire `subdomain_active` ;
- écrire `subject_active` ;
- écrire `dominant_idea_active` ;
- écrire `kernel_code` ;
- créer ou valider des Dominant Ideas ;
- appliquer les règles ValidationDominantIdeas ;
- lire les Banks ou curseurs internes de Taxonomy ;
- choisir l’occurrence de bassin Taxonomy ;
- produire `DOMAIN_EXHAUSTED` ;
- produire `DEPTH_EXHAUSTED` ;
- réactiver un Domain `ESTOMPÉ` dans le même tour ;
- utiliser `Général` comme domaine de création ;
- déclarer `PRODUCTION_ON_HOLD` à la simple fin du Depth 10 ;
- inventer une logique Phase 1 ou Phase 2 non spécifiée.

---

# 5. Entrées

## 5.1 Nouveau KernelBlueprint

Préconditions :

```text
blueprint_id = REMPLI
depth = NULL
domain = NULL
```

KRP n’est pas propriétaire de la création de cette enveloppe.

## 5.2 RotationState persistant

Source de vérité locale KRP pour :

- Depth/tour actif ;
- Domaines `VISIBLE / ESTOMPÉ` du tour actif ;
- position de rotation ;
- transitions d’épuisement déjà commitées ;
- `cycle_completed` par Depth ;
- transition en attente de persistance, le cas échéant.

## 5.3 DepthNeedMatrix

Autorité quantitative globale.

Cibles officielles de tours :

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100
```

Calcul :

```text
cycle_remaining[depth]
= max(0, cycle_target[depth] - cycle_completed[depth])
```

`DepthNeedMatrix` ne déclare jamais l’épuisement intellectuel d’un Domain ou d’un tour.

## 5.4 Signaux Taxonomy

KRP peut recevoir :

```text
DOMAIN_EXHAUSTED(depth, domain)
DEPTH_EXHAUSTED(depth)
```

Ces signaux sont produits par Taxonomy selon son propre contrat.

---

# 6. Sorties

## 6.1 Sortie normale vers Taxonomy

Le même Blueprint ressort de KRP avec exactement :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

Cette sortie constitue la **porte d’entrée de Taxonomy**.

KRP n’a pas à exécuter Taxonomy lui-même. L’orchestration peut transmettre le Blueprint au module suivant. Le contrat de KRP se termine dès que `depth + domain` et la position correspondante sont valablement établis/persistés.

## 6.2 Sortie opérationnelle

```text
PRODUCTION_ON_HOLD
```

n’est émise que lorsque tous les Depths ont satisfait leurs cibles globales et qu’aucune transition KRP n’est en attente de commit.

---

# 7. Slots Blueprint

KRP possède exactement deux slots métier du Blueprint :

```text
depth
domain
```

Écriture :

```text
Blueprint.fillRotation(depth, domain)
```

Les deux valeurs forment un groupe logique atomique et write-once selon le contrat KernelBlueprint.

Aucun autre slot Blueprint n’appartient à KRP.

---

# 8. Données internes

## 8.1 DepthCycle officiel

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → retour cyclique vers le prochain Depth encore nécessaire
```

Le retour `10 → 2` est autorisé et obligatoire si un besoin global subsiste.

## 8.2 DomainCycle officiel de création

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

## 8.3 Horloge KRP

```text
HEURE   = Depth
MINUTES = Domaines
TOUR    = une révolution complète des 8 Domaines d’un Depth
```

Chaque nouveau tour d’un Depth possède son propre cadran de Domaines initialement `VISIBLE`.

---

# 9. Mécanisme de sélection

## 9.1 Premier démarrage

KRP choisit le premier Depth du `DepthCycle` dont `cycle_remaining > 0`, puis le premier Domain `VISIBLE` de son tour.

Avec les cibles officielles initiales, le premier Depth nécessaire est `2`.

## 9.2 Dans un tour actif

Le prochain Domain est le prochain Domain `VISIBLE` selon le `DomainCycle`.

Un Domain `ESTOMPÉ` est ignoré pour le reste du même tour.

## 9.3 Après fermeture d’un tour

Après commit valide de `DEPTH_EXHAUSTED(depth)` :

```text
cycle_completed[depth] += 1
```

exactement une fois.

KRP poursuit ensuite le `DepthCycle` et sélectionne le prochain Depth pour lequel :

```text
cycle_remaining[depth] > 0
```

Les Depths dont la cible est déjà satisfaite sont ignorés.

Après Depth 10, la recherche reprend cycliquement à Depth 2.

## 9.4 Nouvelle occurrence d’un même Depth

Si KRP revient plus tard au même Depth, un **nouveau tour** est ouvert :

- les 8 Domaines de ce nouveau tour repartent `VISIBLE` ;
- cela ne réactive pas le tour historique précédent ;
- l’occurrence de bassin correspondante reste interne à Taxonomy et n’est pas écrite par KRP.

---

# 10. Double autorité — DEC-094

La rotation repose sur deux vérités qui ne se remplacent jamais :

### Taxonomy

Autorité de la fin intellectuelle réelle du tour :

```text
DOMAIN_EXHAUSTED
DEPTH_EXHAUSTED
```

### DepthNeedMatrix

Autorité du besoin quantitatif global :

```text
cycle_target
cycle_completed
cycle_remaining
```

### KernelRotationPlanner

Autorité de combinaison :

```text
épuisement réel du tour
+
besoin global restant
↓
prochain Depth + Domain
```

`DEPTH_EXHAUSTED` seul ne signifie jamais que le Depth est définitivement terminé.

---

# 11. DOMAIN_EXHAUSTED — DEC-107

Taxonomy ne peut produire `DOMAIN_EXHAUSTED` qu’après sa garde terminale :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Sinon Taxonomy bloque en amont avec :

```text
TAX-003 — DOMAIN_EXHAUSTION_BLOCKED_REMAINING_CONTENT
```

KRP n’exécute pas cette garde Taxonomy. Il fait confiance uniquement au signal valide reçu.

Transition KRP :

```text
VISIBLE
↓ DOMAIN_EXHAUSTED valide
ESTOMPÉ
```

Dans le même tour :

```text
ESTOMPÉ → VISIBLE
```

est interdit.

Un message contradictoire ou régressif ne modifie pas `RotationState` et est traité comme anomalie sans effet métier.

---

# 12. DEPTH_EXHAUSTED — DEC-108

Taxonomy produit :

```text
DEPTH_EXHAUSTED(depth)
```

lorsque les huit Domaines du tour courant sont épuisés.

Ce signal signifie uniquement :

```text
FIN DU TOUR ACTUEL DE CE DEPTH
```

Il ne signifie jamais :

```text
FIN DÉFINITIVE DU BESOIN GLOBAL DU DEPTH
```

Après persistance valide de cette fermeture :

```text
cycle_completed[depth] += 1
```

exactement une fois.

---

# 13. États et transitions

## 13.1 Domain d’un tour

```text
VISIBLE
↓ DOMAIN_EXHAUSTED valide + commit
ESTOMPÉ
```

Transition inverse dans le même tour : interdite.

## 13.2 Tour de Depth

```text
OPEN
↓ huit Domaines ESTOMPÉS + DEPTH_EXHAUSTED valide + commit
CLOSED
```

Un tour `CLOSED` ne redevient jamais `OPEN`.

Un retour futur au même Depth crée un nouveau tour distinct.

## 13.3 État opérationnel

```text
NORMAL
BLOCKED
PRODUCTION_ON_HOLD
```

`BLOCKED` est opérationnel lors d’un échec persistant de commit KRP après retries.

`PRODUCTION_ON_HOLD` n’est pas un état du KernelBlueprint.

---

# 14. Persistance et idempotence — DEC-111

## 14.1 DOMAIN_EXHAUSTED

Première réception valide :

```text
VISIBLE → ESTOMPÉ
↓
COMMIT RotationState
```

Toute répétition du même signal déjà commité :

```text
NO-OP
```

Aucun second effet de progression.

## 14.2 DEPTH_EXHAUSTED

La fermeture du même tour et l’incrément correspondant de `cycle_completed` ne peuvent être commis qu’une seule fois.

Toute répétition après commit :

```text
NO-OP
```

## 14.3 Échec de persistance

Politique :

```text
1 tentative initiale
+ 3 retries techniques maximum
```

Tant que le commit n’est pas confirmé :

- la transition n’est pas considérée comme effectuée ;
- aucun nouveau Blueprint / aucune nouvelle progression de rotation n’est autorisé à partir de cet état incertain ;
- la reprise réapplique la même transition idempotente.

Codes contractuels :

```text
KRP-002 — DOMAIN_EXHAUSTED_PERSIST_FAILED
KRP-003 — DEPTH_EXHAUSTED_PERSIST_FAILED
```

Après épuisement des retries : état opérationnel `BLOCKED` + incident persistant Admin/Ops.

---

# 15. Communication inter-modules

## Entrée lifecycle

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ orchestration/lifecycle
→ Factory crée nouveau Blueprint
→ KRP
```

`CURRENT_KERNEL_RECEIVED` n’est pas un appel direct ReadyBank → KRP.

## Sortie vers Taxonomy

```text
KRP
→ Blueprint avec blueprint_id + depth + domain
→ Taxonomy
```

## Retour informationnel Taxonomy

```text
Taxonomy
→ DOMAIN_EXHAUSTED / DEPTH_EXHAUSTED
→ KRP RotationState
```

Ce retour d’information est distinct du déclenchement de création du Blueprint suivant.

---

# 16. Cas limites

1. **Signal DOMAIN_EXHAUSTED répété** → `NO-OP` après premier commit.
2. **Signal DEPTH_EXHAUSTED répété pour le même tour** → aucun second incrément.
3. **Domain déjà ESTOMPÉ annoncé disponible** → aucune régression d’état.
4. **Depth cible déjà satisfaite** → ignoré lors de la recherche du prochain besoin.
5. **Depth 10 fermé mais besoins restants ailleurs** → retour cyclique vers le prochain Depth nécessaire, possiblement 2.
6. **Toutes les cibles satisfaites** → `PRODUCTION_ON_HOLD`.
7. **Échec de persistance** → retry idempotent ; pas de progression avant commit.
8. **Blueprint sans blueprint_id** → entrée invalide ; KRP ne doit pas fabriquer l’identité.
9. **Blueprint déjà doté de depth/domain** → aucune réécriture normale autorisée.
10. **Général demandé comme création** → invalide pour le DomainCycle de création.
11. **Taxonomy possède encore du contenu** → KRP ne doit jamais recevoir `DOMAIN_EXHAUSTED`; la garde appartient à Taxonomy/TAX-003.
12. **Occurrence future du même Depth+Domain** → nouveau bassin géré par Taxonomy, aucun nouveau slot KRP/Blueprint.

---

# 17. Contrats de validation / tests KRP

La validation de l’implantation KRP doit tester **KRP et ses portes**, sans exiger que les modules aval réalisent déjà leur propre métier.

## Tests contractuels minimaux

1. reçoit un Blueprint déjà créé avec `blueprint_id` ;
2. ne crée jamais le Blueprint ;
3. consulte `DepthNeedMatrix` ;
4. respecte le `DepthCycle` `2→4→6→7→8→9→10` ;
5. retourne vers un Depth encore nécessaire après 10 ;
6. respecte les huit Domaines de création et exclut Général ;
7. choisit uniquement un Domain `VISIBLE` ;
8. écrit uniquement `depth + domain` ;
9. n’écrit aucun slot Taxonomy ;
10. n’écrit pas `kernel_code` ;
11. laisse le Blueprint dans un état recevable par Taxonomy ;
12. `DOMAIN_EXHAUSTED` produit `VISIBLE→ESTOMPÉ` ;
13. répétition `DOMAIN_EXHAUSTED` = `NO-OP` ;
14. aucun `ESTOMPÉ→VISIBLE` dans le même tour ;
15. `DEPTH_EXHAUSTED` ferme un tour et incrémente `cycle_completed` une seule fois ;
16. répétition `DEPTH_EXHAUSTED` = `NO-OP` ;
17. un nouveau tour du même Depth recrée un cadran neuf sans réouvrir l’ancien ;
18. un Depth ayant atteint sa cible est ignoré ;
19. `PRODUCTION_ON_HOLD` seulement si toutes les cibles sont satisfaites ;
20. échec de persistance bloque la progression et applique la politique 1+3 retries ;
21. `KRP-002` et `KRP-003` sont produits aux échecs persistants correspondants ;
22. aucune réception directe ReadyBank/CURRENT_KERNEL_RECEIVED → KRP ;
23. le retour d’information Taxonomy est distinct du déclenchement du Blueprint suivant ;
24. la porte de sortie `KRP → Taxonomy` transporte le même nouveau Blueprint avec seulement `blueprint_id + depth + domain` remplis dans le territoire intellectuel concerné.

Les tests de génération Taxonomy, ValidationDominantIdeas, QuestionIntent, Phase 1 ou Phase 2 ne sont pas des tests contractuels KRP.

---

# 18. Extension future Phases 1–2

La partie intellectuelle de KRP est **complète et verrouillée** par cette v3.3.

Les Phases 1 et 2 pourront révéler plus tard des besoins d’intégration autour du cycle global. Pour éviter toute architecture anticipée :

```text
AUCUNE interface Phase1/Phase2 n’est inventée ici.
```

La règle future est :

```text
spécification propriétaire Phase 1 ou Phase 2
↓
besoin d’interface KRP démontré
↓
nouvelle version complète KRP
↓
nouvelle DEC
↓
audit / implantation / validation de l’extension
```

Une extension future ne peut pas, sans nouvelle décision officielle :

- transférer à KRP la création du Blueprint ;
- lui faire écrire autre chose que ses slots propriétaires ;
- supprimer la double autorité DEC-094 ;
- contourner les gardes d’épuisement DEC-107/108 ;
- casser l’idempotence/persistance DEC-111.

---

# 19. Verrouillage

Pour le périmètre **KRP — partie intellectuelle** :

```text
Mission             100 %
Responsabilités     100 %
Interdictions       100 %
Entrées              100 %
Sorties              100 %
Slots Blueprint      100 %
Données internes     100 %
Mécanismes           100 %
Communication        100 %
Contrats             100 %
États                100 %
Transitions          100 %
Cas limites          100 %
Persistance          100 %
Validation           100 %
Tests                100 %
Architecture         100 %
```

**STATUT : VERROUILLÉ — PARTIE INTELLECTUELLE.**

Prochaine opération autorisée :

```text
AUDIT-02-00
↓
audit du code KRP réel contre cette v3.3
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
↓
implantation ciblée
↓
validation terminale du code
```
