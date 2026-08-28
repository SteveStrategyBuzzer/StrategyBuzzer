<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

/**
 * TaxonomyConfig — constantes de configuration de la couche Taxonomy.
 *
 * SOURCE UNIQUE de vérité pour tous les plafonds et paramètres du pipeline.
 * NE PAS dupliquer ces valeurs dans d'autres classes.
 */
final class TaxonomyConfig
{
    // ── Capacités par niveau ──────────────────────────────────────────────────

    /** Nombre maximum de Sous-domaines générés par (Depth × Domaine). */
    public const MAX_SUBDOMAINS_PER_DOMAIN = 20;

    /** Nombre maximum de Sujets générés par Sous-domaine. */
    public const MAX_SUBJECTS_PER_SUBDOMAIN = 50;

    /**
     * Capacité technique par appel Gemini pour la génération de Sujets (DEC-100).
     * Nombre minimal d'appels pour N Sujets = ceil(N / MAX_SUBJECTS_PER_GEMINI_CALL).
     * Les lots sont répartis aussi également que possible (écart maximal de 1 Sujet).
     */
    public const MAX_SUBJECTS_PER_GEMINI_CALL = 10;

    /** Nombre maximum d'Idées Dominantes PASS par Sujet (spec §2 : 1..5). */
    public const MAX_DOMINANT_IDEAS_PER_SUBJECT = 5;

    // ── Tentatives Gemini ─────────────────────────────────────────────────────

    /** Nombre maximum d'appels Gemini pour générer des Sous-domaines pour un contexte. */
    public const MAX_SUBDOMAIN_GENERATION_ATTEMPTS = 3;

    /** Nombre maximum d'appels Gemini pour générer des Sujets pour un Sous-domaine. */
    public const MAX_SUBJECT_GENERATION_ATTEMPTS = 3;

    /** Nombre maximum d'appels Gemini pour générer des Idées pour un Sujet. */
    public const MAX_DOMINANT_IDEA_GENERATION_ATTEMPTS = 3;

    // ── Modèle Gemini ─────────────────────────────────────────────────────────

    /** Modèle Gemini utilisé pour la génération Taxonomy. */
    public const GEMINI_MODEL = 'gemini-3.6-flash';

    /** URL de base Gemini REST API. */
    public const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** Timeout en secondes pour les appels Gemini. */
    public const GEMINI_TIMEOUT_SECONDS = 45;
}
