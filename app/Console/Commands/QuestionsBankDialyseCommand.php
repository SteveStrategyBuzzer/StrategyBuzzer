<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\QuestionIntent;
use App\Models\QuestionGroup;

/**
 * questions:bank:dialyse
 *
 * Dialyse ready_bank noyau par noyau.
 * Workflow : pick (SKIP LOCKED) → audit → corriger → semantic_key → complet
 *
 * Ce que cette commande fait :
 *   - Verrouiller 1 noyau pending via FOR UPDATE SKIP LOCKED
 *   - Afficher ses variantes existantes et leurs traductions
 *   - Calculer variantes_present / variantes_missing
 *   - Proposer une semantic_key
 *   - Auditer longueur question/réponses, depth, cognition, traductions
 *   - Permettre de corriger un champ, bloquer ou valider
 *   - Mettre à jour question_intents (variantes, semantic_key, dialysis_*)
 *
 * Ce que cette commande NE fait PAS :
 *   - Générer des variantes manquantes (Bank Worker uniquement)
 *   - Toucher Node / Redis / Firestore
 *   - Modifier le gameplay
 *
 * Usage :
 *   php artisan questions:bank:dialyse
 *   php artisan questions:bank:dialyse --dry-run
 *   php artisan questions:bank:dialyse --id=42
 *   php artisan questions:bank:dialyse --domain=Histoire
 *   php artisan questions:bank:dialyse --release-stale
 */
class QuestionsBankDialyseCommand extends Command
{
    protected $signature = 'questions:bank:dialyse
        {--id=            : Démarrer depuis un intent_id spécifique}
        {--domain=        : Filtrer par domaine (Art, Histoire, Science, …)}
        {--dry-run        : Afficher sans verrouiller ni modifier}
        {--release-stale  : Libérer les verrous in_progress bloqués (> 2h) et quitter}';

    protected $description = 'Dialyse noyau par noyau : audit → corriger → semantic_key → complet (sans génération)';

