# 02_KernelRotationPlanner — Spécification canonique

**Version :** 3.6  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Architecture intellectuelle :** 100 %  
**Contrat intellectuel :** 100 %  
**Implémentation :** À RÉAUDITER CONTRE v3.6  
**Validation terminale code :** NON  
**Décision de verrouillage :** DEC-117

> Cette v3.6 remplace intégralement la v3.5. Elle verrouille deux principes : **un seul module métier actif à la fois** et `DOMAIN_EXHAUSTED(depth, domain)` signifie uniquement **« ce Domain est vide »**.
>
> Taxonomy émet ce fait à la fin de son propre travail. Ce fait est conservé à la frontière de communication sans activer KRP. KRP ne le consomme et ne l'applique à sa rotation qu'à sa prochaine activation, après création d'un nouveau KernelBlueprint déclenchée par ReadyBank.
>
> Taxonomy ne choisit jamais le prochain Domain, le prochain Depth, la fermeture de tour ou HOLD. Il n'émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

---

# 1. Mission

`KernelRotationPlanner` (KRP) est l'autorité unique de rotation du moteur intellectuel.

À chaque activation, KRP reçoit un **nouveau KernelBlueprint déjà créé** et choisit exactement :

```text
depth + domain
```

KRP décide à partir de :

1. `RotationState` — son propre état persistant ;
2. `DepthNeedMatrix` — les besoins quantitatifs globaux par Depth ;
3. les faits `DOMAIN_EXHAUSTED(depth, domain)` émis par une phase Taxonomy **déjà terminée** et conservés jusqu'à la prochaine activation KRP.

KRP ne lit pas Taxonomy, ne poll pas ses Banks et ne s'exécute jamais en parallèle de Taxonomy.

---

# 2. Invariant d'exécution séquentielle

Principe absolu :

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

Séquence intellectuelle :

```text
KRP ACTIF
↓
KRP écrit depth + domain
↓
KRP FIN
↓
Taxonomy ACTIF
↓
Taxonomy travaille le territoire
↓
Taxonomy FIN
↓
modules suivants du pipeline
...
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
Factory crée NOUVEAU Blueprint
↓
KRP ACTIF à nouveau
```

Interdiction :

```text
Taxonomy ACTIF
+
KRP ACTIF
```

n'existe jamais comme état normal du pipeline.

---

# 3. Frontière Taxonomy → KRP

## 3.1 Sortie factuelle Taxonomy

Lorsqu'à la fin de son travail Taxonomy sait que le Domain attribué est vide, il émet :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification contractuelle exacte :

```text
CE DOMAIN EST VIDE
```

Le signal ne veut jamais dire :

- passe au prochain Domain ;
- ferme le tour ;
- passe au prochain Depth ;
- mets HOLD ;
- exécute KRP maintenant.

La garde intellectuelle qui autorise Taxonomy à émettre ce fait appartient à Taxonomy. À la frontière active, le fait signifie que sa vérification de vacuité est terminée.

## 3.2 Pas d'activation KRP au moment du signal

Lorsque Taxonomy émet `DOMAIN_EXHAUSTED` :

```text
Taxonomy termine sa phase
↓
DOMAIN_EXHAUSTED(depth, domain)
↓
fait conservé par la frontière de communication
↓
KRP RESTE INACTIF
```

Le transport technique, la file, l'inbox ou le stockage de ce fait ne constituent pas une responsabilité métier KRP ou Taxonomy et ne sont pas fixés par ce contrat.

Aucune transition de rotation n'est exécutée pendant la phase Taxonomy.

---

