# 02_KernelRotationPlanner — Spécification canonique

**Version :** 3.4  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Architecture intellectuelle :** 100 %  
**Contrat intellectuel :** 100 %  
**Implémentation :** À RÉAUDITER CONTRE v3.4  
**Validation terminale code :** NON  
**Décision de verrouillage :** DEC-115

> Cette v3.4 remplace intégralement la v3.3. Elle corrige l’ownership de la frontière Taxonomy → KRP : Taxonomy expose la réalité de ses réservoirs; KRP reste l’autorité unique qui interprète cette réalité et applique toute rotation.
>
> Les besoins éventuels des futures Phases 1 et 2 restent **RÉSERVÉS / NON SPÉCIFIÉS** et ne peuvent modifier silencieusement ce contrat.

---

# 1. Mission

`KernelRotationPlanner` (KRP) est l’autorité unique de rotation du moteur intellectuel.

Pour chaque **nouveau KernelBlueprint déjà créé**, KRP choisit et écrit exactement :

```text
depth + domain
```

KRP prend sa décision à partir de trois réalités distinctes :

1. `RotationState` — son propre état de rotation persistant ;
2. `DepthNeedMatrix` — le besoin quantitatif global par Depth ;
3. l’état de réalité intellectuelle exposé par Taxonomy — présence ou absence de contenu exploitable pour le territoire travaillé.

Taxonomy ne commande jamais la rotation. `DepthNeedMatrix` ne commande jamais la fin d’un Domain. KRP combine ces informations et décide seul.

---

# 2. Position canonique

```text
noyau courant termine son pipeline
↓
ReadyBank reçoit le noyau
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle/orchestration externe
↓
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint + blueprint_id
↓
KernelRotationPlanner
↓
lecture RotationState
+
lecture DepthNeedMatrix
+
lecture de la réalité Taxonomy disponible
↓
KRP applique SON contrat de rotation
↓
Blueprint.fillRotation(depth, domain)
↓
persistance KRP
↓
FIN KRP
↓
porte vers Taxonomy
```

Règles absolues :

- ReadyBank ne remet jamais l’ancien Blueprint à KRP ;
- `CURRENT_KERNEL_RECEIVED` ne décide aucune rotation ;
- Taxonomy ne déclenche pas directement KRP ;
- le Blueprint reçu par KRP est toujours une nouvelle enveloppe créée par Factory.

---

# 3. Responsabilités

KRP doit :

1. recevoir un nouveau Blueprint avec `blueprint_id` rempli et `depth/domain` vides ;
2. charger son `RotationState` ;
3. consulter `DepthNeedMatrix` ;
4. consulter la réalité Taxonomy nécessaire à sa décision sans lire les Banks internes ;
5. connaître le `DepthCycle` officiel ;
6. connaître le `DomainCycle` officiel ;
7. posséder les états de rotation Domain `VISIBLE / ESTOMPÉ` ;
8. conserver le même `depth + domain` tant que Taxonomy expose qu’un contenu exploitable y reste ;
9. passer le Domain courant `VISIBLE → ESTOMPÉ` lorsque Taxonomy expose qu’aucun contenu exploitable n’y reste ;
10. sélectionner le prochain Domain selon son propre `DomainCycle` ;
11. fermer lui-même un tour lorsque les huit Domaines sont `ESTOMPÉ` ;
12. incrémenter `cycle_completed[depth]` exactement une fois par tour fermé ;
13. demander à `DepthNeedMatrix` le prochain Depth encore nécessaire ;
14. revenir vers Depth 2 après Depth 10 si un besoin global subsiste ;
15. produire `PRODUCTION_ON_HOLD` seulement lorsque toutes les cibles globales sont satisfaites ;
16. écrire uniquement `depth + domain` dans le Blueprint ;
17. persister toute transition de rotation avant de la considérer commise ;
18. laisser le même Blueprint prêt pour Taxonomy.

---

# 4. Interdictions

KRP ne doit jamais :

