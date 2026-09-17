<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Gameplay;

/**
 * Gameplay-only adapter for the General/Shuffle command.
 *
 * `general` is a gameplay selection mode, never a CreatorDomainRegistry
 * identity. The configured French labels and weights remain authoritative so
 * existing Shuffle ordering and quota behavior are unchanged.
 */
final class GameplayShuffleAdapter
{
    private const SHUFFLE_THEMES = [
        'general',
        'général',
    ];

    public static function isShuffleTheme(string $theme): bool
    {
        return in_array(self::normalise($theme), self::SHUFFLE_THEMES, true);
    }

    /**
     * @return array<int, string>
     */
    public static function subDomains(array $config): array
    {
        return array_values($config['general_sub_domains'] ?? []);
    }

    /**
     * @return array<string, int>|string
     */
    public static function weights(array $config): array|string
    {
        return $config['general_sub_domain_weights'] ?? 'equal';
    }

    /**
     * @return array<int, string>
     */
    public static function resolveDomainList(string $theme, array $config): array
    {
        return self::isShuffleTheme($theme)
            ? self::subDomains($config)
            : [$theme];
    }

    private static function normalise(string $theme): string
    {
        return strtolower($theme);
    }
}