# 4. Position canonique de KRP

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
KernelRotationPlanner devient ACTIF
↓
charge RotationState
+
consomme les faits DOMAIN_EXHAUSTED en attente
+
consulte DepthNeedMatrix si nécessaire
↓
applique SON contrat de rotation
↓
Blueprint.fillRotation(depth, domain)
↓
persistance KRP
↓
KRP FIN
↓
Taxonomy peut devenir ACTIF
```

ReadyBank ne remet jamais l'ancien Blueprint à KRP.

`CURRENT_KERNEL_RECEIVED` déclenche le lifecycle, mais ne décide aucune rotation.

---

# 5. Responsabilités

KRP doit :

1. recevoir un nouveau Blueprint identifié avec `depth/domain` vides ;
2. charger `RotationState` ;
3. consommer les faits `DOMAIN_EXHAUSTED` en attente provenant de phases Taxonomy terminées ;
4. valider qu'un fait correspond à un territoire connu de son tour ;
5. transformer un fait Domain vide en état de rotation `ESTOMPÉ` ;
6. traiter un fait déjà appliqué comme `NO-OP` ;
7. exclure tout Domain `ESTOMPÉ` des sélections restantes du tour courant ;
8. conserver le même Domain tant qu'il reste `VISIBLE` ;
9. choisir seul le prochain Domain `VISIBLE` selon le `DomainCycle` ;
10. constater seul qu'un tour est terminé lorsque ses huit Domaines sont `ESTOMPÉ` ;
11. fermer seul ce tour ;
12. incrémenter `cycle_completed[depth]` exactement une fois pour ce tour ;
13. consulter `DepthNeedMatrix` pour le prochain Depth encore nécessaire ;
14. revenir vers Depth 2 après Depth 10 lorsqu'un besoin subsiste ;
15. produire `PRODUCTION_ON_HOLD` uniquement lorsque toutes les cibles globales sont satisfaites ;
16. écrire uniquement `depth + domain` dans le Blueprint ;
17. persister ses transitions avant de les considérer commises ;
18. terminer complètement avant activation de Taxonomy.

---

# 6. Interdictions

KRP ne doit jamais :

- créer le KernelBlueprint ;
- modifier `blueprint_id` ;
- recycler l'ancien Blueprint ;
- être actif en même temps que Taxonomy ;
- être invoqué par Taxonomy pendant l'exécution Taxonomy ;
- lire ou poller SubjectBanks, IdeaBanks, curseurs ou états internes Taxonomy ;
- demander à Taxonomy « reste-t-il du contenu ? » pendant sa création ;
- recevoir de Taxonomy le prochain Domain ou prochain Depth ;
- recevoir `DEPTH_EXHAUSTED` comme commande Taxonomy ;
- appliquer une rotation au moment où Taxonomy émet son signal ;
- écrire `subdomain_active`, `subject_active`, `dominant_idea_active` ou `kernel_code` ;
- créer ou valider du contenu intellectuel ;
- utiliser `Général` comme domaine de création ;
- déclarer HOLD uniquement parce que Depth 10 vient d'être terminé ;
- inventer les interfaces Phase1/Phase2.

---

# 7. Entrées

## 7.1 Nouveau KernelBlueprint

```text
blueprint_id           = REMPLI
depth                  = NULL
domain                 = NULL
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

## 7.2 RotationState

KRP possède l'état de rotation nécessaire pour :

- Depth courant ;
- Domain courant ;
- position dans le DomainCycle ;
- états `VISIBLE / ESTOMPÉ` des huit Domaines du tour ;
- état `OPEN / CLOSED` du tour ;
- fermeture exactement une fois ;
- état opérationnel `NORMAL / BLOCKED / PRODUCTION_ON_HOLD`.

## 7.3 Faits Domain en attente

Interface sémantique :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Ces faits ont été produits par Taxonomy alors que KRP était inactif.

Ils sont disponibles à la **prochaine activation KRP**.

Le mécanisme technique qui les conserve entre les phases n'est pas contractuel.

## 7.4 DepthNeedMatrix

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

`DepthNeedMatrix` ne connaît pas les Banks Taxonomy et ne décide pas qu'un Domain est vide.

---

# 8. Sorties

## 8.1 Sortie normale

Le même nouveau Blueprint ressort de KRP avec uniquement :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

Après cette sortie :

```text
KRP FIN
↓
Taxonomy peut devenir ACTIF
```

## 8.2 Sortie opérationnelle

```text
PRODUCTION_ON_HOLD
```

uniquement si tous les Depths ont satisfait leurs cibles et qu'aucune transition KRP n'est incertaine.

---

# 9. Slots Blueprint

KRP possède exactement :

```text
depth
domain
```

Écriture :

```text
Blueprint.fillRotation(depth, domain)
```

Aucun autre slot Blueprint n'appartient à KRP.

---

# 10. Cycles officiels

## 10.1 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

Après 10, la recherche reprend cycliquement vers le premier Depth encore nécessaire, potentiellement 2.

