<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QuestionsBankPhase0Command extends Command
{
    protected $signature = 'questions:bank:phase0
                            {--step=inventory : inventory | backfill | verify}
                            {--batch=200 : Batch size for bulk updates}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Phase 0 — Migrate existing question bank to post_review_status structure';

    private array $domainCodes = [
        'Géographie' => 'GE',
        'Histoire'   => 'HI',
        'Faune'      => 'FA',
        'Art'        => 'AR',
        'Sport'      => 'SP',
        'Cinéma'     => 'CI',
        'Cuisine'    => 'CU',
        'Science'    => 'SC',
    ];

    private array $cognitiveCodes = [
        'recognition'    => 'R',
        'reasoning'      => 'N',
        'deceptive_trap' => 'T',
    ];

    private array $typeCodes = [
        'qcm'        => 'Q',
        'true_false' => 'V',
    ];

    public function handle(): int
    {
        $step     = $this->option('step');
        $isDryRun = (bool) $this->option('dry-run');

        match ($step) {
            'backfill' => $this->runBackfill($isDryRun),
            'verify'   => $this->runVerify(),
            default    => $this->runInventory(),
        };

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVENTORY
    // ─────────────────────────────────────────────────────────────────────────

    private function runInventory(): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║    PHASE 0 — INVENTAIRE BANQUE QUESTIONS             ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->line('');

        $total         = DB::table('question_groups')->count();
        $valTrue       = DB::table('question_groups')->where('validated', true)->count();
        $valFalse      = DB::table('question_groups')->where('validated', false)->count();

        $this->info("  question_groups total   : {$total}");
        $this->line("  validated = true        : {$valTrue}");
        $this->line("  validated = false       : {$valFalse}");

        $this->line('');
        $this->line('  Post-review status actuel :');
        DB::table('question_groups')
            ->select('post_review_status', DB::raw('COUNT(*) as n'))
            ->groupBy('post_review_status')
            ->get()
            ->each(function ($r) {
                $label = $r->post_review_status ?? 'NULL (non migré)';
                $this->line("    {$label} : {$r->n}");
            });

        $this->line('');
        $this->line('  Répartition domaines :');
        DB::table('question_groups')
            ->select('domain', DB::raw('COUNT(*) as n'))
            ->groupBy('domain')
            ->orderByDesc('n')
            ->get()
            ->each(fn ($r) => $this->line("    {$r->domain} : {$r->n}"));

        $this->line('');
        $this->line('  Répartition question_type / cognitive_type :');
        DB::table('question_groups')
            ->select('question_type', 'cognitive_type', DB::raw('COUNT(*) as n'))
            ->groupBy('question_type', 'cognitive_type')
            ->orderByDesc('n')
            ->get()
            ->each(fn ($r) => $this->line("    {$r->question_type}/{$r->cognitive_type} : {$r->n}"));

        $translationsTotal = DB::table('question_translations')->count();
        $this->line('');
        $this->line("  Traductions total : {$translationsTotal}");
        DB::table('question_translations')
            ->select('language', DB::raw('COUNT(*) as n'))
            ->groupBy('language')
            ->orderByDesc('n')
            ->get()
            ->each(fn ($r) => $this->line("    {$r->language} : {$r->n}"));

        $this->line('');
        $this->line('  Champs Phase -1 remplis :');
        $withStatus      = DB::table('question_groups')->whereNotNull('post_review_status')->count();
        $withIntentKey   = DB::table('question_groups')->whereNotNull('question_intent_key')->count();
        $withReadable    = DB::table('question_groups')->whereNotNull('readable_code')->count();
        $withHashQ       = DB::table('question_translations')->whereNotNull('hash_question')->count();
        $this->line("    post_review_status      : {$withStatus}/{$total}");
        $this->line("    question_intent_key     : {$withIntentKey}/{$total}");
        $this->line("    readable_code           : {$withReadable}/{$total}");
        $this->line("    hash_question           : {$withHashQ}/{$translationsTotal}");

        $this->line('');
        $this->line('  Commandes disponibles :');
        $this->line('    php artisan questions:bank:phase0 --step=backfill --dry-run');
        $this->line('    php artisan questions:bank:phase0 --step=backfill');
        $this->line('    php artisan questions:bank:phase0 --step=verify');
        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BACKFILL
    // ─────────────────────────────────────────────────────────────────────────

    private function runBackfill(bool $isDryRun): void
    {
        $batchSize = max(50, (int) $this->option('batch'));

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║    PHASE 0 — BACKFILL ANCIENNE BANQUE                ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        if ($isDryRun) {
            $this->warn('  MODE DRY-RUN — aucune écriture en base');
        }
        $this->line('');

        $this->step1BackfillStatus($isDryRun);
        $this->step2BackfillReadableCode($isDryRun, $batchSize);
        $this->step3BackfillIntentKey($isDryRun, $batchSize);
        $this->step4CreateQuestionIntents($isDryRun, $batchSize);
        $this->step5BackfillHashes($isDryRun, $batchSize);

        $this->line('');
        $this->info('  ✓ Backfill Phase 0 terminé.');
        $this->line('  → Vérification : php artisan questions:bank:phase0 --step=verify');
        $this->line('');
    }

    // ── Step 1 ──────────────────────────────────────────────────────────────

    private function step1BackfillStatus(bool $dry): void
    {
        $this->line('  [1/5] Backfill post_review_status …');

        $toReady = DB::table('question_groups')
            ->whereNull('post_review_status')
            ->where('validated', true)
            ->count();

        $this->line("        {$toReady} blocs validated=true → ready_bank");

        if (!$dry && $toReady > 0) {
            DB::table('question_groups')
                ->whereNull('post_review_status')
                ->where('validated', true)
                ->update(['post_review_status' => 'ready_bank']);
        }

        $toReview = DB::table('question_groups')
            ->whereNull('post_review_status')
            ->where('validated', false)
            ->count();

        if ($toReview > 0) {
            $this->warn("        {$toReview} blocs validated=false → review_bank");
            if (!$dry) {
                DB::table('question_groups')
                    ->whereNull('post_review_status')
                    ->where('validated', false)
                    ->update(['post_review_status' => 'review_bank']);
            }
        }

        $this->info('        ✓ done');
    }

    // ── Step 2 ──────────────────────────────────────────────────────────────

    private function step2BackfillReadableCode(bool $dry, int $batchSize): void
    {
        $this->line('  [2/5] Backfill readable_code …');

        $total = DB::table('question_groups')->whereNull('readable_code')->count();
        $this->line("        {$total} blocs sans readable_code");

        if ($total === 0) {
            $this->info('        ✓ done (déjà rempli)');
            return;
        }

        if ($dry) {
            $this->info('        ✓ skipped (dry-run)');
            return;
        }

        $done = 0;
        DB::table('question_groups')
            ->whereNull('readable_code')
            ->orderBy('id')
            ->select(['id', 'domain', 'difficulty_depth', 'question_type', 'cognitive_type', 'concept_id'])
            ->chunkById($batchSize, function ($rows) use (&$done, $total) {
                foreach ($rows as $row) {
                    DB::table('question_groups')
                        ->where('id', $row->id)
                        ->update(['readable_code' => $this->buildReadableCode($row)]);
                    $done++;
                }
                $this->line("        {$done}/{$total}…");
            });

        $this->info("        ✓ {$done} readable_code générés");
    }

    // ── Step 3 ──────────────────────────────────────────────────────────────

    private function step3BackfillIntentKey(bool $dry, int $batchSize): void
    {
        $this->line('  [3/5] Backfill question_intent_key …');

        $total = DB::table('question_groups')->whereNull('question_intent_key')->count();
        $this->line("        {$total} blocs sans question_intent_key");

        if ($total === 0) {
            $this->info('        ✓ done (déjà rempli)');
            return;
        }

        if ($dry) {
            $this->info('        ✓ skipped (dry-run)');
            return;
        }

        $done = 0;
        DB::table('question_groups')
            ->whereNull('question_intent_key')
            ->orderBy('id')
            ->select(['id', 'domain', 'sub_domain', 'concept_id', 'concept_family', 'cognitive_type', 'question_type'])
            ->chunkById($batchSize, function ($rows) use (&$done, $total) {
                foreach ($rows as $row) {
                    DB::table('question_groups')
                        ->where('id', $row->id)
                        ->update(['question_intent_key' => $this->buildLegacyIntentKey($row)]);
                    $done++;
                }
                $this->line("        {$done}/{$total}…");
            });

        $this->info("        ✓ {$done} question_intent_key générés");
    }

    // ── Step 4 ──────────────────────────────────────────────────────────────
    // Bulk approach: load all → bulk INSERT missing intents → CASE WHEN UPDATE

    private function step4CreateQuestionIntents(bool $dry, int $batchSize): void
    {
        $this->line("  [4/5] Création question_intents (noyaux legacy) …");

        $groups = DB::table('question_groups')
            ->whereNotNull('question_intent_key')
            ->whereNull('question_intent_id')
            ->select(['id', 'question_intent_key', 'domain', 'sub_domain',
                      'difficulty_depth', 'concept_family'])
            ->get();

        $toProcess = $groups->count();
        $this->line("        {$toProcess} blocs à relier");

        if ($toProcess === 0) {
            $this->info('        ✓ done (déjà relié)');
            return;
        }

        if ($dry) {
            $this->info('        ✓ skipped (dry-run)');
            return;
        }

        $uniqueKeys = $groups->pluck('question_intent_key')->unique()->values()->toArray();

        // Load already existing intents
        $intentMap = DB::table('question_intents')
            ->whereIn('intent_key', $uniqueKeys)
            ->pluck('id', 'intent_key')
            ->toArray();

        // Bulk insert missing intents
        $missingKeys = array_diff($uniqueKeys, array_keys($intentMap));
        if (!empty($missingKeys)) {
            $byKey = $groups->keyBy('question_intent_key');
            $now   = now();
            $rows  = array_map(fn ($k) => [
                'intent_key'       => $k,
                'language_source'  => 'en',
                'domain'           => $byKey[$k]->domain,
                'sub_domain'       => $byKey[$k]->sub_domain,
                'difficulty_depth' => max(1, (int) $byKey[$k]->difficulty_depth),
                'concept_family'   => $byKey[$k]->concept_family,
                'source'           => 'legacy',
                'created_at'       => $now,
                'updated_at'       => $now,
            ], $missingKeys);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('question_intents')->insert($chunk);
            }
            $this->line('        ' . count($missingKeys) . ' question_intents insérés');
        }

        // Reload full map
        $allMap = DB::table('question_intents')
            ->whereIn('intent_key', $uniqueKeys)
            ->pluck('id', 'intent_key')
            ->toArray();

        // CASE WHEN bulk UPDATE in chunks of 1 000
        $done = 0;
        foreach ($groups->chunk(1000) as $chunk) {
            $caseBlock = '';
            $chunkIds  = [];
            foreach ($chunk as $row) {
                $intentId = $allMap[$row->question_intent_key] ?? null;
                if (!$intentId) {
                    continue;
                }
                $esc        = str_replace("'", "''", $row->question_intent_key);
                $caseBlock .= " WHEN '{$esc}' THEN {$intentId}";
                $chunkIds[] = $row->id;
            }

            if (!empty($chunkIds)) {
                $idList = implode(',', $chunkIds);
                DB::statement(
                    "UPDATE question_groups
                     SET question_intent_id = CASE question_intent_key{$caseBlock} END
                     WHERE id IN ({$idList})"
                );
                $done += count($chunkIds);
                $this->line("        {$done}/{$toProcess}…");
            }
        }

        $this->info("        ✓ {$done} blocs reliés à question_intents");
    }

    // ── Step 5 ──────────────────────────────────────────────────────────────
    // Single PostgreSQL UPDATE with md5 + CASE — no PHP loop over 24 k rows.

    private function step5BackfillHashes(bool $dry, int $batchSize): void
    {
        $this->line('  [5/5] Backfill hash_question / hash_answer …');

        $total = DB::table('question_translations')->whereNull('hash_question')->count();
        $this->line("        {$total} traductions sans hash");

        if ($total === 0) {
            $this->info('        ✓ done (déjà calculé)');
            return;
        }

        if ($dry) {
            $this->info('        ✓ skipped (dry-run)');
            return;
        }

        DB::statement("
            UPDATE question_translations
            SET
                hash_question = md5(
                    trim(regexp_replace(lower(question_text), '[^a-z0-9\\s]', '', 'g'))
                ),
                hash_answer = md5(
                    trim(regexp_replace(lower(
                        CASE correct_answer_key
                            WHEN 'A' THEN coalesce(answer_a, '')
                            WHEN 'B' THEN coalesce(answer_b, '')
                            WHEN 'C' THEN coalesce(answer_c, '')
                            WHEN 'D' THEN coalesce(answer_d, '')
                            ELSE coalesce(answer_a, '')
                        END
                    ), '[^a-z0-9\\s]', '', 'g'))
                )
            WHERE hash_question IS NULL
        ");

        $done = DB::table('question_translations')->whereNotNull('hash_question')->count();
        $this->info("        ✓ {$done} hashes calculés");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERIFY
    // ─────────────────────────────────────────────────────────────────────────

    private function runVerify(): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║    PHASE 0 — VÉRIFICATION FINALE                     ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->line('');

        $total       = DB::table('question_groups')->count();
        $readyBank   = DB::table('question_groups')->where('post_review_status', 'ready_bank')->count();
        $reviewBank  = DB::table('question_groups')->where('post_review_status', 'review_bank')->count();
        $blocked     = DB::table('question_groups')->where('post_review_status', 'blocked_critical')->count();
        $duplicate   = DB::table('question_groups')->where('post_review_status', 'duplicate_blocked')->count();
        $correction  = DB::table('question_groups')->where('post_review_status', 'correction_needed')->count();
        $nullStatus  = DB::table('question_groups')->whereNull('post_review_status')->count();

        $withIntentKey  = DB::table('question_groups')->whereNotNull('question_intent_key')->count();
        $withIntentId   = DB::table('question_groups')->whereNotNull('question_intent_id')->count();
        $withReadable   = DB::table('question_groups')->whereNotNull('readable_code')->count();
        $intentsTotal   = DB::table('question_intents')->count();

        $transTotal  = DB::table('question_translations')->count();
        $withHashQ   = DB::table('question_translations')->whereNotNull('hash_question')->count();
        $withHashA   = DB::table('question_translations')->whereNotNull('hash_answer')->count();

        $this->info("  question_groups total   : {$total}");
        $this->line("  ready_bank              : {$readyBank}");
        $this->line("  review_bank             : {$reviewBank}");
        $this->line("  correction_needed       : {$correction}");
        $this->line("  blocked_critical        : {$blocked}");
        $this->line("  duplicate_blocked       : {$duplicate}");
        $this->line("  NULL (non migré)        : {$nullStatus}");
        $this->line('');
        $this->line("  question_intent_key     : {$withIntentKey}/{$total}");
        $this->line("  question_intent_id      : {$withIntentId}/{$total}");
        $this->line("  readable_code           : {$withReadable}/{$total}");
        $this->line("  question_intents        : {$intentsTotal} enregistrements");
        $this->line('');
        $this->line("  question_translations   : {$transTotal}");
        $this->line("  hash_question rempli    : {$withHashQ}/{$transTotal}");
        $this->line("  hash_answer rempli      : {$withHashA}/{$transTotal}");
        $this->line('');

        if ($nullStatus > 0) {
            $this->warn("  ⚠  {$nullStatus} blocs sans post_review_status — relancer --step=backfill");
        } elseif ($readyBank === 0) {
            $this->warn('  ⚠  Aucun bloc en ready_bank — backfill non exécuté ?');
        } else {
            $this->info("  ✓ {$readyBank} blocs jouables en ready_bank — aucune perte détectée");
        }

        $missing = $total - $withIntentKey;
        if ($missing > 0) {
            $this->warn("  ⚠  {$missing} blocs sans question_intent_key");
        }

        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function buildReadableCode(object $row): string
    {
        $dom    = $this->domainCodes[$row->domain] ?? 'XX';
        $depth  = str_pad((string) max(1, (int) $row->difficulty_depth), 2, '0', STR_PAD_LEFT);
        $type   = $this->typeCodes[$row->question_type] ?? 'Q';
        $cog    = $this->cognitiveCodes[$row->cognitive_type] ?? 'R';
        $suffix = strtoupper(substr(hash('sha256', (string) ($row->concept_id ?? $row->id)), 0, 5));

        return "{$dom}-D{$depth}-{$type}-{$cog}-{$suffix}";
    }

    private function buildLegacyIntentKey(object $row): string
    {
        if (!empty($row->concept_id)) {
            return 'legacy_' . $row->concept_id;
        }

        $parts = array_filter([
            !empty($row->domain)         ? $this->slugify($row->domain)         : null,
            !empty($row->sub_domain)     ? $this->slugify($row->sub_domain)     : null,
            !empty($row->concept_family) ? $this->slugify($row->concept_family) : null,
            $row->cognitive_type,
            $row->question_type,
            substr(hash('sha256', (string) $row->id), 0, 8),
        ]);

        return 'legacy_' . implode('_', $parts);
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $conv = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $conv !== false ? $conv : $text;
        $text = preg_replace('/[^a-z0-9]+/', '_', $text) ?? $text;
        return trim($text, '_');
    }
}
