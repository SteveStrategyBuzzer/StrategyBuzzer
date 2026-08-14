<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

/**
 * RotationResolution — résultat de KernelRotationPlanner::resolveNextRotation().
 *
 * Value object immutable.
 *
 * Deux états exclusifs :
 *   - Rotation disponible : depth + domain + domainPosition définis, noRotationReason = null.
 *   - Pas de rotation      : depth/domain/domainPosition = null, noRotationReason défini.
 *
 * Motifs d'indisponibilité (noRotationReason) :
 *   PRODUCTION_ON_HOLD      — depth_state persisté = PRODUCTION_ON_HOLD.
 *   PENDING_DEPTH_TRANSITION — tous les domaines du Depth actif sont DOMAIN_EXHAUSTED
 *                              et pending_depth_exhausted_depth est mémorisé ;
 *                              la transition sera appliquée au prochain CKR.
 */
final class RotationResolution
{
    public readonly ?int    $depth;
    public readonly ?string $domain;
    public readonly ?int    $domainPosition;

    private readonly ?string $noRotationReason;

    private function __construct(
        ?int    $depth,
        ?string $domain,
        ?int    $domainPosition,
        ?string $noRotationReason,
    ) {
        $this->depth            = $depth;
        $this->domain           = $domain;
        $this->domainPosition   = $domainPosition;
        $this->noRotationReason = $noRotationReason;
    }

    // =========================================================================
    // Constructeurs statiques
    // =========================================================================

    /**
     * Rotation disponible — depth + domain + domainPosition déterminés par KRP.
     *
     * @param int    $domainPosition  Index (0-7) du domaine sélectionné dans le DomainCycle.
     */
    public static function available(int $depth, string $domain, int $domainPosition): self
    {
        return new self(
            depth:            $depth,
            domain:           $domain,
            domainPosition:   $domainPosition,
            noRotationReason: null,
        );
    }

    /**
     * Aucune rotation disponible.
     *
     * @param string $reason  PRODUCTION_ON_HOLD | PENDING_DEPTH_TRANSITION
     */
    public static function noRotation(string $reason): self
    {
        return new self(
            depth:            null,
            domain:           null,
            domainPosition:   null,
            noRotationReason: $reason,
        );
    }

    // =========================================================================
    // Interrogation
    // =========================================================================

    public function isAvailable(): bool
    {
        return $this->noRotationReason === null;
    }

    public function isNoRotation(): bool
    {
        return $this->noRotationReason !== null;
    }

    public function noRotationReason(): ?string
    {
        return $this->noRotationReason;
    }
}