    private const LANG_ORDER = ['fr', 'en', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

    private const LANG_NAMES = [
        'fr' => 'Français',  'en' => 'English',   'es' => 'Español',
        'de' => 'Deutsch',   'it' => 'Italiano',  'pt' => 'Português',
        'ru' => 'Русский',   'zh' => '中文',       'ar' => 'العربية',
        'el' => 'Ελληνικά',
    ];

    private const TARGET_VARIANTS = [
        'qcm/recognition',
        'qcm/reasoning',
        'qcm/deceptive_trap',
        'true_false/recognition',
        'true_false/reasoning',
    ];

    private const Q_MAX     = 110;
    private const Q_MAX_ZH  = 60;
    private const Q_MAX_AR  = 75;
    private const A_MAX     = 60;
    private const A_MAX_ZH  = 30;
    private const A_MAX_AR  = 40;
    private const SV_MIN    = 30;
    private const SV_MAX    = 220;

    private const NEG_KEYWORDS = [
        "n'est pas", "ne sont pas", "ne fut pas", "ne peut pas", "ne doit pas",
        "n'a pas", "n'était pas", " sauf ", " excepté ", " hormis ",
        " à l'exception", "aucun de ces", "aucune de ces",
    ];

    private const REASONING_MARKERS = [
        'pourquoi', 'parce que', 'raison', 'cause', 'conséquence',
        'car ', 'donc', 'permet de', 'quel est le lien', 'explique',
        'calcul', 'résulte', 'provoque', 'entraîne',
    ];

    // ─────────────────────────────────────────────────────────
    // Entry point
    // ─────────────────────────────────────────────────────────

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $stale   = (bool) $this->option('release-stale');
        $startId = $this->option('id') ? (int) $this->option('id') : null;
        $domain  = $this->option('domain') ?: null;

        $this->printBanner($dryRun);

        if ($stale) {
            return $this->releaseStale();
        }

        $processed = 0;

        while (true) {
            $intent  = $this->pickNext($startId, $domain, $dryRun);
            $startId = null;

            if (! $intent) {
                $this->line('');
                $this->info('✅  Aucun noyau pending' . ($domain ? " dans le domaine «{$domain}»" : '') . '.');
                break;
            }

            $result = $this->dialyseIntent($intent, $dryRun);

            if (in_array($result, ['complete', 'blocked'])) {
                $processed++;
            }
            if ($result === 'quit') {
                $this->line('');
                $this->info("Session terminée. {$processed} noyau(x) finalisé(s).");
                break;
            }
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────
    // Pick & Lock (FOR UPDATE SKIP LOCKED)
    // ─────────────────────────────────────────────────────────

    private function pickNext(?int $startId, ?string $domain, bool $dryRun): ?QuestionIntent
    {
        if ($dryRun) {
            $q = QuestionIntent::where('dialysis_status', 'pending');
            if ($startId) {
                $q->where('id', '>=', $startId);
            }
            if ($domain) {
                $q->where('domain', $domain);
            }
            return $q->orderBy('id')->first();
        }

        $lockedBy = gethostname() . '-' . getmypid();
        $intent   = null;

        DB::transaction(function () use ($startId, $domain, $lockedBy, &$intent) {
            $where = ["qi.dialysis_status = 'pending'"];
            $binds = [];

            if ($domain) {
                $where[] = 'qi.domain = ?';
                $binds[] = $domain;
            }
            if ($startId) {
                $where[] = 'qi.id >= ?';
                $binds[] = $startId;
            }

            $whereClause = implode(' AND ', $where);
            $row = DB::selectOne(
                "SELECT qi.id FROM question_intents qi WHERE {$whereClause} ORDER BY qi.id LIMIT 1 FOR UPDATE SKIP LOCKED",
                $binds
            );

            if (! $row) {
                return;
            }

            DB::statement(
                "UPDATE question_intents SET dialysis_status = 'in_progress', locked_at = NOW(), locked_by = ? WHERE id = ?",
                [$lockedBy, $row->id]
            );

            $intent = QuestionIntent::find($row->id);
        });

        return $intent;
    }

    private function releaseStale(): int
    {
        $cutoff = now()->subHours(2)->toDateTimeString();
        $count  = DB::affectingStatement(
            "UPDATE question_intents SET dialysis_status = 'pending', locked_at = NULL, locked_by = NULL
             WHERE dialysis_status = 'in_progress' AND locked_at < ?",
            [$cutoff]
        );
        $this->info("🔓  {$count} verrou(s) stale (> 2h) libéré(s) → repassé(s) en pending.");
        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────
    // Main workflow per noyau
    // ─────────────────────────────────────────────────────────

    private function dialyseIntent(QuestionIntent $intent, bool $dryRun): string
    {
        $groups = $this->loadGroups($intent);

        // Calculer variantes_present / variantes_missing
        $varPresent = [];
        foreach ($groups as $g) {
            if ($g->post_review_status === 'ready_bank') {
                $varPresent[] = $g->question_type . '/' . $g->cognitive_type;
            }
        }
        $varPresent = array_values(array_unique($varPresent));
        $varMissing = array_values(array_diff(self::TARGET_VARIANTS, $varPresent));

        if (! $dryRun) {
            $intent->update([
                'variantes_present' => $varPresent,
                'variantes_missing' => $varMissing,
                'variantes_count'   => count($varPresent),
            ]);
        }

        // Display
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->printIntentHeader($intent, $varPresent, $varMissing, $dryRun);
        $this->line('');
        $this->printVariants($groups);
        $this->line('');

        // Quality checks
        $intentChecks  = $this->runIntentChecks($intent, $groups, $varPresent, $varMissing);
        $variantChecks = $this->runAllVariantChecks($groups);
        $this->printChecks($intentChecks, $variantChecks);
        $this->line('');

        return $this->menu($intent, $groups, $varPresent, $varMissing, $intentChecks, $variantChecks, $dryRun);
    }

    private function loadGroups(QuestionIntent $intent): array
    {
        return QuestionGroup::where('question_intent_id', $intent->id)
            ->with('translations')
            ->orderBy('id')
            ->get()
            ->all();
    }

    // ─────────────────────────────────────────────────────────
    // Display helpers
    // ─────────────────────────────────────────────────────────

    private function printBanner(bool $dryRun): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║       DIALYSE READY_BANK  —  Noyau par noyau                   ║');
        $this->line('║  pick → audit → corriger → semantic_key → complet              ║');
        if ($dryRun) {
            $this->line('║  ⚠️   MODE DRY-RUN — aucune modification en base               ║');
        }
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
    }

    private function printIntentHeader(QuestionIntent $intent, array $varPresent, array $varMissing, bool $dryRun): void
    {
        $lockIcon = $dryRun ? '🔍' : '🔒';
        $this->line("  <fg=cyan;options=bold>{$lockIcon} Noyau #{$intent->id}</> — {$intent->intent_key}");

        if ($intent->semantic_key) {
            $this->line("  <fg=green>Clé sémantique :</> {$intent->semantic_key}");
        } else {
            $this->line('  <fg=yellow>Clé sémantique :</> ⚠️  non définie (legacy)');
        }

        $this->line('');
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['Domaine / Sub',   $intent->domain . ' / ' . ($intent->sub_domain ?: '—')],
                ['Depth',           $intent->difficulty_depth . '/10'],
                ['Subject',         $intent->subject ?: '—'],
                ['Angle large',     $intent->angle_large ?: '—'],
                ['Micro-angle',     $intent->micro_angle ?: '—'],
                ['concept_family',  $intent->concept_family ?: '—'],
                ['source',          $intent->source ?: '—'],
                ['Variantes',       count($varPresent) . '/5 présentes'],
                ['Manquantes',      count($varMissing) > 0 ? implode(', ', $varMissing) : '— aucune ✅'],
                ['Actions dialyse', (string) ($intent->dialysis_action_count ?? 0)],
            ]
        );
    }

