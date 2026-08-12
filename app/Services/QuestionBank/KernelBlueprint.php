<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

/**
 * KernelBlueprint — enveloppe canonique d'un noyau en construction.
 *
 * Partie 1 : 6 champs d'identité intellectuelle + blueprint_id.
 *
 * ── Ownership des slots ───────────────────────────────────────────────────
 *   blueprint_id        ← KernelBlueprintFactory  (initializeBlueprintId)
 *   depth + domain      ← KernelRotationPlanner   (fillRotation)
 *   subdomain_active
 *   subject_active      ← Taxonomy                (fillTaxonomy)
 *   dominant_idea_active
 *   kernel_code         ← KernelCodeEngine        (fillKernelCode)
 *
 * ── Règles d'écriture ─────────────────────────────────────────────────────
 *   • Toute propriété est lisible publiquement ($bp->depth).
 *   • Toute écriture directe externe ($bp->depth = x) est INTERDITE —
 *     __set() lève une LogicException.
 *   • Chaque slot ne peut être attribué qu'une seule fois (write-once) :
 *     un second appel au fill*() correspondant lève une LogicException.
 *   • Les slots non encore écrits valent null.
 *
 * Parties 2–6 : non encore implémentées — attendues ultérieurement.
 */
class KernelBlueprint
{
    // ─── Identité canonique du Blueprint (DEC-059) ───────────────────────────

    /**
     * Propriétaire : KernelBlueprintFactory.
     * UUIDv7 (time-ordered) généré avant l'entrée dans KRP.
     * Immuable après initializeBlueprintId(). Distinct de kernel_code.
     */
    private ?string $blueprint_id = null;

    // ─── Partie 1 — 6 champs de l'identité intellectuelle ───────────────────

    /**
     * Propriétaire : KernelRotationPlanner.
     * Détermine le DepthContract utilisé par tous les moteurs suivants.
     * Immuable après fillRotation().
     */
    private ?int $depth = null;

    /**
     * Propriétaire : KernelRotationPlanner.
     * Détermine le réservoir exploité par Taxonomy.
     * Domaines autorisés : Géographie, Histoire, Faune, Art, Sport, Cinéma, Cuisine, Général.
     * Immuable après fillRotation().
     */
    private ?string $domain = null;

    /**
     * Propriétaire : Taxonomy.
     * Découle du domaine actif. Plus précis que le domaine, jamais un sujet déguisé.
     * Immuable après fillTaxonomy().
     */
    private ?string $subdomain_active = null;

    /**
     * Propriétaire : Taxonomy.
     * Appartient au sous-domaine actif. Court, fermé, sans réponse ni indice.
     * Immuable après fillTaxonomy().
     */
    private ?string $subject_active = null;

    /**
     * Propriétaire : Taxonomy (propose) + DominantIdeaValidator (valide).
     * N'est inscrit dans le Blueprint QU'APRÈS obtention d'un PASS de DominantIdeaValidator.
     * Immuable après fillTaxonomy().
     */
    private ?string $dominant_idea_active = null;

