# 02_KernelRotationPlanner — Spécification canonique

**Version :** 4.0  
**Date :** 2026-08-24  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Architecture intellectuelle :** 100 %  
**Contrat intellectuel :** 100 %  
**Implémentation :** À RÉAUDITER CONTRE v4.0  
**Validation terminale code :** NON  
**Décision de verrouillage :** DEC-119

> Cette v4.0 est reconstruite depuis la v3.3 canonique, qui possédait déjà la bonne mécanique de cadran : à chaque nouveau Blueprint, KRP avance vers le prochain Domain `VISIBLE` du `DomainCycle`, et tout Domain `ESTOMPÉ` est ignoré pour le reste du tour.
>
> La correction architecturale de v4.0 concerne l’ownership des mécanismes d’épuisement : `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` sont désormais des **moteurs internes de KRP**, et non des signaux produits par Taxonomy. Taxonomy fournit uniquement un fait terminal sur la consommation de son contenu. À partir de ce fait, toute décision de rotation appartient à KRP.
>
> Cette révision ne modifie pas `01_KernelBlueprint` et ne réécrit pas `03_Taxonomy`. La frontière Taxonomy sera corrigée dans son propre tour.

---

# 1. Mission

`KernelRotationPlanner` (KRP) est l’autorité unique qui détermine le prochain couple :

```text
depth + domain
```

à écrire dans un **nouveau KernelBlueprint déjà créé et identifié**.

KRP organise deux rotations imbriquées :

```text
rotation des Domaines à l’intérieur du Depth actif
+
rotation des Depths selon les besoins globaux
```

KRP ne crée aucun contenu intellectuel et ne lit jamais les Banks internes de Taxonomy.

---

# 2. Position canonique

```text
ReadyBank
↓
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
application des faits Taxonomy en attente
↓
rotation Depth si nécessaire
+
rotation Domain
↓
Blueprint.fillRotation(depth, domain)
↓
persistance KRP
↓
FIN KRP
↓
porte de sortie vers Taxonomy
```

Règles absolues :

```text
CURRENT_KERNEL_RECEIVED
≠ appel direct à KRP
```

et :

```text
ReadyBank
NE remet JAMAIS l’ancien Blueprint à KRP.
```

KRP reçoit toujours une nouvelle enveloppe créée par `KernelBlueprintFactory`.

---

# 3. Architecture interne KRP

La mécanique de rotation est structurée ainsi :

```text
KernelRotationPlanner
│
├── DomainRotation
│   └── avance dans le DomainCycle à chaque nouveau Blueprint
│
├── DOMAIN_EXHAUSTED
│   ├── reçoit le fait terminal provenant de Taxonomy
│   ├── rend le Domain concerné ESTOMPÉ
│   └── détecte si ce Domain était le dernier Domain actif
│
└── DEPTH_EXHAUSTED
    ├── est déclenché par DOMAIN_EXHAUSTED lorsque plus aucun Domain actif ne reste
    ├── contient / possède DepthNeedMatrix
    ├── ferme le tour du Depth
    ├── maintient les besoins par Depth
    └── choisit le prochain Depth nécessaire
```

`DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` sont des mécanismes internes de KRP.

Ils ne sont pas des responsabilités Taxonomy.

---

# 4. Responsabilités

KRP doit :

1. recevoir un KernelBlueprint canonique déjà identifié par `blueprint_id` ;
2. charger son `RotationState` persistant ;
3. consommer les faits Taxonomy terminalement reçus et encore en attente ;
4. exploiter le moteur interne `DOMAIN_EXHAUSTED` ;
5. exploiter le moteur interne `DEPTH_EXHAUSTED` ;
6. faire contenir `DepthNeedMatrix` dans le territoire fonctionnel de `DEPTH_EXHAUSTED` ;
7. connaître le `DomainCycle` officiel ;
8. connaître le `DepthCycle` officiel ;
9. avancer vers le prochain Domain `VISIBLE` à chaque nouveau Blueprint ;
10. ignorer tout Domain `ESTOMPÉ` pour le reste du tour courant ;
11. détecter lorsqu’un signal Taxonomy termine le dernier Domain actif du tour ;
12. déclencher alors `DEPTH_EXHAUSTED` ;
13. fermer le tour du Depth exactement une fois ;
14. maintenir `cycle_target`, `cycle_completed` et `cycle_remaining` via `DepthNeedMatrix` ;
15. choisir le prochain Depth encore nécessaire ;
16. revenir de Depth 10 vers Depth 2 / le prochain Depth nécessaire tant qu’un besoin global subsiste ;
17. écrire **uniquement** `depth + domain` dans le Blueprint ;
18. persister ses transitions avant de les considérer commitées ;
19. rendre les répétitions techniques idempotentes ;
20. produire `PRODUCTION_ON_HOLD` uniquement quand tous les besoins globaux sont satisfaits ;
21. terminer son travail avant la transmission du Blueprint à Taxonomy.

