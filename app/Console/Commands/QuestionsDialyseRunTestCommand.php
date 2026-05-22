<?php

namespace App\Console\Commands;

use App\Models\QuestionGroup;
use App\Models\QuestionIntent;
use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankAIGenerator;
use App\Services\QuestionBank\Worker\QualityGuards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Real end-to-end dialysis test on 10 specific noyaux.
 *
 * For each noyau this command:
 *   1. Records BEFORE state
 *   2. Auto-fills semantic_key + metadata in question_intents
 *   3. Generates the 4 missing variants via BankAIGenerator
 *   4. Runs QualityGuards on each generated payload
 *   5. Persists passing variants + links them to the intent
 *   6. Records AFTER state
 *   7. Exports exports/dialyse_10_noyaux_completed.md
 *
 * Usage:
 *   php artisan questions:bank:dialyse:run-test
 *   php artisan questions:bank:dialyse:run-test --dry-run
 */
class QuestionsDialyseRunTestCommand extends Command
{
    protected $signature = 'questions:bank:dialyse:run-test
                            {--dry-run : Simulate only — no DB writes, no AI calls}
                            {--ids=4,7,34,46,64,67,85,100,121,139 : Comma-separated intent IDs}
                            {--output-file= : Override default export path}';

    protected $description = 'Real end-to-end dialysis test on N noyaux — generates missing variants, validates, exports report';

    private const TARGET_VARIANTS = [
        ['question_type' => 'qcm',        'cognitive_type' => 'recognition'],
        ['question_type' => 'qcm',        'cognitive_type' => 'reasoning'],
        ['question_type' => 'qcm',        'cognitive_type' => 'deceptive_trap'],
        ['question_type' => 'true_false',  'cognitive_type' => 'recognition'],
        ['question_type' => 'true_false',  'cognitive_type' => 'reasoning'],
    ];

    private const DOMAIN_PREFIX = [
        'Histoire'      => 'HI',
        'Sport'         => 'SP',
        'Géographie'    => 'GE',
        'Cinéma'        => 'CI',
        'Cuisine'       => 'CU',
        'Science'       => 'SC',
        'Art'           => 'AR',
        'Faune'         => 'FA',
        'Musique'       => 'MU',
        'Technologie'   => 'TE',
        'Littérature'   => 'LI',
        'Géologie'      => 'GO',
    ];

    private const CTYPE_CODE = [
        'recognition'   => 'R',
        'reasoning'     => 'S',
        'deceptive_trap'=> 'D',
    ];

    private const QTYPE_CODE = [
        'qcm'        => 'Q',
        'true_false' => 'T',
    ];

    private const DEPTH_TO_LEVEL = [
        1 => 1,  2 => 1,  3 => 1,
        4 => 1,  5 => 11, 6 => 21,
        7 => 40, 8 => 41, 9 => 70,
        10 => 80,
    ];

    private const SV_TAUTOLOGY_KW = [
        "n'est pas","ne sont pas"," sauf "," excepté ","à l'exception","aucun de ces",
    ];

    private BankAIGenerator $generator;
    private QualityGuards   $guards;
    private QuestionBankRepository $repo;
    private bool $dryRun = false;

    /** Accumulated log for the markdown report */
    private array $noyauxReport = [];

    public function __construct(
        BankAIGenerator $generator,
        QualityGuards   $guards,
        QuestionBankRepository $repo
    ) {
        parent::__construct();
        $this->generator = $generator;
        $this->guards    = $guards;
        $this->repo      = $repo;
    }

    // =========================================================================
    // Main handle
    // =========================================================================

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $ids = array_map('intval', explode(',', $this->option('ids')));

        $mode = $this->dryRun ? '[DRY-RUN]' : '[LIVE]';
        $this->info("{$mode} Dialyse test — " . count($ids) . ' noyaux : ' . implode(', ', $ids));
        $this->line('');

        $intents = QuestionIntent::whereIn('id', $ids)->orderBy('id')->get();

        if ($intents->count() !== count($ids)) {
            $found = $intents->pluck('id')->toArray();
            $missing = array_diff($ids, $found);
            $this->warn('Noyaux introuvables : ' . implode(', ', $missing));
        }

        $globalStats = ['complete' => 0, 'incomplete' => 0, 'generated' => 0, 'rejected' => 0];
        $startTime   = microtime(true);

