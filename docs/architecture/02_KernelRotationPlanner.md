# STRATEGYBUZZER — MÉCANISME EXACT DU KERNELROTATIONPLANNER

**Version :** 3.0
**Date :** 13 août 2026
**Statut :** UNDER_REVIEW
**Remplace :** version 2.1 intégralement
**Aucune implémentation pendant cette phase.**

---

# 1. Principe central

`KernelRotationPlanner` décide du prochain couple `depth + domain` et l'écrit **une seule fois** dans le Blueprint.

Flow canonique :

```
KernelBlueprintFactory
↓
Blueprint canonique vide
↓
KernelRotationPlanner
↓
écrit depth + domain (write-once)
↓
Taxonomy
  ↕ ValidationDominantIdeas
↓
QuestionIntent / KernelCodeEngine
↓
suite du pipeline intellectuel
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
nouveau Blueprint
↓
KRP — rotation suivante
```

Nous sommes uniquement dans le pipeline de **création intellectuelle**.

---

# 2. Mission et responsabilités

## 2.1 KernelBlueprintFactory

Responsabilités :

- créer une nouvelle instance vide de `KernelBlueprint` ;
- générer `blueprint_id` (UUIDv7 — `Str::orderedUuid()`) ;
- créer l'enregistrement d'exécution dans `kernel_blueprint_runs` ;
- vérifier qu'aucun Blueprint actif (`CREATED_UNENGAGED` ou `ENGAGED_IN_PIPELINE`) n'existe déjà ;
- ne remplir aucun slot métier ;
- ne sélectionner aucun Depth ;
- ne sélectionner aucun Domaine.

## 2.2 KernelRotationPlanner

Responsabilités :

- recevoir un Blueprint déjà créé par `KernelBlueprintFactory` ;
- lire l'état de rotation persisté (`active_depth`, `domain_states`) ;
- sélectionner le prochain Domaine encore ACTIF dans le DomainCycle ;
- écrire `depth + domain` via `fillRotation()` (write-once) ;
- avancer la position dans le DomainCycle au prochain `CURRENT_KERNEL_RECEIVED` ;
- sauter les domaines marqués `DOMAIN_EXHAUSTED` pour le Depth courant ;
- changer de Depth après `DEPTH_EXHAUSTED` de Taxonomy ;
- persister l'état de rotation.

Interdictions absolues :

- ne jamais créer le Blueprint ;
- ne jamais écrire `blueprint_id` ;
- ne jamais écrire `kernel_code` ;
- ne jamais écrire `subdomain_active`, `subject_active`, `dominant_idea_active` ;
- ne jamais réutiliser le même Blueprint avec un autre couple `depth + domain` ;
- ne jamais appeler `overwriteRotation()` — **cette méthode est interdite et n'existe pas** ;
- ne jamais inspecter les réservoirs internes de Taxonomy ;
- ne jamais calculer lui-même l'épuisement d'un bassin Taxonomy.

## 2.3 KernelPipelineOrchestrator

Responsabilités :

- demander la création du Blueprint à `KernelBlueprintFactory` ;
- transmettre le Blueprint à KRP (`planV2`) ;
- transmettre `depth + domain` à Taxonomy (`peekNext`) ;
- sur `TERRITORY_PROVIDED` : écrire les slots Taxonomy + passer `ENGAGED_IN_PIPELINE` ;
- coordonner la suite du pipeline (Taxonomy → VDI → QuestionIntent → ReadyBank) ;
- ne jamais décider lui-même du Depth ou du Domaine.

Interdictions :

- ne jamais réassigner le même Blueprint à un autre Depth ou Domaine ;
- `KernelRotationPlanner.php` n'appelle jamais `KernelCodeEngine` directement.

Note : `KernelPipelineOrchestrator` coordonne les moteurs successifs (KRP → Taxonomy → QuestionIntent).
`KernelRotationPlanner` s'arrête après `fillRotation()`. Ce sont deux responsabilités distinctes.

---

# 3. Identité canonique du Blueprint

```
blueprint_id
```

Format : UUIDv7 (`Str::orderedUuid()`).

Règles :

- généré par `KernelBlueprintFactory` avant l'entrée dans KRP ;
- immuable pendant tout le pipeline ;
- distinct de `kernel_code` ;
- `rotation_identifier` est définitivement supprimé.

---

# 4. Slots Blueprint — périmètre exclusif

| Slot | Propriétaire | Contrainte |
|---|---|---|
| `blueprint_id` | KernelBlueprintFactory | write-once à la création |
| `depth` | KernelRotationPlanner (`fillRotation`) | write-once |
| `domain` | KernelRotationPlanner (`fillRotation`) | write-once |
| `subdomain_active` | Taxonomy | après préparation complète |
| `subject_active` | Taxonomy | après préparation complète |
| `dominant_idea_active` | Taxonomy | après préparation complète |
| `kernel_code` | KernelCodeEngine | hors périmètre KRP |

`depth` et `domain` sont write-once. Aucun composant ne peut les réécrire après `fillRotation()`.

---

# 5. États du cycle de vie du Blueprint

```
CREATED_UNENGAGED
ENGAGED_IN_PIPELINE
READY_BANK_RECEIVED
NOT_ENGAGED_PRODUCTION_ON_HOLD
```

**CREATED_UNENGAGED** — Blueprint créé, aucun slot métier rempli.

**ENGAGED_IN_PIPELINE** — Taxonomy a fourni `subdomain_active / subject_active / dominant_idea_active`. Le couple `depth + domain` est définitivement engagé.

**READY_BANK_RECEIVED** — ReadyBank a reçu le Blueprint terminé. Provoque l'émission de `CURRENT_KERNEL_RECEIVED`.

**NOT_ENGAGED_PRODUCTION_ON_HOLD** — KRP constate qu'aucun Depth disponible. Aucun slot métier inscrit. Le Blueprint reste comme trace technique non engagée.

Règle : à tout instant, un seul Blueprint peut être `CREATED_UNENGAGED` ou `ENGAGED_IN_PIPELINE`.

---

# 6. DomainCycle officiel

```
1. Géographie
2. Histoire
3. Faune
4. Art
5. Sport
6. Cinéma
7. Cuisine
8. Science
```

Code interne (snake_case) :

