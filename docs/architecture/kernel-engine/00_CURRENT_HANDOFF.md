# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-23  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Module actif :** `02_KernelRotationPlanner`  
**Bloc actif :** `RÉAUDIT-02-v3.5 AVANT REPRISE IMPL-02-01`  
**Dernière décision structurante :** `DEC-116`

> Ce fichier est un pointeur de reprise. En cas de contradiction : `00_ArchitectureRegister.md + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md + spécification canonique du module` priment.

---

# 1. État canonique KRP

```text
specifications/02_KernelRotationPlanner.md
Version : 3.5
Statut : VERROUILLÉ — PARTIE INTELLECTUELLE
Architecture : 100 %
Contrat : 100 %
Implémentation : modifications locales v3.3/v3.4 à réauditer
Validation : NON
DEC : DEC-116
```

---

# 2. Ownership DEC-116

```text
Taxonomy
= propriétaire de ses Banks
= pousse DOMAIN_EXHAUSTED(depth, domain) quand le Domain actif est réellement vide
= ne choisit aucune rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle du noyau suivant
= ne choisit aucune rotation

Factory
= crée un NOUVEAU Blueprint

KRP
= autorité UNIQUE de rotation
```

Taxonomy **parle à KRP**. KRP ne lit/poll pas les Banks Taxonomy.

---

# 3. Flow exact

## Information d’épuisement

```text
Taxonomy travaille Depth + Domain
↓
remaining_subjects = 0
AND
remaining_ideas = 0
↓
DOMAIN_EXHAUSTED(depth, domain)
↓
KRP persiste VISIBLE → ESTOMPÉ
↓
aucune rotation immédiate
```

## Rotation suivante

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
KRP reçoit ce Blueprint
↓
KRP lit SON RotationState + DepthNeedMatrix
↓
Domain courant encore VISIBLE ?
  OUI → même Depth + même Domain
  NON → prochain Domain VISIBLE selon DomainCycle
↓
si 8 Domaines ESTOMPÉ : KRP ferme SON tour
↓
cycle_completed += 1 exactement une fois
↓
Matrix choisit le prochain Depth encore nécessaire
↓
fillRotation(depth, domain)
↓
porte Taxonomy
```

Après Depth 10, retour possible à Depth 2 si un besoin subsiste.

---

# 4. Ce qui est désormais interdit

Ne plus utiliser comme vérité :

```text
KRP lit/poll la réalité Taxonomy
Taxonomy → DEPTH_EXHAUSTED → KRP
Taxonomy choisit le prochain Domain
Taxonomy choisit le prochain Depth
réception DOMAIN_EXHAUSTED → rotation immédiate
```

Le seul signal Taxonomy actif pour cette frontière est factuel :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

---

# 5. Taxonomy v1.0

`03_Taxonomy v1.0` reste utile pour ses détails intellectuels internes mais sa frontière KRP est superseded par DEC-116.

Boundary bridge actif :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-116.md
```

Le bridge DEC-115 est `SUPERSEDED`.

Taxonomy devra être réécrit intégralement en v1.1 dans son propre tour.

---

# 6. Build Replit #163

La Task #163 / `IMPL-02-01` a été démarrée contre v3.3/v3.4 puis arrêtée manuellement.

**Ne pas lui dire simplement « continue ».**

À conserver potentiellement :

- Factory avant KRP ;
- suppression du `10 → HOLD` automatique ;
- usage de `DepthNeedMatrix` ;
- `VISIBLE / ESTOMPÉ` comme états KRP ;
- sortie KRP avant Taxonomy ;
- `CURRENT_KERNEL_RECEIVED` hors responsabilité métier KRP.

À réauditer/corriger :

- toute lecture/poll de réalité Taxonomy par KRP ;
- tout `receiveDepthExhausted()` actif ;
- toute fermeture de tour déclenchée directement par Taxonomy ;
- tout `receiveDomainExhausted()` qui choisit immédiatement le Domain suivant ;
- tout test où `DOMAIN_EXHAUSTED` est une commande de rotation ;
- toute interface qui fait transmettre le prochain Domain/Depth par Taxonomy.

---

# 7. Prochaine opération EXACTE

```text
RÉAUDIT-02-v3.5
```

Comparer le diff local Replit déjà créé contre v3.5 et classer :

```text
KEEP
REVERT
MODIFY
MISSING
```

Puis seulement reprendre `IMPL-02-01`.

---

# 8. Tests v3.5 obligatoires

1. nouveau Blueprint créé avant KRP ;
2. sans signal `DOMAIN_EXHAUSTED` → même Domain au Blueprint suivant ;
3. Taxonomy envoie `DOMAIN_EXHAUSTED(depth, domain)` factuel ;
4. réception du signal → `VISIBLE→ESTOMPÉ`, sans rotation immédiate ;
5. replay → `NO-OP` ;
6. prochain Blueprint après Domain ESTOMPÉ → KRP choisit le suivant ;
7. huit Domaines ESTOMPÉ → KRP ferme le tour ;
8. `cycle_completed` exactement une fois ;
9. prochain Depth choisi via Matrix ;
10. après 10, retour vers le prochain besoin, potentiellement 2 ;
11. HOLD seulement toutes cibles atteintes ;
12. KRP écrit seulement `depth + domain` ;
13. aucun `DEPTH_EXHAUSTED` Taxonomy actif ;
14. `CURRENT_KERNEL_RECEIVED` reste lifecycle.

---

# 9. DO NOT REDO

Ne pas :

- revenir à v3.4/v3.3 ;
- reprendre ALIGN-02 ;
- faire poller les Banks Taxonomy par KRP ;
- laisser Taxonomy choisir le prochain Domain ou Depth ;
- faire de ReadyBank une autorité de rotation ;
- faire tourner le Domain immédiatement à la réception du signal ;
- inventer Phase1/Phase2.
