<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Contracts\KeyStructurePipelineGateInterface;
use Illuminate\Support\Facades\Log;

/**
 * BlockedKeyStructureGate — implémentation de production de KeyStructurePipelineGateInterface.
 *
 * Retourne systématiquement STATUS_BLOCKED tant que KEY_STRUCTURE n'est pas implanté.
 *
 * FRONTIÈRE DÉCLARÉE :
 *   Module absent       : KEY_STRUCTURE
 *   Interface attendue  : KeyStructurePipelineGateInterface::check()
 *   Entrée              : territoire {sub_domain, subject, dominant_idea}, domainCode, depth
 *   Sortie attendue     : PASS | FAIL
 *   Point de blocage    : après KLD PASS, avant confirmConsumed()
 *   Code terminé avant  : Factory → KRP → Taxonomy → KLD PASS (si dominant_idea disponible)
 *
 * À remplacer par KeyStructureValidationGate une fois KEY_STRUCTURE implanté.
 */
final class BlockedKeyStructureGate implements KeyStructurePipelineGateInterface
{
    public function check(array $territory, string $domainCode, int $depth): string
    {
        Log::warning(
            '[BlockedKeyStructureGate] KEY_STRUCTURE non implanté — pipeline BLOQUÉ à cette frontière.',
            [
                'domain'      => $domainCode,
                'depth'       => $depth,
                'sub_domain'  => $territory['sub_domain']  ?? '',
                'subject'     => $territory['subject']     ?? '',
                'dominant_idea' => $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? '—',
            ]
        );

        return self::STATUS_BLOCKED;
    }
}