```
geographie, histoire, faune, art, sport, cinema, cuisine, science
```

Règles :

- `Général` est **exclu** de la création intellectuelle ;
- l'ordre est déterministe et circulaire ;
- **aucun signal `AVAILABLE` n'existe** ;
- le cycle est commun à tous les Depths.

DepthCycle officiel :

```
2 → 4 → 6 → 7 → 8 → 9 → 10
```

Depth 10 est intellectuellement valide. La restriction Solo Boss niveau 100 appartient au gameplay consommateur — elle ne modifie pas la capacité de création de Taxonomy/KRP.

---

# 7. Initialisation KRP

Au démarrage de la production intellectuelle :

```
active_depth = 2
premier domaine = Géographie
domain_states[2] = tous ACTIF
```

Au démarrage de chaque nouveau Depth (après `DEPTH_EXHAUSTED`) :

```
active_depth = prochain Depth du DepthCycle
domain_states[nouveau_depth] = tous ACTIF
```

---

# 8. Rotation normale KRP

## 8.1 Fonctionnement en absence de signal

Taxonomy ne dit rien = le domaine est disponible.

```
CURRENT_KERNEL_RECEIVED
↓
KRP avance au prochain domaine ACTIF du DomainCycle pour ce Depth
↓
nouveau Blueprint créé par KernelBlueprintFactory
↓
KRP écrit depth + domain (write-once)
↓
Taxonomy traite en silence
↓
pipeline intellectuel
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
(répéter)
```

KRP ne connaît pas :

- le numéro du sujet actif de chaque domaine ;
- le numéro de l'idée dominante active ;
- l'avancement interne des réservoirs Taxonomy.

**KRP tourne, c'est tout.**

## 8.2 Règle de sélection du domaine

1. Part du domaine précédent.
2. Parcourt le DomainCycle circulairement.
3. Saute les domaines `DOMAIN_EXHAUSTED` pour ce Depth.
4. Retourne le premier domaine `ACTIF` trouvé.

## 8.3 Rotation KRP ≠ Progression Taxonomy (règle fondamentale)

**KRP Tour Number ne détermine jamais le Taxonomy Subject Number ni le Taxonomy Idea Number.**

Les 8 domaines partagent le cycle KRP, mais leurs réservoirs Taxonomy progressent **indépendamment**.

Exemple valide au Tour KRP 2 :

```
Géographie → Sujet 1 / Idée 2
Histoire   → Sujet 2 / Idée 1
```

Ceci est parfaitement correct. Le Sujet 1 de Histoire n'avait qu'une seule idée dominante valide.

Chaque bassin `Depth + Domaine` possède son propre état Taxonomy persistant.

**Interdiction absolue : aucune synchronisation artificielle entre les progressions de domaines.**

---

# 9. Contrat externe attendu de Taxonomy

KRP ne connaît pas les réservoirs internes de Taxonomy.

KRP connaît uniquement :

1. Le territoire fourni (`subdomain_active / subject_active / dominant_idea_active`) après préparation complète.
2. Deux signaux prospectifs que Taxonomy peut émettre après consommation.

## 9.1 Signal normal

**Absence de signal = domaine disponible.**

Taxonomy ne dit rien pendant le fonctionnement normal.
Il n'existe aucun signal `AVAILABLE` nécessaire pour chaque noyau.

## 9.2 Deux signaux d'épuisement

```
DOMAIN_EXHAUSTED(depth, domain)
DEPTH_EXHAUSTED(depth)
```

Taxonomy est **seule autorité** pour déterminer l'épuisement.

KRP ne calcule jamais lui-même la quantité intellectuelle restante.

## 9.3 Premier passage d'un bassin

Lors de la première arrivée KRP sur un bassin non encore initialisé :

Taxonomy prépare :

1. le sous-domaine ;
2. son réservoir de SubjectSlots ;
3. le premier SubjectSlot exploitable ;
4. l'IdeaBank de ce premier sujet (complètement, selon le contrat VDI) ;
5. seulement ensuite ouvre la consommation.

Le Blueprint attend cette préparation.

KRP ne saute pas vers un autre domaine pendant l'initialisation.
KRP ne réécrit pas `depth + domain`.
KRP ne crée pas de nouveau Blueprint.

Taxonomy fournit le territoire quand l'état est READY.

Note : « réservoir IdeaSlots complété » ne signifie pas automatiquement 5 IdeaSlots FILLED.
Certains slots peuvent être EMPTY selon le contrat Taxonomy/VDI.
Le futur contrat `03_Taxonomy` déterminera les critères exacts permettant de déclarer un sujet READY.

## 9.4 Retour sur un bassin déjà initialisé

Taxonomy ne recrée pas le sous-domaine ni le SubjectBank.

Elle recharge son état persistant et continue depuis son propre curseur.

Exemple :

```
Depth 2 / Géographie — Sous-domaine : Rocheuses / Sujet : Parcs

IdeaSlot 1 → CONSUMED
IdeaSlot 2 → FILLED   ← prochain
IdeaSlot 3 → FILLED
IdeaSlot 4 → FILLED
IdeaSlot 5 → EMPTY

Taxonomy utilise IdeaSlot 2
→ ACTIVE → écrit dans Blueprint → CONSUMED
```

## 9.5 Changement de sujet

Quand tous les IdeaSlots exploitables du sujet courant sont CONSUMED :

```
SubjectSlot courant → EXHAUSTED
↓
Taxonomy prépare le SubjectSlot suivant
↓
IdeaBank préparé complètement selon contrat VDI
↓
READY FOR CONSUMPTION
↓
seulement ensuite : subdomain_active / subject_active / dominant_idea_active → Blueprint
```

Le Blueprint attend cette préparation. Aucun Blueprint réassigné.

## 9.6 Consommation exacte

L'IdeaSlot sélectionné = l'IdeaSlot validé VDI = l'IdeaSlot écrit dans le Blueprint = l'IdeaSlot marqué CONSUMED.

```
IdeaSlot exact sélectionné
↓
ACTIVE
↓
subdomain_active / subject_active / dominant_idea_active écrits dans le Blueprint
↓
cette écriture réussit
↓
CE MÊME IdeaSlot → CONSUMED
```

