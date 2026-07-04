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
 * DB : SQLite in-memory (Tests\TestCase).
 * PAS de RefreshDatabase : la migration 2026_03_15_100004_fix_bot_qualification_events_constraint
 * utilise ADD CONSTRAINT ... CHECK, syntaxe incompatible SQLite.
 * → La table taxonomy_progress est créée manuellement dans setUp() et détruite dans tearDown().
 * Aucun accès Neon / production.
 *
 * TaxonomyReader lit taxonomy.json réel via base_path() (source de vérité, pas de DB).
 *
 * Domaine de référence : 'science' → Science → Sciences
 *   Sujets : ADN, Trou_noir, Radioactivité, Photosynthèse (4 sujets × 5 idées = 20 paires)
 *   ADN ideas : double_hélice, Watson, Franklin, génome, transcription
 *
 * Domaine secondaire : 'Général' (clé directe, 4 sous-domaines)
 *   Utilisé pour tester la transition de sous-domaine (tests 5 et 6).
 */
class TaxonomyProgressManagerTest extends TestCase
{
    private TaxonomyProgressManager $manager;

    // Paire attendue pour 'science' → Sciences → ADN → idée[0]
    private const SCIENCE_FIRST_PAIR = [
        'sub_domain'          => 'Sciences',
        'subject'             => 'ADN',
        'dominant_idea'       => 'double_hélice',
        'knowledge_frequency' => 7,
    ];

    // Idées dominantes de ADN dans l'ordre de taxonomy.json
    private const ADN_IDEAS = ['double_hélice', 'Watson', 'Franklin', 'génome', 'transcription'];

    // =========================================================================
    // Lifecycle
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        // Créer uniquement la table nécessaire (pas de migrate --all)
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
        $pair = $this->manager->peekNext(4, 'science');

        $this->assertNotNull($pair, 'peekNext doit retourner une paire sur un bassin vide');
        $this->assertSame(4,                                            $pair['depth']);
        $this->assertSame('science',                                    $pair['domain']);
        $this->assertSame(self::SCIENCE_FIRST_PAIR['sub_domain'],      $pair['sub_domain']);
        $this->assertSame(self::SCIENCE_FIRST_PAIR['subject'],         $pair['subject']);
        $this->assertSame(self::SCIENCE_FIRST_PAIR['dominant_idea'],   $pair['dominant_idea']);
        $this->assertSame(self::SCIENCE_FIRST_PAIR['knowledge_frequency'], $pair['knowledge_frequency']);

        // La ligne de progression doit exister en DB
        $status = $this->manager->getStatus(4, 'science');
        $this->assertNotNull($status);
        $this->assertSame('Sciences', $status['active_sub_domain']);
        $this->assertSame('ADN',      $status['active_subject']);
        $this->assertSame(0,          $status['dominant_idea_index']);
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
        $this->assertSame($first['dominant_idea'], $second['dominant_idea'],
            'Double peekNext sans confirmConsumed doit retourner la même idée dominante');
        $this->assertSame($first['dominant_idea'], $third['dominant_idea'],
            'Triple peekNext sans confirmConsumed doit retourner la même idée dominante');
        $this->assertSame($first['subject'],    $second['subject']);
        $this->assertSame($first['sub_domain'], $second['sub_domain']);

