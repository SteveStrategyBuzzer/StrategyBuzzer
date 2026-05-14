<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * questions:bank:audit-content
 *
 * PATCH GROUP QUALITÉ CONTENU — PATCH E
 *
 * Scans every existing FR translation in question_translations and classifies
 * each group by the violations it contains. Does NOT delete anything.
 *
 * Violations detected:
 *   - question_too_long   : question_text > threshold
 *   - answer_too_long     : any answer_a/b/c/d > threshold
 *   - saviez_vous_too_long: saviez_vous > threshold
 *   - negative_framing    : question contains forbidden negative keywords
 *
 * With --invalidate the command sets validated=false on violating groups
 * so the picker skips them and the bank worker regenerates them in due course.
 * Without --invalidate it is fully read-only (safe to run at any time).
 *
 * Usage:
 *   php artisan questions:bank:audit-content            # dry-run report
 *   php artisan questions:bank:audit-content --invalidate   # mark bad groups
 *   php artisan questions:bank:audit-content --show-samples # print examples
 */
class QuestionsBankAuditContentCommand extends Command
{
    protected $signature = 'questions:bank:audit-content
        {--invalidate : Mark violating groups as validated=false (picker skips them)}
        {--samples : Print up to 3 sample violating rows per category}
        {--q-max=250 : question_text length threshold for audit (default lenient for existing DB)}
        {--a-max=130 : answer length threshold for audit}
        {--sv-max=300 : saviez_vous length threshold for audit}';

    protected $description = 'Audit existing translations for content quality violations (length, negative framing). PATCH GROUP QUALITÉ CONTENU — E.';

    private const NEG_KEYWORDS = [
        "n'est pas", "ne sont pas", "ne fut pas", "ne peut pas",
        "ne doit pas", "n'a pas", "n'était pas",
        " sauf ", " excepté ", " hormis ", " à l'exception",
        "aucun de ces", "aucune de ces", "lequel ne",
        "laquelle ne", "lesquels ne",
    ];

