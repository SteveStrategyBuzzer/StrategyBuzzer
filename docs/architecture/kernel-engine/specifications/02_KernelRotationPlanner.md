# 02_KernelRotationPlanner — Spécification canonique

**Version :** 3.7  
**Date :** 2026-08-23  
**Statut :** **VERROUILLÉ — PARTIE INTELLECTUELLE**  
**Architecture intellectuelle :** 100 %  
**Contrat intellectuel :** 100 %  
**Implémentation :** À RÉAUDITER CONTRE v3.7  
**Validation terminale code :** NON  
**Décision de verrouillage :** DEC-118

> Cette v3.7 remplace intégralement la v3.6. Elle verrouille définitivement trois invariants de frontière : **un seul module métier actif à la fois**, `DOMAIN_EXHAUSTED(depth, domain)` signifie uniquement **« ce Domain est vide »**, et Taxonomy n’émet ce fait **qu’au changement réel de besoin**, dans sa fermeture de sortie, jamais comme message répétitif.
>
> Taxonomy termine son travail intellectuel, écrit son triplet exact dans le Blueprint, consomme l’IdeaSlot exact correspondant, puis seulement si cette consommation fait passer l’occurrence du Domain de « encore exploitable » à « vide », il émet une seule fois `DOMAIN_EXHAUSTED(depth, domain)`.
>
> Ce fait n’active pas KRP. Il reste en attente jusqu’à la prochaine activation KRP, qui n’arrive qu’après ReadyBank → CURRENT_KERNEL_RECEIVED → Factory → nouveau KernelBlueprint.

---

# 1. Mission

`KernelRotationPlanner` (KRP) est l’autorité unique de rotation du moteur intellectuel.

À chaque activation, KRP reçoit un **nouveau KernelBlueprint déjà créé** et choisit exactement :

```text
depth + domain
```

KRP décide à partir de :

1. `RotationState` — son propre état persistant ;
2. `DepthNeedMatrix` — les besoins quantitatifs globaux par Depth ;
3. les faits `DOMAIN_EXHAUSTED(depth, domain)` émis par une phase Taxonomy **déjà terminée** et conservés jusqu’à la prochaine activation KRP.

KRP ne lit pas Taxonomy, ne poll pas ses Banks et ne s’exécute jamais en parallèle de Taxonomy.

---

# 2. Invariant d’exécution séquentielle

Principe absolu :

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

Séquence normale :

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
Taxonomy ferme sa sortie
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

État interdit :

```text
KRP ACTIF
+
Taxonomy ACTIF
```

Aucun signal Taxonomy ne réactive KRP pendant la phase Taxonomy.

---

# 3. Frontière de sortie Taxonomy

## 3.1 Moment exact

Taxonomy ne distribue aucune information de rotation pendant son travail intermédiaire.

La vérification pertinente survient dans la **fermeture de sortie Taxonomy**, lorsque le triplet exact est prêt et que l’écriture du noyau courant se termine.

Séquence contractuelle :

```text
Taxonomy sélectionne l’IdeaSlot exact
↓
triplet exact prêt :
Subdomain + Subject + Dominant Idea
↓
écriture Blueprint réussie
↓
consommation immédiate du même IdeaSlot
↓
Taxonomy évalue l’état final de l’occurrence du Domain
```

Si du contenu exploitable reste :

```text
aucun changement de besoin
↓
AUCUN SIGNAL
```

Si cette consommation fait passer le Domain de :

```text
ENCORE EXPLOITABLE
↓
VIDE
```

alors Taxonomy émet :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

puis Taxonomy termine.

## 3.2 Signification exacte

`DOMAIN_EXHAUSTED(depth, domain)` signifie uniquement :

```text
CE DOMAIN EST VIDE
```

Il ne signifie jamais :

- passe au prochain Domain ;
- ferme le tour ;
- passe au prochain Depth ;
- mets HOLD ;
- exécute KRP maintenant.

## 3.3 Signal par changement seulement

