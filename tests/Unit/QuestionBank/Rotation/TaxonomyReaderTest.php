<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\TaxonomyReader;
use Tests\TestCase;

/**
 * Tests unitaires pour TaxonomyReader.
 *
 * Couvre :
 *   - Résolution domain_code (DomainCycle) → clé réelle taxonomy.json
 *   - Règles de non-mapping science→Général et general→Science
 *   - Candidates retournées depuis la racine "Science" (jamais depuis "Général")
 *   - Accessibilité de "Général" uniquement si demandé explicitement
 *
 * Tous les tests lisent taxonomy.json réel (resources/rotation/taxonomy.json).
 * Aucune DB — base_path() suffit.
 */
class TaxonomyReaderTest extends TestCase
{
    private TaxonomyReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new TaxonomyReader();
    }

    // =========================================================================
    // Test 1 — Résolution des 8 domain_codes Gameplay vers les clés taxonomy.json
    // =========================================================================

    public function test_it_resolves_gameplay_domain_codes_to_taxonomy_keys(): void
    {
        $codes = [
            'histoire'   => 'Histoire',
            'geographie' => 'Géographie',
            'sport'      => 'Sport',
            'art'        => 'Art',
            'cuisine'    => 'Cuisine',
            'science'    => 'Science',
            'cinema'     => 'Cinéma',
            'faune'      => 'Faune',
        ];

        foreach ($codes as $code => $expectedKey) {
            $this->assertTrue(
                $this->reader->hasDomain($code),
                "hasDomain('{$code}') doit être true — résolu vers '{$expectedKey}'"
            );
        }

        // La clé directe "Science" (déjà résolue) doit aussi fonctionner
        $this->assertTrue(
            $this->reader->hasDomain('Science'),
            "hasDomain('Science') doit être true — clé directe dans taxonomy.json"
        );
    }

    // =========================================================================
    // Test 2 — science ne mappe jamais vers Général
    // =========================================================================

    public function test_it_does_not_map_science_to_general(): void
    {
        $subDomains = $this->reader->getSubDomains('science');

        // science → Science → contient uniquement "Sciences"
        $this->assertContains(
            'Sciences',
            $subDomains,
            "getSubDomains('science') doit contenir 'Sciences'"
        );

        // Les sous-domaines de Général ne doivent PAS apparaître
        $this->assertNotContains(
            'Technologies',
            $subDomains,
            "getSubDomains('science') ne doit PAS contenir 'Technologies' (appartient à Général)"
        );
        $this->assertNotContains(
            'Économie',
            $subDomains,
            "getSubDomains('science') ne doit PAS contenir 'Économie' (appartient à Général)"
        );
        $this->assertNotContains(
            'Philosophie',
            $subDomains,
            "getSubDomains('science') ne doit PAS contenir 'Philosophie' (appartient à Général)"
        );

        // Vérification candidats : aucun sujet de Général (Internet, Transistor, Inflation…)
        $candidates    = $this->reader->getCandidates('science');
        $subDomainsInCandidates = array_unique(array_column($candidates, 'sub_domain'));

        $this->assertNotContains(
            'Technologies',
            $subDomainsInCandidates,
            "getCandidates('science') ne doit PAS contenir de candidats du sous-domaine 'Technologies'"
        );
        $this->assertNotContains(
            'Économie',
            $subDomainsInCandidates,
            "getCandidates('science') ne doit PAS contenir de candidats du sous-domaine 'Économie'"
        );
        $this->assertNotContains(
            'Philosophie',
            $subDomainsInCandidates,
            "getCandidates('science') ne doit PAS contenir de candidats du sous-domaine 'Philosophie'"
        );
    }

    // =========================================================================
    // Test 3 — general ne mappe jamais vers Science (ni vers Général)
    // =========================================================================

    public function test_it_does_not_map_general_to_science(): void
    {
        // 'general' (minuscule, sans accent) n'est dans aucun DOMAIN_MAP
        // → resolve() le passe tel quel → 'general' est absent de taxonomy.json
        $this->assertFalse(
            $this->reader->hasDomain('general'),
            "hasDomain('general') doit être false — 'general' n'est pas une clé taxonomy.json"
        );

        // getSubDomains('general') doit retourner [] (pas de fallback Science ou Général)
        $this->assertSame(
            [],
            $this->reader->getSubDomains('general'),
            "getSubDomains('general') doit retourner [] — aucun fallback"
        );

        // getCandidates('general') doit retourner [] (pas de fallback Science ou Général)
        $this->assertSame(
            [],
            $this->reader->getCandidates('general'),
            "getCandidates('general') doit retourner [] — aucun fallback vers Science ni Général"
        );
    }

    // =========================================================================
    // Test 4 — getCandidates('science') retourne 20 candidats depuis la racine Science
    // =========================================================================

    public function test_it_returns_science_candidates_from_science_root(): void
    {
        $candidates = $this->reader->getCandidates('science');

        // 4 sujets × 5 idées dominantes = 20 candidats
        $this->assertCount(
            20,
            $candidates,
            "getCandidates('science') doit retourner 20 candidats (4 sujets × 5 idées dominantes)"
        );

        // Structure d'un candidat
        $first = $candidates[0];
        $this->assertArrayHasKey('sub_domain',          $first);
        $this->assertArrayHasKey('subject',             $first);
        $this->assertArrayHasKey('idee_dominante',      $first);
        $this->assertArrayHasKey('knowledge_frequency', $first);

        // Tous les candidats appartiennent au sous-domaine "Sciences"
        foreach ($candidates as $candidate) {
            $this->assertSame(
                'Sciences',
                $candidate['sub_domain'],
                "Tous les candidats de 'science' doivent avoir sub_domain='Sciences'"
            );
        }

        // Les 4 sujets attendus sont présents
        $subjects = array_unique(array_column($candidates, 'subject'));
        sort($subjects);
        $this->assertSame(
            ['ADN', 'Photosynthèse', 'Radioactivité', 'Trou_noir'],
            $subjects,
            "Les 4 sujets attendus dans Science→Sciences doivent être présents"
        );

        // knowledge_frequency cohérente (1-8)
        foreach ($candidates as $candidate) {
            $kf = $candidate['knowledge_frequency'];
            $this->assertGreaterThanOrEqual(1, $kf);
            $this->assertLessThanOrEqual(8, $kf);
        }
    }

    // =========================================================================
    // Test 5 — Général reste accessible uniquement si demandé explicitement
    // =========================================================================

    public function test_it_keeps_general_available_only_when_explicitly_requested(): void
    {
        // Accès explicite "Général" (clé directe) → doit fonctionner
        $this->assertTrue(
            $this->reader->hasDomain('Général'),
            "hasDomain('Général') doit être true — clé directe dans taxonomy.json"
        );

        // 'general' (sans accent, minuscule) → NON mappé → false
        $this->assertFalse(
            $this->reader->hasDomain('general'),
            "hasDomain('general') doit être false"
        );

        // Général possède bien ses 4 sous-domaines d'origine
        $generalSubDomains = $this->reader->getSubDomains('Général');
        $this->assertContains('Sciences',     $generalSubDomains);
        $this->assertContains('Technologies', $generalSubDomains);
        $this->assertContains('Économie',     $generalSubDomains);
        $this->assertContains('Philosophie',  $generalSubDomains);

        // science ne touche PAS Général — ses candidats sont isolés dans Science
        $scienceCandidates = $this->reader->getCandidates('science');
        $generalCandidates = $this->reader->getCandidates('Général');

        // Vérification isolation : aucun candidat Science ne provient d'un sous-domaine Général
        $scienceSubDomains = array_unique(array_column($scienceCandidates, 'sub_domain'));
        $generalSubDomainsInCandidates = array_unique(array_column($generalCandidates, 'sub_domain'));

        foreach ($scienceSubDomains as $sd) {
            $this->assertFalse(
                in_array($sd, ['Technologies', 'Économie', 'Philosophie'], true),
                "Science ne doit pas contenir des sous-domaines de Général (trouvé: '{$sd}')"
            );
        }

        // Science et Général peuvent partager "Sciences" comme sous-domaine (duplication intentionnelle)
        // mais les candidats comptés doivent rester indépendants (chacun 20 pour science)
        $this->assertCount(20, $scienceCandidates);
        $this->assertGreaterThan(0, count($generalCandidates));
    }
}
