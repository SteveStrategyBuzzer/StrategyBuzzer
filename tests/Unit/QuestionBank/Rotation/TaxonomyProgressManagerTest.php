<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\TaxonomyProgressManager;
use App\Services\QuestionBank\Rotation\TaxonomyReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests unitaires pour TaxonomyProgressManager.
 *
 * CURSEUR SUJET : le curseur pointe sur le sujet actif uniquement.
 * dominant_idea_index est une colonne DB résiduelle (toujours 0) — plus un curseur métier.
 * 1 confirmConsumed() = 1 sujet entièrement consommé → avance au sujet suivant.
 *
 * DB : SQLite in-memory (Tests\TestCase).
 * PAS de RefreshDatabase : la migration 2026_03_15_100004 utilise ADD CONSTRAINT CHECK,
 * syntaxe incompatible SQLite.
 * → La table taxonomy_progress est créée manuellement dans setUp() et détruite dans tearDown().
 *
 * TaxonomyReader lit taxonomy.json réel via base_path() (source de vérité, pas de DB).
 *
 * Domaine de référence : 'science' → Science → Sciences
 *   Sujets : ADN, Trou_noir, Radioactivité, Photosynthèse (4 sujets)
 *   4 confirmConsumed pour épuiser le bassin science entier.
 *
 * Domaine secondaire : 'Général' (4 sous-domaines × 4 sujets = 16 sujets)
 *   Sciences → Technologies → Économie → Philosophie
 *   4 confirmConsumed pour épuiser le sous-domaine Sciences.
 */
class TaxonomyProgressManagerTest extends TestCase
{
    private TaxonomyProgressManager $manager;

    // Premier sujet attendu pour 'science'
    private const SCIENCE_FIRST = [
        'sub_domain'          => 'Sciences',
        'subject'             => 'ADN',
        'knowledge_frequency' => 7,
    ];

    // Sujets de Sciences dans l'ordre de taxonomy.json
    private const SCIENCES_SUBJECTS = ['ADN', 'Trou_noir', 'Radioactivité', 'Photosynthèse'];