Note pour `03_Taxonomy` : l'actuel `confirmConsumed(depth, domain)` cherche à nouveau « first available idea » — cela crée un risque que l'idée sélectionnée ≠ l'idée consommée. Cet écart doit être résolu dans `03_Taxonomy`. Ne pas corriger le code maintenant.

---

# 10. DOMAIN_EXHAUSTED

## 10.1 Définition

Taxonomy a traité le Blueprint courant normalement.
Après consommation exacte, Taxonomy constate que ce bassin `Depth + Domaine` n'a plus de matière intellectuelle exploitable.

Signal : `DOMAIN_EXHAUSTED(depth, domain)`

Sémantique :
> "Le Blueprint courant reste valide et continue normalement. Après cette consommation, il n'existe plus de matière pour ce Depth + Domaine. Ne m'attribue plus ce Domaine pour les prochains Blueprints de ce Depth."

## 10.2 Signal prospectif

Le Blueprint déclencheur :

- reste **VALIDE** ;
- continue normalement dans le pipeline ;
- n'est PAS réassigné ;
- n'est PAS annulé.

Le signal modifie uniquement la rotation **future** de KRP.

## 10.3 Portée

Exclusivement : `Depth + Domaine`.

```
DOMAIN_EXHAUSTED(2, faune)
→ Depth 2 / Faune : DOMAIN_EXHAUSTED
→ Depth 4 / Faune : non affecté
→ Depth 6 / Faune : non affecté
→ ...
```

## 10.4 Rotation après DOMAIN_EXHAUSTED

Avant : `GÉ → HI → FA → AR → SP → CI → CU → SC`

Après `DOMAIN_EXHAUSTED(2, faune)` :

```
GÉ → HI → AR → SP → CI → CU → SC → GÉ → ...
```

Même Depth. Aucun Blueprint réassigné. Aucune boucle. Aucun overwrite.

## 10.5 Idempotence

`DOMAIN_EXHAUSTED` reçu deux fois pour le même couple → NO-OP.

---

# 11. DEPTH_EXHAUSTED

## 11.1 Définition

Taxonomy constate que tous les bassins Domaines du Depth courant sont épuisés.

Signal : `DEPTH_EXHAUSTED(depth)`

Sémantique :
> "Le Blueprint courant reste valide. Après cette consommation, tous les bassins Domaines de ce Depth sont épuisés. Le prochain Blueprint doit appartenir au prochain Depth."

## 11.2 Signal prospectif

Le Blueprint déclencheur reste valide et continue normalement.
Le signal modifie uniquement le Depth de la rotation future.

## 11.3 Rotation après DEPTH_EXHAUSTED

```
DEPTH_EXHAUSTED(2)
↓
Blueprint courant continue normalement
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
nouveau Blueprint
↓
KRP : active_depth = 4, domain_states[4] = tous ACTIF
↓
premier domaine : Géographie
```

## 11.4 Idempotence

`DEPTH_EXHAUSTED` reçu alors que `depth_state = DEPTH_EXHAUSTED_PENDING` → NO-OP.

---

# 12. Canal de transmission des signaux d'épuisement

## 12.1 Deux flux distincts

**FLUX INFORMATIONNEL :**

```
Taxonomy
↓
consommation exacte
↓
détection éventuelle : DOMAIN_EXHAUSTED ou DEPTH_EXHAUSTED
↓
information rendue disponible immédiatement à la couche de rotation
(pour les prochains Blueprints)
```

**FLUX DE DÉCLENCHEMENT :**

```
Blueprint courant continue
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
nouveau Blueprint
↓
KRP applique réellement la rotation modifiée
```

Ces deux flux sont indépendants. Taxonomy met à jour l'information d'épuisement immédiatement. KRP effectue réellement la rotation au prochain `CURRENT_KERNEL_RECEIVED`.

## 12.2 Autorités

```
Taxonomy = autorité de l'épuisement
KRP      = autorité de la rotation
ReadyBank = déclencheur de la rotation suivante
```

Taxonomy ne modifie pas arbitrairement la rotation elle-même.

KRP ne consulte pas les réservoirs internes de Taxonomy pour décider de l'épuisement.

## 12.3 Canal technique — DÉCISION OUVERTE (D1)

La forme exacte du canal entre Taxonomy et l'état KRP n'est pas encore définie.

Contraintes du canal à respecter :

- pas de second propriétaire de l'état de rotation ;
- Taxonomy n'écrit pas directement dans `kernel_rotation_state_v2` ;
- KRP ne lit pas les tables Taxonomy pour décider de l'épuisement.

Options à trancher lors de la spécification de `03_Taxonomy` :

- valeur de retour enrichie de la consommation Taxonomy ;
- événement / Listener distinct (Outbox) ;
- table d'état intermédiaire consultée par KRP au prochain `CURRENT_KERNEL_RECEIVED`.

**Ne rien implémenter sur ce point sans décision.**

---

# 13. CURRENT_KERNEL_RECEIVED

Seul déclencheur officiel de la prochaine rotation.

## 13.1 Canal

Événement transactionnel via `kernel_pipeline_outbox`.

Payload obligatoire :

```
event_id          UUID
event_type        = CURRENT_KERNEL_RECEIVED
schema_version    = 1
blueprint_id      UUID
depth             int
domain            string
occurred_at       datetime
```

`kernel_code` peut être présent pour traçabilité — KRP ne l'utilise pas pour l'idempotence.

## 13.2 Listener

`ApplyCurrentKernelReceivedToRotation`

Séquence :

1. vérifier `blueprint_id` dans `kernel_current_kernel_receipts` ;
2. si présent → NO-OP (idempotent) ;
3. sinon → insérer le reçu + `kernel_received_total[depth][domain] += 1` ;
4. marquer l'événement Outbox comme traité.

## 13.3 Règle de comptabilisation

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si des slots sont `FAIL`.

Quarantine ne bloque jamais la rotation.

---

# 14. États internes de rotation (persistés)

## 14.1 État de rotation

```
active_depth      : int (2, 4, 6, 7, 8, 9, ou 10)
domain_states     : map depth → map domain → ACTIF | DOMAIN_EXHAUSTED
depth_state       : ROTATION_ACTIVE | DEPTH_EXHAUSTED_PENDING | NOT_ENGAGED_PRODUCTION_ON_HOLD
```

