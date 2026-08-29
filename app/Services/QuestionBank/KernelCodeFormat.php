<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

use App\Exceptions\QuestionBank\KernelCodeEngineException;
use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;

/**
 * Encodage canonique partagé des segments du kernel_code DEC-121 v2.2.
 *
 * Le KernelBlueprint utilise cette classe pour sa projection progressive et
 * KernelCodeEngine l'utilise pour le bassin VVVV et l'assemblage final.
 */
final class KernelCodeFormat
{
    public const FORMAT_REGEX = '/^[0-9]{2}-[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}-[0-9A-Z]{4}$/';
    public const CODE_LENGTH = 23;

    private const OFFICIAL_DOMAINS = [
        'Géographie', 'Histoire', 'Faune', 'Art',
        'Sport', 'Cinéma', 'Cuisine', 'Science',
        'geographie', 'histoire', 'faune', 'art',
        'sport', 'cinema', 'cinéma', 'cuisine', 'science',
    ];

    /**
     * Mapping historique exact observé dans KernelCodeEngine avant DEC-121 v2.2.
     * Il sert uniquement à détecter un bassin legacy non réconcilié.
     */
    private const LEGACY_DOMAIN_CODES = [
        'Géographie' => 'GE',
        'Histoire'   => 'HI',
        'Faune'      => 'FA',
        'Art'        => 'AR',
        'Sport'      => 'SP',
        'Cinéma'     => 'CI',
        'Cuisine'    => 'CU',
        'Science'    => 'SC',
        'geographie' => 'GE',
        'histoire'   => 'HI',
        'faune'      => 'FA',
        'art'        => 'AR',
        'sport'      => 'SP',
        'cinema'     => 'CI',
        'cinéma'     => 'CI',
        'cuisine'    => 'CU',
        'science'    => 'SC',
    ];

    public static function depth(int $depth): string
    {
        if (! DepthContractRegistry::isKnown($depth)) {
            throw new KernelCodeEngineException(
                KernelCodeEngineException::INVALID_DEPTH,
                "Depth non reconnu par DepthContractRegistry : {$depth}"
            );
        }

        return str_pad((string) $depth, 2, '0', STR_PAD_LEFT);
    }

    public static function domain(string $domain): string
    {
        if (! in_array($domain, self::OFFICIAL_DOMAINS, true)) {
            throw new KernelCodeEngineException(
                KernelCodeEngineException::INVALID_DOMAIN,
                "Domaine non reconnu ou non autorisé en création : «{$domain}»"
            );
        }

        return self::segment($domain);
    }

    public static function legacyDomain(string $domain): ?string
    {
        return self::LEGACY_DOMAIN_CODES[$domain] ?? null;
    }

    public static function segment(string $value): string
    {
        if (function_exists('normalizer_normalize')) {
            $nfd = (string) normalizer_normalize($value, \Normalizer::NFD);
            $ascii = (string) preg_replace('/[\x{0300}-\x{036f}]/u', '', $nfd);
        } else {
            $ascii = (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
        }

        $clean = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($ascii));

        if ($clean === '') {
            throw new KernelCodeEngineException(
                KernelCodeEngineException::INVALID_SEGMENT,
                "Aucun caractère exploitable dans le segment : «{$value}»"
            );
        }

        return str_pad(substr($clean, 0, 3), 3, 'X');
    }
}