        foreach ($intents as $intent) {
            $result = $this->processNoyau($intent);
            $this->noyauxReport[] = $result;

            if ($result['after_complete']) {
                $globalStats['complete']++;
            } else {
                $globalStats['incomplete']++;
            }
            $globalStats['generated'] += $result['generated_count'];
            $globalStats['rejected']  += $result['rejected_count'];
        }

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->line('');
        $this->info("─────────────────────────────────────────────");
        $this->info("RÉSUMÉ GLOBAL");
        $this->info("  Complets    : {$globalStats['complete']}/" . count($intents));
        $this->info("  Incomplets  : {$globalStats['incomplete']}/" . count($intents));
        $this->info("  Générés     : {$globalStats['generated']} variantes");
        $this->info("  Refusés     : {$globalStats['rejected']} variantes");
        $this->info("  Durée       : {$elapsed}s");
        $this->info("─────────────────────────────────────────────");

        $this->exportMarkdown($globalStats, $elapsed);

        return 0;
    }

    // =========================================================================
    // Process one noyau
    // =========================================================================

    private function processNoyau(QuestionIntent $intent): array
    {
        $this->line("━━━ NOYAU #{$intent->id} · {$intent->domain} · depth {$intent->difficulty_depth} ━━━");

        $beforeGroups = QuestionGroup::where('question_intent_id', $intent->id)
            ->with('translations')
            ->get();

        $beforeVariants = $beforeGroups->map(fn ($g) => [
            'group_id'      => $g->id,
            'readable_code' => $g->readable_code,
            'question_type' => $g->question_type,
            'cognitive_type'=> $g->cognitive_type,
            'status'        => $g->post_review_status,
            'langs'         => $g->translations->pluck('language')->sort()->values()->toArray(),
            'sample_fr'     => $this->sampleFr($g),
        ])->toArray();

        $beforeIssues = $this->auditNoyau($intent, $beforeGroups);

        $this->comment("  AVANT : " . count($beforeVariants) . "/5 variantes · " . count($beforeIssues) . " problèmes");

        // ── Step 2 : fill metadata ────────────────────────────────────────────
        $metadataPatch = $this->buildMetadata($intent);
        $this->applyMetadata($intent, $metadataPatch);
        $this->comment("  Métadonnées : semantic_key={$metadataPatch['semantic_key']}");

        // ── Steps 3-5 : generate missing variants ────────────────────────────
        $presentVariants = $beforeGroups->map(fn ($g) => $g->question_type . '/' . $g->cognitive_type)->toArray();
        $missingVariants = array_filter(
            self::TARGET_VARIANTS,
            fn ($v) => !in_array($v['question_type'] . '/' . $v['cognitive_type'], $presentVariants, true)
        );

        $generatedVariants = [];
        $rejectedVariants  = [];

        foreach ($missingVariants as $variant) {
            $vtag = $variant['question_type'] . '/' . $variant['cognitive_type'];
            $this->output->write("    Génération {$vtag} … ");

            if ($this->dryRun) {
                $this->line('[dry-run — skipped]');
                $generatedVariants[] = [
                    'vtag'   => $vtag,
                    'status' => 'dry_run',
                    'group_id' => null,
                    'readable_code' => null,
                    'sample_fr' => null,
                    'guard_result' => null,
                ];
                continue;
            }

            $result = $this->generateVariant($intent, $variant);

            if ($result['ok']) {
                $this->info('✓  group_id=' . $result['group_id']);
                $generatedVariants[] = $result;
            } else {
                $this->warn('✗  ' . ($result['error'] ?? 'unknown'));
                $rejectedVariants[] = $result + ['vtag' => $vtag];
            }
        }

        // ── Step 6 : reload after state ──────────────────────────────────────
        $afterGroups = QuestionGroup::where('question_intent_id', $intent->id)
            ->with('translations')
            ->get();

        $afterVariants = $afterGroups->map(fn ($g) => [
            'group_id'       => $g->id,
            'readable_code'  => $g->readable_code,
            'question_type'  => $g->question_type,
            'cognitive_type' => $g->cognitive_type,
            'status'         => $g->post_review_status,
            'langs'          => $g->translations->pluck('language')->sort()->values()->toArray(),
            'sample_fr'      => $this->sampleFr($g),
        ])->toArray();

        $afterIssues  = $this->auditNoyau($intent->fresh(), $afterGroups);
        $afterComplete = count($afterVariants) === 5
            && collect($afterVariants)->every(fn ($v) => count($v['langs']) === 10)
            && empty(array_filter($afterIssues, fn ($i) => str_starts_with($i, '❌')));

        if ($afterComplete && !$this->dryRun) {
            $intent->update(['dialysis_status' => 'complete', 'dialysed_at' => now()]);
        }

        $status = $afterComplete ? '✅ COMPLETE' : '❌ INCOMPLETE';
        $this->info("  APRÈS  : " . count($afterVariants) . "/5 variantes · {$status}");

        return [
            'intent'            => $intent->toArray() + ['semantic_key' => $metadataPatch['semantic_key']],
            'before_variants'   => $beforeVariants,
            'before_issues'     => $beforeIssues,
            'metadata_patch'    => $metadataPatch,
            'generated_variants'=> $generatedVariants,
            'rejected_variants' => $rejectedVariants,
            'after_variants'    => $afterVariants,
            'after_issues'      => $afterIssues,
            'after_complete'    => $afterComplete,
            'generated_count'   => count($generatedVariants),
            'rejected_count'    => count($rejectedVariants),
        ];
    }

    // =========================================================================
    // Generate one variant
    // =========================================================================

    private function generateVariant(QuestionIntent $intent, array $variant): array
    {
        $depth = (int) $intent->difficulty_depth;
        $level = self::DEPTH_TO_LEVEL[$depth] ?? 1;

        $segment = [
            'domain'             => $intent->domain,
            'sub_domain'         => $intent->sub_domain ?: $intent->domain,
            'cognitive_type'     => $variant['cognitive_type'],
            'question_type'      => $variant['question_type'],
            'depth_range'        => [$depth, $depth],
            'mode_target'        => [
                'type'   => 'solo_range',
                'levels' => [$level, $level],
            ],
            'forbidden_concepts' => $this->forbiddenConcepts($intent->id),
            'forbidden_families' => $this->forbiddenFamilies($intent, $variant, $level),
            // P4 — noyau lock: steer the AI toward the exact noyau
            // (subject + angle + micro-angle + answer target) so it does
            // not drift to adjacent topics within the same broad domain.
            'concept_hint'       => $this->buildConceptHint($intent),
        ];

        // Generate via AI
        $genResult = $this->generator->generateForSegment($segment);
        if (!$genResult['ok']) {
            return [
                'ok'    => false,
                'vtag'  => $variant['question_type'] . '/' . $variant['cognitive_type'],
                'error' => $genResult['error'] ?? 'AI generation failed',
                'step'  => 'generate',
            ];
        }

        $payload = $genResult['payload'];

        // Quality guard
        $guardResult = $this->guards->evaluate($payload);
        if (!$guardResult['ok']) {
            return [
                'ok'           => false,
                'vtag'         => $variant['question_type'] . '/' . $variant['cognitive_type'],
                'error'        => 'QualityGuard rejected: ' . ($guardResult['code'] ?? '?') . ' — ' . ($guardResult['detail'] ?? ''),
                'guard_code'   => $guardResult['code']   ?? null,
                'guard_detail' => $guardResult['detail'] ?? null,
                'step'         => 'guard',
                'fr_question'  => $payload['translations']['fr']['question_text'] ?? null,
            ];
        }

        // Persist
        $group = $this->repo->addToBank($payload, false);
        if ($group === null) {
            return [
                'ok'    => false,
                'vtag'  => $variant['question_type'] . '/' . $variant['cognitive_type'],
                'error' => 'addToBank returned null (duplicate concept_id)',
                'step'  => 'persist',
            ];
        }

        // Link back to intent
        $readableCode = $this->buildReadableCode($intent, $variant);
        $group->update([
            'question_intent_id'  => $intent->id,
            'question_intent_key' => $intent->semantic_key ?: $intent->intent_key,
            'post_review_status'  => 'ready_bank',
            'readable_code'       => $readableCode,
        ]);

        $frTr = $payload['translations']['fr'] ?? [];

        return [
            'ok'           => true,
            'vtag'         => $variant['question_type'] . '/' . $variant['cognitive_type'],
            'group_id'     => $group->id,
            'readable_code'=> $readableCode,
            'guard_result' => $guardResult,
            'langs_count'  => count($payload['translations'] ?? []),
            'sample_fr'    => [
                'question'    => $frTr['question_text']     ?? '',
                'a'           => $frTr['answer_a']          ?? '',
                'b'           => $frTr['answer_b']          ?? '',
                'c'           => $frTr['answer_c']          ?? null,
                'd'           => $frTr['answer_d']          ?? null,
                'correct_key' => $frTr['correct_answer_key'] ?? '',
                'explanation' => $frTr['explanation']       ?? '',
                'saviez_vous' => $frTr['saviez_vous']       ?? '',
            ],
            'source'       => $payload['source'] ?? 'unknown',
        ];
    }

    // =========================================================================
    // Audit helpers
    // =========================================================================

    private function auditNoyau(QuestionIntent $intent, $groups): array
    {
        $issues = [];
        if (empty($intent->semantic_key)) {
            $issues[] = '⚠️  semantic_key non définie';
        }
        if (empty($intent->subject) || empty($intent->angle_large)) {
            $issues[] = '⚠️  subject / angle_large vides';
        }
        $presentCount = $groups->count();
        if ($presentCount < 5) {
            $issues[] = "❌ {$presentCount}/5 variantes présentes";
        }
        foreach ($groups as $g) {
            $fr = $g->translations->firstWhere('language', 'fr');
            if (!$fr) {
                $issues[] = "❌ groupe #{$g->id} : traduction FR manquante";
                continue;
            }
            // Tautology
            $correctText = ['A' => $fr->answer_a, 'B' => $fr->answer_b, 'C' => $fr->answer_c, 'D' => $fr->answer_d][$fr->correct_answer_key ?? 'A'] ?? '';
            if ($correctText && !empty($fr->saviez_vous) && stripos($fr->saviez_vous, $correctText) !== false) {
                $issues[] = "⚠️  groupe #{$g->id} : Saviez-vous tautologique (contient « {$correctText} »)";
            }
            if (strlen($fr->question_text ?? '') > 110) {
                $issues[] = "⚠️  groupe #{$g->id} : Question FR trop longue (" . strlen($fr->question_text) . " chars)";
            }
        }
        return $issues;
    }

    private function sampleFr(QuestionGroup $g): array
    {
        $fr = $g->translations->firstWhere('language', 'fr');
        if (!$fr) return [];
        return [
            'question'    => $fr->question_text     ?? '',
            'a'           => $fr->answer_a           ?? '',
            'b'           => $fr->answer_b           ?? '',
            'c'           => $fr->answer_c           ?? null,
            'd'           => $fr->answer_d           ?? null,
            'correct_key' => $fr->correct_answer_key ?? '',
            'saviez_vous' => $fr->saviez_vous        ?? '',
            'explanation' => $fr->explanation        ?? '',
        ];
    }

    // =========================================================================
    // Metadata helpers
    // =========================================================================

    private function buildMetadata(QuestionIntent $intent): array
    {
        $family  = $intent->concept_family ?? '';
        $domain  = $intent->domain ?? '';
        $sub     = $intent->sub_domain ?? $domain;

        // Semantic key: domain-slug + concept_family (both slugified)
        $domSlug    = $this->slugify($domain);
        $familySlug = $this->slugify($family ?: $sub);
        $semanticKey = $domSlug . '-' . $familySlug;
        $semanticKey = substr($semanticKey, 0, 80);

        // Subject: human readable from concept_family
        static $subjectMap = [
            'guerre-independance-americaine'  => 'Guerre d\'Indépendance américaine',
            'tennis-grand-slam-records'       => 'Records du Grand Chelem (tennis)',
            'african-geography'               => 'Géographie africaine',
            'academy-awards-best-picture'     => 'Oscars — Meilleur Film',
            'french-cuisine-ingredients'      => 'Ingrédients cuisine française',
            'coral-reef-ecosystem'            => 'Écosystème des récifs coralliens',
            'french-romanticism'              => 'Romantisme français (peinture)',
            'world-war-one'                   => 'Première Guerre mondiale',
            'avian-anatomy-adaptation'        => 'Anatomie et adaptations aviaires',
            'astronomy-cosmic-structures'     => 'Structures cosmiques (astronomie)',
        ];
        $subject = $subjectMap[$family] ?? ucwords(str_replace('-', ' ', $family));

        static $angleLargeMap = [
            'guerre-independance-americaine'  => 'Conflits historiques',
            'tennis-grand-slam-records'       => 'Records et statistiques sportives',
            'african-geography'               => 'Géographie continentale',
            'academy-awards-best-picture'     => 'Récompenses cinématographiques',
            'french-cuisine-ingredients'      => 'Techniques et ingrédients culinaires',
            'coral-reef-ecosystem'            => 'Écosystèmes marins',
            'french-romanticism'              => 'Mouvements artistiques européens',
            'world-war-one'                   => 'Guerres mondiales',
            'avian-anatomy-adaptation'        => 'Biologie des oiseaux',
            'astronomy-cosmic-structures'     => 'Cosmologie et astrophysique',
        ];
        $angleLarge = $angleLargeMap[$family] ?? ucwords(str_replace('-', ' ', $domain));

        static $microAngleMap = [
            'guerre-independance-americaine'  => 'Chronologie et dates clés',
            'tennis-grand-slam-records'       => 'Titres en simple masculin',
            'african-geography'               => 'Pays et capitales africaines',
            'academy-awards-best-picture'     => 'Films primés années 2000–2020',
            'french-cuisine-ingredients'      => 'Herbes et épices régionales',
            'coral-reef-ecosystem'            => 'Symbioses et organismes clés',
            'french-romanticism'              => 'Peintres et œuvres majeures',
            'world-war-one'                   => 'Batailles décisives 1914–1918',
            'avian-anatomy-adaptation'        => 'Structures physiques et vol',
            'astronomy-cosmic-structures'     => 'Galaxies, amas et filaments',
        ];
        $microAngle = $microAngleMap[$family] ?? null;

        static $answerTargetMap = [
            'guerre-independance-americaine'  => 'Année de la Déclaration d\'Indépendance',
            'tennis-grand-slam-records'       => 'Nombre de titres Grand Chelem',
            'african-geography'               => 'Nom de pays ou capitale africaine',
            'academy-awards-best-picture'     => 'Titre du film lauréat',
            'french-cuisine-ingredients'      => 'Ingrédient ou technique culinaire',
            'coral-reef-ecosystem'            => 'Organisme ou relation écologique',
            'french-romanticism'              => 'Artiste ou œuvre du romantisme',
            'world-war-one'                   => 'Événement ou date de la Grande Guerre',
            'avian-anatomy-adaptation'        => 'Structure anatomique ou adaptation',
            'astronomy-cosmic-structures'     => 'Structure cosmique ou propriété',
        ];
        $answerTarget = $answerTargetMap[$family] ?? null;

        static $potentialTrapMap = [
            'guerre-independance-americaine'  => 'Confusion 1776 vs 1783 (traité de Paris)',
            'tennis-grand-slam-records'       => 'Confusion Federer/Djokovic/Nadal selon l\'année',
            'african-geography'               => 'Pays aux capitales non-intuitives',
            'academy-awards-best-picture'     => 'Confusion film nominé vs film lauréat',
            'french-cuisine-ingredients'      => 'Ingrédients similaires de régions différentes',
            'coral-reef-ecosystem'            => 'Confusion corail / anémone / zooxanthelles',
            'french-romanticism'              => 'Confusion romantisme / impressionnisme',
            'world-war-one'                   => 'Confusion WWI / WWII pour des batailles similaires',
            'avian-anatomy-adaptation'        => 'Adaptation partagée avec reptiles (évolution)',
            'astronomy-cosmic-structures'     => 'Confusion étoile / planète / galaxie à grande échelle',
        ];
        $potentialTrap = $potentialTrapMap[$family] ?? null;

        return [
            'semantic_key'   => $semanticKey,
            'subject'        => $subject,
            'angle_large'    => $angleLarge,
            'micro_angle'    => $microAngle,
            'answer_target'  => $answerTarget,
            'potential_trap' => $potentialTrap,
        ];
    }

    private function applyMetadata(QuestionIntent $intent, array $patch): void
    {
        if ($this->dryRun) return;
        $intent->update($patch);
        $intent->refresh();
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        if (class_exists('Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::NFD);
            $s = preg_replace('/[\x{0300}-\x{036f}]/u', '', $s);
        }
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = preg_replace('/-+/', '-', $s);
        return trim($s, '-');
    }

    // =========================================================================
    // Code helpers
    // =========================================================================

    private function buildReadableCode(QuestionIntent $intent, array $variant): string
    {
        $domPrefix = self::DOMAIN_PREFIX[$intent->domain] ?? strtoupper(substr($intent->domain, 0, 2));
        $depth     = str_pad((string) $intent->difficulty_depth, 2, '0', STR_PAD_LEFT);
        $qtCode    = self::QTYPE_CODE[$variant['question_type']]  ?? 'Q';
        $ctCode    = self::CTYPE_CODE[$variant['cognitive_type']] ?? 'X';
        $hash      = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        return "{$domPrefix}-D{$depth}-{$qtCode}-{$ctCode}-{$hash}";
    }

    private function forbiddenConcepts(int $intentId): array
    {
        return QuestionGroup::where('question_intent_id', $intentId)
            ->whereNotNull('concept_id')
            ->pluck('concept_id')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * P5 — concept_family_share pre-guard.
     *
     * Mirrors QualityGuards::evaluate() segment query to detect which
     * concept_family values are already dominant in the segment.  Any family
     * whose (count+1)/(total+1) would exceed the 0.40 cap is returned as
     * forbidden, so the AI generates a question in a different family
     * instead of producing a variant that will be immediately rejected.
     *
     * Segment key: domain × sub_domain × cognitive_type × difficulty_level
     * (identical to the guard's own filter).
     */
    private function forbiddenFamilies(QuestionIntent $intent, array $variant, int $level): array
    {
        $domain      = $intent->domain;
        $subDomain   = $intent->sub_domain ?: $intent->domain;
        $cogType     = $variant['cognitive_type'];
        $cap         = 0.40;

        $segmentBase = QuestionGroup::query()
            ->where('domain', $domain)
            ->where('sub_domain', $subDomain)
            ->where('cognitive_type', $cogType)
            ->where('difficulty_level', $level);

        $total = (clone $segmentBase)->count();

        if ($total === 0) {
            return [];
        }

        $families = (clone $segmentBase)
            ->whereNotNull('concept_family')
            ->groupBy('concept_family')
            ->selectRaw('concept_family, COUNT(*) as cnt')
            ->pluck('cnt', 'concept_family')
            ->toArray();

        $forbidden = [];
        foreach ($families as $family => $count) {
            $share = ($count + 1) / ($total + 1);
            if ($share > $cap) {
                $forbidden[] = $family;
            }
        }

        return $forbidden;
    }

    /**
     * P4 — Noyau lock: build a focused topic hint from the intent's metadata.
     *
     * Injected as `concept_hint` in the generate-bank-question body.
     * The Node endpoint renders it as:
     *   "Indice concept: <hint>"
     * which steers the AI toward the exact noyau (subject + angle +
     * micro-angle + answer target) rather than the broader domain.
     *
     * Without this, the AI may produce topically correct but semantically
     * drifted questions (e.g. volleyball/marathon for a tennis-grand-slam
     * noyau, or Asian/European geography for an african-geography noyau).
     *
     * Returns '' when no useful metadata is available (fail-open: the
     * existing generic prompt "Choisis un fait précis" is used instead).
     */
    private function buildConceptHint(QuestionIntent $intent): string
    {
        $parts = array_filter([
            $intent->subject       ? 'Sujet: '       . trim($intent->subject)       : null,
            $intent->angle_large   ? 'Angle: '       . trim($intent->angle_large)   : null,
            $intent->micro_angle   ? 'Micro-angle: ' . trim($intent->micro_angle)   : null,
            $intent->answer_target ? 'Cible: '       . trim($intent->answer_target) : null,
        ]);

        if (empty($parts)) {
            return '';
        }

        $hint = implode('. ', $parts) . '.';

        // Append the semantic_key as a hard constraint so the AI cannot ignore
        // the noyau boundary even when the hint is loosely worded.
        $sk = trim((string) ($intent->semantic_key ?? $intent->intent_key ?? ''));
        if ($sk !== '') {
            $hint .= " Reste STRICTEMENT dans ce noyau ({$sk}) — toute dérive vers un autre sous-thème est interdite.";
        }

        return $hint;
    }

    // =========================================================================
    // Markdown export
    // =========================================================================

    private function exportMarkdown(array $globalStats, float $elapsed): void
    {
        $outPath = $this->option('output-file')
            ? base_path($this->option('output-file'))
            : base_path('exports/dialyse_10_noyaux_completed.md');
        if (!is_dir(dirname($outPath))) {
            mkdir(dirname($outPath), 0755, true);
        }

        $mode    = $this->dryRun ? 'DRY-RUN — aucune modification réelle' : 'LIVE — modifications réelles appliquées';
        $date    = now()->toDateString();
        $time    = now()->toTimeString();
        $elapsed = round($elapsed, 1);
        $total   = count($this->noyauxReport);

        $lines = [];
        $lines[] = "# Dialyse complète — {$total} noyaux";
        $lines[] = "";
        $lines[] = "**Date :** {$date} {$time}  ";
        $lines[] = "**Mode :** {$mode}  ";
        $lines[] = "**Durée :** {$elapsed}s  ";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        // ── Global summary table ─────────────────────────────────────────────
        $lines[] = "## Résumé global";
        $lines[] = "";
        $lines[] = "| Noyau | Domaine | Depth | AVANT | APRÈS | Générés | Refusés | Statut |";
        $lines[] = "|---|---|---|---|---|---|---|---|";

        foreach ($this->noyauxReport as $r) {
            $iid       = $r['intent']['id'];
            $domain    = $r['intent']['domain'];
            $depth     = $r['intent']['difficulty_depth'];
            $before    = count($r['before_variants']) . '/5';
            $after     = count($r['after_variants']) . '/5';
            $generated = $r['generated_count'];
            $rejected  = $r['rejected_count'];
            $status    = $r['after_complete'] ? '✅ COMPLET' : '❌ INCOMPLET';
            $lines[] = "| #{$iid} | {$domain} | {$depth} | {$before} | {$after} | {$generated} | {$rejected} | {$status} |";
        }
        $lines[] = "";
        $lines[] = "**Complets : {$globalStats['complete']}/{$total} | Incomplets : {$globalStats['incomplete']}/{$total} | Variantes générées : {$globalStats['generated']} | Refusées : {$globalStats['rejected']}**";
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "";

        // ── Per-noyau sections ───────────────────────────────────────────────
        foreach ($this->noyauxReport as $idx => $r) {
            $n    = $idx + 1;
            $iid  = $r['intent']['id'];
            $dom  = $r['intent']['domain'];
            $dep  = $r['intent']['difficulty_depth'];
            $sk   = $r['intent']['semantic_key'] ?? '*(non définie)*';
            $cf   = $r['intent']['concept_family'] ?? '—';

            $lines[] = "## NOYAU {$n} — #{$iid} · {$dom} · depth {$dep}";
            $lines[] = "";

            // ── Métadonnées ──────────────────────────────────────────────────
            $lines[] = "### 1. Métadonnées noyau";
            $lines[] = "";
            $lines[] = "| Champ | AVANT | APRÈS |";
            $lines[] = "|---|---|---|";
            $lines[] = "| semantic_key | *(null)* | `{$r['metadata_patch']['semantic_key']}` |";
            $lines[] = "| subject | *(null)* | {$r['metadata_patch']['subject']} |";
            $lines[] = "| angle_large | *(null)* | {$r['metadata_patch']['angle_large']} |";
            $lines[] = "| micro_angle | *(null)* | " . ($r['metadata_patch']['micro_angle'] ?? '—') . " |";
            $lines[] = "| answer_target | *(null)* | " . ($r['metadata_patch']['answer_target'] ?? '—') . " |";
            $lines[] = "| potential_trap | *(null)* | " . ($r['metadata_patch']['potential_trap'] ?? '—') . " |";
            $lines[] = "| concept_family | {$cf} | {$cf} *(inchangé)* |";
            $lines[] = "";

            // ── Audit AVANT ──────────────────────────────────────────────────
            $lines[] = "### 2. Problèmes détectés AVANT";
            $lines[] = "";
            if (empty($r['before_issues'])) {
                $lines[] = "✅ Aucun problème AVANT";
            } else {
                foreach ($r['before_issues'] as $issue) {
                    $lines[] = "- {$issue}";
                }
            }
            $lines[] = "";

            // ── Variantes AVANT ──────────────────────────────────────────────
            $lines[] = "### 3. Variantes AVANT";
            $lines[] = "";
            if (empty($r['before_variants'])) {
                $lines[] = "*Aucune variante*";
            } else {
                foreach ($r['before_variants'] as $bv) {
                    $langStr = implode(', ', $bv['langs']);
                    $lines[] = "**#{$bv['group_id']} — {$bv['question_type']}/{$bv['cognitive_type']}** (`{$bv['readable_code']}`) · {$bv['status']} · langues: {$langStr}";
                    if (!empty($bv['sample_fr'])) {
                        $fr = $bv['sample_fr'];
                        $lines[] = "";
                        $lines[] = "> **Q (FR):** {$fr['question']}";
                        $lines[] = "> **[{$fr['correct_key']}]✅** " . ($fr[$fr['correct_key'] ? strtolower($fr['correct_key']) : 'a'] ?? '?');
                        $lines[] = "> **SV:** " . (substr($fr['saviez_vous'], 0, 120) ?: '*(vide)*');
                    }
                    $lines[] = "";
                }
            }

            // ── Variantes générées ───────────────────────────────────────────
            $lines[] = "### 4. Variantes générées";
            $lines[] = "";
            if (empty($r['generated_variants'])) {
                $lines[] = "*Aucune variante générée*";
            } else {
                foreach ($r['generated_variants'] as $gv) {
                    if (($gv['status'] ?? null) === 'dry_run') {
                        $lines[] = "**{$gv['vtag']}** — `[dry-run]`";
                        continue;
                    }
                    if (!$gv['ok']) {
                        $lines[] = "**{$gv['vtag']}** — ❌ REFUSÉE";
                        $lines[] = "";
                        $lines[] = "- Étape : `{$gv['step']}`";
                        $lines[] = "- Raison : {$gv['error']}";
                        if (!empty($gv['fr_question'])) {
                            $lines[] = "- Question FR générée : {$gv['fr_question']}";
                        }
                        $lines[] = "";
                        continue;
                    }
                    $langCount = $gv['langs_count'] ?? '?';
                    $lines[] = "**{$gv['vtag']}** — ✅ group_id=#{$gv['group_id']} · `{$gv['readable_code']}` · {$langCount} langues · source={$gv['source']}";
                    $lines[] = "";
                    if (!empty($gv['sample_fr'])) {
                        $fr = $gv['sample_fr'];
                        $ck = strtolower($fr['correct_key'] ?? 'a');
                        $correctText = $fr[$ck] ?? '?';
                        $lines[] = "<details>";
                        $lines[] = "<summary><strong>FR — détail complet</strong></summary>";
                        $lines[] = "";
                        $lines[] = "**Question :** {$fr['question']}";
                        $lines[] = "";
                        $lines[] = "| Clé | Réponse | Correcte |";
                        $lines[] = "|---|---|---|";
                        $lines[] = "| A | {$fr['a']} | " . ($fr['correct_key'] === 'A' ? '✅' : '') . " |";
                        $lines[] = "| B | {$fr['b']} | " . ($fr['correct_key'] === 'B' ? '✅' : '') . " |";
                        if (!empty($fr['c'])) {
                            $lines[] = "| C | {$fr['c']} | " . ($fr['correct_key'] === 'C' ? '✅' : '') . " |";
                        }
                        if (!empty($fr['d'])) {
                            $lines[] = "| D | {$fr['d']} | " . ($fr['correct_key'] === 'D' ? '✅' : '') . " |";
                        }
                        $lines[] = "";
                        $lines[] = "**Correcte :** [{$fr['correct_key']}] {$correctText}";
                        $lines[] = "";
                        $lines[] = "**Explication :** " . ($fr['explanation'] ?: '*(vide)*');
                        $lines[] = "";
                        $sv = $fr['saviez_vous'] ?? '';
                        $svLen = mb_strlen($sv);
                        // Tautology flag
                        $tautFlag = '';
                        if ($sv && stripos($sv, $correctText) !== false) {
                            $tautFlag = ' ⚠️ *tautologique*';
                        }
                        $lines[] = "**Saviez-vous ({$svLen}ch){$tautFlag} :** {$sv}";
                        $lines[] = "";
                        $lines[] = "</details>";
                    }
                    $lines[] = "";
                }
            }

            // ── Variantes refusées ───────────────────────────────────────────
            if (!empty($r['rejected_variants'])) {
                $lines[] = "### 4b. Variantes REFUSÉES par QualityGuards";
                $lines[] = "";
                foreach ($r['rejected_variants'] as $rv) {
                    $code   = $rv['guard_code'] ?? $rv['step'] ?? '?';
                    $detail = $rv['guard_detail'] ?? $rv['error'] ?? '?';
                    $vtag   = $rv['vtag'] ?? '?';
                    $lines[] = "- **{$vtag}** — `{$code}` : {$detail}";
                }
                $lines[] = "";
            }

            // ── Audit APRÈS ──────────────────────────────────────────────────
            $lines[] = "### 5. Problèmes résiduels APRÈS";
            $lines[] = "";
            if (empty($r['after_issues'])) {
                $lines[] = "✅ Aucun problème résiduel";
            } else {
                foreach ($r['after_issues'] as $issue) {
                    $lines[] = "- {$issue}";
                }
            }
            $lines[] = "";

            // ── Variantes APRÈS ──────────────────────────────────────────────
            $lines[] = "### 6. Variantes APRÈS";
            $lines[] = "";
            if (empty($r['after_variants'])) {
                $lines[] = "*Aucune variante*";
            } else {
                foreach ($r['after_variants'] as $av) {
                    $langCount = count($av['langs']);
                    $icon      = $langCount === 10 ? '✅' : "⚠️ {$langCount}/10";
                    $lines[] = "- #{$av['group_id']} · **{$av['question_type']}/{$av['cognitive_type']}** · `{$av['readable_code']}` · {$icon} langues";
                }
            }
            $lines[] = "";

            // ── Résultat final ───────────────────────────────────────────────
            $finalStatus = $r['after_complete'] ? '✅ **COMPLETE**' : '❌ **INCOMPLETE**';
            $lines[] = "### 7. Résultat final";
            $lines[] = "";
            $lines[] = "| | |";
            $lines[] = "|---|---|";
            $lines[] = "| Variantes présentes | " . count($r['after_variants']) . "/5 |";
            $lines[] = "| Semantic key | `{$r['metadata_patch']['semantic_key']}` |";
            $lines[] = "| Problèmes résiduels | " . count($r['after_issues']) . " |";
            $lines[] = "| Statut dialyse | {$finalStatus} |";
            $lines[] = "";
            $lines[] = "---";
            $lines[] = "";
        }

        // Footer
        $lines[] = "*Généré par `questions:bank:dialyse:run-test` le {$date} {$time}*";
        $lines[] = "";

        $content = implode("\n", $lines);
        file_put_contents($outPath, $content);

        $sizeKb = round(strlen($content) / 1024, 1);
        $this->info("📄 Export : exports/dialyse_10_noyaux_completed.md ({$sizeKb} Ko)");
    }
}