    /**
     * Propriétaire : KernelCodeEngine.
     * Produit uniquement après validation complète des 5 champs d'identité.
     * Format : yy-xx-xxx-xxx-xxx-zz (KernelCodeContract).
     * Immuable après fillKernelCode().
     */
    private ?string $kernel_code = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Accès public aux propriétés
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Lecture publique — toutes les propriétés restent accessibles via $bp->prop.
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new \LogicException(
            "Propriété KernelBlueprint::\${$name} inexistante."
        );
    }

    /**
     * Écriture directe externe interdite.
     * Utiliser la méthode fill*() du propriétaire du slot.
     */
    public function __set(string $name, mixed $value): void
    {
        throw new \LogicException(
            "[KernelBlueprint] Écriture directe interdite sur '{$name}'. "
            . "Utiliser la méthode fill*() du propriétaire du slot."
        );
    }

    /**
     * isset($bp->depth) retourne true si le slot est rempli (non null).
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name) && $this->$name !== null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Méthodes d'écriture — une méthode par contrat de responsabilité
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Appelée par KernelBlueprintFactory uniquement — une seule fois.
     *
     * @throws \LogicException si blueprint_id est déjà initialisé (write-once).
     */
    public function initializeBlueprintId(string $id): void
    {
        if ($this->blueprint_id !== null) {
            throw new \LogicException(
                '[KernelBlueprint] blueprint_id déjà initialisé — write-once violation.'
            );
        }

        $this->blueprint_id = $id;
    }

    /**
     * Appelée par KernelRotationPlanner uniquement — premier moteur.
     *
     * Remplit depth + domain simultanément. Ne touche pas aux champs Taxonomy ni kernel_code.
     *
     * @throws \LogicException si la rotation est déjà définie (write-once).
     */
    public function fillRotation(int $depth, string $domain): void
    {
        if ($this->depth !== null || $this->domain !== null) {
            throw new \LogicException(
                '[KernelBlueprint] Rotation déjà définie — write-once violation (fillRotation).'
            );
        }

        $this->depth  = $depth;
        $this->domain = $domain;
    }

    /**
     * Appelée par Taxonomy uniquement — après validation DominantIdeaValidator.
     *
     * Taxonomy lit depth + domain (déjà écrits par KernelRotationPlanner).
     * dominant_idea_active ne doit être passé ici QU'APRÈS obtention d'un PASS.
     * Ne touche pas à depth, domain, kernel_code.
     *
     * @throws \LogicException si les slots Taxonomy sont déjà définis (write-once).
     */
    public function fillTaxonomy(
        string $subdomainActive,
        string $subjectActive,
        string $dominantIdeaActive
    ): void {
        if ($this->subdomain_active !== null
            || $this->subject_active !== null
            || $this->dominant_idea_active !== null) {
            throw new \LogicException(
                '[KernelBlueprint] Taxonomy déjà définie — write-once violation (fillTaxonomy).'
            );
        }

        $this->subdomain_active     = $subdomainActive;
        $this->subject_active       = $subjectActive;
        $this->dominant_idea_active = $dominantIdeaActive;
    }

    /**
     * Appelée par KernelCodeEngine uniquement.
     *
     * Précondition : isIdentityComplete() === true.
     * Lit les 5 champs d'identité — ne les modifie jamais.
     *
     * @throws \LogicException si kernel_code est déjà défini (write-once).
     */
    public function fillKernelCode(string $kernelCode): void
    {
        if ($this->kernel_code !== null) {
            throw new \LogicException(
                '[KernelBlueprint] kernel_code déjà défini — write-once violation (fillKernelCode).'
            );
        }

        $this->kernel_code = $kernelCode;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers d'état — lecture seule
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Vérifie que KernelRotationPlanner a rempli sa partie (depth + domain).
     */
    public function isRotationFilled(): bool
    {
        return $this->depth !== null && $this->domain !== null;
    }

    /**
     * Vérifie que Taxonomy a rempli sa partie
     * (subdomain_active + subject_active + dominant_idea_active).
     * dominant_idea_active est garanti PASS de DominantIdeaValidator si true.
     */
    public function isTaxonomyFilled(): bool
    {
        return $this->subdomain_active !== null
            && $this->subject_active !== null
            && $this->dominant_idea_active !== null;
    }

    /**
     * Vérifie que les 5 champs d'identité sont remplis.
     * Précondition obligatoire pour que KernelCodeEngine puisse écrire kernel_code.
     */
    public function isIdentityComplete(): bool
    {
        return $this->isRotationFilled() && $this->isTaxonomyFilled();
    }

    /**
     * Vérifie que la Partie 1 est entièrement complète (kernel_code inclus).
     * Précondition pour passer à la Partie 2 du pipeline.
     */
    public function isComplete(): bool
    {
        return $this->isIdentityComplete() && $this->kernel_code !== null;
    }

    /**
     * Exporte les 6 champs de la Partie 1 sous forme de tableau.
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