Taxonomy n’informe KRP **que lorsqu’il y a un changement réel de besoin**.

Donc :

- tant que le Domain reste exploitable → silence ;
- un noyau supplémentaire dans le même Domain → silence si rien ne change ;
- un Domain déjà déclaré vide → aucun nouveau signal normal pour la même occurrence ;
- aucun signal positif `AVAILABLE` n’est nécessaire ;
- `DOMAIN_EXHAUSTED` est émis au maximum une fois par occurrence de bassin Taxonomy, au passage réel vers l’état vide ;
- une nouvelle occurrence du même `(Depth + Domain)` dans un tour futur peut produire un nouveau `DOMAIN_EXHAUSTED` lorsqu’elle devient elle-même vide.

Le fonctionnement normal est donc **delta-only** : Taxonomy communique le changement, pas l’état répétitif.

---

# 4. Conservation du fait sans activation KRP

Après la fermeture Taxonomy :

```text
Taxonomy FIN
↓
DOMAIN_EXHAUSTED(depth, domain) si changement réel
↓
fait conservé par la frontière de communication
↓
KRP INACTIF
```

Le mécanisme technique de conservation — inbox, outbox, file, table, événement durable ou équivalent — n’est pas fixé par le contrat métier.

Exigences sémantiques :

- le fait doit survivre jusqu’à la prochaine activation KRP ;
- il ne doit pas déclencher KRP ;
- il ne doit pas contenir le prochain Domain ou prochain Depth ;
- il doit être dédupliqué/idempotent pour la même occurrence ;
- la frontière de communication ne prend aucune décision de rotation.

---

# 5. Position canonique de KRP

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

ReadyBank ne remet jamais l’ancien Blueprint à KRP.

`CURRENT_KERNEL_RECEIVED` déclenche le lifecycle, mais ne décide aucune rotation.

---

# 6. Responsabilités

KRP doit :

1. recevoir un nouveau Blueprint identifié avec `depth/domain` vides ;
2. charger `RotationState` ;
3. consommer les faits `DOMAIN_EXHAUSTED` en attente provenant de phases Taxonomy terminées ;
4. valider qu’un fait correspond à une occurrence/position de rotation connue ;
5. transformer un fait Domain vide en état KRP `ESTOMPÉ` ;
6. traiter un replay déjà appliqué comme `NO-OP` ;
7. exclure tout Domain `ESTOMPÉ` des sélections restantes du tour courant ;
8. conserver le même Domain tant qu’il reste `VISIBLE` ;
9. choisir seul le prochain Domain `VISIBLE` selon le `DomainCycle` ;
10. constater seul qu’un tour est terminé lorsque ses huit Domaines sont `ESTOMPÉ` ;
11. fermer seul ce tour ;
12. incrémenter `cycle_completed[depth]` exactement une fois pour ce tour ;
13. consulter `DepthNeedMatrix` pour le prochain Depth encore nécessaire ;
14. revenir vers Depth 2 après Depth 10 lorsqu’un besoin subsiste ;
15. produire `PRODUCTION_ON_HOLD` uniquement lorsque toutes les cibles globales sont satisfaites ;
16. écrire uniquement `depth + domain` dans le Blueprint ;
17. persister ses transitions avant de les considérer commises ;
18. terminer complètement avant activation de Taxonomy.

---

# 7. Interdictions

KRP ne doit jamais :

- créer le KernelBlueprint ;
- modifier `blueprint_id` ;
- recycler l’ancien Blueprint ;
- être actif en même temps que Taxonomy ;
- être invoqué par Taxonomy pendant l’exécution Taxonomy ;
- lire ou poller SubjectBanks, IdeaBanks, curseurs ou états internes Taxonomy ;
- demander à Taxonomy « reste-t-il du contenu ? » pendant sa création ;
- recevoir de Taxonomy le prochain Domain ou prochain Depth ;
- recevoir `DEPTH_EXHAUSTED` comme commande Taxonomy ;
- appliquer une rotation au moment où Taxonomy émet son signal ;
- exiger un signal Taxonomy à chaque noyau ;
- exiger un signal positif lorsque rien ne change ;
- écrire `subdomain_active`, `subject_active`, `dominant_idea_active` ou `kernel_code` ;
- créer ou valider du contenu intellectuel ;
- utiliser `Général` comme domaine de création ;
- déclarer HOLD uniquement parce que Depth 10 vient d’être terminé ;
- inventer les interfaces Phase1/Phase2.

