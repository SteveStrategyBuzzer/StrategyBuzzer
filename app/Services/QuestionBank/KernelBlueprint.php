<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

/**
 * KernelBlueprint — enveloppe canonique d'un noyau en construction.
 *
 * Section 1 : identité canonique, Rotation, Taxonomy et kernel_code.
 * L'identité canonique du Blueprint précède les écritures de pipeline.
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
 *   • Les propriétés non encore écrites valent null.
 *
 * Parties 2–6 : non encore implémentées — attendues ultérieurement.
 */
class KernelBlueprint
{
    public const COGNITIVE_TYPES = [
        'QCM_RECOGNITION',
        'QCM_REASONING',
        'QCM_TRAP',
        'TRUE_FALSE_RECOGNITION_TRUE',
        'TRUE_FALSE_RECOGNITION_FALSE',
        'TRUE_FALSE_REASONING_TRUE',
        'TRUE_FALSE_REASONING_FALSE',
    ];

    // ─── Identité canonique du Blueprint (DEC-059) ───────────────────────────

    /**
     * Propriétaire : KernelBlueprintFactory.
     * UUIDv7 (time-ordered) généré avant l'entrée dans KRP.
     * Immuable après initializeBlueprintId(). Distinct de kernel_code.
     */
    private ?string $blueprint_id = null;

    // ─── Section 1 — champs de l'identité et du pipeline ────────────────────

    /**
     * Propriétaire : KernelRotationPlanner.
     * Détermine le DepthContract utilisé par tous les moteurs suivants.
     * Immuable après fillRotation().
     */
    private ?int $depth = null;

    /**
     * Propriétaire : KernelRotationPlanner.
     * Détermine le domaine transmis à Taxonomy.
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
     * Propriétaire : Taxonomy.
     * Taxonomy ne l'inscrit qu'après validation de sa valeur.
     * Immuable après fillTaxonomy().
     */
    private ?string $dominant_idea_active = null;

    /**
     * Propriétaire : KernelCodeEngine.
     * Produit uniquement après complétude de l'identité et de la Taxonomy.
     * Immuable après fillKernelCode().
     */
    private ?string $kernel_code = null;

    /**
     * Sept enfants permanents, indexés par cognitive_type.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $cognitive_slots = [];

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
     * @param array<string, array<string, mixed>> $slots
     */
    public function initializeCognitiveSlots(array $slots): void
    {
        if ($this->cognitive_slots !== []) {
            throw new \LogicException(
                '[KernelBlueprint] CognitiveSlots déjà initialisés.'
            );
        }

        $this->assertCognitiveSlots($slots);
        $this->cognitive_slots = $slots;
    }

    /**
     * Recharge uniquement les enfants persistés; la Section 1 reste inchangée.
     *
     * @param array<string, array<string, mixed>> $slots
     */
    public function synchronizeCognitiveSlots(array $slots): void
    {
        $this->assertCognitiveSlots($slots);
        $this->cognitive_slots = $slots;
    }

    /**
     * Appelée par KernelRotationPlanner uniquement — après l'identité canonique.
     *
     * Remplit depth + domain simultanément. Ne touche pas aux champs Taxonomy ni kernel_code.
     *
     * @throws \LogicException si blueprint_id n'est pas initialisé.
     * @throws \LogicException si la rotation est déjà définie (write-once).
     */
    public function fillRotation(int $depth, string $domain): void
    {
        if ($this->depth !== null || $this->domain !== null) {
            throw new \LogicException(
                '[KernelBlueprint] Rotation déjà définie — write-once violation (fillRotation).'
            );
        }

        if ($this->blueprint_id === null) {
            throw new \LogicException(
                '[KernelBlueprint] Identité canonique requise avant la Rotation.'
            );
        }

        $this->depth  = $depth;
        $this->domain = $domain;
    }

