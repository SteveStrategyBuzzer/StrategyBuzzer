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
 *   VVVV                ← KernelCodeEngine        (fillVvvv)
 *   kernel_code         ← PostgreSQL generated projection (read-only)
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

    public static function emptyCognitiveSlotSource(string $cognitiveType): array
    {
        if (! in_array($cognitiveType, self::COGNITIVE_TYPES, true)) {
            throw new \LogicException(
                "[KernelBlueprint] Type cognitif non officiel: {$cognitiveType}."
            );
        }

        $isQcm = str_starts_with($cognitiveType, 'QCM_');

        return [
            'schema_version' => 'phase1.source.v1',
            'source_language' => 'fr',
            'cognitive_type' => $cognitiveType,
            'question' => null,
            'choices' => $isQcm
                ? ['a' => null, 'b' => null, 'c' => null, 'd' => null]
                : ['a' => null, 'b' => null],
            'correct_answer_key' => str_ends_with($cognitiveType, '_FALSE') ? 'b' : 'a',
            'sv' => null,
            'creation_evidence' => null,
        ];
    }

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
     * Projection PostgreSQL read-only. No method accepts a complete code;
     * every writer persists only VVVV via fillVvvv().
     */
    private ?string $kernel_code = null;

    /** Canonical persisted segments (DD-DO-SUB-SUJ-IDE-VVVV). */
    private ?string $kernel_code_dd = null;
    private ?string $kernel_code_do = null;
    private ?string $kernel_code_sub = null;
    private ?string $kernel_code_suj = null;
    private ?string $kernel_code_ide = null;
    private ?string $kernel_code_vvvv = null;

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
        if ($name === 'kernel_code') {
            return $this->kernelCodeProjection();
        }

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
        if ($name === 'kernel_code') {
            return $this->kernelCodeProjection() !== null;
        }

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

        $canonicalDomain = CreatorDomainRegistry::fromInput($domain);

        $this->depth  = $depth;
        $this->domain = $canonicalDomain;
        $this->kernel_code_dd = KernelCodeFormat::depth($depth);
        $this->kernel_code_do = KernelCodeFormat::domain($canonicalDomain);
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
        $this->kernel_code_sub = KernelCodeFormat::segment($subdomainActive);
        $this->kernel_code_suj = KernelCodeFormat::segment($subjectActive);
        $this->kernel_code_ide = KernelCodeFormat::segment($dominantIdeaActive);
    }

    /**
     * QuestionIntent owns this final segment. It is deliberately the only
     * mutation method after Taxonomy and never accepts a complete code.
     *
     * There is no method that accepts a full kernel_code: kernel_code is
     * exclusively a PostgreSQL-generated read-only projection (see
     * kernelCodeProjection()). A caller reconstructing a Blueprint from an
     * already-persisted kernel_code must extract the trailing VVVV segment
     * itself and pass only that to fillVvvv().
     */
    public function fillVvvv(string $vvvv): void
    {
        if ($this->kernel_code_vvvv !== null) {
            if ($this->kernel_code_vvvv !== $vvvv) {
                throw new \LogicException(
                    '[KernelBlueprint] VVVV déjà attribué — write-once violation.'
                );
            }

            return;
        }

        if (! $this->isIdentityComplete() || ! preg_match('/^[0-9A-Z]{4}$/', $vvvv)) {
            throw new \LogicException(
                '[KernelBlueprint] Taxonomy complète et VVVV canonique requis.'
            );
        }

        $this->kernel_code_vvvv = $vvvv;
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
     * Précondition obligatoire pour que KernelCodeEngine puisse écrire VVVV.
     */
    public function isIdentityComplete(): bool
    {
        return $this->isRotationFilled() && $this->isTaxonomyFilled();
    }

    /**
     * Projection dérivée DEC-121 v2.2. Aucune chaîne partielle n'est persistée.
     */
    public function kernelCodeProjection(): ?string
    {
        if ($this->kernel_code !== null) {
            return $this->kernel_code;
        }

        if ($this->kernel_code_dd === null || $this->kernel_code_do === null
            || $this->kernel_code_sub === null || $this->kernel_code_suj === null
            || $this->kernel_code_ide === null || $this->kernel_code_vvvv === null) {
            return null;
        }

        return implode('-', [
            $this->kernel_code_dd,
            $this->kernel_code_do,
            $this->kernel_code_sub,
            $this->kernel_code_suj,
            $this->kernel_code_ide,
            $this->kernel_code_vvvv,
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
            $this->kernel_code_dd,
            $this->kernel_code_do,
            $this->kernel_code_sub,
            $this->kernel_code_suj,
            $this->kernel_code_ide,
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
            && $this->kernel_code_vvvv !== null;
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
            'kernel_code_dd'       => $this->kernel_code_dd,
            'kernel_code_do'       => $this->kernel_code_do,
            'kernel_code_sub'     => $this->kernel_code_sub,
            'kernel_code_suj'     => $this->kernel_code_suj,
            'kernel_code_ide'     => $this->kernel_code_ide,
            'kernel_code_vvvv'    => $this->kernel_code_vvvv,
            'kernel_code'          => $this->kernelCodeProjection(),
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
