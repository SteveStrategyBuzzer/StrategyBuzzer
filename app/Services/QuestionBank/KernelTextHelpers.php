<?php

namespace App\Services\QuestionBank;

/**
 * KernelTextHelpers
 *
 * A2 — Static text utilities shared across the kernel quality pipeline.
 *
 * Extracted from QualityGuards (where they were private) so that
 * KernelContentBuilder, VariantAlignmentChecker, and future Phase 2
 * services can reuse the same implementations without duplication.
 *
 * Methods:
 *   normForMatch(string)               → lowercase + diacritic-stripped form
 *   significantTokens(string)          → content words (>3 chars, no stopwords)
 *   jaccardShingle(string, string)     → 3-shingle Jaccard similarity [0.0–1.0]
 *   hasKeywordOverlap(string, string)  → bool (exact + prefix fallback)
 *   subjectTouchScore(array, string)   → composite subject-anchor score [0.0–1.0]
 *
 * subjectTouchScore — two strategies:
 *
 *   Strategy 1 (preferred): en_anchor_terms
 *     Used when frame_en.kernel_core.en_anchor_terms is present (≥4 terms).
 *     These are significant English tokens extracted from the master question
 *     after Phase 1 step 3-A, stored in KernelContentBuilder.
 *     Scoring: prefix-match (6 chars) each term against the variant haystack.
 *     This is language-agnostic and accurate for EN content.
 *
 *   Strategy 2 (fallback): French-field token matching
 *     Used when en_anchor_terms absent (e.g. legacy frames, unit tests).
 *     Applies diacritic normalization + 7-char prefix matching against the
 *     five French kernel_core fields with their canonical weights.
 *
 * Decision thresholds (Phase 2 VariantAlignmentChecker):
 *   score >= 0.45  → OK
 *   0.22–0.45      → warn_phase1
 *   0.10–0.22      → flag variant — retry Phase 1
 *   < 0.10         → human_review
 */
final class KernelTextHelpers
{
    private const STOP_EN = [
        'the','a','an','of','in','is','was','were','are','be','been','being',
        'to','and','or','for','on','at','by','from','with','as','that','this',
        'it','its','into','about','which','who','what','when','where','how',
        'did','do','does','had','has','have','not','but','if','so','also',
        'its','then','than','there','their','they','their','would','could',
        'true','false','since','because','both','each','more','most','many',
    ];

    private const STOP_FR = [
        'le','la','les','de','du','des','un','une','et','ou','en','au','aux',
        'est','était','sont','était','ont','a','il','elle','ils','elles',
        'qui','que','se','sa','son','leur','leurs','par','pour','sur','dans',
        'avec','mais','car','donc','lors','plus','très','aussi','tout','même',
        'cette','celui','celle','ceux','celles','comme','nous','vous','ils',
    ];

