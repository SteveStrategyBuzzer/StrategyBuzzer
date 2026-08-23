# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-23  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Module actif :** `02_KernelRotationPlanner`  
**Bloc actif :** `RÉAUDIT-02-v3.4 AVANT REPRISE IMPL-02-01`  
**Dernière décision structurante :** `DEC-115`

> Ce fichier est un pointeur de reprise. En cas de contradiction : `00_ArchitectureRegister.md + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md + specification canonique du module` priment.

---

# 1. État canonique KRP

```text
specifications/02_KernelRotationPlanner.md
Version : 3.4
Statut : VERROUILLÉ — PARTIE INTELLECTUELLE
Architecture : 100 %
Contrat : 100 %
Implémentation : modifications locales v3.3 à réauditer
Validation : NON
DEC : DEC-115
```

---

# 2. Correction d’ownership DEC-115

```text
Taxonomy
= expose la réalité de ses réservoirs
= aucune décision de rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle du noyau suivant
= aucune décision de rotation

Factory
= crée un NOUVEAU Blueprint

KRP
= autorité UNIQUE de rotation
```

KRP lit :

```text
RotationState
+
DepthNeedMatrix
+
réalité Taxonomy disponible
```

puis décide seul `depth + domain`.

---

# 3. Flow exact

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
KRP lit état rotation + Matrix + réalité Taxonomy
↓
contenu Taxonomy restant ?
  OUI → même Depth + même Domain
  NON → KRP estompe le Domain et choisit le suivant
↓
si 8 Domaines ESTOMPÉ : KRP ferme le tour
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
Taxonomy → DOMAIN_EXHAUSTED → KRP
Taxonomy → DEPTH_EXHAUSTED → KRP
```

Taxonomy ne déclare pas la fin d’un Domain au sens rotationnel et ne déclare pas la fin d’un tour KRP.

Il expose seulement la réalité de ses Banks.

---

# 5. Taxonomy v1.0

`03_Taxonomy v1.0` reste utile pour ses détails intellectuels internes mais sa frontière KRP est superseded par DEC-115.

Boundary bridge actif :

```text
working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-115.md
```

Taxonomy devra être réécrit intégralement en v1.1 dans son propre tour.

---

# 6. Build Replit #163

La Task #163 / `IMPL-02-01` a été démarrée contre v3.3 puis arrêtée manuellement.

**Ne pas lui dire simplement « continue ».**

Certaines modifications v3.3 peuvent être conservées, notamment :

- Factory avant KRP ;
- suppression du `10 → HOLD` automatique ;
- usage de `DepthNeedMatrix` ;
- `VISIBLE / ESTOMPÉ` comme états KRP ;
- sortie KRP avant Taxonomy ;
- `CURRENT_KERNEL_RECEIVED` hors responsabilité métier KRP.

Mais doivent être réaudités/corrigés :

- tout `receiveDomainExhausted()` utilisé comme commande Taxonomy ;
- tout `receiveDepthExhausted()` utilisé comme commande Taxonomy ;
- toute fermeture immédiate de tour déclenchée par Taxonomy ;
- toute rotation de Domain automatique à chaque nouveau Blueprint sans vérifier la réalité Taxonomy ;
- tests bâtis autour des signaux v3.3.

---

# 7. Prochaine opération EXACTE

```text
RÉAUDIT-02-v3.4
```

Comparer le diff local Replit déjà créé contre v3.4 et classer :

```text
KEEP
REVERT
MODIFY
MISSING
```

Puis seulement reprendre `IMPL-02-01`.

---

# 8. Tests v3.4 obligatoires

1. nouveau Blueprint créé avant KRP ;
2. réalité Taxonomy = contenu restant → même Domain ;
3. réalité Taxonomy = aucun contenu → KRP seul fait `VISIBLE→ESTOMPÉ` ;
4. KRP choisit le Domain suivant selon le cycle ;
5. huit Domaines ESTOMPÉ → KRP ferme le tour ;
6. `cycle_completed` exactement une fois ;
7. prochain Depth choisi via Matrix ;
8. après 10, retour vers le prochain besoin, potentiellement 2 ;
9. HOLD seulement toutes cibles atteintes ;
10. KRP écrit seulement `depth + domain` ;
11. aucune commande `DOMAIN_EXHAUSTED/DEPTH_EXHAUSTED` requise depuis Taxonomy ;
12. `CURRENT_KERNEL_RECEIVED` reste lifecycle.

---

# 9. DO NOT REDO

Ne pas :

- revenir à v3.3 ;
- reprendre ALIGN-02 ;
- laisser Taxonomy choisir le prochain Domain ou Depth ;
- faire de ReadyBank une autorité de rotation ;
- faire tourner le Domain automatiquement à chaque nouveau Blueprint ;
- inventer Phase1/Phase2.
