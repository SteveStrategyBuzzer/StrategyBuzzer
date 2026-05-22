<?php

namespace App\Console\Commands;

use App\Models\QuestionGroup;
use App\Models\QuestionIntent;
use Illuminate\Console\Command;

/**
 * One-shot export : état final réel des 10 noyaux de dialyse.
 *
 * Usage : php artisan questions:dialyse:final-export
 */
class QuestionsDialyseFinalExportCommand extends Command
{
    protected $signature   = 'questions:dialyse:final-export';
    protected $description = 'Export état final réel des 10 noyaux de dialyse → exports/dialyse_10_noyaux_FINAL.md';

    private const NOYAU_IDS = [4, 7, 34, 46, 64, 67, 85, 100, 121, 139];

    private const LANG_NAMES = [
        'fr' => 'Français',    'en' => 'English',    'es' => 'Español',
        'de' => 'Deutsch',     'it' => 'Italiano',   'pt' => 'Português',
        'ru' => 'Русский',     'zh' => '中文',        'ar' => 'العربية',
        'el' => 'Ελληνικά',
    ];

    private const LANG_ORDER = ['fr','en','es','de','it','pt','ru','zh','ar','el'];

    private const TARGET_VARIANTS = [
        ['question_type' => 'qcm',        'cognitive_type' => 'recognition'],
        ['question_type' => 'qcm',        'cognitive_type' => 'reasoning'],
        ['question_type' => 'qcm',        'cognitive_type' => 'deceptive_trap'],
        ['question_type' => 'true_false', 'cognitive_type' => 'recognition'],
        ['question_type' => 'true_false', 'cognitive_type' => 'reasoning'],
    ];

    private const SV_MAX_DEFAULT = 220;
    private const SV_MAX_AR      = 140;
    private const SV_MAX_ZH      = 100;
    private const Q_MAX_DEFAULT  = 110;
    private const Q_MAX_AR       = 75;
    private const Q_MAX_ZH       = 60;
    private const A_MAX_DEFAULT  = 60;
    private const A_MAX_AR       = 40;
    private const A_MAX_ZH       = 30;

