# CURRENT HANDOFF — StrategyBuzzer Kernel Engine

**Mis à jour :** 2026-08-20  
**Branche :** `replit/intellectual-engine-current-2026-08-16`  
**Bloc actif :** `PREP-01-SYNC`  
**Dernier bloc fermé :** `AUDIT-01-00`

> Ce fichier n’a aucune autorité architecturale propre. En cas de contradiction, `00_ArchitectureRegister.md + spécification canonique verrouillée + 00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md` priment.

---

# 1. Module actif

```text
01_KernelBlueprint
```

Spécification canonique :

```text
specifications/01_KernelBlueprint.md
Version : 2.0
Architecture : 100 %
Contrat : 100 %
Statut : VERROUILLÉ
DEC : DEC-113
```

Certificat :

```text
certificates/01_KernelBlueprint/01_KernelBlueprint_CERTIFICAT_VERROUILLAGE.md
```

`02_KernelRotationPlanner` reste fermé tant que 01 n’a pas terminé implantation + validation terminale.

---

# 2. GitHub — état confirmé

Avant AUDIT-01-00 :

```text
690c859e4b13a0cde1056d363f41ff8dbb03aa67
docs(kernel-engine): close KernelBlueprint v2.0 specification
```

Commit propre à l’audit :

```text
f258464aab9f4eda060175e9ab303f3028667bff
docs(kernel-engine): record KernelBlueprint v2.0 code audit
```

Audit canonique :

```text
audits/01_KernelBlueprint/01_KernelBlueprint_CODE_AUDIT_V2.md
```

Le commit qui contient le présent fichier est un checkpoint opérationnel distinct de l’audit.

---

# 3. Replit — état réel confirmé pendant l’audit

Projet : `StrategyBuzzer`

```text
branche locale : replit/intellectual-engine-current-2026-08-16
HEAD local : db26047532cfdf5e030c348dba4455f8eb310971
git status : propre
origin local connu : 0 ahead / 0 behind
```

IMPORTANT : `origin` local est périmé. GitHub était 52 commits devant `db260475` au moment de la comparaison ; ces 52 commits étaient exclusivement documentaires.

Donc :

- code `app/**`, `tests/**` et migrations du workspace Replit = code audité sur GitHub ;
- documents Bible Replit = en retard ;
- aucun patch n’est autorisé avant synchronisation Replit avec GitHub.

---

# 4. AUDIT-01-00 — CLOSED

Résultat :

```text
Architecture v2.0 : 100 %
Contrat v2.0      : 100 %
Implémentation    : PARTIELLE / NON CONFORME v2.0
Validation v2.0   : NON
UNRESOLVED bloquant : AUCUN
```

Baseline Replit exécutée sans modification :

```text
KernelBlueprintPart1Test
+
KernelBlueprintFactoryTest
=
75 tests / 130 assertions / PASS
```

Ces tests sont historiques et ne certifient pas v2.0.

---

# 5. KEEP principal

Conserver :

- propriétés Section 1 privées ;
- lecture contrôlée / écriture directe refusée ;
- `initializeBlueprintId()` write-once ;
- `fillRotation()` groupé/write-once ;
- `fillTaxonomy()` groupé/write-once ;
- `fillKernelCode()` write-once ;
- aucune écriture directe externe trouvée ;
- Factory distincte de KRP ;
- création `CREATED_UNENGAGED` ;
- garde applicative + index PostgreSQL `one_active_blueprint_idx` ;
- test concurrent Factory ;
- dans le chemin principal, engagement après `fillTaxonomy()` réussi ;
- KernelCodeEngine respecte Science et refuse Général ;
- réception ReadyBank atomique réception + Outbox.

---

# 6. MODIFY principal

À aligner :

