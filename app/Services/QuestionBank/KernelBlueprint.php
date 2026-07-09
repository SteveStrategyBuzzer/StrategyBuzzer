<?php

namespace App\Services\QuestionBank;

/**
 * KernelBlueprint — enveloppe vivante du noyau (Partie 1).
 *
 * ══ RÔLE ══════════════════════════════════════════════════════════════════════
 *
 * Le Blueprint transporte uniquement les valeurs actives du noyau courant.
 * Il n'est pas un slot. Il n'est pas un conteneur de règles.
 * Il ne connaît ni DepthContract, ni RotationRules, ni TaxonomyRules,
 * ni KernelCodeFormat. Ces règles restent dans leurs composants respectifs.
 *
 * ══ STRUCTURE PARTIE 1 (6 champs) ═════════════════════════════════════════════
 *
 *   depth               — rempli par KernelRotationPlanner
 *   domain              — rempli par KernelRotationPlanner
 *   subdomain_active    — rempli par Taxonomy
 *   subject_active      — rempli par Taxonomy
 *   dominant_idea_active— rempli par Taxonomy
 *   kernel_code         — rempli par KernelCodeEngine
 *
 * ══ RESPONSABILITÉS D'ÉCRITURE ════════════════════════════════════════════════
 *
 *   KernelRotationPlanner  → fillRotation(depth, domain)
 *   Taxonomy               → fillTaxonomy(subdomain, subject, dominantIdea)
 *   KernelCodeEngine       → fillKernelCode(kernelCode)
 *
 * ══ RÈGLES ARCHITECTURALES ════════════════════════════════════════════════════
 *
 *   - Chaque composant écrit UNIQUEMENT ses champs désignés.
 *   - Taxonomy lit depth + domain, ne les modifie jamais.
 *   - KernelCodeEngine lit les 5 premiers champs, ne les modifie jamais.
 *   - Taxonomy ne crée pas le domaine et ne décide pas le domaine.
 *   - Taxonomy ne remplit pas depth.
 *   - Aucune règle n'est stockée dans le Blueprint.
 *   - DepthContract reste externe (dans son composant dédié).
 *   - RotationRules restent dans KernelRotationPlanner.
 *   - TaxonomyRules restent dans Taxonomy.
 *   - KernelCodeFormat / KernelCodeRules restent dans KernelCodeEngine.
 */
class KernelBlueprint
{
    // ─── 6 champs de la Partie 1 ─────────────────────────────────────────────

    /** Rempli par KernelRotationPlanner */
    public ?int $depth = null;

    /** Rempli par KernelRotationPlanner */
    public ?string $domain = null;

    /** Rempli par Taxonomy */
    public ?string $subdomain_active = null;

    /** Rempli par Taxonomy */
    public ?string $subject_active = null;

    /** Rempli par Taxonomy */
    public ?string $dominant_idea_active = null;

    /** Rempli par KernelCodeEngine (après rotation + taxonomy complètes) */
    public ?string $kernel_code = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Méthodes d'écriture — une méthode par responsabilité
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Appelée par KernelRotationPlanner uniquement.
     * Remplit depth et domain. Ne touche pas aux champs Taxonomy ni kernel_code.
     */
    public function fillRotation(int $depth, string $domain): void
    {
        $this->depth  = $depth;
        $this->domain = $domain;
    }

    /**
     * Appelée par Taxonomy uniquement.
     * Lit depth + domain (déjà remplis). Remplit les 3 champs actifs Taxonomy.
     * Ne touche pas à depth, domain, kernel_code.
     */
    public function fillTaxonomy(
        string $subdomainActive,
        string $subjectActive,
        string $dominantIdeaActive
    ): void {
        $this->subdomain_active     = $subdomainActive;
        $this->subject_active       = $subjectActive;
        $this->dominant_idea_active = $dominantIdeaActive;
    }

    /**
     * Appelée par KernelCodeEngine uniquement.
     * Lit depth + domain + subdomain_active + subject_active + dominant_idea_active.
     * Remplit kernel_code. Ne touche pas aux 5 champs d'identité.
     */
    public function fillKernelCode(string $kernelCode): void
    {
        $this->kernel_code = $kernelCode;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers d'état — lecture seule
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Vérifie que KernelRotationPlanner a rempli sa partie.
     */
    public function isRotationFilled(): bool
    {
        return $this->depth !== null && $this->domain !== null;
    }

    /**
     * Vérifie que Taxonomy a rempli sa partie.
     */
    public function isTaxonomyFilled(): bool
    {
        return $this->subdomain_active !== null
            && $this->subject_active !== null
            && $this->dominant_idea_active !== null;
    }

    /**
     * Vérifie que les 5 champs d'identité (rotation + taxonomy) sont remplis.
     * Précondition pour que KernelCodeEngine puisse générer kernel_code.
     */
    public function isIdentityComplete(): bool
    {
        return $this->isRotationFilled() && $this->isTaxonomyFilled();
    }

    /**
     * Vérifie que le noyau est entièrement identifié (kernel_code inclus).
     */
    public function isComplete(): bool
    {
        return $this->isIdentityComplete() && $this->kernel_code !== null;
    }

    /**
     * Exporte les 6 champs sous forme de tableau.
     * Aucune règle, aucun contrat, aucune métadonnée.
     */
    public function toArray(): array
    {
        return [
            'depth'                => $this->depth,
            'domain'               => $this->domain,
            'subdomain_active'     => $this->subdomain_active,
            'subject_active'       => $this->subject_active,
            'dominant_idea_active' => $this->dominant_idea_active,
            'kernel_code'          => $this->kernel_code,
        ];
    }
}
