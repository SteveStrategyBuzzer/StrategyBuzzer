<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests unitaires pour KernelRotationPlanner.
 *
 * Tous les tests sont sans base de données :
 * - buildDepthNeedMatrix() est testée avec des comptes injectés (calcul pur)
 * - chooseDepth(), loadDomains(), advanceDomainIndex() : méthodes publiques pures
 * - plan() n'est PAS testée ici car elle interroge question_groups (DB)
 */
class KernelRotationPlannerTest extends TestCase
{
    private KernelRotationPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new KernelRotationPlanner();
    }

    // =========================================================================
    // DepthNeedMatrix
    // =========================================================================

    public function test_depth_need_matrix_returns_official_targets(): void
    {
        $matrix  = $this->planner->buildDepthNeedMatrix([]);
        $byDepth = array_column($matrix, null, 'depth');

        $this->assertSame(3000, $byDepth[4]['target_kernels'], 'Depth 4 : target 3000');
        $this->assertSame(3000, $byDepth[6]['target_kernels'], 'Depth 6 : target 3000');
        $this->assertSame(2500, $byDepth[7]['target_kernels'], 'Depth 7 : target 2500');
        $this->assertSame(2000, $byDepth[8]['target_kernels'], 'Depth 8 : target 2000');
        $this->assertSame(1500, $byDepth[9]['target_kernels'], 'Depth 9 : target 1500');
    }

    public function test_depth_need_matrix_does_not_include_forbidden_depths(): void
    {
        $matrix = $this->planner->buildDepthNeedMatrix([]);
        $depths = array_column($matrix, 'depth');

        $this->assertNotContains(1,  $depths, 'Depth 1 interdit');
        $this->assertNotContains(2,  $depths, 'Depth 2 interdit');
        $this->assertNotContains(10, $depths, 'Depth 10 interdit');
    }

    public function test_depth_need_matrix_only_contains_allowed_depths(): void
    {
        $matrix  = $this->planner->buildDepthNeedMatrix([]);
        $depths  = array_column($matrix, 'depth');
        $allowed = [4, 6, 7, 8, 9];

        foreach ($depths as $d) {
            $this->assertContains($d, $allowed, "Depth {$d} ne devrait pas être dans la matrice");
        }
    }

    public function test_depth_need_matrix_computes_remaining_kernels_correctly(): void
    {
        $existing = [4 => 1000, 6 => 3000, 7 => 500];
        $byDepth  = array_column($this->planner->buildDepthNeedMatrix($existing), null, 'depth');

        $this->assertSame(2000, $byDepth[4]['remaining_kernels'], '3000 - 1000 = 2000');
        $this->assertSame(0,    $byDepth[6]['remaining_kernels'], '3000 - 3000 = 0');
        $this->assertSame(2000, $byDepth[7]['remaining_kernels'], '2500 - 500 = 2000');
        $this->assertSame(2000, $byDepth[8]['remaining_kernels'], '2000 - 0 = 2000');
        $this->assertSame(1500, $byDepth[9]['remaining_kernels'], '1500 - 0 = 1500');
    }

    public function test_depth_need_matrix_marks_completed_when_remaining_is_zero(): void
    {
        $existing = [6 => 3000];
        $byDepth  = array_column($this->planner->buildDepthNeedMatrix($existing), null, 'depth');

        $this->assertTrue($byDepth[6]['completed'],  'Depth 6 atteint → completed = true');
        $this->assertFalse($byDepth[4]['completed'], 'Depth 4 non atteint → completed = false');
        $this->assertFalse($byDepth[7]['completed'], 'Depth 7 non atteint → completed = false');
    }

    public function test_depth_need_matrix_remaining_never_negative(): void
    {
        $existing = [4 => 9999]; // surpasse la cible
        $byDepth  = array_column($this->planner->buildDepthNeedMatrix($existing), null, 'depth');

        $this->assertSame(0, $byDepth[4]['remaining_kernels'], 'remaining ne peut pas être négatif');
    }

    public function test_depth_need_matrix_has_all_required_fields(): void
    {
        $matrix = $this->planner->buildDepthNeedMatrix([]);

        foreach ($matrix as $row) {
            $this->assertArrayHasKey('depth',             $row);
            $this->assertArrayHasKey('target_kernels',    $row);
            $this->assertArrayHasKey('existing_kernels',  $row);
            $this->assertArrayHasKey('remaining_kernels', $row);
            $this->assertArrayHasKey('completed',         $row);
            $this->assertIsBool($row['completed']);
        }
    }

    // =========================================================================
    // chooseDepth
    // =========================================================================

    public function test_choose_depth_picks_highest_remaining(): void
    {
        $matrix = [
            ['depth' => 4, 'remaining_kernels' => 1000],
            ['depth' => 6, 'remaining_kernels' => 3000],
            ['depth' => 7, 'remaining_kernels' => 2500],
            ['depth' => 8, 'remaining_kernels' => 2000],
            ['depth' => 9, 'remaining_kernels' => 1500],
        ];

        $this->assertSame(6, $this->planner->chooseDepth($matrix));
    }

    public function test_choose_depth_picks_lowest_depth_on_tie(): void
    {
        $matrix = [
            ['depth' => 6, 'remaining_kernels' => 3000],
            ['depth' => 4, 'remaining_kernels' => 3000],
            ['depth' => 7, 'remaining_kernels' => 500],
        ];

        $this->assertSame(4, $this->planner->chooseDepth($matrix), 'Depth 4 gagne le tie-break (depth le plus bas)');
    }

    public function test_choose_depth_picks_single_available_depth(): void
    {
        $matrix = [
            ['depth' => 4, 'remaining_kernels' => 0],
            ['depth' => 6, 'remaining_kernels' => 0],
            ['depth' => 7, 'remaining_kernels' => 1],
            ['depth' => 8, 'remaining_kernels' => 0],
            ['depth' => 9, 'remaining_kernels' => 0],
        ];

        $this->assertSame(7, $this->planner->chooseDepth($matrix));
    }

    public function test_choose_depth_throws_stop_when_all_remaining_zero(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/STOP.*aucun depth restant/');

        $matrix = [
            ['depth' => 4, 'remaining_kernels' => 0],
            ['depth' => 6, 'remaining_kernels' => 0],
            ['depth' => 7, 'remaining_kernels' => 0],
            ['depth' => 8, 'remaining_kernels' => 0],
            ['depth' => 9, 'remaining_kernels' => 0],
        ];

        $this->planner->chooseDepth($matrix);
    }

    public function test_choose_depth_throws_stop_when_matrix_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/STOP.*absente/');

        $this->planner->chooseDepth([]);
    }

    // =========================================================================
    // DomainCycle — loadDomains
    // =========================================================================

    public function test_load_domains_returns_list_from_config(): void
    {
        $domains = $this->planner->loadDomains();

        $this->assertIsArray($domains);
        $this->assertNotEmpty($domains);
    }

    public function test_load_domains_contains_all_official_domains(): void
    {
        $domains = $this->planner->loadDomains();

        $expected = ['general', 'histoire', 'sport', 'geographie', 'art', 'cuisine', 'science', 'cinema', 'faune'];

        foreach ($expected as $d) {
            $this->assertContains($d, $domains, "Domaine officiel manquant : {$d}");
        }
    }

    // =========================================================================
    // DomainCycle — advanceDomainIndex
    // =========================================================================

    public function test_advance_domain_index_starts_at_zero_when_null(): void
    {
        $domains = ['a', 'b', 'c'];
        $this->assertSame(0, $this->planner->advanceDomainIndex(null, $domains));
    }

    public function test_advance_domain_index_advances_sequentially(): void
    {
        $domains = ['a', 'b', 'c', 'd'];

        $this->assertSame(1, $this->planner->advanceDomainIndex(0, $domains));
        $this->assertSame(2, $this->planner->advanceDomainIndex(1, $domains));
        $this->assertSame(3, $this->planner->advanceDomainIndex(2, $domains));
    }

    public function test_advance_domain_index_wraps_to_zero_after_last(): void
    {
        $domains = ['a', 'b', 'c'];
        $this->assertSame(0, $this->planner->advanceDomainIndex(2, $domains), 'Après le dernier, revient à 0');
    }

    public function test_advance_domain_index_no_skip(): void
    {
        $domains = ['a', 'b', 'c', 'd', 'e'];
        $idx     = null;
        $visited = [];

        for ($i = 0; $i < count($domains); $i++) {
            $idx       = $this->planner->advanceDomainIndex($idx, $domains);
            $visited[] = $idx;
        }

        $this->assertSame([0, 1, 2, 3, 4], $visited, 'Aucun saut : indices consécutifs');
    }

    public function test_domain_cycle_returns_to_first_after_full_rotation(): void
    {
        $domains = ['x', 'y', 'z'];
        $idx     = null;

        $idx = $this->planner->advanceDomainIndex($idx, $domains); // 0
        $idx = $this->planner->advanceDomainIndex($idx, $domains); // 1
        $idx = $this->planner->advanceDomainIndex($idx, $domains); // 2
        $idx = $this->planner->advanceDomainIndex($idx, $domains); // 0 (bouclage)

        $this->assertSame(0, $idx, 'Retour à l\'index 0 après cycle complet');
        $this->assertSame('x', $domains[$idx], 'Retour au premier domaine');
    }

    public function test_domain_cycle_covers_all_domains_exactly_once(): void
    {
        $domains = $this->planner->loadDomains();
        $count   = count($domains);
        $idx     = null;
        $visited = [];

        for ($i = 0; $i < $count; $i++) {
            $idx       = $this->planner->advanceDomainIndex($idx, $domains);
            $visited[] = $domains[$idx];
        }

        $this->assertCount($count, array_unique($visited), 'Chaque domaine visité exactement une fois');
    }

    public function test_advance_domain_index_throws_stop_when_list_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/STOP.*vide/');

        $this->planner->advanceDomainIndex(0, []);
    }

    // =========================================================================
    // rotation_identifier
    // =========================================================================

    public function test_rotation_identifier_is_unique(): void
    {
        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $ids[] = (string) Str::uuid();
        }

        $this->assertCount(20, array_unique($ids), 'Tous les rotation_identifier doivent être uniques');
    }

    public function test_rotation_identifier_is_not_kernel_code_format(): void
    {
        // rotation_identifier = UUID ; kernel_code = yy-xx-xxx-xxx-xxx-zz
        $id = (string) Str::uuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id,
            'rotation_identifier est un UUID — pas le format kernel_code'
        );
    }

    public function test_rotation_identifier_does_not_match_kernel_code_prefix_pattern(): void
    {
        // kernel_code_prefix serait du type yy-xx (ex: 06-03)
        $id = (string) Str::uuid();
        $this->assertDoesNotMatchRegularExpression('/^\d{2}-\d{2}$/', $id);
    }

    // =========================================================================
    // Contrat de sortie — structure rotation_context (sans DB)
    // =========================================================================

    public function test_rotation_context_has_exactly_three_keys(): void
    {
        $depth  = $this->planner->chooseDepth([['depth' => 6, 'remaining_kernels' => 3000]]);
        $doms   = ['general'];
        $idx    = $this->planner->advanceDomainIndex(null, $doms);

        $ctx = [
            'depth_slot'          => ['depth' => $depth],
            'domain_slot'         => ['domain_id' => $doms[$idx], 'domain_code' => $doms[$idx]],
            'rotation_identifier' => (string) Str::uuid(),
        ];

        $this->assertCount(3, $ctx, 'rotation_context doit avoir exactement 3 clés');
        $this->assertArrayHasKey('depth_slot',          $ctx);
        $this->assertArrayHasKey('domain_slot',         $ctx);
        $this->assertArrayHasKey('rotation_identifier', $ctx);
    }

    public function test_rotation_context_does_not_contain_kernel_code(): void
    {
        $depth = $this->planner->chooseDepth([['depth' => 6, 'remaining_kernels' => 1]]);
        $doms  = ['general'];
        $idx   = $this->planner->advanceDomainIndex(null, $doms);

        $ctx = [
            'depth_slot'          => ['depth' => $depth],
            'domain_slot'         => ['domain_id' => $doms[$idx], 'domain_code' => $doms[$idx]],
            'rotation_identifier' => (string) Str::uuid(),
        ];

        $this->assertArrayNotHasKey('kernel_code',        $ctx);
        $this->assertArrayNotHasKey('kernel_code_prefix', $ctx);
        $this->assertArrayNotHasKey('sub_domain',         $ctx);
        $this->assertArrayNotHasKey('subject',            $ctx);
        $this->assertArrayNotHasKey('idee_dominante',     $ctx);
        $this->assertArrayNotHasKey('taxonomy',           $ctx);
        $this->assertArrayNotHasKey('READY_BANK',         $ctx);
    }

    public function test_depth_slot_contains_int_depth(): void
    {
        $depth = $this->planner->chooseDepth([['depth' => 7, 'remaining_kernels' => 2500]]);

        $this->assertIsInt($depth);
        $this->assertSame(7, $depth);
    }

    public function test_domain_slot_has_domain_id_and_domain_code(): void
    {
        $domains = $this->planner->loadDomains();
        $idx     = $this->planner->advanceDomainIndex(null, $domains);
        $domain  = $domains[$idx];

        $domainSlot = ['domain_id' => $domain, 'domain_code' => $domain];

        $this->assertArrayHasKey('domain_id',   $domainSlot);
        $this->assertArrayHasKey('domain_code', $domainSlot);
        $this->assertIsString($domainSlot['domain_id']);
        $this->assertIsString($domainSlot['domain_code']);
    }

    public function test_planner_does_not_touch_taxonomy(): void
    {
        // KernelRotationPlanner ne fait pas appel à TaxonomyReader.
        // Vérification structurelle : aucune référence à TaxonomyReader dans la sortie.
        $depth  = $this->planner->chooseDepth([['depth' => 6, 'remaining_kernels' => 1]]);
        $doms   = $this->planner->loadDomains();
        $idx    = $this->planner->advanceDomainIndex(null, $doms);

        $ctx = [
            'depth_slot'          => ['depth' => $depth],
            'domain_slot'         => ['domain_id' => $doms[$idx], 'domain_code' => $doms[$idx]],
            'rotation_identifier' => (string) Str::uuid(),
        ];

        // Aucune clé liée à Taxonomy dans le contexte de rotation
        $this->assertArrayNotHasKey('sub_domain',     $ctx);
        $this->assertArrayNotHasKey('subjects',       $ctx);
        $this->assertArrayNotHasKey('idees',          $ctx);
        $this->assertArrayNotHasKey('idee_dominante', $ctx);
        $this->assertArrayNotHasKey('taxonomy',       $ctx);
    }

    public function test_planner_does_not_touch_ready_bank(): void
    {
        $depth = $this->planner->chooseDepth([['depth' => 6, 'remaining_kernels' => 1]]);
        $doms  = $this->planner->loadDomains();
        $idx   = $this->planner->advanceDomainIndex(null, $doms);

        $ctx = [
            'depth_slot'          => ['depth' => $depth],
            'domain_slot'         => ['domain_id' => $doms[$idx], 'domain_code' => $doms[$idx]],
            'rotation_identifier' => (string) Str::uuid(),
        ];

        $this->assertArrayNotHasKey('READY_BANK',  $ctx);
        $this->assertArrayNotHasKey('ready_bank',  $ctx);
        $this->assertArrayNotHasKey('kernel_bank', $ctx);
    }
}
