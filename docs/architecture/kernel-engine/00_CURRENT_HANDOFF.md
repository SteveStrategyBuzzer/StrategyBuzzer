# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-19  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Bloc actif :** `AUDIT-01-00`  
**Dernier bloc fermé :** `SPEC-01-CLOSE`

> Ce fichier n’a aucune autorité architecturale propre. Il indique le point exact de reprise entre deux chats. En cas de contradiction, `Architecture Register + spécification canonique verrouillée + document maître actif` priment.

---

# 1. Ordre officiel de travail

```text
01 KernelBlueprint
↓ spécification VERROUILLÉE
AUDIT CODE
↓
IMPLANTATION PAR MICRO-BLOCS
↓
VALIDATION TERMINALE
↓
02 KernelRotationPlanner
↓
...
```

Il est interdit de passer à 02 tant que 01 n’est pas fermé en Architecture + Contrat + Implémentation + Validation.

---

# 2. 01 — KernelBlueprint

## Spécification

```text
Version : 2.0
Architecture : 100 %
Contrat : 100 %
Statut : VERROUILLÉ
```

Canon :

```text
specifications/01_KernelBlueprint.md
```

Certificat :

```text
certificates/01_KernelBlueprint/01_KernelBlueprint_CERTIFICAT_VERROUILLAGE.md
```

Architecture active confirmée :

```text
KernelBlueprintFactory
↓
nouveau KernelBlueprint + blueprint_id
↓
KernelRotationPlanner
↓
Taxonomy
↓
QuestionIntent
↓
...
↓
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
KernelBlueprintFactory crée le Blueprint suivant
```

Rappels obligatoires :

- KRP ne crée pas le Blueprint ;
- ReadyBank ne renvoie pas l’ancien Blueprint à KRP ;
- le Blueprint suivant reçoit un nouveau `blueprint_id` ;
- Section 1 est write-once dans le chemin normal ;
- `PRODUCTION_ON_HOLD` n’est pas un état Blueprint ;
- les Banks/cycle data restent hors Blueprint ;
- DEC-106 : Idea sélectionnée = Idea écrite = Idea consommée après succès de `fillTaxonomy`.

## Implantation

```text
État : CODE HISTORIQUE PRÉSENT — NON ENCORE AUDITÉ CONTRE v2.0
Avancement normalisé : 20 %
```

Aucun patch v2.0 n’est encore autorisé avant l’audit du code.

## Validation

```text
État : ancienne validation historique disponible, mais validation terminale v2.0 à rejouer
Avancement normalisé : 20 %
```

---

# 3. Bloc fermé — SPEC-01-CLOSE

Résultat :

- reconstruction complète de 01 ;
- spécification canonique v2.0 produite ;
- checklist Mission → Tests contractuels = 100 % ;
- certificat de verrouillage produit ;
- DEC-113 ajouté au Register ;
- reconstruction `working/` fermée ;
- aucun fichier `app/**` modifié ;
- aucun fichier `tests/**` modifié.

Le commit de fermeture est le commit Git portant simultanément la promotion canonique, ce handoff et DEC-113.

---

# 4. Prochain bloc EXACT

```text
AUDIT-01-00
```

Mission unique :

```text
auditer le code réel de 01_KernelBlueprint
contre specifications/01_KernelBlueprint.md v2.0
```

Avant tout patch, produire :

```text
KEEP
MODIFY
REMOVE
MISSING
UNRESOLVED
```

pour les slices exacts concernés.

Audit minimum :

- `KernelBlueprint` ;
- `KernelBlueprintFactory` ;
- persistance du Blueprint ;
- migrations associées ;
- appels de création ;
- appels `fillRotation` ;
- appels `fillTaxonomy` ;
- appels `fillKernelCode` ;
- écritures directes éventuelles ;
- tests existants de 01 ;
- concurrence/atomicité ;
- frontière ReadyBank/CURRENT_KERNEL_RECEIVED uniquement pour vérifier qu’aucun ancien Blueprint n’est recyclé.

Aucune correction pendant `AUDIT-01-00`.

---

# 5. Micro-blocs d’implantation

Ils ne sont nommés définitivement qu’après l’audit.

Format :

```text
IMPL-01-01
→ une responsabilité
→ fichiers autorisés précis
→ patch minimal
→ tests ciblés
→ tests cumulatifs
→ diff Git
→ un commit
→ CLOSED
```

Ne pas préfabriquer les patches avant l’audit.

---

# 6. 02 — KernelRotationPlanner

```text
v3.2 : historique
v3.3 : à reconstruire/verrouiller plus tard
```

DEC actives à conserver pour sa future reconstruction :

```text
DEC-094
DEC-095 frontière
DEC-108
DEC-111
```

`02` reste **FERMÉ AU TRAVAIL** tant que 01 n’a pas terminé implantation + validation.

---

# 7. 03 — Taxonomy

```text
Spécification v1.0 : VERROUILLÉE
Architecture : 100 %
Contrat : 100 %
```

Aucune implantation maintenant.

---

# 8. Synchronisation Replit

Dernier état connu avant `SPEC-01-CLOSE` : Replit était encore sur `db260475` et son `git pull` échouait par authentification SSH. Son message « up to date with origin » reposait donc sur une référence distante locale périmée.

Après le commit `SPEC-01-CLOSE`, la synchronisation Replit doit être confirmée avant toute implantation.

Preuve exigée :

```text
git fetch réussi
+
HEAD local Replit = HEAD de la branche GitHub
+
git status propre
```

Commande de contrôle cible :

```bash
git rev-list --left-right --count HEAD...origin/replit/intellectual-engine-current-2026-08-16
```

Résultat attendu lorsque synchronisé :

```text
0    0
```

---

# 9. DO NOT REDO

Ne pas :

- reconstruire `01_KernelBlueprint` depuis les anciens chats ;
- utiliser `docs/architecture/01_KernelBlueprint.md` comme contrat actif ;
- réouvrir le flow « KRP crée Blueprint » ;
- réintroduire ReadyBank → ancien Blueprint → KRP ;
- commencer KRP v3.3 ;
- commencer Taxonomy implantation ;
- modifier du code avant `AUDIT-01-00` ;
- considérer les anciens tests comme certification automatique de v2.0.

---

# 10. Reprise du prochain chat

Lire dans l’ordre :

1. `START_HERE.md`
2. `00_ConstitutionCognitive.md`
3. `00_ArchitectureRegister.md`
4. `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md`
5. `00_CURRENT_HANDOFF.md`
6. `specifications/01_KernelBlueprint.md`
7. `certificates/01_KernelBlueprint/01_KernelBlueprint_CERTIFICAT_VERROUILLAGE.md`

Puis reprendre directement :

```text
AUDIT-01-00
```

sans patch de code avant le rapport d’audit.