- docblocks KernelBlueprint historiques ;
- `toArray()` / tests limités à l’ancien Blueprint de six clés ;
- références `NOT_ENGAGED_PRODUCTION_ON_HOLD` ;
- `KernelBlueprintRunRepository::markEngaged()` inutilisé et historiquement couplé à rotation ;
- persistance Section 1 incomplète ;
- persistance atomique Rotation/Taxonomy incomplète ;
- orchestrateur résout actuellement KRP avant Factory ;
- cleanup destructif d’un `CREATED_UNENGAGED` sur Taxonomy null ;
- `CURRENT_KERNEL_RECEIVED` va directement vers `KernelRotationPlanner::receiveKernelReceivedV2()` ;
- tests historiques qui figent ces anciens comportements.

---

# 7. MISSING principal

Manquent :

1. 7 CognitiveSlots permanents dans KernelBlueprint ;
2. couche TranslationSlots 1:1 ;
3. interfaces contrôlées Sections 2/3 ;
4. persistance du triplet Taxonomy sous `blueprint_id` ;
5. persistance des conteneurs Sections 2/3 ;
6. réhydratation/reconstruction d’un KernelBlueprint après redémarrage ;
7. reprise technique d’un `CREATED_UNENGAGED` orphelin après crash ;
8. tests contractuels v2.0 correspondants.

---

# 8. REMOVE du chemin canonique

Ne plus utiliser comme vérité de 01 :

- `KernelFrameBuilder` comme faux Blueprint parallèle ;
- ancien handoff direct `ReadyBank/CURRENT_KERNEL_RECEIVED → KRP`.

Le retrait physique du legacy ne doit pas casser les modules aval non encore spécifiés.

---

# 9. Plan de micro-blocs 01

Après synchronisation Replit :

```text
IMPL-01-01 — coeur canonique v2.0
IMPL-01-02 — 7 coquilles CognitiveSlots
IMPL-01-03 — coquilles TranslationSlots 1:1
IMPL-01-04 — persistance canonique Section 1
IMPL-01-05 — persistance conteneurs Sections 2/3
IMPL-01-06 — réhydratation / reprise technique
IMPL-01-07 — lifecycle Taxonomy
IMPL-01-08 — frontière CURRENT_KERNEL_RECEIVED → nouveau Blueprint
IMPL-01-09 — nettoyage legacy contractuel 01
IMPL-01-10 — validation terminale v2.0
```

Chaque bloc doit recevoir sa fiche exacte avant patch. Jamais deux blocs en parallèle.

---

# 10. Prochaine opération EXACTE

```text
PREP-01-SYNC
```

Mission unique :

```text
synchroniser le workspace Replit
avec la branche GitHub officielle
sans modification métier
```

Critères :

```text
Replit HEAD = GitHub HEAD
+
git status propre
+
référence origin fraîche
```

Seulement ensuite :

```text
IMPL-01-01
```

---

# 11. DO NOT REDO

Ne pas :

- refaire `SPEC-01-CLOSE` ;
- refaire `AUDIT-01-00` ;
- reconstruire 01 depuis les anciens chats ;
- utiliser l’ancien `docs/architecture/01_KernelBlueprint.md` comme contrat actif ;
- déduire l’architecture depuis KernelFrameBuilder ;
- réintroduire `PRODUCTION_ON_HOLD` comme état Blueprint ;
- réintroduire ReadyBank → ancien Blueprint → KRP ;
- commencer KRP v3.3 ;
- commencer Taxonomy implantation ;
- patcher Replit avant `PREP-01-SYNC`.

---

# 12. Reprise prochain chat

Lire :

1. `START_HERE.md`
2. `00_ConstitutionCognitive.md`
3. `00_ArchitectureRegister.md`
4. `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md`
5. `00_CURRENT_HANDOFF.md`
6. `specifications/01_KernelBlueprint.md`
7. `audits/01_KernelBlueprint/01_KernelBlueprint_CODE_AUDIT_V2.md`

Puis reprendre directement :

```text
PREP-01-SYNC
```

sans refaire l’audit.