    private function printVariants(array $groups): void
    {
        $this->line('<fg=yellow;options=bold>📦  VARIANTES EXISTANTES</>');
        $this->line('');

        if (empty($groups)) {
            $this->warn('  Aucun question_group lié à ce noyau.');
            return;
        }

        foreach ($groups as $i => $group) {
            $translations = $group->translations->keyBy('language');
            $fr           = $translations->get('fr');
            $langCount    = $translations->count();

            $statusIcon = match ($group->post_review_status) {
                'ready_bank'        => '<fg=green>✅</>',
                'blocked_critical'  => '<fg=red>🚫</>',
                'correction_needed' => '<fg=yellow>⚠️</>',
                default             => '<fg=gray>—</>',
            };

            $this->line(
                "  {$statusIcon} <fg=cyan;options=bold>[" . ($i + 1) . "] {$group->question_type} / {$group->cognitive_type}</>"
                . " — group #{$group->id} — {$langCount}/10 langs"
            );

            if ($fr) {
                $qLen       = mb_strlen($fr->question_text ?? '');
                $correct    = strtoupper((string) $fr->correct_answer_key);
                $correctTxt = match ($correct) {
                    'A' => $fr->answer_a, 'B' => $fr->answer_b,
                    'C' => $fr->answer_c, 'D' => $fr->answer_d,
                    default => '?',
                };
                $preview = mb_substr($fr->question_text ?? '', 0, 95);
                if ($qLen > 95) {
                    $preview .= '…';
                }
                $this->line("     <fg=white>Q({$qLen}ch): {$preview}</>");
                $this->line("     <fg=green>✅ [{$correct}]: {$correctTxt}</>");
            } else {
                $this->warn('     ⚠️  Traduction FR manquante');
            }

            // Lang coverage bar (10 blocks)
            $bar = '';
            foreach (self::LANG_ORDER as $lang) {
                $bar .= $translations->has($lang) ? '<fg=green>█</>' : '<fg=red>░</>';
            }
            $this->line("     Langs [{$bar}] " . implode(' ', self::LANG_ORDER));
            $this->line('');
        }
    }

    // ─────────────────────────────────────────────────────────
    // Quality checks
    // ─────────────────────────────────────────────────────────

