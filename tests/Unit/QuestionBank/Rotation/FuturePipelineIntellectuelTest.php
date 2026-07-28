<?php

namespace Tests\Unit\QuestionBank\Rotation;

use PHPUnit\Framework\TestCase;

/**
 * TESTS FUTURS — PIPELINE INTELLECTUEL
 *
 * Ces tests couvrent des responsabilités hors périmètre du KRP (02_KernelRotationPlanner).
 * Ils seront déplacés dans les fichiers de tests des modules concernés
 * lorsque leurs spécifications respectives seront traitées.
 *
 * Modules concernés :
 *   - IdeaSlotLoader       → fournit dominant_idea pour le sujet actif
 *   - KeyLearningDirection → KLD anti-répétition pédagogique
 *   - KEY_STRUCTURE        → validation de la structure taxonomique
 *   - confirmConsumed()    → déclenchement après KLD PASS + KEY_STRUCTURE PASS
 *   - Phase 1              → génération du squelette
 *   - Validation 1         → validation structurelle
 *   - Phase 2              → remplissage du contenu
 *   - Validation 2         → validation contenu + subject-touch
 *
 * Règle future sur confirmConsumed() :
 *   KLD PASS
 *   +
 *   KEY_STRUCTURE PASS
 *   ↓
 *   confirmConsumed() exactement une fois
 *   (sera implantée dans la spécification KLD/KEY_STRUCTURE)
 */
class FuturePipelineIntellectuelTest extends TestCase
{
    /** IdeaSlotLoader — dominant_idea absent → pipeline bloqué. */
    public function test_pipeline_blocked_when_dominant_idea_missing(): void
    {
        $this->markTestSkipped(
            'FUTUR — IdeaSlotLoader non implanté. '
            . 'À activer dans la spécification IdeaSlotLoader.'
        );
    }

    /** KLD PASS + KEY_STRUCTURE PASS → confirmConsumed() appelé exactement une fois. */
    public function test_confirm_consumed_called_once_on_kld_pass_and_ks_pass(): void
    {
        $this->markTestSkipped(
            'FUTUR — KeyLearningDirection + KeyStructurePipelineGate non implantés dans KRP. '
            . 'À activer dans la spécification KLD/KEY_STRUCTURE.'
        );
    }

    /** KLD FAIL → confirmConsumed() jamais appelé. */
    public function test_confirm_consumed_not_called_on_kld_fail(): void
    {
        $this->markTestSkipped(
            'FUTUR — KeyLearningDirection non implanté dans KRP. '
            . 'À activer dans la spécification KLD.'
        );
    }

    /** KEY_STRUCTURE FAIL → confirmConsumed() jamais appelé. */
    public function test_confirm_consumed_not_called_on_ks_fail(): void
    {
        $this->markTestSkipped(
            'FUTUR — KEY_STRUCTURE non implanté dans KRP. '
            . 'À activer dans la spécification KEY_STRUCTURE.'
        );
    }

    /** KEY_STRUCTURE BLOCKED → retour PIPELINE_BLOCKED_AWAITING_KEY_STRUCTURE. */
    public function test_pipeline_blocked_awaiting_key_structure(): void
    {
        $this->markTestSkipped(
            'FUTUR — KEY_STRUCTURE non implanté dans KRP. '
            . 'À activer dans la spécification KEY_STRUCTURE.'
        );
    }

    /** confirmConsumed() idempotent — au plus une fois par cycle. */
    public function test_confirm_consumed_idempotent(): void
    {
        $this->markTestSkipped(
            'FUTUR — confirmConsumed() déclenchement hors périmètre KRP. '
            . 'À activer dans la spécification KLD/KEY_STRUCTURE.'
        );
    }
}
