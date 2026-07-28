<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\DepthTourState;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests unitaires pour DepthTourState.
 *
 * Aucune base de données — classe pure immuable.
 */
class DepthTourStateTest extends TestCase
{
    // =========================================================================
    // initTour
    // =========================================================================

    public function test_init_tour_creates_8_on_domains(): void
    {
        $tour = DepthTourState::initTour();

        $this->assertCount(8, $tour->getOnDomains());
        $this->assertSame(0, $tour->getEmptyProgress());
        $this->assertFalse($tour->isTourComplete());
    }

    public function test_init_tour_all_official_domains_are_on(): void
    {
        $tour    = DepthTourState::initTour();
        $domains = DepthTourState::DOMAIN_CYCLE;

        foreach ($domains as $domain) {
            $this->assertTrue($tour->isOn($domain), "Domaine {$domain} doit être ON à l'initialisation");
        }
    }

    public function test_init_tour_contains_exactly_the_8_official_domains(): void
    {
        $expected = ['geographie', 'histoire', 'faune', 'art', 'sport', 'cinema', 'cuisine', 'science'];

        $this->assertSame($expected, DepthTourState::DOMAIN_CYCLE);
    }

    public function test_init_tour_excludes_general(): void
    {
        $this->assertNotContains('general', DepthTourState::DOMAIN_CYCLE);
    }

    // =========================================================================
    // applyEmpty — immutabilité
    // =========================================================================

    public function test_apply_empty_returns_new_instance(): void
    {
        $tour    = DepthTourState::initTour();
        $newTour = $tour->applyEmpty('geographie');

        $this->assertNotSame($tour, $newTour, 'applyEmpty doit retourner une nouvelle instance');
    }

    public function test_apply_empty_does_not_mutate_original(): void
    {
        $tour = DepthTourState::initTour();
        $tour->applyEmpty('geographie');

        $this->assertTrue($tour->isOn('geographie'), 'L\'instance originale est immuable');
        $this->assertSame(0, $tour->getEmptyProgress());
    }

    // =========================================================================
    // applyEmpty — transition ON → OFF
    // =========================================================================

    public function test_apply_empty_passes_domain_to_off(): void
    {
        $tour    = DepthTourState::initTour();
        $newTour = $tour->applyEmpty('histoire');

        $this->assertTrue($newTour->isOff('histoire'), 'histoire doit être OFF après EMPTY');
    }

    public function test_apply_empty_increments_progress(): void
    {
        $tour = DepthTourState::initTour()
            ->applyEmpty('geographie')
            ->applyEmpty('histoire');

        $this->assertSame(2, $tour->getEmptyProgress());
    }

    public function test_apply_empty_idempotent_on_off_domain(): void
    {
        $tour      = DepthTourState::initTour()->applyEmpty('art');
        $tourAgain = $tour->applyEmpty('art'); // déjà OFF

        $this->assertSame($tour, $tourAgain, 'NO-OP si Domaine déjà OFF');
        $this->assertSame(1, $tourAgain->getEmptyProgress(), 'Pas de double incrément');
    }

    public function test_apply_empty_throws_on_unknown_domain(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Domaine inconnu/');

        DepthTourState::initTour()->applyEmpty('unknown_domain');
    }

    // =========================================================================
    // isTourComplete
    // =========================================================================

    public function test_tour_complete_when_all_8_empty(): void
    {
        $tour = DepthTourState::initTour();

        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $tour = $tour->applyEmpty($domain);
        }