    // =========================================================================
    // Lifecycle
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('taxonomy_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('active_sub_domain', 128)->nullable();
            $table->string('active_subject', 128)->nullable();
            $table->unsignedTinyInteger('dominant_idea_index')->default(0);
            $table->text('used_sub_domains')->default('[]');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['depth', 'domain_code']);
        });

        $this->manager = new TaxonomyProgressManager(new TaxonomyReader());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('taxonomy_progress');
        parent::tearDown();
    }

    // =========================================================================
    // Test 1 — Initialisation automatique au premier appel
    // =========================================================================

    public function test_peek_next_initialises_on_first_call(): void
    {
        $peek = $this->manager->peekNext(4, 'science');

        $this->assertNotNull($peek, 'peekNext doit retourner une entrée sur un bassin vide');
        $this->assertSame(4,                                              $peek['depth']);
        $this->assertSame('science',                                      $peek['domain']);
        $this->assertSame(self::SCIENCE_FIRST['sub_domain'],             $peek['sub_domain']);
        $this->assertSame(self::SCIENCE_FIRST['subject'],                $peek['subject']);
        $this->assertSame(self::SCIENCE_FIRST['knowledge_frequency'],    $peek['knowledge_frequency']);
        $this->assertArrayNotHasKey('dominant_idea', $peek,
            'dominant_idea ne doit plus exister dans peekNext — curseur sujet uniquement');

        $status = $this->manager->getStatus(4, 'science');
        $this->assertNotNull($status);
        $this->assertSame('Sciences', $status['active_sub_domain']);
        $this->assertSame('ADN',      $status['active_subject']);
        $this->assertSame('active',   $status['status']);
        $this->assertSame([],         $status['used_sub_domains']);
    }

    // =========================================================================
    // Test 2 — peekNext est idempotent sans confirmConsumed
    // =========================================================================

    public function test_peek_next_is_idempotent_without_confirm(): void
    {
        $first  = $this->manager->peekNext(4, 'science');
        $second = $this->manager->peekNext(4, 'science');
        $third  = $this->manager->peekNext(4, 'science');

        $this->assertNotNull($first);
        $this->assertSame($first['subject'],    $second['subject'],
            'Double peekNext sans confirmConsumed doit retourner le même sujet');
        $this->assertSame($first['subject'],    $third['subject'],
            'Triple peekNext sans confirmConsumed doit retourner le même sujet');
        $this->assertSame($first['sub_domain'], $second['sub_domain']);
        $this->assertSame($first['sub_domain'], $third['sub_domain']);
    }

    // =========================================================================
    // Test 3 — confirmConsumed avance d'UN SEUL SUJET (sémantique sujet)
    // =========================================================================

    public function test_confirm_consumed_advances_to_next_subject(): void
    {
        $this->manager->peekNext(4, 'science');
        $this->manager->confirmConsumed(4, 'science');

        $status = $this->manager->getStatus(4, 'science');
        $this->assertSame('Trou_noir', $status['active_subject'],
            'Après 1 confirmConsumed, le sujet actif doit passer de ADN à Trou_noir');
        $this->assertSame('Sciences', $status['active_sub_domain'],
            'Le sous-domaine ne doit pas changer lors du changement de sujet');

        $peek = $this->manager->peekNext(4, 'science');
        $this->assertSame('Trou_noir', $peek['subject']);
        $this->assertArrayNotHasKey('dominant_idea', $peek,
            'dominant_idea ne doit pas apparaître après avancement');

        // Deuxième confirm : Trou_noir → Radioactivité
        $this->manager->confirmConsumed(4, 'science');
        $peek2 = $this->manager->peekNext(4, 'science');
        $this->assertSame('Radioactivité', $peek2['subject'],
            'Après 2 confirmConsumed, le sujet doit être Radioactivité');
    }

    // =========================================================================
    // Test 4 — Changement de sous-domaine après épuisement de tous les sujets
    //           Utilise 'Général' (4 sous-domaines : Sciences > Technologies > …)
    // =========================================================================

    public function test_confirm_consumed_changes_subdomain_after_all_subjects_exhausted(): void
    {
        // 'Général' Sciences = 4 sujets → 4 confirmConsumed épuisent Sciences
        $this->manager->peekNext(4, 'Général');

        for ($i = 0; $i < 4; $i++) {
            $this->manager->confirmConsumed(4, 'Général');
        }

        $status = $this->manager->getStatus(4, 'Général');
        $this->assertSame('active', $status['status'],
            'Après épuisement de Sciences, le bassin doit rester actif (Technologies disponible)');
        $this->assertNotSame('Sciences', $status['active_sub_domain'],
            'Le sous-domaine actif ne doit plus être Sciences');
        $this->assertNotNull($status['active_sub_domain'],
            'Un nouveau sous-domaine doit être actif');
        $this->assertSame('Internet', $status['active_subject'],
            'Premier sujet du sous-domaine Technologies = Internet');

        $peek = $this->manager->peekNext(4, 'Général');
        $this->assertNotNull($peek);
        $this->assertNotSame('Sciences', $peek['sub_domain'],
            'La paire retournée ne doit pas appartenir à Sciences (épuisé)');
    }

    // =========================================================================
    // Test 5 — Le sous-domaine épuisé est ajouté dans used_sub_domains
    // =========================================================================

    public function test_exhausted_subdomain_added_to_used_list(): void
    {
        // Épuiser Sciences dans 'Général' (4 sujets)
        $this->manager->peekNext(4, 'Général');

        for ($i = 0; $i < 4; $i++) {
            $this->manager->confirmConsumed(4, 'Général');
        }

        $status = $this->manager->getStatus(4, 'Général');
        $this->assertContains('Sciences', $status['used_sub_domains'],
            'Sciences doit être dans used_sub_domains après épuisement complet');
        $this->assertNotContains('Technologies', $status['used_sub_domains'],
            'Technologies ne doit pas être dans used_sub_domains (encore actif)');
    }

    // =========================================================================
    // Test 6 — status = exhausted quand tous les sous-domaines sont épuisés
    // =========================================================================

    public function test_status_becomes_exhausted_when_all_subdomains_done(): void
    {
        // 'science' n'a qu'un seul sous-domaine (Sciences) → 4 sujets → exhausted
        $this->manager->peekNext(4, 'science');

        for ($i = 0; $i < 4; $i++) {
            $this->manager->confirmConsumed(4, 'science');
        }

        $this->assertTrue(
            $this->manager->isExhausted(4, 'science'),
            'isExhausted doit retourner true après épuisement complet du bassin science'
        );

        $status = $this->manager->getStatus(4, 'science');
        $this->assertSame('exhausted', $status['status']);
        $this->assertNull($status['active_sub_domain']);
        $this->assertNull($status['active_subject']);
        $this->assertContains('Sciences', $status['used_sub_domains']);
    }

    // =========================================================================
    // Test 7 — peekNext retourne null quand le bassin est exhausted
    // =========================================================================

    public function test_peek_returns_null_when_exhausted(): void
    {
        $this->manager->peekNext(4, 'science');

        for ($i = 0; $i < 4; $i++) {
            $this->manager->confirmConsumed(4, 'science');
        }

        $peek = $this->manager->peekNext(4, 'science');
        $this->assertNull($peek, 'peekNext doit retourner null quand le bassin est exhausted');
        $this->assertNull($this->manager->peekNext(4, 'science'),
            'Deuxième appel aussi null');
    }

    // =========================================================================
    // Test 8 — science n'utilise jamais les sous-domaines de Général
    // =========================================================================

    public function test_science_never_uses_general_subdomains(): void
    {
        $this->manager->peekNext(4, 'science');

        $seenSubDomains = [];
        $seenSubjects   = [];

        // Traverser les 4 sujets du bassin science
        for ($i = 0; $i < 4; $i++) {
            $peek = $this->manager->peekNext(4, 'science');
            $this->assertNotNull($peek, "Le sujet #{$i} ne doit pas être null");

            $seenSubDomains[] = $peek['sub_domain'];
            $seenSubjects[]   = $peek['subject'];

            $this->manager->confirmConsumed(4, 'science');
        }

        $uniqueSubDomains = array_values(array_unique($seenSubDomains));
        $this->assertSame(['Sciences'], $uniqueSubDomains,
            'science ne doit retourner que le sous-domaine Sciences (jamais Technologies/Économie/Philosophie)');

        $this->assertNotContains('Technologies', $uniqueSubDomains);
        $this->assertNotContains('Économie',     $uniqueSubDomains);
        $this->assertNotContains('Philosophie',  $uniqueSubDomains);

        sort($seenSubjects);
        $this->assertSame(
            ['ADN', 'Photosynthèse', 'Radioactivité', 'Trou_noir'],
            $seenSubjects,
            'Les 4 sujets de Science→Sciences doivent tous apparaître exactement une fois'
        );
    }

    // =========================================================================
    // Test 9 — Deux domaines différents ont des curseurs indépendants (même depth)
    // =========================================================================

    public function test_two_domains_have_independent_cursors(): void
    {
        $sciencePeek  = $this->manager->peekNext(4, 'science');
        $histoirePeek = $this->manager->peekNext(4, 'histoire');

        $this->assertNotNull($sciencePeek,  'science doit retourner un sujet');
        $this->assertNotNull($histoirePeek, 'histoire doit retourner un sujet');

        $this->assertNotNull($this->manager->getStatus(4, 'science'));
        $this->assertNotNull($this->manager->getStatus(4, 'histoire'));

        // Avancer science n'affecte pas histoire
        $this->manager->confirmConsumed(4, 'science');

        $scienceStatus  = $this->manager->getStatus(4, 'science');
        $histoireStatus = $this->manager->getStatus(4, 'histoire');

        $this->assertSame('Trou_noir', $scienceStatus['active_subject'],
            'science doit avoir avancé au sujet suivant (Trou_noir)');
        $this->assertSame('Ottoman', $histoireStatus['active_subject'],
            'histoire doit rester sur Ottoman — curseur indépendant de science');

        // Avancer histoire n'affecte pas science
        $this->manager->confirmConsumed(4, 'histoire');

        $scienceStatus2  = $this->manager->getStatus(4, 'science');
        $histoireStatus2 = $this->manager->getStatus(4, 'histoire');

        $this->assertSame('Trou_noir', $scienceStatus2['active_subject'],
            'science ne doit pas avoir bougé après avancement de histoire');
        $this->assertSame('Mongol', $histoireStatus2['active_subject'],
            'histoire doit avoir avancé à Mongol');
    }

    // =========================================================================
    // Test 10 — Deux depths différents ont des curseurs indépendants (même domain)
    // =========================================================================

    public function test_two_depths_have_independent_cursors(): void
    {
        $peek4 = $this->manager->peekNext(4, 'science');
        $peek6 = $this->manager->peekNext(6, 'science');

        $this->assertNotNull($peek4, 'depth=4 doit retourner un sujet');
        $this->assertNotNull($peek6, 'depth=6 doit retourner un sujet');

        $this->assertSame($peek4['subject'], $peek6['subject'],
            'Les deux depths commencent au même premier sujet (ADN)');

        // Avancer depth=4 n'affecte pas depth=6
        $this->manager->confirmConsumed(4, 'science');

        $status4 = $this->manager->getStatus(4, 'science');
        $status6 = $this->manager->getStatus(6, 'science');

        $this->assertSame('Trou_noir', $status4['active_subject'],
            'depth=4 doit avoir avancé à Trou_noir');
        $this->assertSame('ADN', $status6['active_subject'],
            'depth=6 doit rester sur ADN — curseur indépendant');

        // Avancer depth=6 deux fois n'affecte pas depth=4
        $this->manager->confirmConsumed(6, 'science');
        $this->manager->confirmConsumed(6, 'science');

        $status4b = $this->manager->getStatus(4, 'science');
        $status6b = $this->manager->getStatus(6, 'science');

        $this->assertSame('Trou_noir', $status4b['active_subject'],
            'depth=4 ne doit pas avoir bougé');
        $this->assertSame('Radioactivité', $status6b['active_subject'],
            'depth=6 doit être sur Radioactivité (2 avances depuis ADN)');
    }

    // =========================================================================
    // Test 11 — Un rejet (pas de confirmConsumed) laisse le curseur sujet inchangé
    // =========================================================================

    public function test_confirm_not_called_on_rejection_leaves_cursor_unchanged(): void
    {
        $peekBefore = $this->manager->peekNext(4, 'science');
        $this->assertNotNull($peekBefore);
        $this->assertSame('ADN', $peekBefore['subject']);

        // Rejets multiples sans confirmConsumed (simulation FAIL KLD ou KEY_STRUCTURE)
        $peekAfterReject  = $this->manager->peekNext(4, 'science');
        $peekAfterReject2 = $this->manager->peekNext(4, 'science');

        $this->assertSame('ADN', $peekAfterReject['subject'],
            'Après un rejet, le même sujet doit être retourné');
        $this->assertSame('ADN', $peekAfterReject2['subject'],
            'Après un double rejet, toujours le même sujet');

        // Confirmation → sujet avance
        $this->manager->confirmConsumed(4, 'science');
        $peekAfterConfirm = $this->manager->peekNext(4, 'science');

        $this->assertSame('Trou_noir', $peekAfterConfirm['subject'],
            'Après confirmConsumed, le sujet doit avancer à Trou_noir');
    }

    // =========================================================================
    // Test 12 — Traversal complet : les 4 sujets apparaissent dans le bon ordre
    // =========================================================================

    public function test_subjects_are_traversed_in_order(): void
    {
        $this->manager->peekNext(4, 'science');

        $subjects = [];
        foreach (self::SCIENCES_SUBJECTS as $expected) {
            $peek = $this->manager->peekNext(4, 'science');
            $this->assertNotNull($peek, "Le sujet '{$expected}' ne doit pas être null");
            $subjects[] = $peek['subject'];
            $this->assertSame($expected, $peek['subject'],
                "Ordre de traversal incorrect : attendu={$expected}, obtenu={$peek['subject']}");
            $this->manager->confirmConsumed(4, 'science');
        }

        $this->assertSame(self::SCIENCES_SUBJECTS, $subjects,
            'Les 4 sujets de Sciences doivent être traversés dans leur ordre taxonomy.json');

        // Après le 4e sujet, bassin épuisé
        $this->assertNull($this->manager->peekNext(4, 'science'),
            'Après traversal complet, peekNext doit retourner null');
        $this->assertTrue($this->manager->isExhausted(4, 'science'));
    }
}
