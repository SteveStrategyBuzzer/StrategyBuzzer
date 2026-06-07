<?php

namespace App\Services\QuestionBank\Rotation;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * TaxonomyReader
 *
 * Source structurée de la taxonomie. Fournit les possibilités disponibles.
 * Ne décide pas quoi produire.
 *
 * Responsabilités :
 *   - Recevoir le Domaine fourni par DomainCycle
 *   - Lire les sous-domaines disponibles de ce Domaine
 *   - Lire les sujets disponibles de chaque sous-domaine
 *   - Lire les idées dominantes directrices disponibles pour chaque sujet
 *   - Exposer la structure taxonomique (knowledge_frequency inclus)
 *   - Retourner des candidats disponibles (non filtrés — Rotation filtre)
 *
 * Responsabilités interdites :
 *   - Ne choisit pas le Domaine
 *   - Ne décide pas quoi produire
 *   - Ne gère pas la stratégie de Rotation
 *   - Ne décide pas du fallback final
 *   - Ne valide pas KEY_STRUCTURE
 *   - Ne pose pas QuestionIntent
 *   - Ne contrôle pas knowledge_frequency (stocke/expose, KEY_LEARNING_DIRECTION contrôle)
 *
 * Note sur knowledge_frequency :
 *   La valeur est exposée par la taxonomie mais sa validation/cohérence pédagogique
 *   est contrôlée par KEY_LEARNING_DIRECTION (IntentKeyBuilder), pas par cette classe.
 */
final class TaxonomyReader
{
    private const TAXONOMY_PATH = 'resources/rotation/taxonomy.json';

    private ?array $taxonomy = null;

    // =========================================================================
    // Public API — lecture de la structure
    // =========================================================================

    /**
     * Retourne tous les noms de domaines disponibles dans la taxonomie.
     *
     * @return string[]
     */
    public function getDomains(): array
    {
        return array_keys($this->load());
    }

    /**
     * Vérifie si un domaine existe dans la taxonomie.
     */
    public function hasDomain(string $domain): bool
    {
        return isset($this->load()[$domain]);
    }

    /**
     * Retourne les noms de sous-domaines disponibles pour un domaine.
     *
     * @return string[]
     */
    public function getSubDomains(string $domain): array
    {
        $data = $this->load();

        if (! isset($data[$domain])) {
            return [];
        }

        return array_keys($data[$domain]);
    }

    /**
     * Retourne les noms de sujets disponibles pour un domaine + sous-domaine.
     *
     * @return string[]
     */
    public function getSubjects(string $domain, string $subDomain): array
    {
        $data = $this->load();

        if (! isset($data[$domain][$subDomain])) {
            return [];
        }

        return array_keys($data[$domain][$subDomain]);
    }

    /**
     * Retourne les idées dominantes disponibles pour un sujet donné.
     * Chaque idée dominante = 1 mot (max 2 mots si inséparables sémantiquement).
     *
     * @return string[]
     */
    public function getIdeesDominantes(string $domain, string $subDomain, string $subject): array
    {
        $data = $this->load();

        return $data[$domain][$subDomain][$subject]['idees_dominantes'] ?? [];
    }

    /**
     * Retourne la knowledge_frequency pour un sujet.
     * Valeur stockée dans la taxonomie (1-8).
     * Validation pédagogique du couple sujet+idee_dominante = responsabilité de KEY_LEARNING_DIRECTION.
     *
     * @return int  1-8, ou 0 si introuvable
     */
    public function getKnowledgeFrequency(string $domain, string $subDomain, string $subject): int
    {
        $data = $this->load();

        return (int) ($data[$domain][$subDomain][$subject]['knowledge_frequency'] ?? 0);
    }

    // =========================================================================
    // Public API — candidats (liste plate, non filtrée)
    // =========================================================================