- créer le KernelBlueprint ;
- générer ou modifier `blueprint_id` ;
- recycler l’ancien Blueprint reçu par ReadyBank ;
- traiter `CURRENT_KERNEL_RECEIVED` comme une commande de rotation ;
- recevoir de Taxonomy une commande « va au prochain Domain/Depth » ;
- lire directement les SubjectBanks, IdeaBanks ou curseurs internes de Taxonomy ;
- écrire `subdomain_active`, `subject_active`, `dominant_idea_active` ou `kernel_code` ;
- créer ou valider du contenu intellectuel ;
- utiliser `Général` comme domaine de création ;
- déclarer HOLD parce que Depth 10 vient d’être terminé ;
- inventer une logique Phase 1 ou Phase 2.

---

# 5. Entrées

## 5.1 Nouveau KernelBlueprint

```text
blueprint_id = REMPLI
depth = NULL
domain = NULL
subdomain_active = NULL
subject_active = NULL
dominant_idea_active = NULL
kernel_code = NULL
```

## 5.2 RotationState

KRP possède et persiste au minimum la réalité nécessaire pour garantir :

- Depth courant ;
- Domain courant ;
- position dans le DomainCycle ;
- Domaines `VISIBLE / ESTOMPÉ` du tour courant ;
- tour ouvert/fermé ;
- fermeture d’un même tour exactement une fois ;
- état opérationnel normal/bloqué/hold.

Le nom exact des colonnes/classes n’est pas contractuel.

## 5.3 DepthNeedMatrix

Autorité quantitative globale :

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100
```

```text
cycle_remaining[depth]
= max(0, cycle_target[depth] - cycle_completed[depth])
```

`DepthNeedMatrix` indique quels Depths ont encore besoin de tours. Il ne décide pas qu’un Domain Taxonomy est vide.

## 5.4 Réalité Taxonomy

Taxonomy expose/persiste une vérité lisible par la frontière KRP indiquant, pour le territoire travaillé, si du contenu intellectuel exploitable reste ou non.

Contrat sémantique minimal :

```text
contenu exploitable restant
OU
aucun contenu exploitable restant
```

Le nom technique de cette interface, son transport et son schéma précis ne sont pas fixés ici.

Cette réalité :

- est produite depuis les Banks dont Taxonomy est propriétaire ;
- n’est pas une décision de rotation ;
- n’est pas une commande ;
- ne contient pas « prochain Domain » ou « prochain Depth » ;
- peut être consultée par KRP lorsqu’un nouveau Blueprint lui est remis.

---

# 6. Sorties

## 6.1 Sortie normale vers Taxonomy

Le même Blueprint ressort de KRP avec uniquement :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

KRP s’arrête ici.

## 6.2 Sortie opérationnelle

`PRODUCTION_ON_HOLD` est permis uniquement si :

```text
pour tous les Depths :
cycle_completed[depth] >= cycle_target[depth]
```

et qu’aucune transition KRP n’attend de persistance.

---

# 7. Slots Blueprint

KRP possède exactement :

```text
depth
domain
```

Écriture unique :

```text
Blueprint.fillRotation(depth, domain)
```

Aucun autre slot Blueprint n’appartient à KRP.

---

# 8. Cycles officiels

## 8.1 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

Après 10, la recherche reprend cycliquement à 2.

## 8.2 DomainCycle

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

`Général` est exclu de la création.

---

# 9. Mécanisme de rotation

## 9.1 Premier démarrage absolu

Sans territoire précédent à interpréter :

1. KRP consulte `DepthNeedMatrix` ;
2. sélectionne le premier Depth encore nécessaire ;
3. ouvre son tour avec les huit Domaines `VISIBLE` ;
4. sélectionne Géographie ;
5. écrit `depth + domain` dans le nouveau Blueprint.

## 9.2 Nouveau Blueprint pendant un Domain encore exploitable

Au prochain appel lifecycle :

```text
Taxonomy expose : contenu exploitable restant
↓
KRP conserve le même Depth
↓
KRP conserve le même Domain
↓
KRP écrit ce même couple dans le NOUVEAU Blueprint
```

Le fait de créer un nouveau Blueprint ne provoque donc pas à lui seul une rotation de Domain.

## 9.3 Domain sans contenu exploitable restant

Au prochain appel lifecycle :

```text
Taxonomy expose : aucun contenu exploitable restant
↓
KRP constate cette réalité
↓
KRP persiste VISIBLE → ESTOMPÉ pour le Domain courant
↓
KRP choisit le prochain Domain encore VISIBLE selon DomainCycle
```

Taxonomy n’a pas choisi ce prochain Domain.

## 9.4 Fin du tour de Depth

Lorsque KRP constate que les huit Domaines de son tour sont `ESTOMPÉ` :

```text
KRP ferme SON tour
↓
persiste la fermeture
↓
cycle_completed[depth] += 1 exactement une fois
↓
DepthNeedMatrix
↓
prochain Depth encore nécessaire
```

Taxonomy ne déclare pas la fin du tour.

## 9.5 Après Depth 10

Si le tour Depth 10 est fermé et que des besoins globaux subsistent :

```text
DepthNeedMatrix
↓
recherche cyclique depuis 2
↓
prochain Depth encore nécessaire
```

Si Depth 2 a encore un besoin, KRP repart sur Depth 2 avec un nouveau tour et Géographie `VISIBLE`.

---

# 10. Ownership canonique — DEC-115

```text
Taxonomy
= propriétaire de ses réservoirs
= autorité de la réalité intellectuelle de ce qui reste à exploiter
= aucune autorité de rotation

