# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-23  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Module actif :** `02_KernelRotationPlanner`  
**Bloc actif :** `RÉAUDIT-02-v3.7 AVANT REPRISE IMPL-02-01`  
**Dernière décision structurante :** `DEC-118`

> Ce fichier est un pointeur de reprise. En cas de contradiction : `00_ArchitectureRegister.md + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md + spécification canonique du module` priment.

---

# 1. État canonique KRP

```text
specifications/02_KernelRotationPlanner.md
Version : 3.7
Statut : VERROUILLÉ — PARTIE INTELLECTUELLE
Architecture : 100 %
Contrat : 100 %
Implémentation : modifications locales antérieures à réauditer
Validation : NON
DEC : DEC-118
```

---

# 2. Règle fondamentale

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

```text
KRP ACTIVE
→ KRP FIN
→ Taxonomy ACTIVE
→ Taxonomy FIN
→ ... pipeline ...
→ ReadyBank
→ Factory
→ KRP ACTIVE à nouveau
```

KRP et Taxonomy ne sont jamais actifs simultanément.

---

# 3. Moment exact de l’information Taxonomy

Taxonomy ne communique rien à KRP pendant son travail intermédiaire.

Dans sa fermeture de sortie :

```text
IdeaSlot exact sélectionné
↓
triplet exact prêt
↓
écriture Blueprint réussie
↓
consommation immédiate du même IdeaSlot
↓
évaluation de l’état final du Domain
```

Si le Domain reste exploitable :

```text
AUCUN SIGNAL
```

Si cette consommation provoque :

```text
ENCORE EXPLOITABLE → VIDE
```

Taxonomy émet :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification exacte :

```text
CE DOMAIN EST VIDE
```

---

# 4. Règle delta-only

Taxonomy informe KRP uniquement lorsqu’un besoin change.

Donc :

- pas de signal à chaque noyau ;
- pas de signal à chaque passage ;
- pas de `AVAILABLE` ;
- pas de répétition normale après qu’une occurrence a déjà été déclarée vide ;
- au maximum un `DOMAIN_EXHAUSTED` normal par occurrence de bassin ;
- une nouvelle occurrence future peut produire son propre signal lorsqu’elle devient vide.

---

# 5. Aucun effet KRP immédiat

```text
Taxonomy FIN
↓
DOMAIN_EXHAUSTED si changement réel
↓
fait conservé en attente
↓
KRP INACTIF
```

Le signal ne déclenche pas KRP et ne contient aucune destination de rotation.

Puis seulement :

```text
noyau courant termine
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle
↓
Factory crée NOUVEAU Blueprint
↓
KRP ACTIVE
```

---

# 6. Application KRP

À sa prochaine activation :

```text
KRP consomme DOMAIN_EXHAUSTED en attente
↓
VISIBLE → ESTOMPÉ
```

Définition :

```text
ESTOMPÉ
= Domain abstrait/exclu des rotations restantes du tour courant
```

Ensuite KRP applique seul son contrat :

```text
Domain courant VISIBLE
→ même Depth + même Domain

Domain courant ESTOMPÉ
→ prochain Domain VISIBLE selon DomainCycle

8 Domaines ESTOMPÉ
→ KRP ferme SON tour
→ cycle_completed[depth] += 1 exactement une fois
→ DepthNeedMatrix
→ prochain Depth encore nécessaire
```

Après Depth 10, retour possible vers Depth 2 si un besoin subsiste.

Taxonomy n’émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

---

# 7. Ownership

```text
Taxonomy
= propriétaire de ses Banks
= communique uniquement le changement « ce Domain vient de devenir vide »
= aucune autorité de rotation

Frontière de communication
= conserve le fait jusqu’au prochain KRP
= aucune décision

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle
= aucune décision de rotation

Factory
= crée le NOUVEAU Blueprint

DepthNeedMatrix
= besoins quantitatifs globaux

KRP
= autorité UNIQUE de rotation
```

---

# 8. Build Replit #163

La Task #163 / `IMPL-02-01` est arrêtée.

**Ne pas lui dire simplement « continue ».**

À conserver potentiellement :

- Factory avant KRP ;
- suppression du `10 → HOLD` automatique ;
- usage de `DepthNeedMatrix` ;
- `VISIBLE / ESTOMPÉ` comme états KRP ;
- sortie KRP avant Taxonomy ;
- `CURRENT_KERNEL_RECEIVED` hors responsabilité métier KRP.

À réauditer/corriger impérativement :

- toute exécution KRP pendant Taxonomy ACTIVE ;
- tout traitement immédiat de `DOMAIN_EXHAUSTED` pendant la phase Taxonomy ;
- toute lecture/poll Taxonomy par KRP ;
- tout `receiveDepthExhausted()` actif ;
- toute fermeture de tour directement déclenchée par Taxonomy ;
- tout signal Taxonomy émis à chaque noyau plutôt qu’au changement réel ;
- tout signal émis avant écriture Blueprint réussie + consommation du même IdeaSlot ;
- tout test qui transforme `DOMAIN_EXHAUSTED` en commande de rotation immédiate.

---

# 9. Prochaine opération EXACTE

```text
RÉAUDIT-02-v3.7
```

Comparer le diff local Replit déjà créé contre v3.7 et classer :

```text
KEEP
REVERT
MODIFY
MISSING
```

Puis seulement reprendre `IMPL-02-01`.
