# 02_KernelRotationPlanner — Référence active

**Statut historique :** v3.2 VERROUILLÉ  
**Statut actif :** **EN RÉVISION — v3.3 OBLIGATOIRE avant nouveau verrouillage**  
**Statut du document :** RÉFÉRENCE ACTIVE À RECONSTRUIRE DEPUIS LA SOURCE CANONIQUE VERROUILLÉE + décisions actives validées  

**Position Blueprint :** SECTION 1 — CRÉATION INTELLECTUELLE. KRP y écrit `depth + domain`.

## Brides inter-modules validées

- KRP est propriétaire de `Blueprint.depth` et `Blueprint.domain`.
- KRP ne crée pas le Blueprint : il reçoit un Blueprint déjà créé.
- KRP ne lit pas les banques internes Taxonomy.
- Taxonomy produit les signaux prospectifs `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` qui influencent la prochaine rotation.
- **KRP ne reçoit pas directement `CURRENT_KERNEL_RECEIVED` de ReadyBank.**
- `CURRENT_KERNEL_RECEIVED` déclenche la création du prochain Blueprint.
- KRP intervient ensuite sur ce nouveau Blueprint et y écrit `depth + domain`.

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
création nouveau Blueprint
↓
KRP
↓
fillRotation(depth, domain)
```


## Frontière Taxonomy — occurrence de bassin

KRP peut revenir plusieurs fois au même `Depth + Domain` parce que les besoins globaux `cycle_target` exigent plusieurs tours. Taxonomy distingue alors ses réservoirs par **occurrence de bassin du tour de Depth**.

Cette occurrence est **strictement interne à Taxonomy** :
- KRP ne la choisit pas ;
- KRP ne l'écrit pas dans le Blueprint ;
- aucun nouveau slot Blueprint n'est créé ;
- KRP fournit toujours seulement `depth + domain`.

Le contrat KRP consiste uniquement à ouvrir un nouveau tour de Depth lorsque la rotation y revient ; Taxonomy ouvre alors ses nouvelles occurrences de bassin au fur et à mesure des Domaines demandés.

## Interdictions rappelées

- aucun Subdomain ;
- aucun Subject ;
- aucune Dominant Idea ;
- aucune décision VDI ;
- aucune création directe du Blueprint ;
- aucune réception directe de `CURRENT_KERNEL_RECEIVED` comme déclencheur KRP.

## Règle de monotonicité du cadran — ACTIVE

Dans un même tour de Depth (`heure`), l’état d’un Domain (`minute`) est monotone :

```text
VISIBLE
↓ DOMAIN_EXHAUSTED
ESTOMPÉ
```

Une fois `ESTOMPÉ`, le Domain reste estompé jusqu’à la fin de cette heure/Depth. Il n’existe aucune transition normale `ESTOMPÉ → VISIBLE` dans le même tour de Depth.

Si KRP reçoit accidentellement un message contradictoire prétendant qu’un Domain déjà estompé redevient valide/disponible dans la même heure :
- KRP ne recule pas ;
- le `RotationState` n’est pas modifié ;
- le Domain reste `ESTOMPÉ` ;
- le message est ignoré comme effet métier et journalisé comme anomalie de régression d’état.

Le prochain Domain est toujours choisi parmi les minutes encore visibles du cadran courant.

## Garde-fou de réception DOMAIN_EXHAUSTED — ACTIVE

KRP ne doit estomper un Domain que sur réception d’un `DOMAIN_EXHAUSTED` effectivement validé par Taxonomy après sa vérification terminale des réservoirs.

Si Taxonomy détecte encore du contenu exploitable, elle doit bloquer le signal en amont avec `TAX-003 DOMAIN_EXHAUSTION_BLOCKED_REMAINING_CONTENT`. Dans ce cas KRP ne reçoit aucun ordre d’estompage et son `RotationState` reste inchangé.

Il n’existe donc aucun mécanisme métier normal `ESTOMPÉ → VISIBLE` pour récupérer des Banks oubliées : la prévention doit empêcher l’estompage avant le commit KRP.


## Persistance du cadran et idempotence — ACTIVE

Après une transition valide :

```text
Domain VISIBLE
↓ DOMAIN_EXHAUSTED
Domain ESTOMPÉ
```

KRP doit persister immédiatement et durablement l’état `ESTOMPÉ` dans son `RotationState`. Un redémarrage serveur recharge cet état ; un Domain estompé ne redevient jamais visible par perte de mémoire volatile.

La réception répétée du même `DOMAIN_EXHAUSTED(depth, domain)` pour le même état de rotation est idempotente :
- première réception valide : `VISIBLE → ESTOMPÉ` puis persistance ;
- toute réception identique ultérieure : `NO-OP` ;
- aucun second effet de progression, aucune seconde fermeture et aucune réécriture métier.

## Échec de persistance KRP — ACTIVE

Si KRP reçoit un `DOMAIN_EXHAUSTED` ou un `DEPTH_EXHAUSTED` valide mais ne peut pas persister la transition correspondante :

```text
signal valide reçu
↓
échec d’écriture RotationState
↓
aucune transition considérée COMMITÉE
↓
retry technique de la même persistance
```

Seuil :
- 1 tentative initiale + 3 retries techniques maximum (`MAX_STATE_PERSIST_RETRIES = 3`) ;
- chaque retry reprend exactement la même transition idempotente ;
- aucun nouveau Blueprint / aucune nouvelle rotation n’est autorisé tant que la persistance n’est pas confirmée.

Codes défauts :
- `KRP-002 — DOMAIN_EXHAUSTED_PERSIST_FAILED`
- `KRP-003 — DEPTH_EXHAUSTED_PERSIST_FAILED`

Après épuisement des retries :
- état opérationnel global `BLOCKED` ;
- incident persistant Admin/Ops ;
- notification administrateur ;
- le Blueprint courant déjà produit reste valide ;
- la reprise réapplique la transition en attente et ne crée aucun double effet.

La confirmation officielle d’une transition KRP est le `COMMIT` réussi de son `RotationState`.


# ÉVOLUTION TRANSVERSE VALIDÉE — BESOINS GLOBAUX GAMEPLAY PAR DEPTH

> Cette section corrige une omission de la référence v3.2. Elle doit être intégrée dans la prochaine révision complète du contrat KRP. La v3.2 reste l'historique verrouillé; l'architecture active exige désormais cette révision.

## Autorités distinctes

- `Taxonomy` est l'autorité qui constate l'épuisement réel des réservoirs du tour courant et produit `DOMAIN_EXHAUSTED(depth, domain)` puis `DEPTH_EXHAUSTED(depth)` lorsque les huit Domaines de ce tour sont terminés.
- `DepthNeedMatrix` est l'autorité quantitative des besoins globaux de production requis par le gameplay pour chaque Depth.
- `KernelRotationPlanner` combine l'état de son cadran et les besoins de `DepthNeedMatrix` pour choisir le prochain Depth + Domain. Taxonomy ne choisit jamais le prochain Depth.

## Cibles globales officielles de Tours par Depth

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100
```

