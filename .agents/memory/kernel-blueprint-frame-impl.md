---
name: Kernel Blueprint Frame implantation
description: État réel après implantation 2026-07-03 — ce qui existe, ce qui manque, règles de test.
---

## Ce qui a été implanté (2026-07-03)

**Migration additive** `2026_07_03_100000_add_kernel_code_and_hashes_to_question_intents` :
- `kernel_code` VARCHAR(32) nullable + UNIQUE INDEX partiel (toujours présent)
- `ks_hash` VARCHAR(64) nullable → **DROPPÉ par #142** (migration 2026_08_11_300000)
- `kld_hash` VARCHAR(64) nullable → **DROPPÉ par #142** (migration 2026_08_11_300000)
- `ks_hash`/`kld_hash` retirés du `$fillable` et de la factory ; `kernel_code` conservé
- `HasFactory` trait ajouté à `QuestionIntent`
- `QuestionIntentFactory` créée

**KernelFrameBuilder::buildSkeleton()** retourne 17 clés racines :

```
Blueprint Frame (14 nouvelles) :
  kernel_code            → null
  depth_slot             → {value, source, status, rules, traces}
  domain_slot            → {value, source, status, rules, traces}
  sub_domain_slot        → {value, source, status, rules, traces}
  subjects_inventory     → 50 coquilles {index, value, status, rules, traces}
  active_subject         → null
  dominant_ideas         → []
  active_dominant_idea   → null
  cognitive_slots        → 7 cognitifs × {question_slot, answer_slots(2ou4), correct_answer_key,
                                           sv_slot, translation_slots(9 langs), status, rules, traces}
  rules                  → 10 règles (kernel_code_format='yy-xx-xxx-xxx-xxx-zz', etc.)
  mechanisms             → 11 étapes pipeline
  constraints            → 8 invariants (all boolean/string)
  statuses               → 10 étapes null (rotation→taxonomy→key_structure→kld→
                           question_intent→phase1→phase2→phase3→phase4→ready_bank)
  traces                 → []

Legacy (3 conservées) :
  kernel_core, translation_constraints, variants
```

**Taille JSON noyau vide** : ~118 KB.

## Règle de test — IMPORTANT

Les tests de `KernelFrameBuilder` (pure function, pas de DB) doivent **étendre `PHPUnit\Framework\TestCase` directement**, pas `Tests\TestCase`.

**Pourquoi** : `Tests\TestCase` déclenche `RefreshDatabase` qui exécute toutes les migrations SQLite. Certaines migrations utilisent `ALTER TABLE ... ADD CONSTRAINT` (syntaxe PostgreSQL) → crash SQLite.

**Ne JAMAIS** mettre `use \Illuminate\Foundation\Testing\RefreshDatabase` dans un test de pure function.

## Prochaines briques à implanter

1. `Rotation/KernelRotationPlanner.php` — orchestre Depth+Domain → remplit depth_slot + domain_slot + début kernel_code `yy-xx`
2. `Rotation/DepthNeedMatrix.php` — curseur Redis Depth
3. `Rotation/DomainCycle.php` — curseur Redis Domaine
4. `Rotation/IntentKeyBuilder.php` — KEY_STRUCTURE + KLD → kernel_code complet + ks_hash + kld_hash