---

# 5. Interdictions

KRP ne doit jamais :

- créer le KernelBlueprint ;
- générer ou modifier `blueprint_id` ;
- recevoir directement l’ancien Blueprint depuis ReadyBank ;
- considérer `CURRENT_KERNEL_RECEIVED` comme un appel direct à KRP ;
- lire ou poller les Banks, IdeaBanks, SubjectBanks ou curseurs internes de Taxonomy ;
- demander à Taxonomy quel Domain ou quel Depth choisir ;
- laisser Taxonomy choisir le prochain Domain ;
- laisser Taxonomy choisir le prochain Depth ;
- considérer `DOMAIN_EXHAUSTED` comme un moteur Taxonomy ;
- considérer `DEPTH_EXHAUSTED` comme un moteur ou signal produit par Taxonomy ;
- conserver le même Domain simplement parce qu’il est encore `VISIBLE` : le cadran doit avancer à chaque nouveau Blueprint ;
- sélectionner un Domain `ESTOMPÉ` dans le même tour ;
- réactiver `ESTOMPÉ → VISIBLE` dans le même tour ;
- écrire `subdomain_active` ;
- écrire `subject_active` ;
- écrire `dominant_idea_active` ;
- écrire `kernel_code` ;
- créer ou valider du contenu intellectuel ;
- utiliser `Général` comme domaine de création ;
- déclarer HOLD à la simple fin du Depth 10 ;
- inventer une logique Phase1/Phase2 non spécifiée.

---

# 6. Entrées

## 6.1 Nouveau KernelBlueprint

Préconditions :

```text
blueprint_id = REMPLI
depth = NULL
domain = NULL
```

KRP n’est pas propriétaire de la création de cette enveloppe.

## 6.2 RotationState persistant

Source de vérité KRP pour :

- Depth actif ;
- tour actif de ce Depth ;
- position courante dans le DomainCycle ;
- état `VISIBLE / ESTOMPÉ` des huit Domaines ;
- état `OPEN / CLOSED` du tour ;
- transitions d’épuisement déjà appliquées ;
- identité du tour nécessaire à l’idempotence ;
- état opérationnel normal/bloqué/hold.

## 6.3 Fait terminal provenant de Taxonomy

KRP reçoit de Taxonomy uniquement le **fait métier** suivant :

> Taxonomy vient d’utiliser la dernière Dominant Idea du dernier Subject encore exploitable du Domain qui lui avait été attribué.

Ce fait concerne le `depth + domain` du noyau courant.

Il ne contient aucune décision de rotation.

Le nom technique exact, le payload exact et le transport exact de ce signal seront fixés lors de la correction officielle de `03_Taxonomy`.

Pour KRP, ce fait est l’entrée du moteur interne :

```text
DOMAIN_EXHAUSTED
```

## 6.4 DEPTH_EXHAUSTED / DepthNeedMatrix

`DEPTH_EXHAUSTED` est un moteur interne KRP.

Il contient fonctionnellement `DepthNeedMatrix`, qui porte :

```text
cycle_target
cycle_completed
cycle_remaining
```

Cibles officielles :

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

Taxonomy ne possède pas ces besoins quantitatifs.

---

# 7. Sorties

## 7.1 Sortie normale vers Taxonomy

Le même nouveau Blueprint ressort de KRP avec exactement :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

KRP s’arrête après avoir valablement déterminé, écrit et persisté `depth + domain`.

## 7.2 Sortie opérationnelle

```text
PRODUCTION_ON_HOLD
```

est permise uniquement si :