    public function handle(): int
    {
        $qMax  = (int) $this->option('q-max');
        $aMax  = (int) $this->option('a-max');
        $svMax = (int) $this->option('sv-max');
        $invalidate   = (bool) $this->option('invalidate');
        $showSamples  = (bool) $this->option('samples');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║    AUDIT QUALITÉ CONTENU — question_translations (FR)       ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line("  Seuils : question_text > {$qMax}c  |  answer > {$aMax}c  |  saviez_vous > {$svMax}c");
        $this->line($invalidate ? '  MODE : INVALIDATE — groupes problématiques marqués validated=false' : '  MODE : DRY-RUN (lecture seule)');
        $this->line('');

        // Load all FR translations with their group info in one query.
        $rows = DB::table('question_translations as qt')
            ->join('question_groups as qg', 'qg.id', '=', 'qt.question_group_id')
            ->where('qt.language', 'fr')
            ->select(
                'qg.id as group_id',
                'qg.sub_domain',
                'qg.difficulty_depth',
                'qg.cognitive_type',
                'qg.validated',
                'qt.question_text',
                'qt.answer_a',
                'qt.answer_b',
                'qt.answer_c',
                'qt.answer_d',
                'qt.saviez_vous'
            )
            ->get();

        $total = $rows->count();
        $this->line("  Total groupes avec traduction FR : {$total}");
        $this->line('');

        // Classify violations.
        $violations = [
            'question_too_long'    => [],
            'answer_too_long'      => [],
            'saviez_vous_too_long' => [],
            'negative_framing'     => [],
        ];

        foreach ($rows as $r) {
            $qLen  = mb_strlen(trim((string) $r->question_text));
            $svLen = mb_strlen(trim((string) $r->saviez_vous));
            $aLens = [
                'answer_a' => mb_strlen(trim((string) ($r->answer_a ?? ''))),
                'answer_b' => mb_strlen(trim((string) ($r->answer_b ?? ''))),
                'answer_c' => mb_strlen(trim((string) ($r->answer_c ?? ''))),
                'answer_d' => mb_strlen(trim((string) ($r->answer_d ?? ''))),
            ];
            $maxALen   = max($aLens);
            $maxAField = array_search($maxALen, $aLens);

            if ($qLen > $qMax) {
                $violations['question_too_long'][] = [
                    'group_id'  => $r->group_id,
                    'sub_domain'=> $r->sub_domain,
                    'depth'     => $r->difficulty_depth,
                    'len'       => $qLen,
                    'sample'    => mb_substr(trim((string) $r->question_text), 0, 100),
                ];
            }

            if ($maxALen > $aMax) {
                $violations['answer_too_long'][] = [
                    'group_id'  => $r->group_id,
                    'sub_domain'=> $r->sub_domain,
                    'depth'     => $r->difficulty_depth,
                    'len'       => $maxALen,
                    'field'     => $maxAField,
                    'sample'    => mb_substr(trim((string) ($r->{$maxAField} ?? '')), 0, 100),
                ];
            }

            if ($svLen > $svMax) {
                $violations['saviez_vous_too_long'][] = [
                    'group_id'  => $r->group_id,
                    'sub_domain'=> $r->sub_domain,
                    'depth'     => $r->difficulty_depth,
                    'len'       => $svLen,
                    'sample'    => mb_substr(trim((string) $r->saviez_vous), 0, 100),
                ];
            }

            $qLower = mb_strtolower(trim((string) $r->question_text));
            foreach (self::NEG_KEYWORDS as $kw) {
                if (str_contains($qLower, mb_strtolower($kw))) {
                    $violations['negative_framing'][] = [
                        'group_id'  => $r->group_id,
                        'sub_domain'=> $r->sub_domain,
                        'depth'     => $r->difficulty_depth,
                        'keyword'   => $kw,
                        'sample'    => mb_substr(trim((string) $r->question_text), 0, 100),
                    ];
                    break;
                }
            }
        }

        // Collect all violating group IDs (unique, union).
        $allViolatingIds = [];
        foreach ($violations as $category => $items) {
            foreach ($items as $item) {
                $allViolatingIds[$item['group_id']] = true;
            }
        }
        $totalViolating = count($allViolatingIds);

        // ── Report ──────────────────────────────────────────────────────────
        $labels = [
            'question_too_long'    => 'Guard 11 — question_text trop long',
            'answer_too_long'      => 'Guard 12 — réponse trop longue',
            'saviez_vous_too_long' => 'Guard 13 — saviez_vous trop long',
            'negative_framing'     => 'Guard 14 — formulation négative/ambiguë',
        ];

        foreach ($violations as $category => $items) {
            $n    = count($items);
            $pct  = $total > 0 ? round($n / $total * 100, 1) : 0;
            $flag = $n > 0 ? ($n > 50 ? ' ⛔' : ' ⚠') : ' ✓';
            $this->line("  [{$labels[$category]}]");
            $this->line("    Violations : {$n} / {$total} ({$pct}%){$flag}");

            if ($showSamples && $n > 0) {
                foreach (array_slice($items, 0, 3) as $v) {
                    $len   = $v['len'] ?? '';
                    $extra = isset($v['keyword']) ? "kw=\"{$v['keyword']}\"" : "len={$len}";
                    $this->line("    ↳ group={$v['group_id']} depth={$v['depth']} sub={$v['sub_domain']} {$extra}");
                    $this->line("      «{$v['sample']}»");
                }
            }

            // Sub-domain breakdown for this category
            if ($n > 0) {
                $byDomain = [];
                foreach ($items as $v) {
                    $byDomain[$v['sub_domain']] = ($byDomain[$v['sub_domain']] ?? 0) + 1;
                }
                arsort($byDomain);
                $parts = [];
                foreach (array_slice($byDomain, 0, 5, true) as $dom => $cnt) {
                    $parts[] = "{$dom}:{$cnt}";
                }
                $this->line('    Top sub-domains: ' . implode('  ', $parts));
            }

            $this->line('');
        }

        // ── Summary ─────────────────────────────────────────────────────────
        $pct = $total > 0 ? round($totalViolating / $total * 100, 1) : 0;
        $this->line("  ┌─ RÉSUMÉ ────────────────────────────────────────────────────┐");
        $this->line("  │  Groupes avec ≥1 violation : {$totalViolating} / {$total} ({$pct}%)");
        $this->line("  │  Groupes sans violation     : " . ($total - $totalViolating) . " / {$total}");
        $this->line("  └────────────────────────────────────────────────────────────┘");
        $this->line('');

        // ── Invalidate ──────────────────────────────────────────────────────
        if ($invalidate && $totalViolating > 0) {
            $ids = array_keys($allViolatingIds);

            if (!$this->confirm("Confirmer la mise à validated=false de {$totalViolating} groupes ?", false)) {
                $this->warn('  Annulé — aucune modification.');
                return self::SUCCESS;
            }

            $affected = DB::table('question_groups')
                ->whereIn('id', $ids)
                ->where('validated', true)
                ->update(['validated' => false, 'updated_at' => now()]);

            $this->info("  ✓ {$affected} groupes marqués validated=false.");
            $this->line('  Le worker les régénérera progressivement avec les nouveaux guards.');
        } elseif ($invalidate && $totalViolating === 0) {
            $this->info('  Aucune violation — rien à invalider. ✓');
        } else {
            $this->line('  Dry-run terminé — aucune modification. Relancer avec --invalidate pour agir.');
        }

        $this->line('');
        $this->line('  Relancer à tout moment : php artisan questions:bank:audit-content --show-samples');
        $this->line('');

        return self::SUCCESS;
    }
}