    private function runIntentChecks(QuestionIntent $intent, array $groups, array $varPresent, array $varMissing): array
    {
        $checks = [];

        // Clé sémantique
        $checks['semantic_key'] = [
            'label'  => 'Clé sémantique',
            'ok'     => ! empty($intent->semantic_key),
            'detail' => $intent->semantic_key ?: '⚠️ Non définie — utiliser [K]',
        ];

        // 5/5 variantes
        $checks['variantes_complete'] = [
            'label'  => 'Variantes complètes (5/5)',
            'ok'     => count($varMissing) === 0,
            'detail' => count($varMissing) === 0
                ? '5/5 ✅'
                : count($varPresent) . '/5 — manquantes : ' . implode(', ', $varMissing),
        ];

        // Depth cohérent entre variantes
        if (count($groups) > 1) {
            $depths = array_unique(array_map(fn ($g) => $g->difficulty_depth, $groups));
            $checks['depth_coherent'] = [
                'label'  => 'Depth uniforme entre variantes',
                'ok'     => count($depths) === 1,
                'detail' => count($depths) === 1
                    ? 'depth=' . reset($depths)
                    : '⚠️ Depths différents : ' . implode(', ', $depths),
            ];
        }

        // Métadonnées noyau
        $metaOk = ! empty($intent->subject) && ! empty($intent->angle_large);
        $checks['metadata'] = [
            'label'  => 'Métadonnées (subject + angle)',
            'ok'     => $metaOk,
            'detail' => $metaOk
                ? "'{$intent->subject}' · '{$intent->angle_large}'"
                : '⚠️ subject ou angle_large vide',
        ];

        return $checks;
    }

    private function runAllVariantChecks(array $groups): array
    {
        $all = [];
        foreach ($groups as $group) {
            $translations = $group->translations->keyBy('language')->all();
            $all[$group->id] = [
                'group'  => $group,
                'checks' => $this->runVariantChecks($group, $translations),
            ];
        }
        return $all;
    }

