<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * questions:taxonomy:seed
 *
 * One-shot warm-up command that populates the Taxonomy bank so that
 * KernelPipelineOrchestrator can call peekNext() without hitting an empty bank.
 *
 * What it does:
 *   For each (Depth × Domain) cell (63 cells = 7 depths × 9 domains):
 *     1. Counts subjects already seeded (≥ 1 PASS+AVAILABLE idea) for this cell.
 *     2. If fewer than --subjects-per-cell subjects are seeded, calls
 *        TaxonomyOrchestrator::warmUpCell() to fill the gap.
 *     3. warmUpCell() only targets subjects with ZERO available ideas —
 *        it never touches or consumes existing AVAILABLE ideas.
 *
 * Idempotence:
 *   - Cells already at or above the target are skipped entirely (no Gemini calls).
 *   - Reruns never decrease the available-idea count.
 *   - Safe to run multiple times without side effects.
 *
 * Options:
 *   --depth=N             Restrict to one Depth (2|4|6|7|8|9|10).
 *   --domain=X            Restrict to one Domain code.
 *   --subjects-per-cell=N Target number of subjects with ≥ 1 idea per cell (default 1).
 *   --dry-run             Audit existing bank state without calling Gemini.
 */
class QuestionsTaxonomySeedCommand extends Command
{
    protected $signature = 'questions:taxonomy:seed
        {--depth=             : Restreindre à un Depth unique (2|4|6|7|8|9|10)}
        {--domain=            : Restreindre à un Domaine unique (histoire|geographie|sport|art|cuisine|science|cinema|faune|general)}
        {--subjects-per-cell=1 : Nombre minimum de sujets avec ≥ 1 idée PASS par cellule (défaut 1)}
        {--dry-run            : Afficher l\'état actuel sans appeler Gemini}';

    protected $description = 'Pré-remplit la Taxonomy Bank (sous-domaines + sujets + idées) pour chaque cellule Depth × Domaine sans consommer d\'idées disponibles.';

    /** All official domain codes (mirrors TaxonomyOrchestrator::DOMAIN_LABELS). */
    private const DOMAIN_CODES = [
        'histoire',
        'geographie',
        'sport',
        'art',
        'cuisine',
        'science',
        'cinema',
        'faune',
        'general',
    ];

    public function handle(): int
    {
        $depthFilter     = $this->option('depth')  ? (int) $this->option('depth')  : null;
        $domainFilter    = $this->option('domain') ? (string) $this->option('domain') : null;
        $subjectsPerCell = max(1, (int) ($this->option('subjects-per-cell') ?? 1));
        $dryRun          = (bool) $this->option('dry-run');

        // ── Validate filters ──────────────────────────────────────────────────
        if ($depthFilter !== null && ! DepthContractRegistry::isKnown($depthFilter)) {
            $this->error("Depth inconnu : {$depthFilter}. Valeurs valides : " . implode(', ', DepthContractRegistry::officialDepths()));
            return self::FAILURE;
        }

        if ($domainFilter !== null && ! in_array($domainFilter, self::DOMAIN_CODES, true)) {
            $this->error("Domaine inconnu : {$domainFilter}. Valeurs valides : " . implode(', ', self::DOMAIN_CODES));
            return self::FAILURE;
        }

        $depths  = $depthFilter  !== null ? [$depthFilter]  : DepthContractRegistry::officialDepths();
        $domains = $domainFilter !== null ? [$domainFilter] : self::DOMAIN_CODES;

        $totalCells = count($depths) * count($domains);

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   questions:taxonomy:seed — Warm-up Taxonomy Bank              ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("  Depths ciblés    : " . implode(', ', $depths));
        $this->line("  Domaines ciblés  : " . implode(', ', $domains));
        $this->line("  Cellules totales  : {$totalCells}");
        $this->line("  Sujets / cellule  : {$subjectsPerCell}");
        if ($dryRun) {
            $this->line("  Mode              : <fg=yellow>DRY-RUN</> (aucun appel Gemini)");
        }
        $this->line('');

        if ($dryRun) {
            return $this->runDryAudit($depths, $domains, $subjectsPerCell);
        }

        return $this->runSeed($depths, $domains, $subjectsPerCell);
    }

    // =========================================================================
    // Seeding loop
    // =========================================================================

    private function runSeed(array $depths, array $domains, int $subjectsPerCell): int
    {
        $orchestrator = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            new TaxonomyGeminiClient(),
            new ValidationDominantIdeas(),
        );

        $repo = new TaxonomyBankRepository();

        $successCells = 0;
        $skippedCells = 0;
        $failedCells  = 0;

        foreach ($depths as $depth) {
            foreach ($domains as $domainCode) {
                $cellLabel    = "D{$depth}/{$domainCode}";
                $seededBefore = $repo->countSubjectsWithAvailableIdeas($depth, $domainCode);

                if ($seededBefore >= $subjectsPerCell) {
                    $this->line("  ▶ <fg=cyan>{$cellLabel}</> — <fg=green>déjà {$seededBefore} sujet(s) initialisé(s)</> ≥ cible ({$subjectsPerCell}). Ignoré.");
                    $skippedCells++;
                    continue;
                }

                $this->line("  ▶ <fg=cyan>{$cellLabel}</> — {$seededBefore}/{$subjectsPerCell} sujet(s) initialisé(s). Remplissage…");

                try {
                    $seededAfter = $orchestrator->warmUpCell($depth, $domainCode, $subjectsPerCell);
                } catch (Throwable $e) {
                    $this->warn("    ↳ <fg=red>Erreur</> pour {$cellLabel} : " . $e->getMessage());
                    $failedCells++;
                    continue;
                }

                $added = $seededAfter - $seededBefore;

                if ($seededAfter >= $subjectsPerCell) {
                    $this->line("    ↳ <fg=green>✓</> {$seededAfter} sujet(s) avec idées disponibles (+{$added} ajouté(s)).");
                    $successCells++;
                } elseif ($seededAfter > $seededBefore) {
                    $this->line("    ↳ <fg=yellow>⚠</> {$seededAfter}/{$subjectsPerCell} sujet(s) — domaine partiellement épuisé (+{$added} ajouté(s)).");
                    $successCells++;
                } else {
                    $this->line("    ↳ <fg=red>✗</> Aucun sujet initialisé — domaine épuisé ou erreur Gemini.");
                    $failedCells++;
                }
            }
        }

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   Résumé                                                       ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');
        $this->line("  Cellules remplies    : <fg=green>{$successCells}</>");
        $this->line("  Cellules ignorées    : <fg=cyan>{$skippedCells}</> (déjà à la cible)");
        $this->line("  Cellules en erreur   : " . ($failedCells > 0 ? "<fg=red>{$failedCells}</>" : "<fg=green>0</>"));
        $this->line('');

        if ($failedCells > 0) {
            $this->warn("  ⚠  {$failedCells} cellule(s) n'ont pas pu être initialisées. Vérifier les logs Gemini.");
            return self::FAILURE;
        }

        $this->info('  ✅  Taxonomy Bank warm-up terminé. Le pipeline KRP peut maintenant appeler peekNext() sans banque vide.');
        $this->line('');

        return self::SUCCESS;
    }

