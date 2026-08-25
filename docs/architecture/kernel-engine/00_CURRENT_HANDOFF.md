# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-25  
**Branche officielle :** `replit/intellectual-engine-current-2026-08-16`  
**Module actif unique :** `02_KernelRotationPlanner`  
**Spécification active :** `specifications/02_KernelRotationPlanner.md` v4.0  
**Décision active :** `DEC-119 — OFFICIAL`  
**Frontière suivante verrouillée :** `03_Taxonomy.md` v1.1 / `DEC-120 — OFFICIAL`  
**Prochain bloc exact :** `RECOVERY-02-v4.0`

> Ce fichier est le pointeur opérationnel de reprise. Il ne possède aucune autorité architecturale propre. En cas de contradiction, `00_ArchitectureRegister.md + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md + spécification canonique verrouillée du module` priment.

---

# 1. État Git de référence

Réalignement confirmé le 2026-08-25 :

```text
branche locale Replit
=
branche GitHub officielle

HEAD de réalignement
=
59c0698a1053715c3e535cdfc4487fb0f484d906

divergence
=
0 0

working tree
=
CLEAN
```

Le commit local antérieur a été préservé avant réalignement :

```text
backup/replit-ace19555-before-realign-2026-08-25
→ ace19555a5c78e86885c5f9c339e269e1d4ed653
```

Cette sauvegarde est une source de récupération de code uniquement.

Elle n’est jamais une source d’autorité architecturale.

---

# 2. État canonique des trois premiers modules

```text
01 KernelBlueprint
Version : 2.0
Décision : DEC-113 — OFFICIAL
Statut : intact
```

```text
02 KernelRotationPlanner
Version : 4.0
Décision : DEC-119 — OFFICIAL
Architecture : 100 %
Contrat : 100 %
Implémentation : à aligner/récupérer contre v4.0
Validation terminale : NON
Module actif : OUI
```

```text
03 Taxonomy
Version : 1.1
Décision : DEC-120 — OFFICIAL
Architecture : 100 %
Contrat : 100 %
Implémentation : FERMÉE tant que KRP n’est pas terminé
Module actif : NON
```

Décisions historiques :

```text
DEC-114 → SUPERSEDED
DEC-115 → REJECTED
DEC-116 → REJECTED
DEC-117 → REJECTED
DEC-118 → REJECTED
```

Aucune implantation ne doit être construite depuis KRP v3.3, v3.4, v3.5, v3.6 ou v3.7.

---

# 3. Flow officiel actif

```text
CURRENT_KERNEL_RECEIVED
↓
orchestration lifecycle externe
↓
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint identifié
↓
KernelRotationPlanner
↓
application des faits Taxonomy en attente
↓
rotation Domain
+
rotation Depth si nécessaire
↓
écriture write-once de depth + domain
↓
persistance KRP
↓
FIN KRP
↓
porte vers Taxonomy
```

Règles absolues :

- KRP ne crée pas le Blueprint ;
- Factory crée le nouveau Blueprint avant KRP ;
- ReadyBank ne remet jamais l’ancien Blueprint à KRP ;
- `CURRENT_KERNEL_RECEIVED` appartient au lifecycle externe et n’est pas un appel métier direct à KRP ;
- KRP écrit uniquement `depth + domain` ;
- KRP s’arrête après l’écriture de rotation et la persistance réussie de son état.

---