```text
cycle_remaining[2]  = 0
AND cycle_remaining[4]  = 0
AND cycle_remaining[6]  = 0
AND cycle_remaining[7]  = 0
AND cycle_remaining[8]  = 0
AND cycle_remaining[9]  = 0
AND cycle_remaining[10] = 0
```

---

# 8. Slots Blueprint

KRP possède exactement :

```text
depth
domain
```

Écriture :

```text
Blueprint.fillRotation(depth, domain)
```

Aucun autre slot Blueprint n’appartient à KRP.

---

# 9. DomainCycle — cadran de rotation

DomainCycle officiel :

```text
Géographie
→ Histoire
→ Faune
→ Art
→ Sport
→ Cinéma
→ Cuisine
→ Science
→ retour à Géographie
```

`Général` est exclu de la création.

## 9.1 Règle fondamentale

À **chaque nouveau Blueprint**, dans le Depth actif :

```text
KRP avance vers le prochain Domain VISIBLE
selon le DomainCycle
```

Un Domain `ESTOMPÉ` est sauté.

Exemple sans Domain épuisé :

```text
Blueprint N   → Géographie
Blueprint N+1 → Histoire
Blueprint N+2 → Faune
...
Blueprint N+7 → Science
Blueprint N+8 → Géographie
```

Exemple avec `Histoire = ESTOMPÉ` :

```text
Géographie
→ Faune
→ Art
...
```

Le fait qu’un Domain reste `VISIBLE` ne signifie jamais que KRP doit rester dessus au Blueprint suivant.

---

# 10. Moteur interne DOMAIN_EXHAUSTED

## 10.1 Déclencheur

`DOMAIN_EXHAUSTED` reçoit le fait terminal transmis par Taxonomy :

```text
« la dernière Dominant Idea du dernier Subject de ce Domain vient d’être utilisée »
```

Taxonomy ne décide rien après ce constat.

## 10.2 Effet

KRP applique :

```text
Domain concerné : VISIBLE → ESTOMPÉ
```

Puis persiste cette transition.

`ESTOMPÉ` signifie :

```text
Domain exclu des prochaines rotations
pour le reste du tour courant du Depth
```

## 10.3 Vérification du dernier Domain actif

Après avoir rendu le Domain `ESTOMPÉ`, `DOMAIN_EXHAUSTED` vérifie :

```text
reste-t-il au moins un Domain VISIBLE dans ce tour ?
```

Si OUI :

```text
aucune rotation de Depth
↓
le prochain Blueprint continuera le DomainCycle
avec les Domaines encore VISIBLE
```

Si NON :

```text
DOMAIN_EXHAUSTED
↓
déclenche le moteur interne DEPTH_EXHAUSTED
```

---

# 11. Moteur interne DEPTH_EXHAUSTED

## 11.1 Déclencheur unique

`DEPTH_EXHAUSTED` est déclenché lorsque :

```text
DOMAIN_EXHAUSTED vient de rendre ESTOMPÉ
le dernier Domain encore VISIBLE du tour courant
```

Taxonomy ne produit pas `DEPTH_EXHAUSTED`.

## 11.2 Responsabilités

`DEPTH_EXHAUSTED` doit :

1. fermer le tour courant du Depth ;
2. persister `OPEN → CLOSED` ;
3. incrémenter `cycle_completed[depth]` exactement une fois ;
4. recalculer `cycle_remaining` via `DepthNeedMatrix` ;
5. parcourir le `DepthCycle` ;
6. ignorer tout Depth dont `cycle_remaining = 0` ;
7. sélectionner le prochain Depth dont `cycle_remaining > 0` ;
8. ouvrir un nouveau tour pour ce Depth ;
9. remettre les huit Domaines de ce **nouveau tour** à `VISIBLE` ;
10. positionner la rotation Domain pour la première sélection du nouveau tour ;
11. produire HOLD seulement si aucun Depth n’a de besoin restant.