        // dominant_idea_index doit rester à 0
        $status = $this->manager->getStatus(4, 'science');
        $this->assertSame(0, $status['dominant_idea_index'],
            'dominant_idea_index ne doit pas bouger sans confirmConsumed');
    }

    // =========================================================================
    // Test 3 — confirmConsumed avance l'index d'un seul cran
    // =========================================================================

    public function test_confirm_consumed_advances_idea_index_by_one(): void
    {
        // Initialise + avance 1 cran
        $this->manager->peekNext(4, 'science');
        $this->manager->confirmConsumed(4, 'science');

        $status = $this->manager->getStatus(4, 'science');
        $this->assertSame(1, $status['dominant_idea_index'],
            'dominant_idea_index doit passer de 0 à 1 après un confirmConsumed');
        $this->assertSame('ADN',      $status['active_subject'],    'Le sujet ne doit pas changer');
        $this->assertSame('Sciences', $status['active_sub_domain'], 'Le sous-domaine ne doit pas changer');

        // peekNext retourne maintenant la 2e idée (index 1)
        $pair = $this->manager->peekNext(4, 'science');
        $this->assertSame(self::ADN_IDEAS[1], $pair['dominant_idea'],
            'La 2e idée dominante de ADN doit être Watson');

        // Encore un cran
        $this->manager->confirmConsumed(4, 'science');
        $status2 = $this->manager->getStatus(4, 'science');
        $this->assertSame(2, $status2['dominant_idea_index']);

        $pair2 = $this->manager->peekNext(4, 'science');
        $this->assertSame(self::ADN_IDEAS[2], $pair2['dominant_idea']);
    }

    // =========================================================================
    // Test 4 — Changement de sujet après épuisement des 5 idées du sujet actif
    // =========================================================================

    public function test_confirm_consumed_changes_subject_after_all_ideas_exhausted(): void
    {
        $this->manager->peekNext(4, 'science');

        // 5 confirmConsumed épuisent les idées de ADN → transition T2 vers Trou_noir
        for ($i = 0; $i < 5; $i++) {
            $this->manager->confirmConsumed(4, 'science');
        }

        $status = $this->manager->getStatus(4, 'science');
        $this->assertSame('Trou_noir', $status['active_subject'],
            'Après 5 confirmConsumed, le sujet actif doit passer de ADN à Trou_noir');
        $this->assertSame(0, $status['dominant_idea_index'],
            'dominant_idea_index doit être remis à 0 sur le nouveau sujet');
        $this->assertSame('Sciences', $status['active_sub_domain'],
            'Le sous-domaine ne doit pas changer lors du changement de sujet');

        $pair = $this->manager->peekNext(4, 'science');
        $this->assertSame('Trou_noir',   $pair['subject']);
        $this->assertSame('singularité', $pair['dominant_idea'],
            'La première idée de Trou_noir doit être singularité');
    }

    // =========================================================================
    // Test 5 — Changement de sous-domaine après épuisement de tous les sujets
    //           Utilise 'Général' (4 sous-domaines : Sciences > Technologies > …)
    // =========================================================================

    public function test_confirm_consumed_changes_subdomain_after_all_subjects_exhausted(): void
    {
        // 'Général' a 4 sous-domaines. Sciences = premier sous-domaine.
        // Sciences → 4 sujets × 5 idées = 20 paires pour épuiser Sciences.
        $this->manager->peekNext(4, 'Général');

        for ($i = 0; $i < 20; $i++) {
            $this->manager->confirmConsumed(4, 'Général');
        }

        $status = $this->manager->getStatus(4, 'Général');
        $this->assertSame('active', $status['status'],
            'Après épuisement de Sciences, le bassin doit rester actif (Technologies disponible)');
        $this->assertNotSame('Sciences', $status['active_sub_domain'],
            'Le sous-domaine actif ne doit plus être Sciences');
        $this->assertNotNull($status['active_sub_domain'],
            'Un nouveau sous-domaine doit être actif');
        $this->assertSame(0, $status['dominant_idea_index'],
            'dominant_idea_index doit être remis à 0 sur le nouveau sous-domaine');

        $pair = $this->manager->peekNext(4, 'Général');
        $this->assertNotNull($pair);
        $this->assertNotSame('Sciences', $pair['sub_domain'],
            'La paire retournée ne doit pas appartenir à Sciences (épuisé)');
    }

    // =========================================================================
    // Test 6 — Le sous-domaine épuisé est ajouté dans used_sub_domains
    // =========================================================================

    public function test_exhausted_subdomain_added_to_used_list(): void
    {
        // Épuiser Sciences dans 'Général' (20 paires)
        $this->manager->peekNext(4, 'Général');

        for ($i = 0; $i < 20; $i++) {
            $this->manager->confirmConsumed(4, 'Général');
        }

        $status = $this->manager->getStatus(4, 'Général');
        $this->assertContains('Sciences', $status['used_sub_domains'],
            'Sciences doit être dans used_sub_domains après épuisement complet');
        $this->assertNotContains('Technologies', $status['used_sub_domains'],
            'Technologies ne doit pas être dans used_sub_domains (encore actif ou non démarré)');
    }

    // =========================================================================
    // Test 7 — status = exhausted quand tous les sous-domaines sont épuisés
    // =========================================================================

    public function test_status_becomes_exhausted_when_all_subdomains_done(): void
    {
        // 'science' n'a qu'un seul sous-domaine (Sciences) → 20 paires → exhausted
        $this->manager->peekNext(4, 'science');

        for ($i = 0; $i < 20; $i++) {
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
    // Test 8 — peekNext retourne null quand le bassin est exhausted
    // =========================================================================

    public function test_peek_returns_null_when_exhausted(): void
    {
        $this->manager->peekNext(4, 'science');

        for ($i = 0; $i < 20; $i++) {
            $this->manager->confirmConsumed(4, 'science');
        }

        $pair = $this->manager->peekNext(4, 'science');
        $this->assertNull($pair,
            'peekNext doit retourner null quand le bassin est exhausted');

        // Deuxième appel aussi null
        $this->assertNull($this->manager->peekNext(4, 'science'));
    }

    // =========================================================================
    // Test 9 — science n'utilise jamais les sous-domaines de Général
    // =========================================================================

    public function test_science_never_uses_general_subdomains(): void
    {
        $this->manager->peekNext(4, 'science');

        $seenSubDomains = [];
        $seenSubjects   = [];

        // Traverser toutes les 20 paires du bassin science
        for ($i = 0; $i < 20; $i++) {
            $pair = $this->manager->peekNext(4, 'science');
            $this->assertNotNull($pair, "La paire #{$i} ne doit pas être null");

            $seenSubDomains[] = $pair['sub_domain'];
            $seenSubjects[]   = $pair['subject'];

            $this->manager->confirmConsumed(4, 'science');
        }

        $uniqueSubDomains = array_values(array_unique($seenSubDomains));

        // Seul sous-domaine autorisé : Sciences (racine Science, PAS Général)
        $this->assertSame(['Sciences'], $uniqueSubDomains,
            'science ne doit retourner que le sous-domaine Sciences (jamais Technologies/Économie/Philosophie)');

        $this->assertNotContains('Technologies', $uniqueSubDomains);
        $this->assertNotContains('Économie',     $uniqueSubDomains);
        $this->assertNotContains('Philosophie',  $uniqueSubDomains);

        // Les 4 sujets attendus
        $uniqueSubjects = array_values(array_unique($seenSubjects));
        sort($uniqueSubjects);
        $this->assertSame(
            ['ADN', 'Photosynthèse', 'Radioactivité', 'Trou_noir'],
            $uniqueSubjects,
            'Seuls les 4 sujets de Science→Sciences doivent apparaître'
        );
    }

    // =========================================================================
    // Test 10 — Deux domaines différents ont des curseurs indépendants (même depth)
    // =========================================================================

    public function test_two_domains_have_independent_cursors(): void
    {
        $sciencePair  = $this->manager->peekNext(4, 'science');
        $histoirePair = $this->manager->peekNext(4, 'histoire');

        $this->assertNotNull($sciencePair,  'science doit retourner une paire');
        $this->assertNotNull($histoirePair, 'histoire doit retourner une paire');

        // Chaque domaine a sa propre ligne en DB
        $this->assertNotNull($this->manager->getStatus(4, 'science'));
        $this->assertNotNull($this->manager->getStatus(4, 'histoire'));

        // Avancer science n'affecte pas histoire
        $this->manager->confirmConsumed(4, 'science');

        $scienceStatus  = $this->manager->getStatus(4, 'science');
        $histoireStatus = $this->manager->getStatus(4, 'histoire');

        $this->assertSame(1, $scienceStatus['dominant_idea_index'],
            'science doit avoir avancé à index=1');
        $this->assertSame(0, $histoireStatus['dominant_idea_index'],
            'histoire doit rester à index=0 — indépendant de science');

        // Avancer histoire n'affecte pas science
        $this->manager->confirmConsumed(4, 'histoire');

        $scienceStatus2  = $this->manager->getStatus(4, 'science');
        $histoireStatus2 = $this->manager->getStatus(4, 'histoire');

        $this->assertSame(1, $scienceStatus2['dominant_idea_index'],
            'science ne doit pas avoir bougé après avancement de histoire');
        $this->assertSame(1, $histoireStatus2['dominant_idea_index'],
            'histoire doit avoir avancé à index=1');
    }

    // =========================================================================
    // Test 11 — Deux depths différents ont des curseurs indépendants (même domain)
    // =========================================================================

    public function test_two_depths_have_independent_cursors(): void
    {
        $pair4 = $this->manager->peekNext(4, 'science');
        $pair6 = $this->manager->peekNext(6, 'science');

        $this->assertNotNull($pair4, 'depth=4 doit retourner une paire');
        $this->assertNotNull($pair6, 'depth=6 doit retourner une paire');

        // Les deux démarrent à la même paire initiale (première idée de ADN)
        $this->assertSame($pair4['dominant_idea'], $pair6['dominant_idea'],
            'Les deux depths commencent à la même paire initiale');

        // Avancer depth=4 n'affecte pas depth=6
        $this->manager->confirmConsumed(4, 'science');

        $status4 = $this->manager->getStatus(4, 'science');
        $status6 = $this->manager->getStatus(6, 'science');

        $this->assertSame(1, $status4['dominant_idea_index'],
            'depth=4 doit avoir avancé à index=1');
        $this->assertSame(0, $status6['dominant_idea_index'],
            'depth=6 doit rester à index=0 — curseur indépendant');

        // Avancer depth=6 deux fois n'affecte pas depth=4
        $this->manager->confirmConsumed(6, 'science');
        $this->manager->confirmConsumed(6, 'science');

        $status4b = $this->manager->getStatus(4, 'science');
        $status6b = $this->manager->getStatus(6, 'science');

        $this->assertSame(1, $status4b['dominant_idea_index'],
            'depth=4 ne doit pas avoir bougé');
        $this->assertSame(2, $status6b['dominant_idea_index'],
            'depth=6 doit être à index=2');
    }

    // =========================================================================
    // Test 12 — Un rejet (pas de confirmConsumed) laisse le curseur inchangé
    // =========================================================================

    public function test_confirm_not_called_on_rejection_leaves_cursor_unchanged(): void
    {
        // Simulation : peekNext retourne la paire, KLD/KS rejette → confirmConsumed non appelé
        $pairBefore = $this->manager->peekNext(4, 'science');
        $this->assertNotNull($pairBefore);

        // Premier rejet — pas de confirmConsumed
        $pairAfterReject = $this->manager->peekNext(4, 'science');
        $this->assertSame(
            $pairBefore['dominant_idea'],
            $pairAfterReject['dominant_idea'],
            'Après un rejet, la même idée dominante doit être retournée'
        );
        $this->assertSame($pairBefore['subject'],    $pairAfterReject['subject']);
        $this->assertSame($pairBefore['sub_domain'], $pairAfterReject['sub_domain']);

        // Deuxième rejet — encore la même paire
        $pairAfterSecondReject = $this->manager->peekNext(4, 'science');
        $this->assertSame(
            $pairBefore['dominant_idea'],
            $pairAfterSecondReject['dominant_idea'],
            'Un double rejet doit toujours retourner la même paire'
        );

        // Maintenant on confirme → curseur avance
        $this->manager->confirmConsumed(4, 'science');
        $pairAfterConfirm = $this->manager->peekNext(4, 'science');

        $this->assertNotSame(
            $pairBefore['dominant_idea'],
            $pairAfterConfirm['dominant_idea'],
            'Après confirmConsumed, la paire doit changer'
        );
        $this->assertSame(self::ADN_IDEAS[1], $pairAfterConfirm['dominant_idea'],
            'Après un confirmConsumed, Watson (idée 2 de ADN) doit être retourné'
        );

        $status = $this->manager->getStatus(4, 'science');
        $this->assertSame(1, $status['dominant_idea_index']);
    }
}
