<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

use App\Exceptions\QuestionBank\KernelCodeEngineException;

/**
 * Registre runtime fermé des identités créatrices.
 *
 * Les codes sont les seules identités canoniques de création et correspondent
 * directement au segment DO du kernel_code. Les slugs et les libellés français
 * sont uniquement des formes d'entrée compatibles; General n'est pas une
 * identité créatrice et est donc rejeté par tous les adaptateurs ci-dessous.
 */
final class CreatorDomainRegistry
{
    /** @var int[] */
    private const OFFICIAL_DEPTHS = [2, 4, 6, 7, 8, 9, 10];

    /**
     * @var array<string, array{
     *     code: string,
     *     do: string,
     *     slug: string,
     *     english: string,
     *     french: string,
     *     legacy: string
     * }>
     */
    private const DOMAINS = [
        'GEO' => [
            'code'    => 'GEO',
            'do'      => 'GEO',
            'slug'    => 'geographie',
            'english' => 'Geography',
            'french'  => 'Géographie',
            'legacy'  => 'GE',
        ],
        'HIS' => [
            'code'    => 'HIS',
            'do'      => 'HIS',
            'slug'    => 'histoire',
            'english' => 'History',
            'french'  => 'Histoire',
            'legacy'  => 'HI',
        ],
        'FAU' => [
            'code'    => 'FAU',
            'do'      => 'FAU',
            'slug'    => 'faune',
            'english' => 'Wildlife',
            'french'  => 'Faune',
            'legacy'  => 'FA',
        ],
        'ART' => [
            'code'    => 'ART',
            'do'      => 'ART',
            'slug'    => 'art',
            'english' => 'Art',
            'french'  => 'Art',
            'legacy'  => 'AR',
        ],
        'SPO' => [
            'code'    => 'SPO',
            'do'      => 'SPO',
            'slug'    => 'sport',
            'english' => 'Sports',
            'french'  => 'Sport',
            'legacy'  => 'SP',
        ],
        'CIN' => [
            'code'    => 'CIN',
            'do'      => 'CIN',
            'slug'    => 'cinema',
            'english' => 'Cinema',
            'french'  => 'Cinéma',
            'legacy'  => 'CI',
        ],
        'CUI' => [
            'code'    => 'CUI',
            'do'      => 'CUI',
            'slug'    => 'cuisine',
            'english' => 'Cuisine',
            'french'  => 'Cuisine',
            'legacy'  => 'CU',
        ],
        'SCI' => [
            'code'    => 'SCI',
            'do'      => 'SCI',
            'slug'    => 'science',
            'english' => 'Science',
            'french'  => 'Science',
            'legacy'  => 'SC',
        ],
    ];

    /**
     * @return array<int, array{
     *     code: string,
     *     do: string,
     *     slug: string,
     *     english: string,
     *     french: string,
     *     legacy: string
     * }>
     */
    public static function all(): array
    {
        return array_values(self::DOMAINS);
    }

    /**
     * @return string[]
     */
    public static function officialCodes(): array
    {
        return array_keys(self::DOMAINS);
    }

    /**
     * @return int[]
     */
    public static function officialDepths(): array
    {
        return self::OFFICIAL_DEPTHS;
    }

    public static function isOfficialDepth(int $depth): bool
    {
        return in_array($depth, self::OFFICIAL_DEPTHS, true);
    }

    /**
     * @return array{
     *     code: string,
     *     do: string,
     *     slug: string,
     *     english: string,
     *     french: string,
     *     legacy: string
     * }
     *
     * @throws KernelCodeEngineException si le code n'est pas créateur.
     */
    public static function get(string $code): array
    {
        $canonical = strtoupper(trim($code));

        if (! isset(self::DOMAINS[$canonical])) {
            throw self::invalidDomain($code);
        }

        return self::DOMAINS[$canonical];
    }

    /**
     * Résout un code canonique sans accepter les valeurs intellectuelles
     * anglaises comme identités de stockage.
     *
     * @throws KernelCodeEngineException si l'entrée n'est pas un code, slug ou
     *                                   libellé administratif français connu.
     */
    public static function fromInput(string $input): string
    {
        $value = trim($input);

        foreach (self::DOMAINS as $domain) {
            if ($value === $domain['code']
                || $value === $domain['slug']
                || self::lower($value) === self::lower($domain['french'])) {
                return $domain['code'];
            }
        }

        throw self::invalidDomain($input);
    }

    /**
     * Résout explicitement un slug compatible vers le code canonique.
     *
     * @throws KernelCodeEngineException si le slug est inconnu.
     */
    public static function fromSlug(string $slug): string
    {
        $value = self::lower(trim($slug));

        foreach (self::DOMAINS as $domain) {
            if ($value === $domain['slug']) {
                return $domain['code'];
            }
        }

        throw self::invalidDomain($slug);
    }

    /**
     * Résout explicitement un libellé administratif français.
     *
     * @throws KernelCodeEngineException si le libellé est inconnu.
     */
    public static function fromFrenchLabel(string $label): string
    {
        $value = self::lower(trim($label));

        foreach (self::DOMAINS as $domain) {
            if ($value === self::lower($domain['french'])) {
                return $domain['code'];
            }
        }

        throw self::invalidDomain($label);
    }

    /**
     * Retourne le code canonique associé à un alias legacy à deux caractères.
     *
     * @throws KernelCodeEngineException si l'alias legacy est inconnu.
     */
    public static function fromLegacyAlias(string $legacy): string
    {
        $value = strtoupper(trim($legacy));

        foreach (self::DOMAINS as $domain) {
            if ($value === $domain['legacy']) {
                return $domain['code'];
            }
        }

        throw self::invalidDomain($legacy);
    }

    /**
     * Retourne l'alias legacy du code sans rendre cet alias canonique.
     */
    public static function legacyAlias(string $code): string
    {
        return self::get($code)['legacy'];
    }

    /**
     * @return string
     */
    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private static function invalidDomain(string $input): KernelCodeEngineException
    {
        return new KernelCodeEngineException(
            KernelCodeEngineException::INVALID_DOMAIN,
            "Domaine créateur inconnu ou non autorisé : «{$input}»"
        );
    }
}