Ces cibles représentent les besoins globaux du gameplay. Elles ne déclarent jamais qu'un Domain est épuisé et ne remplacent jamais les constats Taxonomy.

## Horloge numérique KRP

```text
HEURE   = Depth
MINUTES = Domaines
TOUR    = une révolution complète de l'heure/Depth
```

Pendant un tour donné d'un Depth :

- chaque Domain commence `VISIBLE` ;
- `DOMAIN_EXHAUSTED` fait passer uniquement ce Domain `VISIBLE -> ESTOMPÉ` ;
- `ESTOMPÉ` est irréversible pendant ce même tour ;
- quand tous les Domaines de l'heure sont `ESTOMPÉS`, Taxonomy produit `DEPTH_EXHAUSTED(depth)` pour CE TOUR.

À la réception et persistance valide de `DEPTH_EXHAUSTED(depth)` :

```text
cycle_completed[depth] += 1
```

exactement une fois pour ce tour.

Le besoin restant est :

```text
cycle_remaining[depth] = max(0, cycle_target[depth] - cycle_completed[depth])
```

Si `cycle_remaining[depth] > 0`, ce Depth reste un besoin global futur. Lorsqu'un nouveau tour de ce même Depth est ultérieurement ouvert, ses Domaines constituent un nouveau cadran de tour et repartent `VISIBLE`; cela n'est pas un recul dans le tour précédent, qui reste historiquement fermé.

## Sélection du prochain Depth

Après fermeture d'un tour, KRP poursuit le `DepthCycle` et choisit le prochain Depth dont le besoin global n'est pas satisfait. Il peut ignorer les Depths dont `cycle_completed >= cycle_target`.

Après Depth 10, KRP peut revenir vers Depth 2 si au moins un Depth possède encore un besoin global non satisfait.

`PRODUCTION_ON_HOLD` n'est permis que lorsque :

```text
pour tous les Depths : cycle_completed[depth] >= cycle_target[depth]
```

et qu'aucun tour actif n'est en cours de fermeture/persistance.

## Idempotence de DEPTH_EXHAUSTED

Le même `DEPTH_EXHAUSTED` pour le même tour ne peut incrémenter `cycle_completed[depth]` qu'une seule fois. Toute répétition est `NO-OP`.

## Statut documentaire

Cette évolution modifie une décision de la v3.2 qui avait retiré `CYCLE_TARGET/cycle_completed` du chemin décisionnel. La prochaine révision complète de `02_KernelRotationPlanner` devra être versionnée (ex. v3.3) et relue intégralement avant nouveau verrouillage.


# Architecture Register — impact v3.3

- `DEC-088` : **SUPERSEDED**.
- `DEC-094` : OFFICIAL — `DEPTH_EXHAUSTED` termine un tour ; `cycle_target/cycle_completed` portent le besoin global.
- `DEC-095` : OFFICIAL — occurrence de bassin interne Taxonomy, sans slot Blueprint.
- `DEC-108` : OFFICIAL — `DEPTH_EXHAUSTED` = fin d'un tour.
- `DEC-111` : OFFICIAL — persistance/idempotence des transitions KRP.

La v3.3 doit être une **réécriture complète** du contrat, pas un patch de v3.2.