    private function runVariantChecks(QuestionGroup $group, array $translations): array
    {
        $checks = [];
        $fr     = $translations['fr'] ?? null;

        // 1 — 10 langues présentes
        $missing = array_filter(self::LANG_ORDER, fn ($l) => ! isset($translations[$l]));
        $checks['lang_coverage'] = [
            'label'  => '10 langues',
            'ok'     => count($missing) === 0,
            'detail' => count($missing) === 0 ? '10/10' : 'Manquantes : ' . implode(', ', $missing),
        ];

        // 2 — Longueur question (toutes langues)
        $qIssues = [];
        foreach ($translations as $lang => $t) {
            $max = match ($lang) {
                'zh'    => self::Q_MAX_ZH,
                'ar'    => self::Q_MAX_AR,
                default => self::Q_MAX,
            };
            $len = mb_strlen($t->question_text ?? '');
            if ($len > $max) {
                $qIssues[] = "{$lang}:{$len}>{$max}";
            }
        }
        $checks['question_length'] = [
            'label'  => 'Longueur question',
            'ok'     => empty($qIssues),
            'detail' => empty($qIssues) ? 'OK toutes langues' : implode(', ', array_slice($qIssues, 0, 4)),
        ];

        // 3 — Longueur réponses (toutes langues)
        $aIssues = [];
        foreach ($translations as $lang => $t) {
            $max = match ($lang) {
                'zh'    => self::A_MAX_ZH,
                'ar'    => self::A_MAX_AR,
                default => self::A_MAX,
            };
            foreach (['answer_a', 'answer_b', 'answer_c', 'answer_d'] as $f) {
                $len = mb_strlen($t->$f ?? '');
                if ($len > $max) {
                    $aIssues[] = "{$lang}.{$f}:{$len}";
                }
            }
        }
        $checks['answer_length'] = [
            'label'  => 'Longueur réponses',
            'ok'     => empty($aIssues),
            'detail' => empty($aIssues) ? 'OK toutes langues' : implode(', ', array_slice($aIssues, 0, 4)),
        ];

        // 4 — Qualité réponses FR (4 uniques, clé valide)
        if ($fr) {
            $answers = array_filter([$fr->answer_a, $fr->answer_b, $fr->answer_c, $fr->answer_d]);
            $correct = strtoupper((string) $fr->correct_answer_key);
            $count   = count($answers);
            $unique  = count(array_unique($answers));
            $keyOk   = in_array($correct, ['A', 'B', 'C', 'D']);
            $ansOk   = $count >= 2 && $keyOk && ($unique === $count);
            $checks['answer_quality'] = [
                'label'  => 'Qualité réponses (FR)',
                'ok'     => $ansOk,
                'detail' => $ansOk
                    ? "{$count} réponses, clé={$correct}, toutes uniques"
                    : "⚠️ {$count} réponses, {$unique} uniques, clé={$correct}",
            ];
        }

        // 5 — Formulation négative (FR)
        $negFound = '';
        if ($fr) {
            $lower = mb_strtolower($fr->question_text ?? '');
            foreach (self::NEG_KEYWORDS as $kw) {
                if (str_contains($lower, $kw)) {
                    $negFound = $kw;
                    break;
                }
            }
        }
        $checks['negative_framing'] = [
            'label'  => 'Non-négatif (FR)',
            'ok'     => $negFound === '',
            'detail' => $negFound === '' ? 'OK' : "Mot interdit: \"{$negFound}\"",
        ];

        // 6 — Saviez-vous (FR)
        $svLen = $fr ? mb_strlen($fr->saviez_vous ?? '') : 0;
        $checks['funfact'] = [
            'label'  => 'Saviez-vous (FR)',
            'ok'     => $svLen >= self::SV_MIN && $svLen <= self::SV_MAX,
            'detail' => "{$svLen} chars (min " . self::SV_MIN . ", max " . self::SV_MAX . ")"
                . ($svLen === 0 ? ' — VIDE' : ''),
        ];

        // 7 — Cohérence cognitive
        $cogOk     = true;
        $cogDetail = 'OK';
        if ($fr && $group->cognitive_type === 'reasoning') {
            $lower   = mb_strtolower(($fr->question_text ?? '') . ' ' . ($fr->explanation ?? ''));
            $markers = array_filter(self::REASONING_MARKERS, fn ($m) => str_contains($lower, $m));
            $cogOk   = count($markers) > 0;
            $cogDetail = $cogOk
                ? 'Marqueurs : ' . implode(', ', array_slice($markers, 0, 2))
                : '⚠️ Aucun marqueur de raisonnement';
        } elseif ($fr && $group->cognitive_type === 'deceptive_trap') {
            $cogDetail = 'deceptive_trap — vérification manuelle';
        }
        $checks['cognitive'] = [
            'label'  => 'Cohérence cognitive (' . $group->cognitive_type . ')',
            'ok'     => $cogOk,
            'detail' => $cogDetail,
        ];

        return $checks;
    }

    private function printChecks(array $intentChecks, array $variantCheckSets): void
    {
        $this->line('<fg=yellow;options=bold>🔍  VÉRIFICATIONS</>');
        $this->line('');
        $this->line('  <fg=cyan>── Noyau ──</>');

        $allOk = true;
        foreach ($intentChecks as $check) {
            $icon = $check['ok'] ? '<fg=green>✅</>' : '<fg=red>❌</>';
            $this->line('  ' . $icon . '  ' . str_pad($check['label'], 36) . $check['detail']);
            if (! $check['ok']) {
                $allOk = false;
            }
        }

        foreach ($variantCheckSets as $data) {
            $g = $data['group'];
            $this->line('');
            $this->line("  <fg=cyan>── Variante #{$g->id} : {$g->question_type}/{$g->cognitive_type} ──</>");
            foreach ($data['checks'] as $check) {
                $icon = $check['ok'] ? '<fg=green>✅</>' : '<fg=red>❌</>';
                $this->line('     ' . $icon . '  ' . str_pad($check['label'], 32) . $check['detail']);
                if (! $check['ok']) {
                    $allOk = false;
                }
            }
        }

        $this->line('');
        if ($allOk) {
            $this->line('  <fg=green;options=bold>✅  Toutes les vérifications passées.</>');
        } else {
            $this->warn('  ⚠️  Des vérifications ont échoué — corriger avant de valider.');
        }
    }

