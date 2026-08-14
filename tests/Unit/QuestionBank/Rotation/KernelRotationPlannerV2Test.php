<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

/**
 * KernelRotationPlannerV2Test — SUPERSEDED.
 *
 * Ces tests couvraient l'interface V2 (planV2 / applyEmptyTransitionV2) de
 * KernelRotationPlanner, supprimée dans la version 3 (02_KernelRotationPlanner v3.2).
 *
 * Remplacés par :
 *   - KernelRotationPlannerV3Test — couvre resolveNextRotation, applyRotation,
 *     receiveDomainExhausted, receiveDepthExhausted, receiveKernelReceivedV2.
 *
 * Ce fichier est conservé temporairement comme marqueur documentaire.
 * Il peut être supprimé dès que les tests V3 sont validés en CI.
 *
 * @group superseded
 */
class KernelRotationPlannerV2Test
{
    // Aucun test — toutes les couvertures V2 ont été migrées vers KernelRotationPlannerV3Test.
}
