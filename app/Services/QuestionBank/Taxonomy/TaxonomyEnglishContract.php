<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use LanguageDetection\Language;

/**
 * Deterministic boundary for the intellectual language of generated taxonomy.
 *
 * This is intentionally conservative: it rejects clear French markers rather
 * than pretending to be a general-purpose language detector.
 */
final class TaxonomyEnglishContract
{
    /** @var string[] */
    private const FRENCH_MARKERS = [
        'au', 'aux', 'avec', 'dans', 'de', 'des', 'du', 'et', 'la', 'le',
        'les', 'pour', 'sur', 'une', 'un',
        'animaux', 'artistes', 'bataille', 'cinéma', 'cuisine', 'faune',
        'français', 'guerre', 'histoire', 'marins', 'moderne', 'monde', 'mondiale', 'peinture',
        'révolution', 'science', 'siècle', 'société',
    ];

    /** @var string[] */
    private const ENGLISH_CLASSIFIER_EXCEPTIONS = [
        'matter', 'properties',
    ];

    public static function assertValue(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new TaxonomyPreparationException(
                "Taxonomy English contract: {$field} est vide."
            );
        }

        if (preg_match('/[àâçéèêëîïôùûüÿœæ]/iu', $value) === 1) {
            throw new TaxonomyPreparationException(
                "Taxonomy English contract: {$field} n'est pas en anglais : «{$value}»."
            );
        }

        $tokens = preg_split('/[^[:alpha:]]+/u', mb_strtolower($value, 'UTF-8')) ?: [];
        foreach ($tokens as $token) {
            if (in_array($token, self::FRENCH_MARKERS, true)) {
                throw new TaxonomyPreparationException(
                    "Taxonomy English contract: {$field} n'est pas en anglais : «{$value}»."
                );
            }
        }

        $words = array_values(array_filter($tokens));
        if (count($words) >= 2) {
            $scores = (new Language(['en', 'fr']))
                ->detect($value)
                ->bestResults()
                ->close();
            $english = $scores['en'] ?? null;
            $french = $scores['fr'] ?? null;

            $knownEnglishException = array_diff(
                $words,
                self::ENGLISH_CLASSIFIER_EXCEPTIONS
            ) === [];

            if ($french !== null
                && (($english === null && ! $knownEnglishException)
                    || ($english !== null && $french > $english + 0.05))) {
                throw new TaxonomyPreparationException(
                    "Taxonomy English contract: {$field} n'est pas en anglais : «{$value}»."
                );
            }
        }

        return $value;
    }

    /** @param string[] $values */
    public static function assertValues(array $values, string $field): array
    {
        return array_map(
            static fn(string $value): string => self::assertValue($value, $field),
            $values,
        );
    }
}