## 14.2 Transitions

**DOMAIN_EXHAUSTED reçu :**

```
domain_states[depth][domain] : ACTIF → DOMAIN_EXHAUSTED
(idempotent : DOMAIN_EXHAUSTED → DOMAIN_EXHAUSTED = NO-OP)
```

**DEPTH_EXHAUSTED reçu :**

```
depth_state : ROTATION_ACTIVE → DEPTH_EXHAUSTED_PENDING
(idempotent : DEPTH_EXHAUSTED_PENDING → DEPTH_EXHAUSTED_PENDING = NO-OP)
```

**Au prochain CURRENT_KERNEL_RECEIVED si DEPTH_EXHAUSTED_PENDING :**

```
active_depth = prochain Depth du DepthCycle
domain_states[nouveau_depth] = tous ACTIF
depth_state = ROTATION_ACTIVE
```

---

# 15. Flow initial

```
KernelPipelineOrchestrator
↓
KernelBlueprintFactory::create()
↓
Blueprint = CREATED_UNENGAGED
↓
KRP : active_depth = 2, premier domaine = Géographie
↓
fillRotation(2, 'geographie')
↓
Taxonomy.peekNext(2, 'geographie')
↓
préparation du bassin si non initialisé
↓
TERRITORY_PROVIDED
↓
Blueprint.fillTaxonomy(subdomain, subject, dominant_idea)
↓
Blueprint = ENGAGED_IN_PIPELINE
↓
pipeline intellectuel
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
nouveau Blueprint
↓
KRP avance au prochain domaine : Histoire
```

---

# 16. Flow récurrent (rotation normale)

```
CURRENT_KERNEL_RECEIVED
↓
ApplyCurrentKernelReceivedToRotation
↓
kernel_received_total[depth][domain] += 1 (idempotent)
↓
KernelBlueprintFactory::create()
↓
KRP : active_depth inchangé, prochain domaine ACTIF
↓
fillRotation(depth, next_domain)
↓
Taxonomy.peekNext(depth, next_domain)
↓
TERRITORY_PROVIDED (cas normal)
↓
pipeline intellectuel
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
(répéter)
```

Pendant le flux normal, Taxonomy ne dit rien.

---

# 17. Flow avec DOMAIN_EXHAUSTED

```
Blueprint A : KRP → Depth 2 + Faune
↓
Taxonomy consomme la dernière matière disponible pour Faune/Depth 2
↓
Blueprint A continue normalement dans le pipeline
↓
Taxonomy constate : DOMAIN_EXHAUSTED(2, 'faune')
↓
Canal D1 (à définir) : KRP reçoit le signal
↓
domain_states[2][faune] = DOMAIN_EXHAUSTED
↓
Blueprint A → ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
nouveau Blueprint B
↓
KRP : Depth 2, prochain domaine ACTIF après Faune = Art
↓
fillRotation(2, 'art')
```

---

# 18. Flow avec DEPTH_EXHAUSTED

```
Blueprint X : KRP → Depth 2 + Science (dernier domaine actif)
↓
Taxonomy consomme la dernière matière du dernier domaine actif de Depth 2
↓
Blueprint X continue normalement
↓
Taxonomy constate : DEPTH_EXHAUSTED(2)
↓
Canal D1 (à définir) : KRP reçoit le signal
↓
depth_state = DEPTH_EXHAUSTED_PENDING
↓
Blueprint X → ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
nouveau Blueprint Y
↓
KRP : active_depth = 4, domain_states[4] = tous ACTIF
↓
fillRotation(4, 'geographie')
```

---

# 19. Cas limites

## 19.1 Premier passage (Taxonomy non initialisée)

KRP écrit `depth + domain`. Le Blueprint attend la préparation Taxonomy. Aucun timeout côté KRP.

## 19.2 Tous les domaines d'un Depth DOMAIN_EXHAUSTED

Si tous les domaines d'un Depth reçoivent `DOMAIN_EXHAUSTED` sans que `DEPTH_EXHAUSTED` soit encore reçu :

→ KRP ne peut pas sélectionner de domaine.
→ KRP attend `DEPTH_EXHAUSTED` de Taxonomy.
→ KRP ne passe pas au Depth suivant de sa propre initiative.

Note : `DEPTH_EXHAUSTED` est l'autorité. KRP ne dérive pas `DEPTH_EXHAUSTED` depuis les `DOMAIN_EXHAUSTED` individuels.

## 19.3 DEPTH_EXHAUSTED(10) — transition terminale

**DÉCISION ENCORE OUVERTE (D2).**

Que fait KRP après `DEPTH_EXHAUSTED` sur Depth 10 ?

Options à trancher, sans créer aucun état silencieusement :

- KRP s'arrête en état `IDLE` (attend recharge externe) ;
- KRP recommence depuis Depth 2 (cycle infini) ;
- KRP émet un signal ops et se met en pause.

**Ne rien implémenter sans décision.**

## 19.4 Blueprint actif existant au redémarrage

La présence de `active_blueprint_identity` dans l'état persisté ne déclenche pas une nouvelle rotation.

Le véritable déclencheur reste `CURRENT_KERNEL_RECEIVED`.

## 19.5 Reprise après crash

L'état est rechargé depuis `kernel_rotation_state_v2`.

Aucun domaine n'est remis à `ACTIF` lors d'une reprise.
Les `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED_PENDING` précédents sont préservés.

---

# 20. Persistance exacte

Migrations additives — aucune colonne legacy supprimée dans cette série.

## 20.1 `kernel_blueprint_runs`

```
blueprint_id      UUID PRIMARY KEY
execution_state   VARCHAR  (CREATED_UNENGAGED | ENGAGED_IN_PIPELINE
                             | READY_BANK_RECEIVED | NOT_ENGAGED_PRODUCTION_ON_HOLD)
depth             SMALLINT NULL
domain_code       VARCHAR  NULL
created_at        TIMESTAMP
engaged_at        TIMESTAMP NULL
received_at       TIMESTAMP NULL
updated_at        TIMESTAMP
```

## 20.2 `kernel_rotation_state_v2`

Une seule ligne active.