DepthNeedMatrix
= autorité quantitative globale des tours encore nécessaires
= aucune autorité sur l’état des Banks Taxonomy

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclencheur lifecycle du noyau suivant
= aucune autorité de rotation

KernelBlueprintFactory
= crée le nouveau Blueprint

KernelRotationPlanner
= autorité UNIQUE de rotation
= interprète les réalités précédentes
= décide Domain, fin de tour, prochain Depth et HOLD
```

Les anciennes interfaces contractuelles :

```text
Taxonomy → DOMAIN_EXHAUSTED
Taxonomy → DEPTH_EXHAUSTED
```

sont **SUPERSEDED** comme ownership actif par DEC-115.

---

# 11. États KRP

## 11.1 Domain

```text
VISIBLE
↓ KRP constate aucun contenu Taxonomy exploitable
ESTOMPÉ
```

Dans un même tour :

```text
ESTOMPÉ → VISIBLE
```

est interdit.

## 11.2 Tour

```text
OPEN
↓ 8 Domaines ESTOMPÉ + persistance KRP
CLOSED
```

Un retour futur au même Depth crée une nouvelle occurrence de tour avec huit Domaines `VISIBLE`.

## 11.3 Opérationnel

```text
NORMAL
BLOCKED
PRODUCTION_ON_HOLD
```

---

# 12. Persistance et idempotence

KRP persiste ses propres transitions avant progression.

### Domain

Une même constatation « aucun contenu exploitable » réévaluée après que le Domain est déjà `ESTOMPÉ` est un `NO-OP` métier.

### Tour

Un même tour `CLOSED` ne peut incrémenter `cycle_completed` qu’une seule fois, y compris après reprise/retry.

L’identité technique garantissant ce `exactly once` appartient à l’implantation de `RotationState`; elle n’est pas transportée par Taxonomy.

### Échec technique de persistance

Politique :

```text
1 tentative initiale
+ 3 retries techniques maximum
```

Codes :

```text
KRP-002 — DOMAIN_ROTATION_STATE_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
```

Après épuisement : `BLOCKED` et aucune nouvelle progression basée sur un état incertain.

Une donnée Taxonomy incohérente ou indisponible n’est pas reclassée comme panne de persistance KRP; KRP ne doit pas inventer la réalité manquante.

---

# 13. Communication inter-modules

## 13.1 ReadyBank / lifecycle

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ lifecycle
→ Factory
→ nouveau Blueprint
→ KRP
```

