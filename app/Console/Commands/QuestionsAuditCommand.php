<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;

/**
 * questions:bank:audit
 *
 * Workflow interactif d'audit des noyaux ready_bank :
 *   1. Sortir 1 noyau (next unaudited, ou --id=X)
 *   2. Voir toutes ses variantes de langue
 *   3. Vérifier : depth, cognition, gameplay, longueur, piège, qualité réponses, funfact, cohérence langues
 *   4. Corriger
 *   5. Reclassifier si nécessaire
 *   6. Valider
 *   7. Passer au noyau suivant
 *   8. Marquer noyau audité
 *
 * Usage :
 *   php artisan questions:bank:audit
 *   php artisan questions:bank:audit --id=1234
 *   php artisan questions:bank:audit --domain=Histoire
 *   php artisan questions:bank:audit --all   (inclut les déjà audités)
 */
class QuestionsAuditCommand extends Command
{
    protected $signature = 'questions:bank:audit
        {--id=      : Démarrer depuis un group_id spécifique}
        {--domain=  : Filtrer par domaine (Art, Histoire, Science, …)}
        {--all      : Inclure les noyaux déjà audités}';

    protected $description = 'Audit interactif : pull → vérifier → corriger → valider → marquer audité';

    private const LANG_ORDER = ['fr', 'en', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

    private const LANG_NAMES = [
        'fr' => 'Français',  'en' => 'English',   'es' => 'Español',
        'de' => 'Deutsch',   'it' => 'Italiano',  'pt' => 'Português',
        'ru' => 'Русский',   'zh' => '中文',       'ar' => 'العربية',
        'el' => 'Ελληνικά',
    ];

    private const Q_MAX    = 110;
    private const Q_MAX_ZH = 60;
    private const Q_MAX_AR = 75;
    private const A_MAX    = 60;
    private const A_MAX_ZH = 30;
    private const A_MAX_AR = 40;
    private const SV_MIN   = 30;
    private const SV_MAX   = 220;
    private const SV_MAX_ZH = 100;
    private const SV_MAX_AR = 140;

    private const NEG_KEYWORDS = [
        "n'est pas", "ne sont pas", "ne fut pas", "ne peut pas",
        "ne doit pas", "n'a pas", "n'était pas", "jamais",
        " sauf ", " excepté ", " hormis ", " à l'exception",
        "aucun de ces", "aucune de ces", "lequel ne",
        "laquelle ne", "lesquels ne",
    ];

    private const REASONING_MARKERS = [
        'pourquoi', 'parce que', 'raison', 'cause', 'conséquence',
        'car ', 'donc', 'permet de', 'quel est le lien', 'explique',
        'calcul', 'résulte', 'provoque', 'entraîne',
    ];

    public function handle(): int
    {
        $this->printBanner();

        $startId        = $this->option('id') ? (int) $this->option('id') : null;
        $domainFilter   = $this->option('domain') ?: null;
        $includeAudited = (bool) $this->option('all');

        $audited = 0;

        while (true) {
            $group = $this->pickNext($startId, $domainFilter, $includeAudited);
            $startId = null;

            if (! $group) {
                $this->line('');
                $this->info('✅  Aucun noyau à auditer' . ($domainFilter ? " dans le domaine {$domainFilter}" : '') . '.');
                break;
            }

            $action = $this->auditGroup($group);
            if ($action === 'validated' || $action === 'blocked') {
                $audited++;
            }
            if ($action === 'quit') {
                $this->line('');
                $this->info("Audit terminé. {$audited} noyau(x) traité(s) cette session.");
                break;
            }
        }

        return self::SUCCESS;
    }

    private function pickNext(?int $startId, ?string $domain, bool $includeAudited): ?QuestionGroup
    {
        $q = QuestionGroup::with('translations')
            ->where('post_review_status', 'ready_bank');

        if (! $includeAudited) {
            $q->whereNull('audited_at');
        }
        if ($startId) {
            $q->where('id', '>=', $startId);
        }
        if ($domain) {
            $q->where('domain', $domain);
        }

        return $q->orderBy('id')->first();
    }

    private function auditGroup(QuestionGroup $group): string
    {
        $translations = [];
        foreach ($group->translations as $t) {
            $translations[$t->language] = $t;
        }

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->printHeader($group);
        $this->line('');
        $this->printFrench($translations['fr'] ?? null, $group);
        $this->line('');
        $checks = $this->runChecks($group, $translations);
        $this->printChecks($checks);
        $this->line('');
        $this->printLangTable($translations);
        $this->line('');

        return $this->menu($group, $translations, $checks);
    }

    private function printBanner(): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║       AUDIT READY_BANK  —  Workflow interactif noyaux           ║');
        $this->line('║  pull → vérifier → corriger → valider → marquer audité         ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
    }

    private function printHeader(QuestionGroup $group): void
    {
        $auditLabel = $group->audited_at
            ? ' | ✅ Audité le ' . $group->audited_at->format('Y-m-d H:i')
            : ' | 🔵 Non audité';

        $this->line("  <fg=cyan;options=bold>Noyau #{$group->id}</> — {$group->readable_code}{$auditLabel}");
        $this->line('');
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['Domaine / Sub',       $group->domain . ' / ' . $group->sub_domain],
                ['Depth',               $group->difficulty_depth . '/10'],
                ['question_type',       $group->question_type],
                ['cognitive_type',      $group->cognitive_type],
                ['concept_family',      $group->concept_family ?: '—'],
                ['concept_id',          $group->concept_id ?: '—'],
                ['question_intent_key', $group->question_intent_key ?: '—'],
                ['source',              $group->source],
                ['post_review_status',  $group->post_review_status],
            ]
        );
    }

    private function printFrench(?QuestionTranslation $fr, QuestionGroup $group): void
    {
        $this->line('<fg=yellow;options=bold>📋  VERSION FRANÇAISE (référence)</>');
        $this->line('');

        if (! $fr) {
            $this->warn('  ⚠️  Aucune traduction FR.');
            return;
        }

        $this->line('<fg=white;options=bold>Q : ' . $fr->question_text . '</>');
        $this->line('');

        $correct = strtoupper((string) $fr->correct_answer_key);
        foreach (['A' => $fr->answer_a, 'B' => $fr->answer_b, 'C' => $fr->answer_c, 'D' => $fr->answer_d] as $k => $v) {
            if (! $v) {
                continue;
            }
            $mark = ($k === $correct) ? ' <fg=green>✅ CORRECTE</>' : '';
            $this->line("  <fg=cyan>[{$k}]</> {$v}{$mark}");
        }

        $this->line('');
        $this->line('<fg=gray>Explication   :</> ' . ($fr->explanation ?: '<fg=red>VIDE</>'));
        $this->line('<fg=gray>Saviez-vous   :</> ' . ($fr->saviez_vous ?: '<fg=red>VIDE</>'));
        if ($fr->funfact_score) {
            $this->line('<fg=gray>funfact_score :</> ' . $fr->funfact_score . '/4');
        } else {
            $this->line('<fg=gray>funfact_score :</> <fg=yellow>non renseigné (Phase 1)</>');
        }
    }

    private function runChecks(QuestionGroup $group, array $translations): array
    {
        $checks = [];
        $fr     = $translations['fr'] ?? null;

        // 1 — Couverture 10 langues
        $missing = array_values(array_filter(self::LANG_ORDER, fn ($l) => ! isset($translations[$l])));
        $checks['lang_coverage'] = [
            'label'  => 'Couverture 10 langues',
            'ok'     => count($missing) === 0,
            'detail' => count($missing) === 0
                ? '10/10 présentes'
                : 'Manquantes : ' . implode(', ', $missing),
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
            'label'  => 'Longueur question (gameplay)',
            'ok'     => count($qIssues) === 0,
            'detail' => count($qIssues) === 0
                ? 'OK toutes langues (max FR ' . self::Q_MAX . ' chars)'
                : 'Dépassements : ' . implode(', ', $qIssues),
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
                    $aIssues[] = "{$lang}.{$f}:{$len}>{$max}";
                }
            }
        }
        $checks['answer_length'] = [
            'label'  => 'Longueur réponses (gameplay)',
            'ok'     => count($aIssues) === 0,
            'detail' => count($aIssues) === 0
                ? 'OK toutes langues (max FR ' . self::A_MAX . ' chars)'
                : 'Dépassements : ' . implode(', ', array_slice($aIssues, 0, 4))
                  . (count($aIssues) > 4 ? ' …+' . (count($aIssues) - 4) : ''),
        ];

        // 4 — Qualité réponses FR (4 non-vides, clé valide, pas de doublon)
        $ansOk    = true;
        $ansDetail = 'OK';
        if ($fr) {
            $answers = array_filter([$fr->answer_a, $fr->answer_b, $fr->answer_c, $fr->answer_d]);
            $correct  = strtoupper((string) $fr->correct_answer_key);
            $count    = count($answers);
            $unique   = count(array_unique($answers));
            if ($count < 2) {
                $ansOk = false; $ansDetail = "Seulement {$count} réponse(s) non-vides";
            } elseif (! in_array($correct, ['A', 'B', 'C', 'D'])) {
                $ansOk = false; $ansDetail = "Clé correcte invalide : {$correct}";
            } elseif ($unique < $count) {
                $ansOk = false; $ansDetail = "Réponses dupliquées ({$count} total, {$unique} uniques)";
            } else {
                $ansDetail = "{$count} réponses, clé={$correct}, toutes uniques";
            }
        }
        $checks['answer_quality'] = [
            'label'  => 'Qualité réponses',
            'ok'     => $ansOk,
            'detail' => $ansDetail,
        ];

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
            'label'  => 'Formulation non-négative',
            'ok'     => $negFound === '',
            'detail' => $negFound === '' ? 'Aucun mot négatif détecté' : "Mot interdit : \"{$negFound}\"",
        ];

        // 6 — Piège / cognitif cohérent
        $cogOk     = true;
        $cogDetail = '';
        if ($fr) {
            $lower = mb_strtolower(($fr->question_text ?? '') . ' ' . ($fr->explanation ?? ''));
            if ($group->cognitive_type === 'reasoning') {
                $found = array_filter(self::REASONING_MARKERS, fn ($m) => str_contains($lower, $m));
                $cogOk = count($found) > 0;
                $cogDetail = $cogOk
                    ? 'Marqueurs : ' . implode(', ', array_slice($found, 0, 2))
                    : '⚠️ Aucun marqueur de raisonnement';
            } elseif ($group->cognitive_type === 'deceptive_trap') {
                $correct = strtoupper((string) $fr->correct_answer_key);
                $allAns  = ['A' => $fr->answer_a, 'B' => $fr->answer_b, 'C' => $fr->answer_c, 'D' => $fr->answer_d];
                $distractors = array_filter($allAns, fn ($v, $k) => $k !== $correct && $v, ARRAY_FILTER_USE_BOTH);
                $cogDetail = count($distractors) . ' distracteur(s) — vérification manuelle recommandée';
            } else {
                $cogDetail = 'Recognition — vérification manuelle';
            }
        }
        $checks['cognitive_trap'] = [
            'label'  => 'Cognitif / piège (' . $group->cognitive_type . ')',
            'ok'     => $cogOk,
            'detail' => $cogDetail,
        ];

        // 7 — Saviez-vous / funfact (FR)
        $svLen    = $fr ? mb_strlen($fr->saviez_vous ?? '') : 0;
        $svOk     = $svLen >= self::SV_MIN && $svLen <= self::SV_MAX;
        $svDetail = "{$svLen} chars (min " . self::SV_MIN . ", max " . self::SV_MAX . ")";
        if ($svLen === 0) {
            $svDetail .= ' — VIDE';
        }
        // Simple tautology: does saviez_vous contain the exact correct answer text?
        if ($fr && $fr->saviez_vous && $fr->correct_answer_key) {
            $correctText = match (strtoupper($fr->correct_answer_key)) {
                'A' => $fr->answer_a,
                'B' => $fr->answer_b,
                'C' => $fr->answer_c,
                'D' => $fr->answer_d,
                default => '',
            };
            if ($correctText && str_contains(mb_strtolower($fr->saviez_vous), mb_strtolower($correctText))) {
                $svOk     = false;
                $svDetail .= ' — ⚠️ Tautologie possible (contient la réponse correcte)';
            }
        }
        $checks['funfact_quality'] = [
            'label'  => 'Saviez-vous (funfact)',
            'ok'     => $svOk,
            'detail' => $svDetail,
        ];

        // 8 — Depth vs gameplay (cohérence profondeur / contenu)
        $depth     = $group->difficulty_depth;
        $wordCount = $fr ? str_word_count($fr->question_text ?? '') : 0;
        $depthOk   = true;
        $depthDetail = "depth {$depth}/10, {$wordCount} mots (FR)";
        if ($depth <= 4 && $wordCount > 25) {
            $depthOk = false;
            $depthDetail .= ' — trop long pour depth ' . $depth;
        } elseif ($depth >= 8 && $wordCount < 5) {
            $depthOk = false;
            $depthDetail .= ' — trop court pour depth ' . $depth;
        }
        $checks['depth_gameplay'] = [
            'label'  => 'Depth / cohérence gameplay',
            'ok'     => $depthOk,
            'detail' => $depthDetail,
        ];

        return $checks;
    }

    private function printChecks(array $checks): void
    {
        $this->line('<fg=yellow;options=bold>🔍  VÉRIFICATIONS QUALITÉ</>');
        $this->line('');
        $allOk = true;
        foreach ($checks as $check) {
            $icon  = $check['ok'] ? '<fg=green>✅</>' : '<fg=red>❌</>';
            $label = str_pad($check['label'], 36);
            $this->line("  {$icon}  {$label} {$check['detail']}");
            if (! $check['ok']) {
                $allOk = false;
            }
        }
        if ($allOk) {
            $this->line('');
            $this->line('  <fg=green;options=bold>✅  Toutes les vérifications passées.</>');
        }
    }

    private function printLangTable(array $translations): void
    {
        $this->line('<fg=yellow;options=bold>🌍  COUVERTURE LANGUES</>');
        $this->line('');
        $rows = [];
        foreach (self::LANG_ORDER as $lang) {
            $t     = $translations[$lang] ?? null;
            $qLen  = $t ? mb_strlen($t->question_text ?? '') : '—';
            $svLen = $t ? mb_strlen($t->saviez_vous ?? '') : '—';
            $rows[] = [
                $lang,
                self::LANG_NAMES[$lang] ?? $lang,
                $t ? '✅' : '❌',
                $qLen,
                $svLen,
            ];
        }
        $this->table(['Code', 'Langue', 'Présent', 'Q chars', 'SV chars'], $rows);
    }

    private function menu(QuestionGroup $group, array $translations, array $checks): string
    {
        $hasIssues = (bool) array_filter($checks, fn ($c) => ! $c['ok']);

        if ($hasIssues) {
            $this->warn('⚠️   Des vérifications ont échoué — corriger avant de valider.');
        }

        $this->line('<fg=yellow;options=bold>ACTION :</>');
        $this->line('  <fg=cyan>[V]</>  Valider   — confirmer ready_bank + marquer audité');
        $this->line('  <fg=cyan>[C]</>  Corriger  — éditer un champ (texte, réponse, saviez_vous, …)');
        $this->line('  <fg=cyan>[R]</>  Reclassifier — changer cognitive_type ou question_type');
        $this->line('  <fg=cyan>[L]</>  Langues   — afficher toutes les traductions complètes');
        $this->line('  <fg=cyan>[B]</>  Bloquer   — marquer blocked_critical');
        $this->line('  <fg=cyan>[S]</>  Sauter    — passer au suivant sans marquer');
        $this->line('  <fg=cyan>[Q]</>  Quitter');
        $this->line('');

        $action = strtoupper((string) $this->ask('Choix'));

        return match ($action) {
            'V' => $this->doValidate($group),
            'C' => $this->doCorrect($group, $translations),
            'R' => $this->doReclassify($group),
            'L' => $this->doShowAll($group, $translations, $checks),
            'B' => $this->doBlock($group),
            'S' => 'next',
            'Q' => 'quit',
            default => $this->invalidChoice($group, $translations, $checks),
        };
    }

    private function doValidate(QuestionGroup $group): string
    {
        $note = (string) $this->ask('Note d\'audit (optionnel — Entrée pour passer)', '');
        $this->appendLog($group, 'validated', $note ?: 'OK');
        $group->update([
            'post_review_status' => 'ready_bank',
            'audited_at'         => now(),
        ]);
        $this->info("✅  Noyau #{$group->id} validé et marqué audité.");
        return 'validated';
    }

    private function doCorrect(QuestionGroup $group, array $translations): string
    {
        $this->line('');
        $this->line('<fg=yellow>Quel champ corriger ?</>');
        $this->line('  <fg=cyan>[1]</>  question_text');
        $this->line('  <fg=cyan>[2]</>  answer_a');
        $this->line('  <fg=cyan>[3]</>  answer_b');
        $this->line('  <fg=cyan>[4]</>  answer_c');
        $this->line('  <fg=cyan>[5]</>  answer_d');
        $this->line('  <fg=cyan>[6]</>  correct_answer_key');
        $this->line('  <fg=cyan>[7]</>  explanation');
        $this->line('  <fg=cyan>[8]</>  saviez_vous');
        $this->line('  <fg=cyan>[9]</>  Note interne (correction_notes uniquement)');
        $this->line('  <fg=cyan>[0]</>  Retour au menu');

        $fieldChoice = (string) $this->ask('Champ');

        if ($fieldChoice === '0') {
            return $this->menu($group, $translations, []);
        }

        if ($fieldChoice === '9') {
            $note = (string) $this->ask('Note interne');
            $this->appendLog($group, 'note', $note);
            $this->info('Note enregistrée.');
            if ($this->confirm('Corriger un autre champ ?', false)) {
                return $this->doCorrect($group, $translations);
            }
            return $this->menu($group, $translations, []);
        }

        $fieldMap = [
            '1' => 'question_text', '2' => 'answer_a', '3' => 'answer_b',
            '4' => 'answer_c',      '5' => 'answer_d', '6' => 'correct_answer_key',
            '7' => 'explanation',   '8' => 'saviez_vous',
        ];
        $field = $fieldMap[$fieldChoice] ?? null;
        if (! $field) {
            $this->warn('Choix invalide.');
            return $this->doCorrect($group, $translations);
        }

        $lang = strtolower((string) $this->ask('Langue (fr / en / es / de / it / pt / ru / zh / ar / el)'));
        $t    = $translations[$lang] ?? null;
        if (! $t) {
            $this->warn("Traduction '{$lang}' introuvable pour ce noyau.");
            return $this->doCorrect($group, $translations);
        }

        $current  = (string) ($t->$field ?? '');
        $this->line("Valeur actuelle <fg=yellow>{$current}</>");
        $newValue = (string) $this->ask('Nouvelle valeur (Entrée pour annuler)', $current);

        if ($newValue !== '' && $newValue !== $current) {
            if ($field === 'correct_answer_key') {
                $newValue = strtoupper($newValue);
            }
            $t->update([$field => $newValue]);
            $this->appendLog($group, 'correction', "{$field} [{$lang}] : {$current} → {$newValue}");
            $this->info("✅  {$field} ({$lang}) mis à jour.");
        } else {
            $this->line('Aucune modification.');
        }

        if ($this->confirm('Corriger un autre champ ?', false)) {
            // Refresh translations from DB
            $group->load('translations');
            $fresh = [];
            foreach ($group->translations as $tr) {
                $fresh[$tr->language] = $tr;
            }
            return $this->doCorrect($group, $fresh);
        }

        return $this->menu($group, $translations, []);
    }

    private function doReclassify(QuestionGroup $group): string
    {
        $this->line('');
        $this->line("  cognitive_type actuel : <fg=cyan>{$group->cognitive_type}</>");
        $this->line("  question_type actuel  : <fg=cyan>{$group->question_type}</>");
        $this->line('');
        $this->line('  <fg=cyan>[1]</>  Changer cognitive_type');
        $this->line('  <fg=cyan>[2]</>  Changer question_type');
        $this->line('  <fg=cyan>[0]</>  Retour');

        $choice = (string) $this->ask('Choix');

        if ($choice === '1') {
            $new = $this->choice('Nouveau cognitive_type', ['recognition', 'reasoning', 'deceptive_trap']);
            if ($new !== $group->cognitive_type) {
                $old = $group->cognitive_type;
                $group->update(['cognitive_type' => $new]);
                $this->appendLog($group, 'reclassify', "cognitive_type : {$old} → {$new}");
                $this->info("✅  cognitive_type → {$new}");
            }
        } elseif ($choice === '2') {
            $new = $this->choice('Nouveau question_type', ['qcm', 'true_false']);
            if ($new !== $group->question_type) {
                $old = $group->question_type;
                $group->update(['question_type' => $new]);
                $this->appendLog($group, 'reclassify', "question_type : {$old} → {$new}");
                $this->info("✅  question_type → {$new}");
            }
        }

        $group->load('translations');
        $translations = [];
        foreach ($group->translations as $t) {
            $translations[$t->language] = $t;
        }
        return $this->menu($group, $translations, []);
    }

    private function doShowAll(QuestionGroup $group, array $translations, array $checks): string
    {
        $this->line('');
        $this->line('<fg=yellow;options=bold>📖  TOUTES LES TRADUCTIONS</>');

        foreach (self::LANG_ORDER as $lang) {
            $t = $translations[$lang] ?? null;
            $name = self::LANG_NAMES[$lang] ?? strtoupper($lang);
            $this->line('');
            $this->line("  <fg=cyan;options=bold>── {$lang} · {$name} ──</>");

            if (! $t) {
                $this->warn("  ❌ Traduction manquante");
                continue;
            }
            $correct = strtoupper((string) $t->correct_answer_key);
            $this->line("  <fg=white>Q: {$t->question_text}</>");
            foreach (['A' => $t->answer_a, 'B' => $t->answer_b, 'C' => $t->answer_c, 'D' => $t->answer_d] as $k => $v) {
                if (! $v) {
                    continue;
                }
                $mark = ($k === $correct) ? ' ✅' : '';
                $this->line("  [{$k}]{$mark} {$v}");
            }
            $this->line("  <fg=gray>SV: {$t->saviez_vous}</>");
        }

        $this->line('');
        return $this->menu($group, $translations, $checks);
    }

    private function doBlock(QuestionGroup $group): string
    {
        $reason = (string) $this->ask('Raison du blocage');
        $this->appendLog($group, 'blocked', $reason);
        $group->update([
            'post_review_status' => 'blocked_critical',
            'audited_at'         => now(),
        ]);
        $this->warn("🚫  Noyau #{$group->id} marqué blocked_critical.");
        return 'blocked';
    }

    private function invalidChoice(QuestionGroup $group, array $translations, array $checks): string
    {
        $this->warn('Choix invalide. Entrez V, C, R, L, B, S ou Q.');
        return $this->menu($group, $translations, $checks);
    }

    private function appendLog(QuestionGroup $group, string $action, string $detail): void
    {
        $existing = [];
        if ($group->correction_notes) {
            $decoded = json_decode($group->correction_notes, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $existing[] = [
            'action' => $action,
            'detail' => $detail,
            'at'     => now()->toIso8601String(),
        ];
        $group->update([
            'correction_notes' => json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