    /**
     * Common diacritic → ASCII mapping (lowercase).
     */
    private const DIACRITIC_MAP = [
        'á'=>'a','à'=>'a','â'=>'a','ä'=>'a','å'=>'a','ã'=>'a','ā'=>'a','ă'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ě'=>'e','ē'=>'e','ę'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ő'=>'o','ø'=>'o','ō'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ű'=>'u','ū'=>'u',
        'ý'=>'y','ÿ'=>'y',
        'ñ'=>'n','ń'=>'n',
        'ç'=>'c','ć'=>'c','č'=>'c',
        'ğ'=>'g','ş'=>'s','ș'=>'s','ș'=>'s','ş'=>'s',
        'ź'=>'z','ż'=>'z','ž'=>'z',
        'ł'=>'l',
        'đ'=>'d','ð'=>'d',
        'þ'=>'t',
        'ß'=>'ss',
    ];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lowercase + strip diacritics (fold to ASCII base).
     * Used for cross-language token comparison.
     */
    public static function normForMatch(string $text): string
    {
        return strtr(mb_strtolower($text), self::DIACRITIC_MAP);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return significant (content-bearing) tokens from a text.
     * Strips punctuation, lowercases, removes stopwords, keeps tokens > 3 chars.
     * Applies diacritic normalization before stopword filtering.
     */
    public static function significantTokens(string $text): array
    {
        $norm   = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', self::normForMatch($text));
        $tokens = preg_split('/\s+/u', trim((string) $norm)) ?: [];
        $stop   = array_flip(array_merge(self::STOP_EN, self::STOP_FR));

        return array_values(array_filter(
            $tokens,
            static fn(string $t) => mb_strlen($t) > 3 && !isset($stop[$t])
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Jaccard similarity over 3-token shingles [0.0–1.0].
     * Tolerant to small wording changes, harsh on real reformulations.
     */
    public static function jaccardShingle(string $a, string $b): float
    {
        $sA = self::shingles($a);
        $sB = self::shingles($b);

        if (empty($sA) && empty($sB)) {
            return 0.0;
        }

        $inter = count(array_intersect($sA, $sB));
        $union = count(array_unique(array_merge($sA, $sB)));

        return $union === 0 ? 0.0 : $inter / $union;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Keyword overlap fallback (exact + prefix match).
     * Returns true if ≥ 1 significant token (>3 chars) appears in both texts.
     * Handles French morphology via prefix matching (≥5 chars each side).
     */
    public static function hasKeywordOverlap(string $a, string $b): bool
    {
        $tokensA = self::significantTokens($a);
        $tokensB = self::significantTokens($b);

        $keysA = array_flip($tokensA);

        // Strategy 1: exact match
        foreach ($tokensB as $tb) {
            if (isset($keysA[$tb])) {
                return true;
            }
        }

        // Strategy 2: prefix match (≥5 chars each side)
        foreach ($tokensB as $tb) {
            if (mb_strlen($tb) < 5) {
                continue;
            }
            foreach ($tokensA as $ta) {
                if (mb_strlen($ta) < 5) {
                    continue;
                }
                if (str_starts_with($tb, $ta) || str_starts_with($ta, $tb)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Composite subject-anchor score for a variant's haystack.
     *
     * Strategy 1 (preferred): en_anchor_terms
     *   When kernel_core.en_anchor_terms contains ≥4 English terms
     *   (extracted from the master question by KernelContentBuilder),
     *   scores using those terms against the haystack with 6-char prefix
     *   matching for morphological variants (declared/declaration, etc.).
     *
     * Strategy 2 (fallback): French-field token coverage
     *   Diacritic-normalized tokens from 5 kernel_core fields, matched
     *   against the haystack using exact + 7-char prefix matching.
     *
     * @param  array  $kernelCore  The kernel_core array (from frame_en.kernel_core).
     * @param  string $haystack    question_text + ' ' + correct_answer_text + ' ' + explanation
     * @return float               Score in [0.0, 1.0]
     */
    public static function subjectTouchScore(array $kernelCore, string $haystack): float
    {
        $haystackNorm = self::normForMatch($haystack);

        // ── Strategy 1: en_anchor_terms ───────────────────────────────────────
        $enTerms = array_filter(
            $kernelCore['en_anchor_terms'] ?? [],
            static fn(string $t) => mb_strlen($t) > 3
        );

        if (count($enTerms) >= 4) {
            $covered = 0;
            foreach ($enTerms as $term) {
                $termNorm = self::normForMatch((string) $term);
                if (empty($termNorm)) {
                    continue;
                }

                // Exact substring match
                if (str_contains($haystackNorm, $termNorm)) {
                    $covered++;
                    continue;
                }

                // 6-char prefix match for morphological variants
                if (mb_strlen($termNorm) >= 6) {
                    $prefix = mb_substr($termNorm, 0, 6);
                    $words  = preg_split('/\s+/', $haystackNorm) ?: [];
                    foreach ($words as $word) {
                        if (mb_strlen($word) >= 6 && str_starts_with($word, $prefix)) {
                            $covered++;
                            break;
                        }
                    }
                }
            }

            return min(1.0, $covered / count($enTerms));
        }

        // ── Strategy 2: French-field token matching (with diacritic+prefix) ──
        $sources = [
            ['field' => 'subject',        'weight' => 0.35],
            ['field' => 'micro_angle',    'weight' => 0.25],
            ['field' => 'answer_target',  'weight' => 0.20],
            ['field' => 'potential_trap', 'weight' => 0.15],
            ['field' => 'concept_family', 'weight' => 0.05],
        ];

        $score = 0.0;

        foreach ($sources as ['field' => $field, 'weight' => $weight]) {
            $raw    = (string) ($kernelCore[$field] ?? '');
            $tokens = self::significantTokens($raw);

            if (empty($tokens)) {
                continue;
            }

            $covered = 0;
            foreach ($tokens as $token) {
                // tokenNorm already stripped by significantTokens() via normForMatch
                $tokenNorm = $token;

                // Exact substring match
                if (str_contains($haystackNorm, $tokenNorm)) {
                    $covered++;
                    continue;
                }

                // 7-char prefix match (handles FR→EN cognates: independanc/independenc)
                if (mb_strlen($tokenNorm) >= 7) {
                    $prefix = mb_substr($tokenNorm, 0, 7);
                    $words  = preg_split('/\s+/', $haystackNorm) ?: [];
                    foreach ($words as $word) {
                        if (mb_strlen($word) >= 7 && str_starts_with($word, $prefix)) {
                            $covered++;
                            break;
                        }
                    }
                }
            }

            $score += ($covered / count($tokens)) * $weight;
        }

        return min(1.0, $score);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private static function shingles(string $text, int $k = 3): array
    {
        $norm   = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower($text));
        $tokens = preg_split('/\s+/u', trim((string) $norm)) ?: [];

        if (count($tokens) < $k) {
            return $tokens;
        }

        $out = [];
        for ($i = 0, $n = count($tokens) - $k + 1; $i < $n; $i++) {
            $out[] = implode(' ', array_slice($tokens, $i, $k));
        }

        return $out;
    }
}