## 11.3 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → 2 → 4 → ...
```

Le parcours est cyclique.

Après Depth 10 :

```text
10 → recherche depuis 2
```

et KRP sélectionne le premier Depth dont :

```text
cycle_remaining > 0
```

Ainsi :

```text
fin de Depth 10
≠ HOLD automatique
```

HOLD n’existe que lorsque tous les besoins sont à zéro.

---

# 12. Premier démarrage

Au premier démarrage absolu :

1. `DEPTH_EXHAUSTED/DepthNeedMatrix` identifie le premier Depth nécessaire ;
2. avec les cibles initiales, ce Depth est `2` ;
3. KRP ouvre un tour neuf de Depth 2 ;
4. les huit Domaines sont `VISIBLE` ;
5. la première position du DomainCycle est `Géographie` ;
6. KRP écrit :

```text
depth = 2
domain = Géographie
```

---

# 13. Nouvelle occurrence d’un même Depth

Lorsqu’un Depth revient après un tour ultérieur :

```text
nouveau tour
≠ réouverture de l’ancien tour
```

Le nouveau tour possède :

- une nouvelle identité KRP de tour ;
- huit Domaines `VISIBLE` ;
- une nouvelle progression DomainCycle.

Les anciens Domaines `ESTOMPÉ` restent historiques pour leur ancien tour seulement.

---

# 14. États et transitions

## 14.1 Domain

```text
VISIBLE
↓ fait terminal Taxonomy appliqué par DOMAIN_EXHAUSTED
ESTOMPÉ
```

Dans le même tour :

```text
ESTOMPÉ → VISIBLE
```

est interdit.

## 14.2 Tour de Depth

```text
OPEN
↓ dernier Domain VISIBLE devient ESTOMPÉ
↓ DEPTH_EXHAUSTED
CLOSED
```

Un tour `CLOSED` ne redevient jamais `OPEN`.

## 14.3 Opérationnel

```text
NORMAL
BLOCKED
PRODUCTION_ON_HOLD
```

---

# 15. Persistance et idempotence

## 15.1 DOMAIN_EXHAUSTED

Première application valide :

```text
VISIBLE → ESTOMPÉ
↓
COMMIT RotationState
```

Replay du même fait déjà appliqué :

```text
NO-OP
```

## 15.2 DEPTH_EXHAUSTED

La fermeture d’un même tour et l’incrément de `cycle_completed[depth]` doivent être commis exactement une fois.

Replay après commit :

```text
NO-OP
```

## 15.3 Échec de persistance

Politique technique :

```text
1 tentative initiale
+ 3 retries techniques maximum
```

Tant que le commit n’est pas confirmé :

- la transition n’est pas considérée comme effectuée ;
- aucune progression de rotation basée sur cet état incertain n’est autorisée ;
- la reprise rejoue la même opération idempotente.

Codes KRP :

```text
KRP-002 — DOMAIN_EXHAUSTED_PERSIST_FAILED
KRP-003 — DEPTH_EXHAUSTED_PERSIST_FAILED
```

Après échec persistant :

```text
BLOCKED
```

---

# 16. Communication inter-modules

## 16.1 Entrée lifecycle

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ lifecycle/orchestration
→ KernelBlueprintFactory
→ NOUVEAU Blueprint
→ KRP
```

`CURRENT_KERNEL_RECEIVED` n’appelle jamais directement KRP.

## 16.2 KRP → Taxonomy

```text
KRP
→ Blueprint avec blueprint_id + depth + domain
→ FIN KRP
→ Taxonomy
```

## 16.3 Taxonomy → KRP

Taxonomy transmet uniquement le fait terminal :

```text
« dernière Dominant Idea du dernier Subject
  du Domain attribué vient d’être utilisée »
```

Ce fait alimente `DOMAIN_EXHAUSTED`.

Taxonomy ne transmet :

- aucun prochain Domain ;
- aucun prochain Depth ;
- aucun `DEPTH_EXHAUSTED` ;
- aucune décision de rotation.

KRP ne lit/poll jamais Taxonomy pour obtenir cette information.

---

# 17. Cas limites

1. **Aucun fait Taxonomy terminal** → le Domain reste `VISIBLE`, mais le prochain Blueprint avance quand même au prochain Domain `VISIBLE`.
2. **Fait terminal Domain reçu** → `DOMAIN_EXHAUSTED` rend ce Domain `ESTOMPÉ`.
3. **Domain déjà ESTOMPÉ + replay** → `NO-OP`.
4. **Domain ESTOMPÉ** → toujours sauté dans le tour courant.
5. **Dernier Domain actif devient ESTOMPÉ** → `DOMAIN_EXHAUSTED` déclenche `DEPTH_EXHAUSTED`.
6. **Depth cible déjà satisfaite** → `DepthNeedMatrix` l’ignore.
7. **Depth 10 fermé avec besoin restant** → retour cyclique vers Depth 2 / prochain Depth nécessaire.
8. **Tous les besoins satisfaits** → `PRODUCTION_ON_HOLD`.
9. **Blueprint sans blueprint_id** → entrée invalide ; KRP ne crée pas l’identité.
10. **Blueprint déjà doté de depth/domain** → aucune réécriture normale.
11. **Général demandé comme création** → invalide.
12. **KRP sans signal Taxonomy** → il ne devine jamais qu’un Domain est épuisé à partir des Banks Taxonomy.
13. **Échec de persistance** → aucune progression jusqu’à résolution ou BLOCKED.