# 4. DepthCycle officiel

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → 2 → ...
```

Règles :

- Depth 10 est autorisé et fait partie du cycle ;
- après Depth 10, retour à Depth 2 si un besoin subsiste ;
- tout Depth dont `cycle_remaining = 0` est sauté ;
- `PRODUCTION_ON_HOLD` est permis uniquement lorsque tous les besoins de `DepthNeedMatrix` sont satisfaits ;
- aucun HOLD automatique après Depth 10 si un besoin demeure.

Ne jamais réintroduire une règle où Depth 10 serait interdit.

---

# 5. Ownership officiel KRP v4.0

KRP possède exclusivement :

```text
DomainRotation
DOMAIN_EXHAUSTED
DEPTH_EXHAUSTED
DepthNeedMatrix
DomainCycle
DepthCycle
RotationState
VISIBLE / ESTOMPÉ
cycle_target
cycle_completed
cycle_remaining
```

## DomainRotation

À chaque nouveau Blueprint :

```text
Domain courant
↓
prochain Domain VISIBLE selon DomainCycle
```

Un Domain `ESTOMPÉ` est ignoré pour le reste du tour courant.

Il est interdit de conserver automatiquement le même Domain simplement parce qu’il est encore `VISIBLE`.

## Fait terminal Taxonomy

Taxonomy transmet seulement le fait :

```text
la dernière Dominant Idea
du dernier Subject exploitable
de l’occurrence attribuée
vient d’être consommée avec succès
```

Ce fait :

- est conservé jusqu’à la prochaine activation KRP ;
- ne déclenche pas immédiatement KRP ;
- ne contient aucune décision de rotation ;
- n’est pas le moteur `DOMAIN_EXHAUSTED`.

## DOMAIN_EXHAUSTED interne KRP

```text
fait terminal Taxonomy en attente
↓
KRP.DOMAIN_EXHAUSTED
↓
Domain concerné : VISIBLE → ESTOMPÉ
↓
persistance avant progression
```

Même fait répété :

```text
NO-OP idempotent
```

## DEPTH_EXHAUSTED interne KRP

Si le dernier Domain `VISIBLE` du tour devient `ESTOMPÉ` :

```text
KRP.DEPTH_EXHAUSTED
↓
fermeture du tour courant
↓
cycle_completed[depth] + 1 exactement une fois
↓
DepthNeedMatrix
↓
prochain Depth dont cycle_remaining > 0
```

Taxonomy ne produit et ne possède jamais `DOMAIN_EXHAUSTED` ou `DEPTH_EXHAUSTED`.

---

# 6. Frontière Taxonomy v1.1

Taxonomy conserve :

```text
Subdomain
SubjectBank
IdeaBanks
ValidationDominantIdeas pendant la création
sélection exacte
écriture exacte
consommation exacte
curseurs
occurrences
fait terminal
```

Taxonomy ne possède pas :

```text
DomainCycle
DepthCycle
VISIBLE / ESTOMPÉ
DepthNeedMatrix
cycle_target
cycle_completed
cycle_remaining
rotation Domain
rotation Depth
DOMAIN_EXHAUSTED moteur
DEPTH_EXHAUSTED moteur
```

Pendant le travail KRP :

```text
03_Taxonomy
=
FERMÉE À L’IMPLANTATION
```

Sa spécification v1.1 peut uniquement être lue comme contrat frontalier.

---

# 7. Prochain bloc exact — RECOVERY-02-v4.0

Mission unique :

```text
comparer le code sauvegardé ace19555
au HEAD officiel aligné
et à KRP v4.0 / DEC-119
afin de récupérer uniquement le code conforme
```

Comparer :

```text
59c0698
↔
backup/replit-ace19555-before-realign-2026-08-25
↔
specifications/02_KernelRotationPlanner.md v4.0
↔
DEC-119
```

Classer chaque différence utile :

```text
KEEP
MODIFY
REJECT
MISSING
UNRESOLVED
```

Règles :

- ne jamais fusionner ou cherry-pick automatiquement tout `ace19555` ;
- ne jamais considérer le code sauvegardé comme autorité ;
- récupérer uniquement les éléments conformes à v4.0 ;
- ne modifier aucun fichier Taxonomy ;
- ne modifier aucun document Bible ;
- ne lancer aucun travail sur les modules suivants ;
- si un véritable `UNRESOLVED` architectural apparaît : `STOP CODE` ;
- sinon définir le premier micro-bloc KRP implantable avec ses fichiers et tests précis.

---

# 8. DO NOT REDO

Ne pas :

- rechercher à nouveau quelle version KRP est officielle ;
- reconstruire DEC-119 ou DEC-120 ;
- réécrire KRP v4.0 ;
- réécrire Taxonomy v1.1 ;
- réintroduire DEC-114 ou DEC-115 à DEC-118 ;
- reprendre Task #163 v3.3 ;
- refaire le réalignement Git 59c0698 ;
- supprimer la sauvegarde `ace19555` ;
- fusionner aveuglément la sauvegarde ;
- démarrer Taxonomy ;
- demander à l’utilisateur de retrouver les documents dans les anciens chats ;
- utiliser les anciens chats comme source principale.

---

# 9. Critère de sortie du prochain bloc

`RECOVERY-02-v4.0` est fermé uniquement lorsque sont connus et prouvés :

```text
code conforme récupérable
code à modifier
code à rejeter
exigences manquantes
premier micro-bloc exact
fichiers autorisés
tests contractuels requis
absence d’UNRESOLVED architectural
```

Puis seulement :

```text
IMPL-02-01-v4.0
```

Aucun statut `FINI` n’est permis avant implantation complète, matrices Bible PASS, validation terminale et non-régression cumulative.