```
id
active_depth                      SMALLINT NULL
depth_state                       VARCHAR  (ROTATION_ACTIVE | DEPTH_EXHAUSTED_PENDING
                                            | NOT_ENGAGED_PRODUCTION_ON_HOLD)
domain_states                     JSON
active_blueprint_identity         VARCHAR  NULL
last_counted_blueprint_identity   VARCHAR  NULL
lock_version                      INTEGER
created_at                        TIMESTAMP
updated_at                        TIMESTAMP
```

`domain_states` — format par Depth :

```json
{
  "2": {
    "geographie": "ACTIF",
    "histoire":   "ACTIF",
    "faune":      "DOMAIN_EXHAUSTED",
    "art":        "ACTIF",
    "sport":      "ACTIF",
    "cinema":     "ACTIF",
    "cuisine":    "ACTIF",
    "science":    "ACTIF"
  }
}
```

Note : le champ `tour_domain_states` existant (format ON/OFF) est le champ legacy à migrer vers ce format.

## 20.3 `kernel_depth_domain_totals`

56 lignes (7 Depths × 8 Domaines) — traçabilité uniquement.

```
depth                  SMALLINT
domain_code            VARCHAR
kernel_received_total  BIGINT   DEFAULT 0
created_at             TIMESTAMP
updated_at             TIMESTAMP
PRIMARY KEY (depth, domain_code)
```

`kernel_received_total` = compteur de traçabilité. **Non utilisé comme autorité de rotation.**

## 20.4 `kernel_current_kernel_receipts`

```
blueprint_id  UUID PRIMARY KEY
event_id      UUID UNIQUE
depth         SMALLINT
domain_code   VARCHAR
received_at   TIMESTAMP
```

Garantit l'idempotence de `CURRENT_KERNEL_RECEIVED`.

## 20.5 `kernel_pipeline_outbox`

```
event_id       UUID PRIMARY KEY
event_type     VARCHAR
schema_version INTEGER
payload        JSON (TEXT sur SQLite)
occurred_at    TIMESTAMP
processed_at   TIMESTAMP NULL
attempt_count  INTEGER    DEFAULT 0
last_error     TEXT NULL
created_at     TIMESTAMP
updated_at     TIMESTAMP
```

---

# 21. Atomicité obligatoire

## 21.1 Inscription `depth + domain`

Dans une seule transaction :

1. écrire `depth + domain` via `fillRotation()` ;
2. enregistrer `active_blueprint_identity` dans `kernel_rotation_state_v2`.

## 21.2 Traitement DOMAIN_EXHAUSTED

Dans une seule transaction :

1. verrouiller `kernel_rotation_state_v2` ;
2. vérifier que le domaine est `ACTIF` pour ce Depth ;
3. passer à `DOMAIN_EXHAUSTED` ;
4. persister.

`DOMAIN_EXHAUSTED` répété pour un domaine déjà `DOMAIN_EXHAUSTED` → NO-OP.

## 21.3 Traitement DEPTH_EXHAUSTED

Dans une seule transaction :

1. verrouiller `kernel_rotation_state_v2` ;
2. passer `depth_state` à `DEPTH_EXHAUSTED_PENDING` ;
3. persister.

Au prochain `CURRENT_KERNEL_RECEIVED`, dans une seule transaction :

4. avancer `active_depth` vers le prochain Depth du DepthCycle ;
5. initialiser `domain_states[nouveau_depth]` = tous `ACTIF` ;
6. passer `depth_state` à `ROTATION_ACTIVE`.

## 21.4 CURRENT_KERNEL_RECEIVED

Dans une seule transaction :

1. tentative d'insertion du reçu par `blueprint_id` ;
2. si existe → aucun incrément ;
3. sinon → `kernel_received_total[depth][domain] += 1`.

---

# 22. Idempotence

| Opération | Garantie |
|---|---|
| `CURRENT_KERNEL_RECEIVED` | PK `blueprint_id` sur `kernel_current_kernel_receipts` |
| `DOMAIN_EXHAUSTED` reçu | `DOMAIN_EXHAUSTED → DOMAIN_EXHAUSTED` = NO-OP |
| `DEPTH_EXHAUSTED` reçu | `DEPTH_EXHAUSTED_PENDING → DEPTH_EXHAUSTED_PENDING` = NO-OP |
| Reprise après crash | État rechargé depuis `kernel_rotation_state_v2` sans remise à zéro |

---

# 23. Informations relevant de 03_Taxonomy (contrat externe attendu)

Les points suivants appartiennent à `03_Taxonomy` et ne doivent pas être inventés dans KRP :

- cycle de vie exact des SubjectSlots (EMPTY → FILLED → EXHAUSTED) ;
- cycle de vie exact des IdeaSlots (EMPTY → FILLED → ACTIVE → CONSUMED) ;
- définition exacte d'un réservoir READY ;
- nombre minimal d'idées valides permettant un sujet exploitable ;
- traitement d'un sujet produisant zéro idée valide ;
- passage d'un sous-domaine au suivant ;
- condition exacte d'épuisement réel du Domaine ;
- persistance des identifiants exacts SubjectSlot / IdeaSlot ;
- contrat exact remplaçant l'actuel `confirmConsumed(depth, domain)` ;
- canal technique exact D1 (transmission DOMAIN_EXHAUSTED / DEPTH_EXHAUSTED vers KRP) ;
- reprise après restart / concurrence côté Taxonomy.

---

# 24. Concepts UNDER_REVIEW réévalués

## 24.1 AVAILABLE

**REJETÉ.**

Taxonomy ne doit pas envoyer un signal `AVAILABLE`. L'absence de signal d'épuisement signifie disponibilité.

## 24.2 `cycle_completed` / `CYCLE_TARGET` comme autorité de changement de Depth

**SUPERSEDED par DEPTH_EXHAUSTED.**

KRP ne change pas de Depth parce qu'un nombre arbitraire de Tours est atteint. Il change de Depth lorsque Taxonomy émet `DEPTH_EXHAUSTED`.

`CYCLE_TARGET` et `cycle_completed` peuvent être conservés comme données de traçabilité si une décision officielle le justifie, mais ne constituent plus l'autorité de rotation.

`CYCLE_TARGET[10] = 100` n'est **pas** justifié par le numéro de niveau Solo Boss 100. Le numéro 100 du niveau ne définit aucun volume de production intellectuelle.

