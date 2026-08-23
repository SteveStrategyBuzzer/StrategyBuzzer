# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-23  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Module actif :** `02_KernelRotationPlanner`  
**Bloc actif :** `RÉAUDIT-02-v3.6 AVANT REPRISE IMPL-02-01`  
**Dernière décision structurante :** `DEC-117`

> Ce fichier est un pointeur de reprise. En cas de contradiction : `00_ArchitectureRegister.md + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md + spécification canonique du module` priment.

---

# 1. État canonique KRP

```text
specifications/02_KernelRotationPlanner.md
Version : 3.6
Statut : VERROUILLÉ — PARTIE INTELLECTUELLE
Architecture : 100 %
Contrat : 100 %
Implémentation : modifications locales antérieures à réauditer
Validation : NON
DEC : DEC-117
```

---

# 2. Règle fondamentale DEC-117

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

# 3. Signal Taxonomy exact

Taxonomy peut émettre, à la fin de son travail :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification exacte :

```text
CE DOMAIN EST VIDE
```

Ce signal :

- ne choisit aucun prochain Domain ;
- ne choisit aucun prochain Depth ;
- ne ferme aucun tour ;
- ne produit pas HOLD ;
- n'active pas KRP immédiatement.

Taxonomy n'émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

---

# 4. Moment exact des effets

```text
Taxonomy FIN
↓
DOMAIN_EXHAUSTED si nécessaire
↓
fait conservé en attente
↓
KRP INACTIF
```

Puis :

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
↓
KRP consomme le fait en attente
↓
VISIBLE → ESTOMPÉ
```

Définition :

```text
ESTOMPÉ
= Domain abstrait/exclu des rotations restantes du tour courant
```

Après application des faits en attente, KRP choisit seul sa rotation.

---

# 5. Rotation KRP

Sans fait d'épuisement :

```text
Domain courant VISIBLE
→ même Depth + même Domain
```

Avec fait d'épuisement :

```text
DOMAIN_EXHAUSTED en attente
→ KRP rend Domain ESTOMPÉ
→ KRP l'exclut du DomainCycle du tour
→ KRP choisit le prochain Domain VISIBLE
```

Si les huit Domaines sont ESTOMPÉ :

```text
KRP ferme SON tour
→ cycle_completed[depth] += 1 exactement une fois
→ DepthNeedMatrix
→ prochain Depth encore nécessaire
```

Après Depth 10, retour possible vers Depth 2 si un besoin subsiste.

---

# 6. Ownership

```text
Taxonomy
= réalité intellectuelle de ses Banks
= émet le fait « ce Domain est vide »
= aucune autorité de rotation

Frontière de communication
= conserve le fait jusqu'au prochain KRP
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

# 7. Build Replit #163

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
- tout `receiveDomainExhausted()` qui applique immédiatement la transition pendant la phase Taxonomy ;
- toute lecture/poll Taxonomy par KRP ;
- tout `receiveDepthExhausted()` actif ;
- toute fermeture de tour directement déclenchée par Taxonomy ;
- tout test qui suppose KRP et Taxonomy actifs en même temps ;
- tout test qui transforme `DOMAIN_EXHAUSTED` en commande de rotation immédiate.

---

# 8. Prochaine opération EXACTE

```text
RÉAUDIT-02-v3.6
```

Comparer le diff local Replit déjà créé contre v3.6 et classer :

```text
KEEP
REVERT
MODIFY
MISSING
```

Puis seulement reprendre `IMPL-02-01`.

---

# 9. Tests v3.6 obligatoires

1. nouveau Blueprint créé avant KRP ;
2. KRP et Taxonomy jamais actifs simultanément ;
3. Taxonomy FIN peut émettre `DOMAIN_EXHAUSTED` sans activer KRP ;
4. signal = uniquement « ce Domain est vide » ;
5. fait conservé jusqu'au prochain Blueprint ;
6. KRP consomme le fait à sa prochaine activation ;
7. `VISIBLE→ESTOMPÉ` à ce moment seulement ;
8. ESTOMPÉ exclut le Domain des rotations restantes du tour ;
9. sans fait, même Domain conservé ;
10. KRP choisit seul le Domain suivant ;
11. huit ESTOMPÉ → KRP ferme le tour ;
12. `cycle_completed` exactement une fois ;
13. prochain Depth via Matrix ;
14. après 10, retour vers prochain besoin ;
15. HOLD seulement toutes cibles atteintes ;
16. aucun `DEPTH_EXHAUSTED` Taxonomy actif ;
17. KRP écrit uniquement `depth + domain`.

---

# 10. DO NOT REDO

Ne pas :

- revenir à v3.5/v3.4/v3.3 ;
- reprendre ALIGN-02 ;
- faire poller Taxonomy par KRP ;
- faire exécuter KRP pendant Taxonomy ;
- laisser Taxonomy choisir le prochain Domain ou Depth ;
- appliquer la rotation au moment de l'émission du signal ;
- inventer Phase1/Phase2.