## 10.2 DomainCycle

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

# 11. Mécanisme de rotation

## 11.1 Premier démarrage

Sans fait antérieur :

1. KRP consulte `DepthNeedMatrix` ;
2. choisit le premier Depth nécessaire ;
3. ouvre le tour avec huit Domaines `VISIBLE` ;
4. choisit Géographie ;
5. écrit `depth + domain` ;
6. termine.

## 11.2 Domain toujours visible

Si aucun fait Domain vide n'est en attente pour le Domain courant :

```text
Domain courant = VISIBLE
↓
KRP conserve même Depth + même Domain
```

Un nouveau Blueprint ne fait jamais tourner automatiquement le Domain.

## 11.3 Application d'un fait Domain vide

À l'activation KRP suivante :

```text
KRP consomme DOMAIN_EXHAUSTED(depth, domain)
↓
valide le territoire
↓
persiste VISIBLE → ESTOMPÉ
```

Définition opérationnelle :

```text
ESTOMPÉ
= ce Domain est abstrait/exclu des rotations restantes du tour courant
```

Une fois `ESTOMPÉ`, KRP ne sélectionne plus ce Domain dans ce tour.

## 11.4 Choix du Domain suivant

Après application des faits en attente :

```text
Domain courant ESTOMPÉ
↓
KRP parcourt SON DomainCycle
↓
choisit le prochain Domain VISIBLE
```

Taxonomy n'intervient pas dans ce choix.

## 11.5 Fin de tour

Après application des faits en attente, si :

```text
8 Domaines = ESTOMPÉ
```

alors KRP :

```text
ferme SON tour
↓
persiste CLOSED
↓
cycle_completed[depth] += 1 exactement une fois
↓
DepthNeedMatrix
↓
prochain Depth encore nécessaire
↓
ouverture d'un nouveau tour avec 8 Domaines VISIBLE
↓
Géographie
```

Taxonomy n'émet aucun `DEPTH_EXHAUSTED`.

## 11.6 Après Depth 10

```text
Tour Depth 10 fermé
↓
DepthNeedMatrix
↓
prochain Depth encore nécessaire
```

Si Depth 2 est encore nécessaire, KRP ouvre un nouveau tour Depth 2.

---

# 12. Ownership canonique — DEC-117

```text
Taxonomy
= propriétaire de ses Banks
= à la FIN de son travail, peut émettre le fait : DOMAIN_EXHAUSTED(depth, domain)
= signification : « ce Domain est vide »
= aucune autorité de rotation
= n'active jamais KRP directement

Frontière de communication
= conserve le fait jusqu'à la prochaine activation KRP
= ne décide aucune rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle du noyau suivant
= aucune autorité de rotation

KernelBlueprintFactory
= crée le nouveau Blueprint

DepthNeedMatrix
= autorité quantitative globale

KernelRotationPlanner
= seul module actif pendant sa phase
= consomme les faits Domain vides en attente
= rend ces Domaines ESTOMPÉ
= les exclut de SON DomainCycle pour le tour courant
= décide seul Domain, fin de tour, prochain Depth et HOLD
```

---

# 13. États et transitions

## 13.1 Domain

```text
VISIBLE
↓ fait DOMAIN_EXHAUSTED consommé par KRP à sa prochaine activation
ESTOMPÉ
```

Dans le même tour :

```text
ESTOMPÉ → VISIBLE
```

est interdit.

## 13.2 Tour

```text
OPEN
↓ huit Domaines ESTOMPÉ constatés pendant une activation KRP
CLOSED
```

Un futur retour au même Depth ouvre un nouveau tour distinct avec huit Domaines `VISIBLE`.

## 13.3 Module actif

États normaux :

```text
KRP_ACTIVE → KRP_DONE → TAXONOMY_ACTIVE → TAXONOMY_DONE → ...
```

État interdit :

```text
KRP_ACTIVE AND TAXONOMY_ACTIVE
```

---

# 14. Persistance et idempotence

## 14.1 Fait Domain

Première consommation valide :

```text
DOMAIN_EXHAUSTED
↓
VISIBLE → ESTOMPÉ
↓
COMMIT RotationState
```

Replay déjà appliqué :

```text
NO-OP
```

