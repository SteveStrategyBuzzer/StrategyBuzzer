---
name: Kernel pipeline architecture — migration from BankWorker to KernelPartialFiller
description: Locked architectural decisions for the migration from the segment-based BankWorker engine to the QuestionIntent-based Kernel pipeline.
---

# Kernel Pipeline Architecture — Locked Decisions

## Two pipelines exist (pre-migration)

**OLD — BankWorker pipeline (segment-based, QCM-only):**
  BankNeedsCalculator → BankAIGenerator → QualityGuards → addToBank()
  Unit: segment { domain, sub_domain, cognitive_type, question_type }
  Output: 1 question_group per cycle, no kernel concept

**NEW — Kernel pipeline (intent-based, 7 variants):**
  QuestionIntent → refreshVariantState() → KernelNeedsCalculator →
  KernelPartialFiller → KernelTranslator → KernelExporter
  Unit: QuestionIntent + KEY_STRUCTURE + variant_keys_missing
  Output: 1..7 question_groups per cycle (missing variants only)

## Five locked architectural decisions (D1–D5)

D1. A complete kernel = 7 variants ALL with variant_status = 'VALIDATED_OK'.
    variants_validated_ok = 7 is the only completion criterion.

D2. subject + angle_large come exclusively from back-office/QuestionIntent.
    Node never invents them. Worker skips slot if no enriched intent available.

D3. WARNING represents a human decision, not a generation deficit.
    The Worker has NO WARNING file. WARNING correction goes through quarantine only.
    Correction path: KernelVariantCorrector → human validation → UPDATE variant_status.

D4. VALIDATED_OK = gameplay visible. WARNING = invisible/quarantine.
    Gameplay filter: post_review_status = 'ready_bank' AND variant_status = 'VALIDATED_OK'.

D5. Partial kernel (1–6 VALIDATED_OK) is valid and playable.
    Worker completes partial kernels before creating new empty ones.

## The 7 official variant_key values

  qcm_recognition, qcm_reasoning, qcm_deceptive_trap,
  tf_recognition_true, tf_recognition_false,
  tf_reasoning_true, tf_reasoning_false

variant_key is the official unit of a kernel slot. Stored in question_groups.variant_key.
UNIQUE(question_intent_id, variant_key) constraint enforced at DB level.

tf_recognition_true and tf_recognition_false are DISTINCT slots.
They must never be fused into 'true_false/recognition'.

## refreshVariantState() — source of truth

Reads question_groups WHERE question_intent_id = X AND post_review_status = 'ready_bank'
Partitions by variant_status → keysOk / keysWarning / keysMissing
Writes to question_intents:
  variants_total, variants_validated_ok, variants_warning, variants_missing
  variant_keys_ok, variant_keys_warning, variant_keys_missing (JSONB)

Called: (1) before Worker selects work, (2) after KernelExporter inserts.

## Three critical reads needing variant_status = 'VALIDATED_OK' filter

1. QuestionBankRepository::baseQuery() — gameplay picker
2. BankNeedsCalculator (kernel counter for deficit, future: KernelNeedsCalculator)
3. QuestionIntent::refreshVariantState()

## Migration plan — 6 phases

Phase 1: SQL ADD COLUMNS + backfill variant_status = 'VALIDATED_OK' on existing rows
Phase 2: Patch 3 readers (baseQuery, BankNeedsCalculator counter, refreshVariantState)
Phase 3: Patch 4 writers (AuditCommand, Phase0Command, DialyseRunTest, Seeder)
Phase 4: Build missing components (KernelNeedsCalculator, KernelPartialFiller, KernelTranslator, KernelExporter)
Phase 5: Switch Worker to new engine (feature flag) — milestones 1,3,5,6 here
Phase 6: Delete old engine (BankAIGenerator, old QuestionBankRepository root) — milestone 2 here

## Write points to question_groups — status after migration

  DELETED:  BankWorker::run() → addToBank()
  DELETED:  BankAIGenerator::generateForSegment()
  DELETED:  app/Services/QuestionBankRepository.php (root, dead code)
  ADAPTED:  addToBank() in QuestionBank/QuestionBankRepository.php (used by KernelExporter)
  ADAPTED:  QuestionsAuditCommand::doValidate() (add variant_status='VALIDATED_OK')
  ADAPTED:  QuestionsBankPhase0Command backfill (add variant_status='VALIDATED_OK')
  ADAPTED:  QuestionsDialyseRunTestCommand (add variant_key + variant_status)
  ADAPTED:  QuestionBankSeeder (add variant_key + variant_status)
  KEPT:     QuestionsBankPhase0Command SQL UPDATE question_intent_id (structural link)
  KEPT:     QuestionsBankAuditContentCommand validated=false (admin tool)

**Why:** Prevents any path from creating questions outside a kernel after migration.
The UNIQUE(question_intent_id, variant_key) DB constraint is the last safety net.

## Node extension required

POST /generate-kernel-derived-variants must accept optional target_keys?: string[]
  If absent: generates all 6 derived variants (current behavior, backward-compatible)
  If present: generates only the listed variant_keys (anchored on existing master)