## 24.3 `kernel_target` comme autorité de rotation

**REJETÉ comme autorité de rotation.**

La rotation KRP est pilotée par les signaux `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED`, non par un compte à rebours.

## 24.4 `kernel_remaining` comme autorité de rotation

**REJETÉ comme autorité de rotation.**

`kernel_remaining = kernel_target - kernel_received` est mathématiquement correct. Il peut coexister comme donnée de reporting si une décision officielle le justifie, mais n'est pas un critère de sélection de domaine.

## 24.5 Double condition de sélection (`kernel_remaining > 0 AND reservoir_status = AVAILABLE`)

**REJETÉ.**

La sélection repose uniquement sur : domaine `ACTIF` (non `DOMAIN_EXHAUSTED`) pour ce Depth.

## 24.6 `DepthProductionState`

**REJETÉ.**

Structure remplacée par `active_depth + domain_states` dans `kernel_rotation_state_v2`.

## 24.7 SHORTFALL / DEPTH_TARGET_COMPLETE / DEPTH_RESERVOIRS_EXHAUSTED_WITH_SHORTFALL

**REJETÉ.**

Un seul signal : `DEPTH_EXHAUSTED`. L'écart de production est un concept de reporting/observabilité, pas un état de rotation KRP.

---

# 25. Décisions encore réellement ouvertes

## D1 — Canal technique DOMAIN_EXHAUSTED / DEPTH_EXHAUSTED

La forme exacte du canal entre Taxonomy et l'état KRP n'est pas définie.
À spécifier dans `03_Taxonomy` avant implémentation.

## D2 — DEPTH_EXHAUSTED(10) — transition terminale

Que fait KRP après l'épuisement de Depth 10 ?
Ne rien créer ou implémenter sans décision explicite.

## D3 — KRP attend Taxonomy : gestion du temps

Si Taxonomy tarde à préparer un bassin, KRP attend-il indéfiniment ?
Y a-t-il un mécanisme de retry ou d'alerte côté KRP ?
À définir en coordination avec `03_Taxonomy`.

---

# 26. Écarts code actuel ↔ nouvelle architecture

## É1 — EMPTY loop (à supprimer)

Ancien code : `planV2()` appelé plusieurs fois sur le même Blueprint dans `KernelPipelineOrchestrator`.
Nouveau contrat : Blueprint write-once, boucle EMPTY éliminée.
Le chemin `applyEmptyTransitionV2` + boucle `planV2` dans l'Orchestrateur doit être supprimé.

## É2 — `cycle_completed` / `CYCLE_TARGET` comme autorité de Depth (à supersede)

Actuel : `DepthNeedMatrix::nextRequiredDepth()` compare `cycle_completed` vs `CYCLE_TARGET`.
Nouveau : autorité = `DEPTH_EXHAUSTED` de Taxonomy.
À ne pas modifier avant décision D1.

## É3 — `confirmConsumed()` non branché

Implémenté dans `TaxonomyOrchestrator`, jamais appelé en V2.
Le branchement dépend de la définition du canal D1.
À traiter lors de `03_Taxonomy`. Ne pas corriger maintenant.

## É4 — `DomainExhaustionChecker::isExhausted()` (LEGACY PULL)

Mécanisme PULL existant dans `plan()` DEPRECATED. Non utilisé par V2.
À conserver comme référence historique jusqu'à suppression physique autorisée.

## É5 — Format `domain_states` dans `kernel_rotation_state_v2`

Table actuelle : `tour_domain_states` JSON avec états `ON | OFF`.
Nouveau format requis : `domain_states` par Depth (map `depth → domain → ACTIF | DOMAIN_EXHAUSTED`).
Migration additive requise. Ne pas modifier maintenant.

## É6 — `KernelRotationStateRepository` non utilisé par KRP

KRP appelle `DB::table()` directement. Incohérence interne, non bloquante.
À résoudre lors de la réécriture du code KRP.

---

# 27. Règles absolues (KRP-R)

## KRP-R01

`depth` et `domain` sont write-once. `fillRotation()` ne peut être appelé qu'une seule fois sur un Blueprint donné.

## KRP-R02

`overwriteRotation()` est interdit. Cette méthode n'existe pas et ne doit jamais être créée.

## KRP-R03

Taxonomy est seule autorité de l'épuisement. KRP ne calcule pas lui-même l'épuisement d'un bassin.

## KRP-R04

Le signal normal de Taxonomy est l'absence de signal. KRP tourne automatiquement.

## KRP-R05

`DOMAIN_EXHAUSTED` est prospectif. Le Blueprint courant reste valide. Le signal modifie uniquement la rotation future.

## KRP-R06

`DEPTH_EXHAUSTED` est prospectif. Le Blueprint courant reste valide. Le signal modifie uniquement le Depth de la rotation future.

## KRP-R07

Portée de `DOMAIN_EXHAUSTED` : `Depth + Domaine` exclusivement.
`DOMAIN_EXHAUSTED(2, faune)` n'affecte pas Depth 4 / Faune.

## KRP-R08

Aucun signal `AVAILABLE` n'existe.

## KRP-R09

À tout instant, un seul Blueprint peut être `CREATED_UNENGAGED` ou `ENGAGED_IN_PIPELINE`.

## KRP-R10

`Général` est exclu du DomainCycle.

## KRP-R11

`CURRENT_KERNEL_RECEIVED` est le seul déclencheur de la prochaine rotation.
ReadyBank ne décide ni du Depth ni du Domaine.

## KRP-R12

L'idempotence de `CURRENT_KERNEL_RECEIVED` est garantie par `kernel_current_kernel_receipts` (PK `blueprint_id`).

## KRP-R13

L'état de rotation est persistant dans `kernel_rotation_state_v2`.

## KRP-R14

Toute modification de l'état de rotation est exécutée dans une transaction atomique.

## KRP-R15

KRP ne passe pas au Depth suivant de sa propre initiative. `DEPTH_EXHAUSTED` de Taxonomy est requis.

## KRP-R16

KRP n'écrit jamais `kernel_code`.

## KRP-R17

`rotation_identifier` est supprimé. Aucun composant ne le produit ni ne le consomme.

## KRP-R18