    // ─────────────────────────────────────────────────────────
    // Interactive menu
    // ─────────────────────────────────────────────────────────

    private function menu(
        QuestionIntent $intent,
        array $groups,
        array $varPresent,
        array $varMissing,
        array $intentChecks,
        array $variantChecks,
        bool $dryRun
    ): string {
        $this->line('<fg=yellow;options=bold>ACTION :</>');
        $this->line('  <fg=cyan>[K]</>  Clé sémantique — définir ou corriger semantic_key');
        $this->line('  <fg=cyan>[C]</>  Corriger — éditer un champ dans une variante');
        $this->line('  <fg=cyan>[V]</>  Valider — marquer le noyau COMPLETE');
        $this->line('  <fg=cyan>[B]</>  Bloquer — marquer blocked');
        $this->line('  <fg=cyan>[S]</>  Sauter — libérer et passer au noyau suivant');
        $this->line('  <fg=cyan>[Q]</>  Quitter');
        if ($dryRun) {
            $this->line('  <fg=yellow>  [dry-run : aucune écriture]</>');
        }
        $this->line('');

        $action = strtoupper(trim((string) $this->ask('Choix')));

        return match ($action) {
            'K'     => $this->doSemanticKey($intent, $groups, $varPresent, $varMissing, $dryRun),
            'C'     => $this->doCorrect($intent, $groups, $varPresent, $varMissing, $dryRun),
            'V'     => $this->doComplete($intent, $varPresent, $varMissing, $dryRun),
            'B'     => $this->doBlock($intent, $dryRun),
            'S'     => $this->doRelease($intent, $dryRun),
            'Q'     => 'quit',
            default => $this->invalidChoice($intent, $groups, $varPresent, $varMissing, $intentChecks, $variantChecks, $dryRun),
        };
    }

    private function invalidChoice(
        QuestionIntent $intent,
        array $groups,
        array $varPresent,
        array $varMissing,
        array $intentChecks,
        array $variantChecks,
        bool $dryRun
    ): string {
        $this->warn('Choix invalide. Entrez K, C, V, B, S ou Q.');
        return $this->menu($intent, $groups, $varPresent, $varMissing, $intentChecks, $variantChecks, $dryRun);
    }

    // ─────────────────────────────────────────────────────────
    // Action : Clé sémantique
    // ─────────────────────────────────────────────────────────

    private function doSemanticKey(
        QuestionIntent $intent,
        array $groups,
        array $varPresent,
        array $varMissing,
        bool $dryRun
    ): string {
        $this->line('');
        $this->line('<fg=yellow;options=bold>🔑  DÉFINITION SEMANTIC_KEY</>');
        $this->line("  intent_key actuel  : <fg=cyan>{$intent->intent_key}</>");
        $this->line("  semantic_key actuel: " . ($intent->semantic_key
            ? "<fg=green>{$intent->semantic_key}</>"
            : '<fg=yellow>non défini</>'));
        $this->line('');
        $this->line('  Format : lowercase, tirets, 3-60 chars. Ex : histoire-declaration-independance-usa');
        $this->line('');

        $suggestion = $this->suggestSemanticKey($intent);
        $this->line("  Suggestion auto : <fg=cyan>{$suggestion}</>");
        $this->line('');

        $newKey = strtolower(trim((string) $this->ask('Clé sémantique (Entrée = utiliser la suggestion)', $suggestion)));

        if (empty($newKey)) {
            $this->warn('Aucune clé saisie.');
            return $this->menu($intent, $groups, $varPresent, $varMissing, [], [], $dryRun);
        }

        if (! preg_match('/^[a-z0-9][a-z0-9\-]{2,59}$/', $newKey)) {
            $this->warn("Format invalide : '{$newKey}'. Uniquement a-z, 0-9, tirets. 3 à 60 chars.");
            return $this->doSemanticKey($intent, $groups, $varPresent, $varMissing, $dryRun);
        }

        if (! $dryRun) {
            $conflict = DB::selectOne(
                'SELECT id FROM question_intents WHERE semantic_key = ? AND id != ?',
                [$newKey, $intent->id]
            );
            if ($conflict) {
                $this->warn("❌  Clé déjà utilisée par le noyau #{$conflict->id}.");
                return $this->doSemanticKey($intent, $groups, $varPresent, $varMissing, $dryRun);
            }

            DB::transaction(function () use ($intent, $newKey) {
                $intent->update(['semantic_key' => $newKey]);
                DB::statement(
                    'UPDATE question_groups SET question_intent_key = ? WHERE question_intent_id = ?',
                    [$newKey, $intent->id]
                );
            });

            $this->bumpActionCount($intent);
            $this->info("✅  semantic_key = {$newKey}");
            $this->info('    question_groups.question_intent_key mis à jour (transaction atomique).');
        } else {
            $this->info("[DRY-RUN] Aurait défini semantic_key = {$newKey}");
        }

        return $this->menu($intent, $groups, $varPresent, $varMissing, [], [], $dryRun);
    }