        $this->assertTrue($tour->isTourComplete(), 'Tour complet à 8/8');
        $this->assertSame(8, $tour->getEmptyProgress());
    }

    public function test_tour_not_complete_before_all_8(): void
    {
        $tour = DepthTourState::initTour();

        foreach (array_slice(DepthTourState::DOMAIN_CYCLE, 0, 7) as $domain) {
            $tour = $tour->applyEmpty($domain);
        }

        $this->assertFalse($tour->isTourComplete(), 'Pas encore complet à 7/8');
        $this->assertSame(7, $tour->getEmptyProgress());
    }

    // =========================================================================
    // getOnDomains
    // =========================================================================

    public function test_get_on_domains_reflects_transitions(): void
    {
        $tour = DepthTourState::initTour()
            ->applyEmpty('geographie')
            ->applyEmpty('histoire');

        $on = $tour->getOnDomains();

        $this->assertNotContains('geographie', $on);
        $this->assertNotContains('histoire',   $on);
        $this->assertCount(6, $on);
    }

    public function test_get_on_domains_maintains_domain_cycle_order(): void
    {
        $tour       = DepthTourState::initTour()->applyEmpty('faune')->applyEmpty('art');
        $on         = $tour->getOnDomains();
        $remaining  = ['geographie', 'histoire', 'sport', 'cinema', 'cuisine', 'science'];

        $this->assertSame($remaining, $on, 'Ordre DomainCycle respecté');
    }

    // =========================================================================
    // getNextOnDomain
    // =========================================================================

    public function test_get_next_on_domain_null_returns_first_on(): void
    {
        $tour = DepthTourState::initTour();

        $this->assertSame('geographie', $tour->getNextOnDomain(null));
    }

    public function test_get_next_on_domain_advances_sequentially(): void
    {
        $tour = DepthTourState::initTour();

        $this->assertSame('histoire', $tour->getNextOnDomain('geographie'));
        $this->assertSame('faune',    $tour->getNextOnDomain('histoire'));
        $this->assertSame('art',      $tour->getNextOnDomain('faune'));
    }

    public function test_get_next_on_domain_wraps_after_last(): void
    {
        $tour = DepthTourState::initTour();

        $this->assertSame('geographie', $tour->getNextOnDomain('science'));
    }

    public function test_get_next_on_domain_skips_off_domains(): void
    {
        $tour = DepthTourState::initTour()
            ->applyEmpty('histoire')
            ->applyEmpty('faune');

        // Après geographie (ON), le suivant ON est art (histoire et faune sont OFF)
        $this->assertSame('art', $tour->getNextOnDomain('geographie'));
    }

    public function test_get_next_on_domain_returns_null_when_all_off(): void
    {
        $tour = DepthTourState::initTour();

        foreach (DepthTourState::DOMAIN_CYCLE as $domain) {
            $tour = $tour->applyEmpty($domain);
        }

        $this->assertNull($tour->getNextOnDomain(null));
        $this->assertNull($tour->getNextOnDomain('geographie'));
    }

    public function test_get_next_on_domain_unknown_previous_returns_first_on(): void
    {
        $tour = DepthTourState::initTour();

        // previousDomain inconnu → retourne le premier ON
        $next = $tour->getNextOnDomain('inconnu_xyz');
        $this->assertSame('geographie', $next);
    }

    // =========================================================================
    // fromArray / toArray — persistance aller-retour
    // =========================================================================

    public function test_to_array_from_array_round_trip(): void
    {
        $original = DepthTourState::initTour()
            ->applyEmpty('sport')
            ->applyEmpty('cinema');

        $data     = $original->toArray();
        $restored = DepthTourState::fromArray($data);

        $this->assertSame($original->getEmptyProgress(), $restored->getEmptyProgress());
        $this->assertSame($original->getOnDomains(), $restored->getOnDomains());
        $this->assertSame($original->isTourComplete(), $restored->isTourComplete());
    }

    public function test_from_array_restores_off_domains(): void
    {
        $original = DepthTourState::initTour()->applyEmpty('cuisine');
        $restored = DepthTourState::fromArray($original->toArray());

        $this->assertTrue($restored->isOff('cuisine'), 'cuisine doit rester OFF après restauration');
        $this->assertTrue($restored->isOn('geographie'), 'geographie doit rester ON');
    }
}