---

# 8. Entrées

## 8.1 Nouveau KernelBlueprint

```text
blueprint_id           = REMPLI
depth                  = NULL
domain                 = NULL
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

## 8.2 RotationState

KRP possède l’état nécessaire pour :

- Depth courant ;
- Domain courant ;
- position dans le DomainCycle ;
- états `VISIBLE / ESTOMPÉ` des huit Domaines du tour ;
- état `OPEN / CLOSED` du tour ;
- fermeture exactement une fois ;
- état opérationnel `NORMAL / BLOCKED / PRODUCTION_ON_HOLD`.

## 8.3 Faits Domain en attente

Interface sémantique :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Ces faits ont été produits par une fermeture Taxonomy antérieure, uniquement au changement réel vers Domain vide.

Ils sont disponibles à la **prochaine activation KRP**.

## 8.4 DepthNeedMatrix

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

`DepthNeedMatrix` ne connaît pas les Banks Taxonomy et ne décide pas qu’un Domain est vide.

---

# 9. Sorties

## 9.1 Sortie normale

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

## 9.2 Sortie opérationnelle

```text
PRODUCTION_ON_HOLD
```

uniquement si tous les Depths ont satisfait leurs cibles et qu’aucune transition KRP n’est incertaine.

---

# 10. Slots Blueprint

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

# 11. Données internes et cycles officiels

## 11.1 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

Après 10, la recherche reprend cycliquement vers le premier Depth encore nécessaire, potentiellement 2.

## 11.2 DomainCycle

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

## 11.3 États Domain

```text
VISIBLE
ESTOMPÉ
```

Définition :

```text
ESTOMPÉ
= Domain abstrait/exclu des rotations restantes du tour courant
```

---

# 12. Mécanisme de rotation

## 12.1 Premier démarrage

Sans fait antérieur :

1. KRP consulte `DepthNeedMatrix` ;
2. choisit le premier Depth nécessaire ;
3. ouvre le tour avec huit Domaines `VISIBLE` ;
4. choisit Géographie ;
5. écrit `depth + domain` ;
6. termine.

## 12.2 Domain toujours visible

Si aucun fait Domain vide n’est en attente pour le Domain courant :

```text
Domain courant = VISIBLE
↓
KRP conserve même Depth + même Domain
```

Un nouveau Blueprint ne fait jamais tourner automatiquement le Domain.

## 12.3 Application d’un fait Domain vide

À l’activation KRP suivante :

```text
KRP consomme DOMAIN_EXHAUSTED(depth, domain)
↓
valide le territoire
↓
persiste VISIBLE → ESTOMPÉ
```

Une fois `ESTOMPÉ`, KRP ne sélectionne plus ce Domain dans le tour courant.

## 12.4 Choix du Domain suivant

Après application des faits en attente :

```text
Domain courant ESTOMPÉ
↓
KRP parcourt SON DomainCycle
↓
choisit le prochain Domain VISIBLE
```

Taxonomy n’intervient pas dans ce choix.

## 12.5 Fin de tour

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
ouverture d’un nouveau tour avec 8 Domaines VISIBLE
↓
Géographie
```

Taxonomy n’émet aucun `DEPTH_EXHAUSTED`.

## 12.6 Après Depth 10

```text
Tour Depth 10 fermé
↓
DepthNeedMatrix
↓
prochain Depth encore nécessaire
```

Si Depth 2 est encore nécessaire, KRP ouvre un nouveau tour Depth 2.

