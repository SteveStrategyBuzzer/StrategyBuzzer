<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use Illuminate\Console\Command;

/**
 * questions:taxonomy:exhausted-subjects
 *
 * Lists every taxonomy subject that was marked idea_generation_exhausted
 * after Gemini returned only FAIL ideas (0 PASS across all attempts).
 *
 * This is the ops surface for the silent-exhaustion alert added in task #122.
 * When a subject is abandoned with zero usable ideas, a Log::warning() is
 * emitted at generation time; this command lets ops run a full DB scan on
 * demand and is also referenced in the /health endpoint.
 *
 * Exit codes:
 *   0  — no problem found
 *   1  — at least one exhausted-with-zero-pass subject found
 */
class QuestionsTaxonomyExhaustedSubjectsCommand extends Command
{
    protected $signature = 'questions:taxonomy:exhausted-subjects
        {--min-fails=1 : Minimum number of FAIL ideas for a subject to be reported}
        {--depth=      : Filter by depth}
        {--domain=     : Filter by domain_code}
        {--json        : Output as JSON (for scripting / CI)}';

    protected $description = 'List taxonomy subjects exhausted by Gemini with 0 PASS ideas — ops observability for silent bank gaps.';

    public function handle(TaxonomyBankRepository $repo): int
    {
        $minFails = max(1, (int) $this->option('min-fails'));
        $depthFilter  = $this->option('depth')  ? (int) $this->option('depth')  : null;
        $domainFilter = $this->option('domain')  ? (string) $this->option('domain') : null;

        $rows = $repo->findExhaustedWithOnlyFails($minFails);

        // Apply optional filters
        if ($depthFilter !== null) {
            $rows = array_values(array_filter($rows, fn ($r) => (int) $r->depth === $depthFilter));
        }
        if ($domainFilter !== null) {
            $rows = array_values(array_filter($rows, fn ($r) => $r->domain_code === $domainFilter));
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'reported_at'    => now()->toIso8601String(),
                'min_fails'      => $minFails,
                'total'          => count($rows),
                'subjects'       => array_map(fn ($r) => [
                    'depth'          => $r->depth,
                    'domain_code'    => $r->domain_code,
                    'subdomain_name' => $r->subdomain_name,
                    'subject_id'     => $r->subject_id,
                    'subject_name'   => $r->subject_name,
                    'fail_count'     => $r->fail_count,
                    'exhausted_at'   => $r->exhausted_at,
                ], $rows),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return count($rows) > 0 ? self::FAILURE : self::SUCCESS;
        }

        // ── Human-readable output ────────────────────────────────────────────
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║  Taxonomy — Sujets épuisés sans aucune idée PASS               ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line(sprintf('  Snapshot at : %s', now()->format('Y-m-d H:i:s T')));
        $this->line(sprintf('  Filtre      : min_fails=%d%s%s',
            $minFails,
            $depthFilter  !== null ? "  depth={$depthFilter}"    : '',
            $domainFilter !== null ? "  domain={$domainFilter}"   : ''
        ));
        $this->line('');

        if (empty($rows)) {
            $this->info('  ✅  Aucun sujet épuisé sans idée PASS trouvé.');
            $this->line('');
            return self::SUCCESS;
        }

        $this->warn(sprintf('  ⚠  %d sujet(s) épuisé(s) avec 0 idée PASS (≥%d FAIL) :', count($rows), $minFails));
        $this->line('');

        $this->line(sprintf(
            '  %-5s %-12s %-22s %-30s %5s  %s',
            'depth', 'domain', 'subdomain', 'subject', 'fails', 'exhausted_at'
        ));
        $this->line('  ' . str_repeat('─', 100));

        foreach ($rows as $r) {
            $this->line(sprintf(
                '  %-5s %-12s %-22s %-30s %5d  %s',
                $r->depth,
                $r->domain_code,
                mb_substr($r->subdomain_name, 0, 21),
                mb_substr($r->subject_name,   0, 29),
                $r->fail_count,
                substr($r->exhausted_at ?? '', 0, 16)
            ));
        }

        $this->line('');
        $this->line('  Ces sujets ont été abandonnés silencieusement par le pipeline.');
        $this->line('  Un Log::warning("taxonomy.subject_exhausted_with_zero_pass") a été');
        $this->line('  émis pour chacun au moment de l\'épuisement.');
        $this->line('');
        $this->line('  Pour investiguer les raisons FAIL :');
        $this->line('    SELECT idea_value, fail_reason, fail_conflict_with');
        $this->line('    FROM taxonomy_dominant_idea_bank');
        $this->line('    WHERE subject_id = <subject_id> AND validation_status = \'FAIL\';');
        $this->line('');

        return self::FAILURE;
    }
}