    public function handle(): int
    {
        $this->info('Chargement des 10 noyaux…');

        $caseParts = [];
        foreach (self::NOYAU_IDS as $pos => $nid) {
            $caseParts[] = "WHEN id = {$nid} THEN {$pos}";
        }
        $caseExpr = 'CASE ' . implode(' ', $caseParts) . ' ELSE 99 END';

        $intents = QuestionIntent::whereIn('id', self::NOYAU_IDS)
            ->orderByRaw($caseExpr)
            ->get()
            ->keyBy('id');

        $groups = QuestionGroup::whereIn('question_intent_id', self::NOYAU_IDS)
            ->with('translations')
            ->orderBy('id')
            ->get()
            ->groupBy('question_intent_id');

        $lines   = [];
        $lines[] = '# Dialyse 10 noyaux — État final réel';
        $lines[] = '';
        $lines[] = '**Date :** ' . now()->format('Y-m-d H:i:s');
        $lines[] = '**Noyaux :** ' . implode(', ', self::NOYAU_IDS);
        $lines[] = '**Fixes appliqués :** P0 true_false contract · P1 Jaccard saviez_vous_off_topic';
        $lines[] = '';

        // ── Global summary table ────────────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Résumé global';
        $lines[] = '';
        $lines[] = '| # | Noyau ID | Domaine | Depth | Variantes | Statut | Langs manquantes |';
        $lines[] = '|---|---|---|---|---|---|---|';

        foreach (self::NOYAU_IDS as $idx => $id) {
            $intent = $intents[$id] ?? null;
            if (!$intent) {
                $lines[] = "| " . ($idx + 1) . " | #{$id} | *(non trouvé)* | — | — | ❓ | — |";
                continue;
            }
            $grps     = $groups[$id] ?? collect();
            $count    = $grps->count();
            $complete = $count === 5 && $grps->every(fn ($g) => $g->translations->count() === 10);
            $status   = $complete ? '✅ COMPLET' : ($count === 0 ? '🔴 VIDE' : '🟡 INCOMPLET');
            $missingLangs = collect();
            foreach ($grps as $g) {
                $langs = $g->translations->pluck('language')->toArray();
                foreach (self::LANG_ORDER as $l) {
                    if (!in_array($l, $langs)) $missingLangs->push($l);
                }
            }
            $missingStr = $missingLangs->unique()->sort()->implode(', ') ?: '—';
            $lines[] = "| " . ($idx + 1) . " | #{$id} | {$intent->domain} | d{$intent->difficulty_depth} | {$count}/5 | {$status} | {$missingStr} |";
        }
        $lines[] = '';

        // ── Per-noyau sections ──────────────────────────────────────────────
        foreach (self::NOYAU_IDS as $idx => $id) {
            $intent = $intents[$id] ?? null;
            $grps   = ($groups[$id] ?? collect())->sortBy('id');
            $n      = $idx + 1;

            $lines[] = '---';
            $lines[] = '';
            $lines[] = "## NOYAU {$n} — #{$id} · " . ($intent ? $intent->domain . ' · depth ' . $intent->difficulty_depth : '(non trouvé)');
            $lines[] = '';

            // 1. Metadata
            $lines[] = '### 1. Métadonnées noyau';
            $lines[] = '';
            if (!$intent) {
                $lines[] = '> ❌ intent introuvable en base';
                $lines[] = '';
                continue;
            }
            $lines[] = '| Champ | Valeur |';
            $lines[] = '|---|---|';
            $lines[] = "| question_intent_id | {$intent->id} |";
            $lines[] = '| intent_key | ' . ($intent->intent_key ?? '—') . ' |';
            $lines[] = '| semantic_key | ' . ($intent->semantic_key ?? '*(vide)*') . ' |';
            $lines[] = '| domain | ' . ($intent->domain ?? '—') . ' |';
            $lines[] = '| sub_domain | ' . ($intent->sub_domain ?? '—') . ' |';
            $lines[] = '| difficulty_depth | ' . ($intent->difficulty_depth ?? '—') . ' |';
            $lines[] = '| subject | ' . ($intent->subject ?? '—') . ' |';
            $lines[] = '| angle_large | ' . ($intent->angle_large ?? '—') . ' |';
            $lines[] = '| micro_angle | ' . ($intent->micro_angle ?? '—') . ' |';
            $lines[] = '| answer_target | ' . ($intent->answer_target ?? '—') . ' |';
            $lines[] = '| potential_trap | ' . ($intent->potential_trap ?? '—') . ' |';
            $lines[] = '| concept_family | ' . ($intent->concept_family ?? '—') . ' |';
            $lines[] = '| dialysis_status | ' . ($intent->dialysis_status ?? '—') . ' |';
            $lines[] = '| dialysed_at | ' . ($intent->dialysed_at ?? '—') . ' |';
            $lines[] = '';

            // 2. Final state
            $lines[] = '### 2. État final';
            $lines[] = '';
            $count    = $grps->count();
            $allLangs = $grps->every(fn ($g) => $g->translations->count() === 10);
            $complete = ($count === 5 && $allLangs);

            $presentKeys = $grps->map(fn ($g) => $g->question_type . '/' . $g->cognitive_type)->toArray();
            $missingKeys = array_filter(
                self::TARGET_VARIANTS,
                fn ($v) => !in_array($v['question_type'] . '/' . $v['cognitive_type'], $presentKeys, true)
            );

            $status = $complete ? '✅ COMPLET' : ($count === 0 ? '🔴 VIDE' : '🟡 INCOMPLET');
            $lines[] = "**Statut :** {$status}";
            $lines[] = '';
            $lines[] = '| Métrique | Valeur |';
            $lines[] = '|---|---|';
            $lines[] = "| Variantes présentes | {$count}/5 |";
            $missStr = empty($missingKeys)
                ? '—'
                : implode(', ', array_map(fn ($v) => $v['question_type'] . '/' . $v['cognitive_type'], $missingKeys));
            $lines[] = "| Variantes manquantes | {$missStr} |";
            $lines[] = "| Toutes langues complètes | " . ($allLangs ? 'Oui' : 'Non') . " |";

            // Quality flags analysis
            $flags = $this->collectQualityFlags($grps);
            $lines[] = "| Quality flags actifs | " . (empty($flags) ? '✅ Aucun' : implode(', ', $flags)) . " |";
            $lines[] = '';

            // 3. Final variants
            $lines[] = '### 3. Variantes finales';
            $lines[] = '';

            if ($grps->isEmpty()) {
                $lines[] = '> ❌ Aucune variante en banque pour ce noyau.';
                $lines[] = '';
            } else {
                foreach ($grps as $g) {
                    $lines[] = '---';
                    $lines[] = '';
                    $vtag = $g->question_type . '/' . $g->cognitive_type;
                    $lines[] = "#### Variante : `{$vtag}`";
                    $lines[] = '';
                    $lines[] = '| Champ | Valeur |';
                    $lines[] = '|---|---|';
                    $lines[] = "| question_group_id | {$g->id} |";
                    $lines[] = '| readable_code | ' . ($g->readable_code ?? '—') . ' |';
                    $lines[] = "| question_type | {$g->question_type} |";
                    $lines[] = "| cognitive_type | {$g->cognitive_type} |";
                    $lines[] = '| post_review_status | ' . ($g->post_review_status ?? '—') . ' |';
                    $lines[] = "| validated | " . ($g->validated ? 'Oui' : 'Non') . " |";
                    $lines[] = "| source | " . ($g->source ?? '—') . " |";
                    $lines[] = "| concept_family | " . ($g->concept_family ?? '—') . " |";
                    $lines[] = "| langues présentes | " . $g->translations->pluck('language')->sort()->implode(', ') . " |";
                    $lines[] = '';

                    // Per-language display
                    $trByLang = $g->translations->keyBy('language');
                    foreach (self::LANG_ORDER as $lang) {
                        $tr       = $trByLang[$lang] ?? null;
                        $langName = self::LANG_NAMES[$lang] ?? $lang;
                        $flag     = $this->langFlag($lang);
                        $lines[] = "<details>";
                        $lines[] = "<summary><strong>{$flag} {$langName} ({$lang})" . ($tr ? '' : ' — ⚠️ MANQUANT') . "</strong></summary>";
                        $lines[] = '';
                        if (!$tr) {
                            $lines[] = '> ❌ Traduction absente.';
                            $lines[] = '';
                        } else {
                            $correctKey = strtoupper($tr->correct_answer_key ?? '');
                            $answers    = [
                                'A' => $tr->answer_a ?? '',
                                'B' => $tr->answer_b ?? '',
                                'C' => $tr->answer_c ?? '',
                                'D' => $tr->answer_d ?? '',
                            ];

                            $lines[] = '**Question :** ' . ($tr->question_text ?? '—');
                            $lines[] = '';

                            $ansLines = [];
                            foreach ($answers as $key => $text) {
                                if ($text === '' || $text === 'null' || $text === null) continue;
                                $marker    = ($key === $correctKey) ? ' ✅' : '';
                                $ansLines[] = "| {$key} | " . $text . $marker . " |";
                            }
                            if (!empty($ansLines)) {
                                $lines[] = '| Clé | Réponse |';
                                $lines[] = '|---|---|';
                                foreach ($ansLines as $al) $lines[] = $al;
                            }
                            $lines[] = '';
                            $lines[] = '**Correcte :** [' . $correctKey . ']';
                            $lines[] = '';

                            $sv    = trim($tr->saviez_vous ?? '');
                            $svLen = mb_strlen($sv);
                            $svMax = $lang === 'ar' ? self::SV_MAX_AR : ($lang === 'zh' ? self::SV_MAX_ZH : self::SV_MAX_DEFAULT);
                            $svFlag = $svLen > $svMax ? " ⚠️ TROP LONG ({$svLen}>{$svMax})" : " ({$svLen} chars)";
                            $lines[] = '**Saviez-vous' . $svFlag . ' :** ' . ($sv ?: '—');
                            $lines[] = '';

                            $qLen  = mb_strlen(trim($tr->question_text ?? ''));
                            $qMax  = $lang === 'ar' ? self::Q_MAX_AR : ($lang === 'zh' ? self::Q_MAX_ZH : self::Q_MAX_DEFAULT);
                            if ($qLen > $qMax) {
                                $lines[] = '> ⚠️ question_text trop longue : ' . $qLen . ' > max=' . $qMax;
                                $lines[] = '';
                            }
                        }
                        $lines[] = '</details>';
                        $lines[] = '';
                    }
                }
            }

            // 4. Human analysis
            $lines[] = '### 4. Analyse humaine';
            $lines[] = '';
            $lines[] = $this->buildHumanAnalysis($intent, $grps);
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '*Généré par `questions:dialyse:final-export` le ' . now()->format('Y-m-d H:i:s') . '*';

        $md   = implode("\n", $lines);
        $path = base_path('exports/dialyse_10_noyaux_FINAL.md');
        file_put_contents($path, $md);

        $this->info("✅ Export → exports/dialyse_10_noyaux_FINAL.md (" . round(strlen($md) / 1024) . " KB, " . count($lines) . " lignes)");

        return self::SUCCESS;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function collectQualityFlags(\Illuminate\Support\Collection $groups): array
    {
        $flags = [];
        foreach ($groups as $g) {
            $trByLang = $g->translations->keyBy('language');
            $fr       = $trByLang['fr'] ?? null;
            if (!$fr) {
                $flags[] = 'missing_fr';
                continue;
            }

            // Tautological saviez_vous
            if ($fr->saviez_vous) {
                $q   = mb_strtolower($fr->question_text ?? '');
                $sv  = mb_strtolower($fr->saviez_vous);
                $correctKey = strtoupper($fr->correct_answer_key ?? 'A');
                $correctText = mb_strtolower(trim($fr->{'answer_' . strtolower($correctKey)} ?? ''));
                if ($correctText && mb_strlen($correctText) > 3 && str_contains($sv, $correctText)) {
                    $flags[] = "tautological_sv [#{$g->id}]";
                }
            }

            // saviez_vous too long per lang
            foreach ($g->translations as $tr) {
                $svLen = mb_strlen(trim($tr->saviez_vous ?? ''));
                $max   = $tr->language === 'ar' ? self::SV_MAX_AR : ($tr->language === 'zh' ? self::SV_MAX_ZH : self::SV_MAX_DEFAULT);
                if ($svLen > $max) {
                    $flags[] = "sv_too_long_{$tr->language} [#{$g->id}:{$svLen}>{$max}]";
                }
                $qLen = mb_strlen(trim($tr->question_text ?? ''));
                $qMax = $tr->language === 'ar' ? self::Q_MAX_AR : ($tr->language === 'zh' ? self::Q_MAX_ZH : self::Q_MAX_DEFAULT);
                if ($qLen > $qMax) {
                    $flags[] = "q_too_long_{$tr->language} [#{$g->id}:{$qLen}>{$qMax}]";
                }
                $aMax = $tr->language === 'ar' ? self::A_MAX_AR : ($tr->language === 'zh' ? self::A_MAX_ZH : self::A_MAX_DEFAULT);
                foreach (['answer_a','answer_b','answer_c','answer_d'] as $col) {
                    $v = trim($tr->{$col} ?? '');
                    if ($v && $v !== 'null' && mb_strlen($v) > $aMax) {
                        $flags[] = "ans_too_long_{$tr->language}.{$col} [#{$g->id}]";
                    }
                }
            }
        }
        return array_unique($flags);
    }

    private function buildHumanAnalysis(QuestionIntent $intent, \Illuminate\Support\Collection $groups): string
    {
        if ($groups->isEmpty()) {
            return '> ❌ Aucune variante — analyse impossible.';
        }

        $lines = [];

        $trByVariant = $groups->mapWithKeys(fn ($g) => [
            $g->question_type . '/' . $g->cognitive_type => $g
        ]);

        // Cohérence cognitive
        $lines[] = '#### Cohérence cognitive';
        foreach ($groups as $g) {
            $vtag = $g->question_type . '/' . $g->cognitive_type;
            $fr   = $g->translations->where('language', 'fr')->first();
            if (!$fr) { $lines[] = "- **{$vtag}** : ❓ pas de traduction FR"; continue; }
            $q     = $fr->question_text ?? '';
            $qLow  = mb_strtolower($q);
            $notes = [];

            if ($g->question_type === 'true_false') {
                $a = strtolower(trim($fr->answer_a ?? ''));
                $b = strtolower(trim($fr->answer_b ?? ''));
                $c = $fr->answer_c;
                $d = $fr->answer_d;
                $hasTF = in_array($a, ['vrai','true','yes','oui','verdadero','wahr','vero','verdadeiro','верно','真','صحيح','αληθής','αλήθεια','αληθινό'], true)
                       || in_array($b, ['faux','false','no','non','falso','falsch','falso','falso','неверно','假','خطأ','ψευδής','λάθος'], true);
                $nullOk = ($c === null || $c === '' || $c === 'null') && ($d === null || $d === '' || $d === 'null');
                if (!$hasTF) $notes[] = '⚠️ labels Vrai/Faux non reconnus';
                if (!$nullOk) $notes[] = '⚠️ answer_c/d non null';
            }

            if ($g->cognitive_type === 'reasoning') {
                $markers = ['parce que','car ','depuis','pourquoi','résultat','conséquence','entraîne','permet','raison'];
                $hasReasoning = collect($markers)->some(fn ($m) => str_contains($qLow, $m));
                if (!$hasReasoning) $notes[] = '⚠️ question reasoning sans marqueur causal visible';
            }

            if ($g->cognitive_type === 'deceptive_trap') {
                $hasTrap = strlen($fr->answer_b ?? '') > 3 && strlen($fr->answer_c ?? '') > 3;
                if (!$hasTrap) $notes[] = '⚠️ distracteurs courts';
            }

            $icon  = empty($notes) ? '✅' : '⚠️';
            $note  = empty($notes) ? 'OK' : implode(' · ', $notes);
            $lines[] = "- **{$vtag}** : {$icon} {$note}";
        }
        $lines[] = '';

        // Cohérence gameplay (longueur mobile)
        $lines[] = '#### Cohérence gameplay / lisibilité mobile';
        foreach ($groups as $g) {
            $vtag    = $g->question_type . '/' . $g->cognitive_type;
            $issues  = [];
            foreach ($g->translations as $tr) {
                $qLen = mb_strlen(trim($tr->question_text ?? ''));
                $qMax = $tr->language === 'ar' ? self::Q_MAX_AR : ($tr->language === 'zh' ? self::Q_MAX_ZH : self::Q_MAX_DEFAULT);
                if ($qLen > $qMax) $issues[] = "Q-{$tr->language}={$qLen}>{$qMax}";
                $svLen = mb_strlen(trim($tr->saviez_vous ?? ''));
                $svMax = $tr->language === 'ar' ? self::SV_MAX_AR : ($tr->language === 'zh' ? self::SV_MAX_ZH : self::SV_MAX_DEFAULT);
                if ($svLen > $svMax) $issues[] = "SV-{$tr->language}={$svLen}>{$svMax}";
                $aMax = $tr->language === 'ar' ? self::A_MAX_AR : ($tr->language === 'zh' ? self::A_MAX_ZH : self::A_MAX_DEFAULT);
                foreach (['answer_a','answer_b','answer_c','answer_d'] as $col) {
                    $v = trim($tr->{$col} ?? '');
                    if ($v && $v !== 'null' && mb_strlen($v) > $aMax) $issues[] = "{$col}-{$tr->language}=" . mb_strlen($v) . ">{$aMax}";
                }
            }
            $icon   = empty($issues) ? '✅' : '⚠️';
            $detail = empty($issues) ? 'OK' : 'Longueurs dépassées : ' . implode(', ', $issues);
            $lines[] = "- **{$vtag}** : {$icon} {$detail}";
        }
        $lines[] = '';

        // Qualité saviez-vous
        $lines[] = '#### Qualité des Saviez-vous (FR)';
        foreach ($groups as $g) {
            $vtag = $g->question_type . '/' . $g->cognitive_type;
            $fr   = $g->translations->where('language', 'fr')->first();
            if (!$fr) { $lines[] = "- **{$vtag}** : ❓ pas de FR"; continue; }
            $sv    = trim($fr->saviez_vous ?? '');
            $q     = mb_strtolower($fr->question_text ?? '');
            $notes = [];
            $correctKey  = strtoupper($fr->correct_answer_key ?? 'A');
            $correctText = mb_strtolower(trim($fr->{'answer_' . strtolower($correctKey)} ?? ''));
            if ($sv === '') { $notes[] = '❌ vide'; }
            elseif (mb_strlen($sv) < 30) { $notes[] = '⚠️ trop court (' . mb_strlen($sv) . ' chars)'; }
            elseif ($correctText && mb_strlen($correctText) > 3 && str_contains(mb_strtolower($sv), $correctText)) {
                $notes[] = '⚠️ tautologique (contient la réponse correcte "' . $correctText . '")';
            }
            if (mb_strlen($sv) > 0 && mb_strlen($sv) > 50) {
                $svLow = mb_strtolower($sv);
                $isSurprising = preg_match('/\b(en réalité|en fait|cependant|pourtant|contrairement|paradox|surprenant|insolite|anecdote|savait|ignor|rare|seul|unique|record|premier|jamais|seulement|moins de|plus de)\b/u', $svLow);
                if (!$isSurprising) $notes[] = '⚠️ SV sans marqueur de surprise visible';
            }
            $icon  = empty($notes) ? '✅' : (str_contains(implode('', $notes), '❌') ? '❌' : '⚠️');
            $note  = empty($notes) ? "OK ({$sv})" : implode(' · ', $notes) . " → {$sv}";
            $lines[] = "- **{$vtag}** : {$icon} {$note}";
        }
        $lines[] = '';

        // Diversité des variantes
        $lines[] = '#### Diversité des variantes';
        $frTexts = $groups->map(fn ($g) => [
            'vtag' => $g->question_type . '/' . $g->cognitive_type,
            'q'    => mb_strtolower(trim($g->translations->where('language','fr')->first()?->question_text ?? '')),
        ])->filter(fn ($x) => $x['q'] !== '');

        $diversity = count($frTexts) <= 1 ? 'N/A (1 seule variante)' : '✅ Pas de doublons détectés';
        if (count($frTexts) > 1) {
            $texts = $frTexts->pluck('q')->values();
            for ($i = 0; $i < count($texts); $i++) {
                for ($j = $i + 1; $j < count($texts); $j++) {
                    similar_text($texts[$i], $texts[$j], $pct);
                    if ($pct > 70) {
                        $diversity = '⚠️ Variantes ' . $frTexts[$i]['vtag'] . ' et ' . $frTexts[$j]['vtag'] . " très similaires ({$pct}%)";
                    }
                }
            }
        }
        $lines[] = "- {$diversity}";
        $lines[] = '';

        // Problèmes encore visibles
        $lines[] = '#### Problèmes encore visibles';
        $still = [];
        $count = $groups->count();
        if ($count < 5) $still[] = "❌ {$count}/5 variantes présentes — variantes manquantes bloquées par guards";
        foreach ($groups as $g) {
            $fr = $g->translations->where('language','fr')->first();
            if (!$fr) { $still[] = "❌ #{$g->id} manque traduction FR"; }
        }
        $langsWithIssues = [];
        foreach ($groups as $g) {
            foreach ($g->translations as $tr) {
                $svLen = mb_strlen(trim($tr->saviez_vous ?? ''));
                $svMax = $tr->language === 'ar' ? self::SV_MAX_AR : ($tr->language === 'zh' ? self::SV_MAX_ZH : self::SV_MAX_DEFAULT);
                if ($svLen > $svMax) $langsWithIssues[] = "saviez_vous {$tr->language} trop long [{$g->id}]";
                $qLen = mb_strlen(trim($tr->question_text ?? ''));
                $qMax = $tr->language === 'ar' ? self::Q_MAX_AR : ($tr->language === 'zh' ? self::Q_MAX_ZH : self::Q_MAX_DEFAULT);
                if ($qLen > $qMax) $langsWithIssues[] = "question {$tr->language} trop longue [{$g->id}]";
            }
        }
        foreach (array_unique($langsWithIssues) as $li) $still[] = "⚠️ {$li} (P3 non appliqué)";

        if (empty($still)) $lines[] = '✅ Aucun problème résiduel détecté';
        else foreach ($still as $s) $lines[] = "- {$s}";
        $lines[] = '';

        // Dérive sémantique
        $lines[] = '#### Dérive sémantique vs noyau';
        $semanticKey = $intent->semantic_key ?? $intent->intent_key ?? '';
        $subject     = mb_strtolower($intent->subject ?? '');
        foreach ($groups as $g) {
            $vtag = $g->question_type . '/' . $g->cognitive_type;
            $fr   = $g->translations->where('language','fr')->first();
            if (!$fr) continue;
            $qLow = mb_strtolower($fr->question_text ?? '');
            // Extract key words from noyau (subject, concept_family)
            $family   = $intent->concept_family ?? '';
            $keywords = array_filter(array_merge(
                preg_split('/[-\s]+/', $subject, -1, PREG_SPLIT_NO_EMPTY),
                preg_split('/[-\s]+/', $family, -1, PREG_SPLIT_NO_EMPTY)
            ), fn ($w) => mb_strlen($w) > 3);
            $matchCount = 0;
            foreach ($keywords as $kw) {
                if (str_contains($qLow, mb_strtolower($kw))) $matchCount++;
            }
            $total = count($keywords);
            if ($total === 0) {
                $lines[] = "- **{$vtag}** : ⚠️ pas de mots-clés noyau disponibles pour vérifier";
            } elseif ($matchCount === 0) {
                $lines[] = "- **{$vtag}** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)";
            } else {
                $lines[] = "- **{$vtag}** : ✅ {$matchCount}/{$total} mots-clés noyau présents";
            }
        }

        return implode("\n", $lines);
    }

    private function langFlag(string $lang): string
    {
        return match ($lang) {
            'fr' => '🇫🇷', 'en' => '🇬🇧', 'es' => '🇪🇸',
            'de' => '🇩🇪', 'it' => '🇮🇹', 'pt' => '🇵🇹',
            'ru' => '🇷🇺', 'zh' => '🇨🇳', 'ar' => '🇸🇦',
            'el' => '🇬🇷',
            default => '🌐',
        };
    }
}