---

# 13. Communication inter-modules

## 13.1 KRP → Taxonomy

```text
KRP ACTIVE
→ écrit depth + domain
→ KRP FIN
→ Taxonomy ACTIVE
```

## 13.2 Taxonomy → frontière de communication

Dans la fermeture de sortie Taxonomy :

```text
triplet écrit avec succès
↓
IdeaSlot exact consommé
↓
le besoin a-t-il changé vers Domain vide ?
```

Si NON :

```text
silence
```

Si OUI :

```text
DOMAIN_EXHAUSTED(depth, domain)
↓
fait conservé
↓
Taxonomy FIN
↓
KRP reste INACTIF
```

## 13.3 ReadyBank → prochain KRP

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ lifecycle
→ Factory
→ nouveau Blueprint
→ KRP ACTIVE
```

---

# 14. Contrats

## 14.1 Contrat du signal

```text
DOMAIN_EXHAUSTED(depth, domain)
```

est :

- factuel ;
- delta-only ;
- émis uniquement au passage vers Domain vide ;
- non répétitif pour la même occurrence ;
- sans destination de rotation ;
- non déclencheur de KRP.

## 14.2 Contrat de conservation

Le fait doit rester disponible jusqu’à sa consommation KRP, sans perdre son identité d’occurrence et sans double effet.

## 14.3 Contrat KRP

KRP transforme le fait en :

```text
VISIBLE → ESTOMPÉ
```

puis applique lui-même son contrat de rotation.

---

# 15. États et transitions

## 15.1 Domain

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

## 15.2 Tour

```text
OPEN
↓ huit Domaines ESTOMPÉ constatés pendant une activation KRP
CLOSED
```

Un futur retour au même Depth ouvre un nouveau tour distinct avec huit Domaines `VISIBLE`.

## 15.3 Module actif

États normaux :

```text
KRP_ACTIVE → KRP_DONE → TAXONOMY_ACTIVE → TAXONOMY_DONE → ...
```

État interdit :

```text
KRP_ACTIVE AND TAXONOMY_ACTIVE
```

---

# 16. Persistance et idempotence

## 16.1 Émission Taxonomy

Pour une occurrence donnée :

```text
encore exploitable → vide
```

peut produire au maximum un signal métier normal `DOMAIN_EXHAUSTED`.

Un retry technique de transport ne doit pas créer un second effet métier.

## 16.2 Consommation KRP

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

Un fait contradictoire ou hors occurrence connue ne provoque aucune rotation.

## 16.3 Fermeture de tour

Un même tour `CLOSED` ne peut incrémenter `cycle_completed` qu’une seule fois, y compris après reprise/retry.

L’identité technique garantissant cet `exactly once` appartient à l’implantation KRP ; elle n’est pas une responsabilité Taxonomy.

## 16.4 Échec technique KRP

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

# 17. Cas limites

1. Aucun changement de besoin Taxonomy → aucun signal.
2. Plusieurs noyaux successifs dans le même Domain exploitable → aucun signal.
3. Dernier IdeaSlot consommé et Domain devient vide → un seul `DOMAIN_EXHAUSTED`.
4. Retry de transport du même fait → aucun double effet métier.
5. Fait Domain vide en attente → KRP l’applique uniquement à sa prochaine activation.
6. Domain déjà `ESTOMPÉ` → replay = `NO-OP`.
7. Domain `ESTOMPÉ` → exclu de toute sélection restante du tour.
8. Huit Domaines `ESTOMPÉ` → KRP ferme le tour.
9. `cycle_completed` n’augmente qu’une fois par tour.
10. Depth suivant déjà satisfait → ignoré par Matrix.
11. Depth 10 terminé avec besoin restant → retour vers prochain Depth nécessaire.
12. Tous besoins satisfaits → HOLD.
13. Nouveau Blueprint sans signal d’épuisement → pas de rotation automatique.
14. Taxonomy échoue avant écriture Blueprint réussie → aucun `DOMAIN_EXHAUSTED` ne doit être considéré acquis à partir de ce travail incomplet.
15. Nouvelle occurrence future du même `(Depth + Domain)` → peut produire son propre signal lorsqu’elle devient vide.

---

# 18. Validation contractuelle

KRP v3.7 est conforme seulement si les tests prouvent :

1. Factory crée un nouveau Blueprint avant KRP ;
2. KRP et Taxonomy ne sont jamais actifs simultanément ;
3. Taxonomy n’émet aucun fait pendant son travail intermédiaire ;
4. triplet Blueprint écrit avec succès avant qu’un changement final vers vide puisse être communiqué ;
5. le même IdeaSlot écrit est consommé avant l’évaluation finale de vacuité ;
6. aucun changement de besoin → aucun signal ;
7. passage réel encore exploitable → vide → exactement un `DOMAIN_EXHAUSTED` pour l’occurrence ;
8. `DOMAIN_EXHAUSTED` signifie uniquement « ce Domain est vide » ;
9. le fait n’active pas KRP ;
10. le fait reste disponible jusqu’à la prochaine activation KRP ;
11. à cette activation, KRP applique `VISIBLE → ESTOMPÉ` ;
12. `ESTOMPÉ` exclut le Domain des rotations restantes du tour ;
13. sans fait en attente, KRP conserve le même Domain ;
14. KRP choisit seul le prochain Domain `VISIBLE` ;
15. huit Domaines `ESTOMPÉ` → KRP ferme seul le tour ;
16. `cycle_completed` incrémenté exactement une fois ;
17. prochain Depth choisi via `DepthNeedMatrix` ;
18. après 10, retour possible vers 2 ;
19. HOLD seulement si toutes les cibles sont satisfaites ;
20. aucun `DEPTH_EXHAUSTED` Taxonomy actif ;
21. KRP écrit uniquement `depth + domain` ;
22. le même Blueprint devient ensuite l’entrée Taxonomy ;
23. aucune lecture/poll des Banks Taxonomy par KRP.

---

# 19. Tests obligatoires

Minimum contractuel :

1. `Factory_before_KRP` ;
2. `KRP_and_Taxonomy_never_active_together` ;
3. `Taxonomy_silent_while_domain_still_exploitable` ;
4. `Taxonomy_emits_only_after_successful_final_blueprint_write_and_consumption` ;
5. `Taxonomy_emits_once_on_exploitable_to_empty_transition` ;
6. `Taxonomy_does_not_repeat_domain_exhausted_for_same_occurrence` ;
7. `Signal_does_not_activate_KRP` ;
8. `Pending_signal_survives_until_next_KRP_activation` ;
9. `KRP_consumes_signal_and_marks_domain_estompe` ;
10. `Estompe_domain_is_excluded_from_current_tour_rotation` ;
11. `No_pending_signal_keeps_same_domain` ;
12. `KRP_selects_next_visible_domain` ;
13. `Eight_estompe_domains_close_tour_once` ;
14. `DepthNeedMatrix_selects_next_needed_depth` ;
15. `Depth10_wraps_to_next_needed_depth` ;
16. `Hold_only_when_all_targets_satisfied` ;
17. `No_Taxonomy_DEPTH_EXHAUSTED_contract` ;
18. `KRP_writes_only_depth_domain` ;
19. `Persistence_retry_domain_transition_1_plus_3` ;
20. `Persistence_retry_depth_close_1_plus_3`.

Non-régression minimale :

- KernelBlueprint Section 1 ;
- KernelBlueprintFactory.

---

# 20. Architecture = 100 %

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

# 21. Extension future Phases 1–2

KRP est verrouillé ici pour la partie intellectuelle.

Toute future exigence Phase1/Phase2 qui modifierait KRP exige :

```text
spécification propriétaire de la Phase
↓
nouvelle version KRP
↓
nouvelle DEC
```

Aucune interface Phase1/Phase2 n’est inventée dans v3.7.