---

# 18. Validation contractuelle / tests KRP

L’implantation est conforme seulement si les tests prouvent :

1. Factory crée le nouveau Blueprint avant KRP ;
2. KRP reçoit `blueprint_id` déjà rempli ;
3. aucune route directe `CURRENT_KERNEL_RECEIVED → KRP` ;
4. KRP ne lit/poll aucune Bank Taxonomy ;
5. KRP écrit uniquement `depth + domain` ;
6. DomainCycle contient exactement les huit Domaines de création ;
7. Général est absent ;
8. chaque nouveau Blueprint avance au prochain Domain `VISIBLE` ;
9. un Domain `ESTOMPÉ` est sauté ;
10. le tour Domain boucle correctement après Science ;
11. le fait terminal Taxonomy alimente le moteur interne `DOMAIN_EXHAUSTED` ;
12. `DOMAIN_EXHAUSTED` applique `VISIBLE → ESTOMPÉ` ;
13. replay Domain = `NO-OP` ;
14. `ESTOMPÉ → VISIBLE` interdit dans le même tour ;
15. `DOMAIN_EXHAUSTED` ne déclenche pas de changement de Depth s’il reste au moins un Domain `VISIBLE` ;
16. le dernier Domain `VISIBLE` épuisé déclenche le moteur interne `DEPTH_EXHAUSTED` ;
17. Taxonomy ne produit pas `DEPTH_EXHAUSTED` ;
18. `DEPTH_EXHAUSTED` contient/utilise `DepthNeedMatrix` ;
19. `cycle_completed[depth]` augmente exactement une fois par tour fermé ;
20. `cycle_remaining` est recalculé correctement ;
21. DepthCycle respecte `2→4→6→7→8→9→10→2` ;
22. les Depths sans besoin restant sont sautés ;
23. après 10, KRP peut revenir à 2 ;
24. fin de Depth 10 seule ne produit jamais HOLD ;
25. HOLD uniquement si tous les `cycle_remaining = 0` ;
26. nouveau tour d’un Depth = huit Domaines `VISIBLE` neufs ;
27. KRP-002/KRP-003 et politique 1+3 retries respectés ;
28. le Blueprint ressort avec seulement `blueprint_id + depth + domain` remplis dans la Section intellectuelle KRP.

Les tests Taxonomy sont hors périmètre de cette implantation KRP. Ils seront traités lorsque `03_Taxonomy` sera corrigé et implanté dans son propre bloc.

---

# 19. Extension future Phases 1–2

Les interfaces détaillées de Phase1/Phase2 restent :

```text
RÉSERVÉES
NON SPÉCIFIÉES
```

Toute future exigence modifiant KRP exige une nouvelle version complète et une nouvelle décision d’architecture.

---

# 20. Verrouillage

Pour le périmètre **KRP — partie intellectuelle** :

```text
Mission             100 %
Responsabilités     100 %
Interdictions       100 %
Entrées             100 %
Sorties             100 %
Slots Blueprint     100 %
Données internes    100 %
Mécanismes          100 %
Communication       100 %
Contrats            100 %
États               100 %
Transitions         100 %
Cas limites         100 %
Persistance         100 %
Validation          100 %
Tests               100 %
Architecture        100 %
```

**STATUT : VERROUILLÉ — PARTIE INTELLECTUELLE.**

Prochaine opération autorisée après validation documentaire :

```text
RÉAUDIT-02-v4.0
↓
audit du code KRP réel contre cette spécification
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
↓
implantation KRP seulement
↓
validation terminale KRP
```

`03_Taxonomy` n’est pas modifié pendant cette implantation KRP.