    private function suggestSemanticKey(QuestionIntent $intent): string
    {
        $parts = array_filter([
            $intent->domain,
            $intent->subject,
            $intent->angle_large,
        ]);

        if (empty($parts)) {
            $raw = str_replace(['legacy_', '_'], ['', '-'], $intent->intent_key);
            return mb_substr(Str::slug($raw, '-'), 0, 60);
        }

        return mb_substr(Str::slug(implode(' ', $parts), '-'), 0, 60);
    }

    // ─────────────────────────────────────────────────────────
    // Action : Corriger un champ
    // ─────────────────────────────────────────────────────────

    private function doCorrect(
        QuestionIntent $intent,
        array $groups,
        array $varPresent,
        array $varMissing,
        bool $dryRun
    ): string {
        if (empty($groups)) {
            $this->warn('Aucune variante à corriger pour ce noyau.');
            return $this->menu($intent, $groups, $varPresent, $varMissing, [], [], $dryRun);
        }

        // Sélection de la variante (si plusieurs)
        $group = null;
        if (count($groups) === 1) {
            $group = $groups[0];
        } else {
            $this->line('');
            $this->line('<fg=yellow>Quelle variante corriger ?</>');
            foreach ($groups as $i => $g) {
                $this->line("  [" . ($i + 1) . "] {$g->question_type}/{$g->cognitive_type} — group #{$g->id}");
            }
            $this->line('  [0] Retour');
            $idx = (int) $this->ask('Variante');
            if ($idx === 0 || ! isset($groups[$idx - 1])) {
                return $this->menu($intent, $groups, $varPresent, $varMissing, [], [], $dryRun);
            }
            $group = $groups[$idx - 1];
        }

        // Sélection du champ
        $fieldMap = [
            '1' => 'question_text', '2' => 'answer_a', '3' => 'answer_b',
            '4' => 'answer_c',      '5' => 'answer_d', '6' => 'correct_answer_key',
            '7' => 'explanation',   '8' => 'saviez_vous',
        ];
        $this->line('');
        $this->line('<fg=yellow>Quel champ corriger ?</>');
        foreach ($fieldMap as $k => $f) {
            $this->line("  [{$k}] {$f}");
        }
        $this->line('  [0] Retour');
        $fieldChoice = (string) $this->ask('Champ');

        if ($fieldChoice === '0') {
            return $this->menu($intent, $groups, $varPresent, $varMissing, [], [], $dryRun);
        }

        $field = $fieldMap[$fieldChoice] ?? null;
        if (! $field) {
            $this->warn('Choix invalide.');
            return $this->doCorrect($intent, $groups, $varPresent, $varMissing, $dryRun);
        }

        // Sélection de la langue
        $lang = strtolower(trim((string) $this->ask('Langue (fr/en/es/de/it/pt/ru/zh/ar/el)')));
        $t    = $group->translations->where('language', $lang)->first();
        if (! $t) {
            $this->warn("Traduction '{$lang}' introuvable pour la variante #{$group->id}.");
            return $this->doCorrect($intent, $groups, $varPresent, $varMissing, $dryRun);
        }

        // Édition
        $current  = (string) ($t->$field ?? '');
        $this->line("  Valeur actuelle : <fg=yellow>{$current}</>");
        $newValue = (string) $this->ask('Nouvelle valeur (Entrée = annuler)', $current);

        if ($newValue !== '' && $newValue !== $current) {
            if ($field === 'correct_answer_key') {
                $newValue = strtoupper($newValue);
            }
            if (! $dryRun) {
                $t->update([$field => $newValue]);
                $this->bumpActionCount($intent);
                $intent->update(['dialysis_last_issue' => "Corrigé: {$field} [{$lang}] group#{$group->id}"]);
                $this->info("✅  {$field} ({$lang}) mis à jour sur group #{$group->id}.");
            } else {
                $this->info("[DRY-RUN] Aurait corrigé {$field} [{$lang}] sur group #{$group->id}");
            }
        } else {
            $this->line('Aucune modification.');
        }

        if ($this->confirm('Corriger un autre champ ?', false)) {
            // Recharger les traductions pour refléter les modifications
            foreach ($groups as $g) {
                $g->load('translations');
            }
            return $this->doCorrect($intent, $groups, $varPresent, $varMissing, $dryRun);
        }

        return $this->menu($intent, $groups, $varPresent, $varMissing, [], [], $dryRun);
    }