    /**
     * Retourne tous les candidats disponibles pour un domaine donné.
     *
     * Chaque candidat = { sub_domain, subject, idee_dominante, knowledge_frequency }
     *
     * La liste est non filtrée — Rotation (KernelRotationPlanner) est responsable
     * de filtrer les combinaisons selon les exclusions KLD et KS.
     *
     * @param  string  $domain  Domaine fourni par DomainCycle
     * @return array<int, array{
     *     sub_domain: string,
     *     subject: string,
     *     idee_dominante: string,
     *     knowledge_frequency: int
     * }>
     */
    public function getCandidates(string $domain): array
    {
        $data = $this->load();

        if (! isset($data[$domain])) {
            return [];
        }

        $candidates = [];

        foreach ($data[$domain] as $subDomain => $subjects) {
            foreach ($subjects as $subject => $entry) {
                $kf    = (int) ($entry['knowledge_frequency'] ?? 0);
                $idees = $entry['idees_dominantes'] ?? [];

                foreach ($idees as $ideeDominante) {
                    $candidates[] = [
                        'sub_domain'          => $subDomain,
                        'subject'             => $subject,
                        'idee_dominante'      => $ideeDominante,
                        'knowledge_frequency' => $kf,
                    ];
                }
            }
        }

        return $candidates;
    }

    /**
     * Retourne tous les candidats de tous les domaines.
     * Usage : audit / reporting.
     *
     * @return array<string, array<int, array{sub_domain, subject, idee_dominante, knowledge_frequency}>>
     */
    public function getAllCandidatesByDomain(): array
    {
        $result = [];
        foreach ($this->getDomains() as $domain) {
            $result[$domain] = $this->getCandidates($domain);
        }
        return $result;
    }

    // =========================================================================
    // Public API — statistiques (usage interne + audit)
    // =========================================================================

    /**
     * Retourne le nombre de candidats (combinaisons sujet × idee_dominante) par domaine.
     *
     * @return array<string, int>
     */
    public function countCandidatesByDomain(): array
    {
        $counts = [];
        foreach ($this->getDomains() as $domain) {
            $counts[$domain] = count($this->getCandidates($domain));
        }
        return $counts;
    }

    /**
     * Retourne le nombre total de sujets par domaine.
     *
     * @return array<string, int>
     */
    public function countSubjectsByDomain(): array
    {
        $data   = $this->load();
        $counts = [];

        foreach ($data as $domain => $subDomains) {
            $total = 0;
            foreach ($subDomains as $subjects) {
                $total += count($subjects);
            }
            $counts[$domain] = $total;
        }

        return $counts;
    }

    /**
     * Retourne un résumé de la taxonomie pour audit :
     *   domain → { sub_domain_count, subject_count, candidate_count }
     *
     * @return array<string, array{sub_domain_count: int, subject_count: int, candidate_count: int}>
     */
    public function getSummary(): array
    {
        $data    = $this->load();
        $summary = [];

        foreach ($data as $domain => $subDomains) {
            $subDomainCount = count($subDomains);
            $subjectCount   = 0;
            $candidateCount = 0;

            foreach ($subDomains as $subjects) {
                $subjectCount += count($subjects);
                foreach ($subjects as $entry) {
                    $candidateCount += count($entry['idees_dominantes'] ?? []);
                }
            }

            $summary[$domain] = [
                'sub_domain_count' => $subDomainCount,
                'subject_count'    => $subjectCount,
                'candidate_count'  => $candidateCount,
            ];
        }

        return $summary;
    }

    // =========================================================================
    // Chargement taxonomy.json (cache interne)
    // =========================================================================

    /**
     * Charge et met en cache le fichier taxonomy.json.
     * Lève une RuntimeException si le fichier est absent ou invalide.
     */
    private function load(): array
    {
        if ($this->taxonomy !== null) {
            return $this->taxonomy;
        }

        $path = base_path(self::TAXONOMY_PATH);

        if (! file_exists($path)) {
            Log::critical('[TaxonomyReader] taxonomy.json introuvable', ['path' => $path]);
            throw new RuntimeException(
                'TaxonomyReader: taxonomy.json introuvable à ' . $path
            );
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('TaxonomyReader: lecture taxonomy.json échouée.');
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || empty($data)) {
            throw new RuntimeException('TaxonomyReader: taxonomy.json invalide ou vide.');
        }

        $this->taxonomy = $data;

        return $this->taxonomy;
    }
}