## 13.2 Réalité Taxonomy

```text
Taxonomy
→ persiste/expose la réalité de ses réservoirs
```

Puis, lors du prochain appel lifecycle :

```text
KRP
→ lit cette réalité
→ applique son contrat de rotation
```

Il n’existe plus de contrat actif où Taxonomy pousse une commande d’épuisement directement dans KRP.

## 13.3 Sortie KRP

```text
KRP
→ même nouveau Blueprint avec blueprint_id + depth + domain
→ Taxonomy
```

---

# 14. Cas limites

1. Taxonomy indique encore du contenu → même Domain conservé.
2. Taxonomy indique aucun contenu → KRP estompe le Domain puis décide le suivant.
3. Réévaluation d’un Domain déjà ESTOMPÉ → aucun second effet.
4. Huit Domaines ESTOMPÉS → KRP ferme le tour une seule fois.
5. Depth suivant déjà satisfait → KRP l’ignore via Matrix.
6. Depth 10 terminé avec besoin restant → retour cyclique vers le prochain Depth nécessaire, potentiellement 2.
7. Tous les Depths satisfaits → HOLD.
8. Réalité Taxonomy indisponible/indéterminée → aucune rotation devinée.
9. Échec de persistance KRP → retry technique, puis BLOCKED si échec persistant.
10. Nouveau Blueprint créé → ne signifie jamais automatiquement prochain Domain.

---

# 15. Validation contractuelle

KRP est conforme seulement si les tests prouvent :

1. Factory crée un nouveau Blueprint avant KRP ;
2. KRP lit Matrix et la réalité Taxonomy sans lire les Banks internes ;
3. contenu restant → même `depth + domain` ;
4. aucun contenu → KRP seul fait `VISIBLE → ESTOMPÉ` puis choisit le Domain suivant ;
5. DomainCycle officiel respecté ;
6. Général absent ;
7. huit Domaines ESTOMPÉS → KRP ferme le tour ;
8. `cycle_completed` incrémenté exactement une fois ;
9. prochain Depth choisi par besoin global Matrix ;
10. après 10, retour vers un Depth encore nécessaire ;
11. HOLD uniquement lorsque toutes les cibles sont satisfaites ;
12. KRP écrit seulement `depth + domain` ;
13. sortie Blueprint prête pour Taxonomy ;
14. `CURRENT_KERNEL_RECEIVED` reste lifecycle ;
15. aucune commande `DOMAIN_EXHAUSTED/DEPTH_EXHAUSTED` n’est requise depuis Taxonomy ;
16. persistance/retries KRP respectés.

---

# 16. Frontière avec Taxonomy

La future spécification `03_Taxonomy v1.1` devra conserver toutes ses responsabilités intellectuelles internes mais réécrire sa frontière KRP selon DEC-115.

Jusqu’à cette révision, les passages de `03_Taxonomy v1.0` attribuant à Taxonomy la production de `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED` ne sont plus autoritatifs.

---

# 17. Extension future Phases 1–2

KRP est complet pour la partie intellectuelle décrite ici.

Toute exigence nouvelle de Phase1/Phase2 :

```text
spécification propriétaire
↓
nouvelle version KRP si nécessaire
↓
nouvelle DEC
```

Aucune extension future ne peut modifier silencieusement v3.4.

---

# 18. Verdict architecture

```text
Mission          : 100 %
Responsabilités  : 100 %
Interdictions    : 100 %
Entrées          : 100 %
Sorties          : 100 %
Slots Blueprint  : 100 %
Données internes : 100 %
Mécanismes       : 100 %
Communication    : 100 %
Contrats         : 100 %
États            : 100 %
Transitions      : 100 %
Cas limites      : 100 %
Persistance      : 100 %
Validation       : 100 %
Tests            : 100 %
Architecture     : 100 %
```

**02_KernelRotationPlanner v3.4 — VERROUILLÉ — PARTIE INTELLECTUELLE.**