    /**
     * Appelée par Taxonomy uniquement — après la Rotation.
     *
     * Taxonomy lit depth + domain (déjà écrits par KernelRotationPlanner).
     * Ne touche pas à depth, domain, kernel_code.
     *
     * @throws \LogicException si la Rotation n'est pas définie.
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

        if (! $this->isRotationFilled()) {
            throw new \LogicException(
                '[KernelBlueprint] Rotation requise avant la Taxonomy.'
            );
        }

        $this->subdomain_active     = $subdomainActive;
        $this->subject_active       = $subjectActive;
        $this->dominant_idea_active = $dominantIdeaActive;
    }

    /**
     * Appelée par KernelCodeEngine uniquement — après l'identité et Taxonomy.
     *
     * Précondition : blueprint_id et isIdentityComplete() sont définis.
     * Lit les champs précédents — ne les modifie jamais.
     *
     * @throws \LogicException si la Section 1 n'est pas prête pour le code.
     * @throws \LogicException si kernel_code est déjà défini (write-once).
     */
    public function fillKernelCode(string $kernelCode): void
    {
        if ($this->kernel_code !== null) {
            throw new \LogicException(
                '[KernelBlueprint] kernel_code déjà défini — write-once violation (fillKernelCode).'
            );
        }

        if ($this->blueprint_id === null || ! $this->isIdentityComplete()) {
            throw new \LogicException(
                '[KernelBlueprint] Identité canonique, Rotation et Taxonomy requises avant kernel_code.'
            );
        }

        $prefix = $this->kernelCodePrefix();
        if (! preg_match(KernelCodeFormat::FORMAT_REGEX, $kernelCode)
            || $prefix === null
            || ! str_starts_with($kernelCode, $prefix . '-')) {
            throw new \LogicException(
                '[KernelBlueprint] kernel_code invalide ou divergent de la projection canonique.'
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
     * Taxonomy a validé dominant_idea_active avant son écriture.
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
     * Projection dérivée DEC-121 v2.2. Aucune chaîne partielle n'est persistée.
     */
    public function kernelCodeProjection(): string
    {
        if ($this->kernel_code !== null) {
            return $this->kernel_code;
        }

        return implode('-', [
            $this->depth !== null ? KernelCodeFormat::depth($this->depth) : '__',
            $this->domain !== null ? KernelCodeFormat::domain($this->domain) : '___',
            $this->subdomain_active !== null ? KernelCodeFormat::segment($this->subdomain_active) : '___',
            $this->subject_active !== null ? KernelCodeFormat::segment($this->subject_active) : '___',
            $this->dominant_idea_active !== null ? KernelCodeFormat::segment($this->dominant_idea_active) : '___',
            '____',
        ]);
    }

    /**
     * Retourne les cinq segments intellectuels projetés, sans VVVV.
     */
    public function kernelCodePrefix(): ?string
    {
        if (! $this->isIdentityComplete()) {
            return null;
        }

        return implode('-', [
            KernelCodeFormat::depth((int) $this->depth),
            KernelCodeFormat::domain((string) $this->domain),
            KernelCodeFormat::segment((string) $this->subdomain_active),
            KernelCodeFormat::segment((string) $this->subject_active),
            KernelCodeFormat::segment((string) $this->dominant_idea_active),
        ]);
    }

    /**
     * Vérifie que la Section 1 est entièrement complète :
     * identité canonique, Rotation, Taxonomy et kernel_code.
     */
    public function isComplete(): bool
    {
        return $this->blueprint_id !== null
            && $this->isIdentityComplete()
            && $this->kernel_code !== null;
    }

    /**
     * Exporte l'identité canonique et les six champs de la Section 1.
     * Aucune règle, aucun contrat, aucune métadonnée.
     */
    public function toArray(): array
    {
        return [
            'blueprint_id'         => $this->blueprint_id,
            'depth'                => $this->depth,
            'domain'               => $this->domain,
            'subdomain_active'     => $this->subdomain_active,
            'subject_active'       => $this->subject_active,
            'dominant_idea_active' => $this->dominant_idea_active,
            'kernel_code'          => $this->kernel_code,
            'cognitive_slots'      => $this->cognitive_slots,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $slots
     */
    private function assertCognitiveSlots(array $slots): void
    {
        $actual = array_keys($slots);
        sort($actual);
        $expected = self::COGNITIVE_TYPES;
        sort($expected);

        if ($actual !== $expected) {
            throw new \LogicException(
                '[KernelBlueprint] Les sept CognitiveSlots officiels sont requis.'
            );
        }

        foreach ($slots as $type => $slot) {
            if (($slot['cognitive_type'] ?? null) !== $type) {
                throw new \LogicException(
                    "[KernelBlueprint] CognitiveSlot incohérent pour {$type}."
                );
            }
        }
    }
}
