<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation\Contracts;

/**
 * Contrat d'intégration de la vérification KEY_STRUCTURE dans le pipeline Kernel.
 *
 * Appelé depuis KernelPipelineOrchestrator UNIQUEMENT après KLD = PASS.
 *
 * RÈGLE OFFICIELLE (DEC-KS-01) :
 *   - PASS    → structure taxonomique valide → confirmConsumed() autorisé
 *   - FAIL    → structure rejetée → PAS de confirmConsumed()
 *   - BLOCKED → KEY_STRUCTURE non encore implanté → pipeline arrêté à cette frontière
 *
 * Frontière déclarée UNDER_REVIEW — KEY_STRUCTURE n'est pas encore implanté.
 * L'implémentation de production est BlockedKeyStructureGate jusqu'à implantation.
 *
 * Entrée attendue : territoire complet (sub_domain + subject + dominant_idea),
 *                   domain_code et depth fournis par KRP.
 * Sortie : string STATUS_PASS | STATUS_FAIL | STATUS_BLOCKED
 *
 * @see BlockedKeyStructureGate — implémentation de production actuelle (BLOCKED)
 * @see KernelIdentifierManager  — future autorité pour les hashes de structure
 */
interface KeyStructurePipelineGateInterface
{
    public const STATUS_PASS    = 'PASS';
    public const STATUS_FAIL    = 'FAIL';
    public const STATUS_BLOCKED = 'BLOCKED';

    /**
     * Vérifie la validité structurelle du territoire sélectionné.
     *
     * @param array{
     *     sub_domain: string,
     *     subject: string,
     *     dominant_idea?: string,
     *     dominant_idea_active?: string
     * } $territory
     *
     * @return string STATUS_PASS | STATUS_FAIL | STATUS_BLOCKED
     */
    public function check(array $territory, string $domainCode, int $depth): string;
}
