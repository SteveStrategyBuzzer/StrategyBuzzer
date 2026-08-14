<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

/**
 * KernelRotationPlannerTest — SUPERSEDED.
 *
 * Ces tests couvraient l'interface V1 de KernelRotationPlanner :
 * méthodes pures buildDepthNeedMatrix(), chooseDepth(), loadDomains(),
 * advanceDomainIndex() — toutes supprimées lors de la migration V3
 * (02_KernelRotationPlanner v3.2, 2026-08-13).
 *
 * Remplacés par :
 *   - KernelRotationPlannerV3Test — couvre l'interface V3 complète.
 *   - KernelPipelineOrchestratorTest — couvre le flow orchestrateur V3.
 *
 * Ce fichier est conservé comme marqueur documentaire.
 * Il peut être supprimé dès que les tests V3 sont validés en CI.
 *
 * @group superseded
 */
class KernelRotationPlannerTest
{
    // Aucun test — toutes les couvertures V1 ont été remplacées par les suites V3.
}