Un fait contradictoire ou hors territoire connu ne provoque aucune rotation.

## 14.2 Fermeture de tour

Un même tour `CLOSED` ne peut incrémenter `cycle_completed` qu'une seule fois, y compris après reprise/retry.

L'identité technique garantissant cet `exactly once` appartient à l'implantation KRP ; elle n'est pas une responsabilité Taxonomy.

## 14.3 Échec technique

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

Après épuisement : `BLOCKED`. Aucune progression depuis un état incertain.

---

# 15. Communication inter-modules

## 15.1 Taxonomy → frontière

```text
Taxonomy FIN
↓
DOMAIN_EXHAUSTED(depth, domain) si nécessaire
↓
fait en attente
```

Aucun appel KRP à cette étape.

## 15.2 ReadyBank → prochain cycle

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ lifecycle
→ Factory
→ nouveau Blueprint
→ KRP ACTIVE
```

## 15.3 KRP → Taxonomy

```text
KRP applique faits + rotation
→ écrit depth + domain
→ KRP FIN
→ Taxonomy ACTIVE
```

---

# 16. Cas limites

1. Aucun fait Domain vide → même Domain conservé.
2. Fait Domain vide en attente → KRP l'applique à sa prochaine activation seulement.
3. Domain déjà ESTOMPÉ → replay = `NO-OP`.
4. Domain ESTOMPÉ → exclu de toute sélection restante du tour.
5. Huit Domaines ESTOMPÉ → KRP ferme le tour.
6. `cycle_completed` n'augmente qu'une fois par tour.
7. Depth suivant déjà satisfait → ignoré par Matrix.
8. Depth 10 terminé avec besoin restant → retour vers prochain Depth nécessaire.
9. Tous besoins satisfaits → HOLD.
10. Signal Taxonomy reçu pendant que KRP est inactif → conservé, aucune exécution KRP.
11. Nouveau Blueprint sans signal d'épuisement → pas de rotation automatique.
12. Taxonomy ne répond pas/échoue techniquement → KRP n'est pas simultanément invoqué pour deviner une rotation.

---

# 17. Validation contractuelle

KRP v3.6 est conforme seulement si les tests prouvent :

1. Factory crée un nouveau Blueprint avant KRP ;
2. KRP et Taxonomy ne sont jamais actifs simultanément ;
3. Taxonomy peut émettre `DOMAIN_EXHAUSTED(depth, domain)` sans activer KRP ;
4. le signal signifie uniquement « ce Domain est vide » ;
5. le fait reste en attente jusqu'à la prochaine activation KRP ;
6. à cette activation, KRP applique `VISIBLE → ESTOMPÉ` ;
7. `ESTOMPÉ` exclut le Domain des rotations restantes du tour ;
8. sans fait en attente, KRP conserve le même Domain ;
9. KRP choisit seul le prochain Domain VISIBLE ;
10. huit Domaines ESTOMPÉ → KRP ferme seul le tour ;
11. `cycle_completed` incrémenté exactement une fois ;
12. prochain Depth choisi via `DepthNeedMatrix` ;
13. après 10, retour possible vers 2 ;
14. HOLD seulement si toutes les cibles sont satisfaites ;
15. aucun `DEPTH_EXHAUSTED` Taxonomy actif ;
16. KRP écrit uniquement `depth + domain` ;
17. le même Blueprint devient ensuite l'entrée Taxonomy ;
18. aucune lecture/poll des Banks Taxonomy par KRP.

---

# 18. Architecture = 100 %

Checklist de verrouillage :

```text
Mission          100 %
Responsabilités  100 %
Interdictions    100 %
Entrées          100 %
Sorties          100 %
Slots Blueprint  100 %
Données internes 100 %
Mécanismes       100 %
Communication    100 %
Contrats         100 %
États            100 %
Transitions      100 %
Cas limites      100 %
Persistance      100 %
Validation       100 %
Tests            100 %
Architecture     100 %
```

---

# 19. Extension future Phases 1–2

KRP est verrouillé ici pour la partie intellectuelle.

Toute future exigence Phase1/Phase2 qui modifierait KRP exige :

```text
spécification propriétaire de la Phase
↓
nouvelle version KRP
↓
nouvelle DEC
```

Aucune interface Phase1/Phase2 n'est inventée dans v3.6.