**SUPERSEDED.**
Ancienne règle : "sur EMPTY, le même Blueprint est conservé et `depth + domain` remplacés."
Remplacé par KRP-R01 (write-once) et KRP-R05 (DOMAIN_EXHAUSTED prospectif).

## KRP-R19

DepthCycle officiel : `2 → 4 → 6 → 7 → 8 → 9 → 10`.

## KRP-R20

Toute itération de production commence obligatoirement par `KernelBlueprintFactory::create()` puis `KernelRotationPlanner::planV2()`.
Ces deux opérations ne peuvent pas être inversées.

## KRP-R21

**KRP Tour Number ne détermine jamais le Taxonomy Subject Number ni le Taxonomy Idea Number.**
Les 8 domaines partagent le cycle KRP mais leurs réservoirs Taxonomy progressent indépendamment.

## KRP-R22

Le Blueprint attend la préparation Taxonomy. KRP ne saute pas vers un autre domaine pendant l'initialisation d'un bassin.

## KRP-R23

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si des slots sont `FAIL`.
Quarantine ne bloque jamais la rotation.

## KRP-R24

KRP ne dérive pas `DEPTH_EXHAUSTED` depuis les `DOMAIN_EXHAUSTED` individuels.
`DEPTH_EXHAUSTED` de Taxonomy est l'unique autorité de changement de Depth.

---

# 28. Tests requis

## Rotation normale

- Tour complet 8 domaines, aucun signal Taxonomy : KRP avance automatiquement au bon domaine suivant.
- Retour circulaire après Science → Géographie.
- `Général` absent du DomainCycle.

## DOMAIN_EXHAUSTED

- Blueprint courant valide malgré `DOMAIN_EXHAUSTED` reçu simultanément.
- Rotation suivante saute le domaine exhausted.
- Portée limitée au Depth concerné (les autres Depths conservent le domaine `ACTIF`).
- Idempotence : `DOMAIN_EXHAUSTED` reçu deux fois = NO-OP.

## DEPTH_EXHAUSTED

- Blueprint courant valide malgré `DEPTH_EXHAUSTED` reçu.
- `depth_state = DEPTH_EXHAUSTED_PENDING` après réception.
- Au prochain `CURRENT_KERNEL_RECEIVED` : `active_depth` avancé, 8 domaines `ACTIF` initialisés.
- Idempotence : `DEPTH_EXHAUSTED_PENDING → DEPTH_EXHAUSTED_PENDING` = NO-OP.

## CURRENT_KERNEL_RECEIVED

- Idempotence via `kernel_current_kernel_receipts`.
- Blueprint `FAIL` ne bloque pas la rotation.
- `kernel_received_total[depth][domain]` incrémenté une seule fois par `blueprint_id`.

## Progression indépendante des domaines

- Tour KRP 2 : `GÉ → Sujet1/Idée2` + `HI → Sujet2/Idée1` est valide.
- Aucune synchronisation artificielle entre domaines.

## Reprise

- Redémarrage préserve `DOMAIN_EXHAUSTED` déjà reçus.
- Aucun domaine remis à `ACTIF` au redémarrage.
- `DEPTH_EXHAUSTED_PENDING` préservé au redémarrage.

## Invariants éliminés

- Aucun test ne valide la boucle EMPTY (chemin supprimé).
- Aucun test ne valide `overwriteRotation()` (interdit).
- Aucun test ne valide un signal `AVAILABLE` (rejeté).
- Aucun test ne valide `CYCLE_TARGET` comme critère de changement de Depth.

---

# 29. Architecture Register — décisions KRP

## DEC-051 — Initialisation par DepthNeedMatrix

**Statut :** SUPERSEDED par DEC-060

---

## DEC-052 — Réception ReadyBank indépendante de la jouabilité

**Statut :** OFFICIAL

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si des slots sont `FAIL`.

---

## DEC-053 — Deux signaux indépendants

**Statut :** SUPERSEDED par DEC-063

---

## DEC-054 — États distincts des domaines

**Statut :** SUPERSEDED par DEC-061

---

## DEC-055 — Complétion sans domaine sélectionnable

**Statut :** SUPERSEDED par DEC-062

---

## DEC-056 — Persistance obligatoire de RotationState

**Statut :** SUPERSEDED par DEC-064

---

## DEC-057 — DepthCycle sans Depth 10

**Statut :** SUPERSEDED par DEC-065

---

## DEC-058 — Blueprint créé avant KRP

**Statut :** OFFICIAL

`KernelBlueprintFactory` crée le Blueprint avant l'entrée dans KRP. KRP reçoit toujours une enveloppe déjà créée.

---

## DEC-059 — Identité canonique blueprint_id

**Statut :** OFFICIAL

`blueprint_id` est un UUIDv7 généré par `KernelBlueprintFactory`. `rotation_identifier` est supprimé. `kernel_code` ne sert pas d'identité de Blueprint.

---

## DEC-060 — DepthNeedMatrix V2

**Statut :** OFFICIAL (traçabilité)

`DepthNeedMatrix` porte `kernel_received_total[depth][domain]` comme données de traçabilité. `CYCLE_TARGET` et `cycle_completed` ne sont plus l'autorité de changement de Depth — remplacés par `DEPTH_EXHAUSTED` (DEC-083).

---

## DEC-061 — Tour de Depth ON/OFF

**Statut :** SUPERSEDED par DEC-082 + DEC-083

Le modèle Tour ON/OFF basé sur la boucle EMPTY est remplacé par `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` prospectifs.

---

## DEC-062 — Fermeture Tour et bascule Depth via 8/8

**Statut :** SUPERSEDED par DEC-083

Le changement de Depth via compteur 8/8 est remplacé par `DEPTH_EXHAUSTED` de Taxonomy.

---

## DEC-063 — CURRENT_KERNEL_RECEIVED signal unique

**Statut :** OFFICIAL

Seul déclencheur de la prochaine rotation. Canal = événement transactionnel avec Outbox. Listener = `ApplyCurrentKernelReceivedToRotation`. Idempotence = `kernel_current_kernel_receipts` (PK `blueprint_id`).

---

## DEC-064 — Persistance dans kernel_rotation_state_v2

**Statut :** OFFICIAL (format `domain_states` JSON à migrer vers structure par Depth)

---

## DEC-065 — DepthCycle complet incluant Depth 2 et Depth 10