    // ─────────────────────────────────────────────────────────
    // Action : Valider (COMPLETE)
    // ─────────────────────────────────────────────────────────

    private function doComplete(
        QuestionIntent $intent,
        array $varPresent,
        array $varMissing,
        bool $dryRun
    ): string {
        if (count($varMissing) > 0) {
            $this->warn('⚠️  Le noyau n\'a pas ses 5 variantes complètes.');
            $this->line('   Manquantes : ' . implode(', ', $varMissing));
            if (! $this->confirm('Marquer COMPLETE quand même ?', false)) {
                return $this->menu($intent, [], $varPresent, $varMissing, [], [], $dryRun);
            }
        }

        $summary = trim((string) $this->ask('Résumé court de la dialyse (max 500 chars — Entrée pour passer)', ''));
        if (mb_strlen($summary) > 500) {
            $summary = mb_substr($summary, 0, 500);
        }

        if (! $dryRun) {
            $intent->update([
                'dialysis_status'     => 'complete',
                'dialysed_at'         => now(),
                'locked_at'           => null,
                'locked_by'           => null,
                'dialysis_summary'    => $summary ?: null,
                'dialysis_last_issue' => null,
            ]);
            $this->info("✅  Noyau #{$intent->id} marqué <fg=green>COMPLETE</>.");
        } else {
            $this->info('[DRY-RUN] Aurait marqué COMPLETE.');
        }

        return 'complete';
    }

    // ─────────────────────────────────────────────────────────
    // Action : Bloquer
    // ─────────────────────────────────────────────────────────

    private function doBlock(QuestionIntent $intent, bool $dryRun): string
    {
        $reason = trim((string) $this->ask('Raison du blocage (max 255 chars)'));
        $reason = mb_substr($reason, 0, 255);

        if (! $dryRun) {
            $intent->update([
                'dialysis_status'     => 'blocked',
                'locked_at'           => null,
                'locked_by'           => null,
                'dialysis_last_issue' => $reason ?: null,
            ]);
            $this->warn("🚫  Noyau #{$intent->id} marqué <fg=red>blocked</>.");
        } else {
            $this->info("[DRY-RUN] Aurait marqué blocked : {$reason}");
        }

        return 'blocked';
    }

    // ─────────────────────────────────────────────────────────
    // Action : Sauter (libérer le verrou)
    // ─────────────────────────────────────────────────────────

    private function doRelease(QuestionIntent $intent, bool $dryRun): string
    {
        if (! $dryRun) {
            $intent->update([
                'dialysis_status' => 'pending',
                'locked_at'       => null,
                'locked_by'       => null,
            ]);
        }
        $this->line('→ Noyau libéré (pending), passage au suivant.');
        return 'next';
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function bumpActionCount(QuestionIntent $intent): void
    {
        DB::statement(
            'UPDATE question_intents SET dialysis_action_count = dialysis_action_count + 1 WHERE id = ?',
            [$intent->id]
        );
    }
}
