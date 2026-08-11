<?php

declare(strict_types=1);

namespace App\Exceptions\QuestionBank;

use RuntimeException;

/**
 * Exception officielle du module KernelCodeEngine (05_QuestionIntent).
 *
 * Chaque code d'erreur identifie la raison exacte de l'échec.
 * KernelCodeEngine ne décide jamais des transitions downstream (Quarantine,
 * retry, human review) — ces décisions appartiennent à l'orchestrateur.
 */
final class KernelCodeEngineException extends RuntimeException
{
    /** Champ obligatoire absent ou Blueprint introuvable. */
    public const MISSING_INPUT = 'QUESTION_INTENT_MISSING_INPUT';

    /** Depth non reconnu par DepthContractRegistry. */
    public const INVALID_DEPTH = 'QUESTION_INTENT_INVALID_DEPTH';

    /** Domaine absent de la table officielle (ou Général reçu). */
    public const INVALID_DOMAIN = 'QUESTION_INTENT_INVALID_DOMAIN';

    /** Normalisation d'un segment produit 0 caractère exploitable. */
    public const INVALID_SEGMENT = 'QUESTION_INTENT_INVALID_SEGMENT';

    /** next_value a dépassé 1 679 615 (ZZZZ en base36). */
    public const SUFFIX_EXHAUSTED = 'QUESTION_INTENT_SUFFIX_EXHAUSTED';

    /** kernel_code existant en DB ne respecte pas le format officiel. */
    public const EXISTING_CODE_INVALID = 'QUESTION_INTENT_EXISTING_CODE_INVALID';

    /** Collision d'unicité globale (contrainte UNIQUE violée). */
    public const IDENTITY_CONFLICT = 'QUESTION_INTENT_IDENTITY_CONFLICT';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        \Throwable $previous = null
    ) {
        parent::__construct("[{$errorCode}] {$message}", 0, $previous);
    }
}