**Statut :** OFFICIAL

DepthCycle = `2 → 4 → 6 → 7 → 8 → 9 → 10`. Depth 10 intellectuellement valide.

---

## DEC-066 — Conservation du Blueprint sur EMPTY

**Statut :** SUPERSEDED par DEC-034 (write-once Blueprint) + DEC-082 (DOMAIN_EXHAUSTED prospectif)

L'ancienne logique EMPTY (même Blueprint réutilisé avec autre `depth + domain`) est incompatible avec le contrat write-once de `01_KernelBlueprint` v1.5.

---

## DEC-067 — Cycle de vie d'exécution du Blueprint

**Statut :** OFFICIAL

Quatre états : `CREATED_UNENGAGED`, `ENGAGED_IN_PIPELINE`, `READY_BANK_RECEIVED`, `NOT_ENGAGED_PRODUCTION_ON_HOLD`.

---

## DEC-068 — KernelCodeEngine hors périmètre KRP

**Statut :** OFFICIAL

`KernelRotationPlanner.php` n'appelle jamais `KernelCodeEngine` directement. `KernelPipelineOrchestrator` coordonne les deux modules de façon distincte.

---

## DEC-078 — Compte à rebours par domaine

**Version :** 1.0 — **Date :** 14 juillet 2026
**Statut :** REJECTED comme autorité de rotation

`kernel_remaining = kernel_target - kernel_received` est mathématiquement correct mais n'est pas un critère de sélection de domaine dans le contrat actuel. Peut coexister comme donnée de reporting si décidé séparément.

---

## DEC-079 — ReadyBank décrémente le besoin

**Version :** 1.0 — **Date :** 14 juillet 2026
**Statut :** PARTIELLEMENT RETENU (traçabilité)

`CURRENT_KERNEL_RECEIVED` est le seul déclencheur d'incrémentation de `kernel_received_total`. La création d'un Blueprint ne modifie jamais ce compteur. Principe de traçabilité conservé.

---

## DEC-080 — Taxonomy et compteur indépendants

**Version :** 1.0 — **Date :** 14 juillet 2026
**Statut :** REJECTED

`reservoir_status (AVAILABLE | EMPTY)` comme signal vers KRP est remplacé par `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED`.

---

## DEC-081 — Shortfall explicite

**Version :** 1.0 — **Date :** 14 juillet 2026
**Statut :** REJECTED

`SHORTFALL` comme état KRP est remplacé par `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED`. L'écart de production est un concept de reporting/observabilité externe à KRP.

---

## DEC-082 — DOMAIN_EXHAUSTED prospectif

**Version :** 1.0 — **Date :** 13 août 2026
**Statut :** UNDER_REVIEW

`DOMAIN_EXHAUSTED(depth, domain)` : signal prospectif de Taxonomy vers KRP, émis après consommation exacte du Blueprint courant.

Le Blueprint déclencheur reste valide et continue normalement dans le pipeline.
Le signal modifie uniquement la rotation future.
Portée : `Depth + Domaine` exclusivement.
Idempotent : deuxième réception du même signal = NO-OP.

---

## DEC-083 — DEPTH_EXHAUSTED prospectif

**Version :** 1.0 — **Date :** 13 août 2026
**Statut :** UNDER_REVIEW

`DEPTH_EXHAUSTED(depth)` : signal prospectif de Taxonomy vers KRP, émis quand tous les bassins Domaines du Depth courant sont épuisés.

Le Blueprint déclencheur reste valide et continue normalement.
Au prochain `CURRENT_KERNEL_RECEIVED` : KRP avance vers le prochain Depth, tous les domaines du nouveau Depth sont réinitialisés `ACTIF`.
Idempotent : `DEPTH_EXHAUSTED_PENDING → DEPTH_EXHAUSTED_PENDING` = NO-OP.

---

## DEC-084 — Indépendance rotation KRP ↔ progression Taxonomy

**Version :** 1.0 — **Date :** 13 août 2026
**Statut :** UNDER_REVIEW

KRP Tour Number ne détermine jamais le Taxonomy Subject Number ni le Taxonomy Idea Number.
Les 8 domaines partagent le cycle KRP mais leurs réservoirs Taxonomy progressent indépendamment.
Aucune synchronisation artificielle entre les progressions de domaines n'est admise.

---

## DEC-085 — Deux flux distincts : informationnel et déclencheur

**Version :** 1.0 — **Date :** 13 août 2026
**Statut :** UNDER_REVIEW

Flux informationnel : Taxonomy → signal d'épuisement → mise à jour de l'état KRP (immédiate, sans attendre ReadyBank).
Flux déclencheur : `CURRENT_KERNEL_RECEIVED` → prochain Blueprint → rotation effective.
Ces deux flux sont indépendants.

---

## DEC-086 — AVAILABLE rejeté

**Version :** 1.0 — **Date :** 13 août 2026
**Statut :** REJECTED

Taxonomy ne doit pas envoyer un signal `AVAILABLE`. L'absence de signal d'épuisement signifie disponibilité implicite du domaine.

---

## DEC-087 — Canal technique d'épuisement non encore défini

**Version :** 1.0 — **Date :** 13 août 2026
**Statut :** UNDER_REVIEW (décision D1 ouverte)

La forme exacte du canal entre Taxonomy et l'état KRP (`DOMAIN_EXHAUSTED`, `DEPTH_EXHAUSTED`) est à définir lors de la spécification de `03_Taxonomy`.

Contraintes : Taxonomy ne modifie pas directement `kernel_rotation_state_v2`. KRP ne consulte pas les tables Taxonomy pour décider de l'épuisement.

---

## DEC-088 — CYCLE_TARGET / cycle_completed SUPERSEDED comme autorité de Depth

**Version :** 1.0 — **Date :** 13 août 2026
**Statut :** UNDER_REVIEW

`CYCLE_TARGET` et `cycle_completed` ne sont plus l'autorité de décision de changement de Depth. `DEPTH_EXHAUSTED` de Taxonomy est l'autorité.

`CYCLE_TARGET[10] = 100` n'est pas justifié par le numéro de niveau Solo Boss 100.

Ces compteurs peuvent être conservés comme données de traçabilité si une décision officielle le justifie, mais sans rôle de rotation.