    // =========================================================================
    // Dry-run audit
    // =========================================================================

    private function runDryAudit(array $depths, array $domains, int $subjectsPerCell): int
    {
        $repo       = new TaxonomyBankRepository();
        $emptyCells = 0;

        $this->line(sprintf(
            "  %-4s  %-14s  %-10s  %-9s  %-10s  %s",
            'Dept', 'Domaine', 'Sous-dom.', 'Sujets', 'Initialisés', 'État'
        ));
        $this->line('  ' . str_repeat('─', 62));

        foreach ($depths as $depth) {
            foreach ($domains as $domainCode) {
                $subdomains  = $this->countSubdomains($depth, $domainCode);
                $subjects    = $this->countSubjects($depth, $domainCode);
                $seeded      = $repo->countSubjectsWithAvailableIdeas($depth, $domainCode);
                $ok          = $seeded >= $subjectsPerCell;
                $statusLabel = $ok
                    ? "<fg=green>OK ({$seeded}/{$subjectsPerCell})</>"
                    : "<fg=red>VIDE ({$seeded}/{$subjectsPerCell})</>";

                if (! $ok) {
                    $emptyCells++;
                }

                $this->line(sprintf(
                    "  D%-3d  %-14s  %-10d  %-9d  %-10d  %s",
                    $depth, $domainCode, $subdomains, $subjects, $seeded, $statusLabel
                ));
            }
        }

        $this->line('');

        if ($emptyCells === 0) {
            $this->info("  ✅  Toutes les cellules ont atteint la cible ({$subjectsPerCell} sujet(s) initialisé(s)).");
        } else {
            $this->warn("  ⚠  {$emptyCells} cellule(s) sous la cible — relancer sans --dry-run pour les remplir.");
        }

        $this->line('');

        return self::SUCCESS;
    }

    // =========================================================================
    // DB helpers (direct read-only queries for the audit view)
    // =========================================================================

    private function countSubdomains(int $depth, string $domainCode): int
    {
        return (int) DB::table('taxonomy_subdomain_bank')
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->count();
    }

    private function countSubjects(int $depth, string $domainCode): int
    {
        return (int) DB::table('taxonomy_subject_bank as s')
            ->join('taxonomy_subdomain_bank as sd', 'sd.id', '=', 's.subdomain_id')
            ->where('sd.depth', $depth)
            ->where('sd.domain_code', $domainCode)
            ->count();
    }
}
