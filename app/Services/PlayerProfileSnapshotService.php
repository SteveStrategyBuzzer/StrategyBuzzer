<?php

namespace App\Services;

use App\Models\BotProfile;
use App\Models\BotQualificationEvent;
use App\Models\User;

class PlayerProfileSnapshotService
{
    public static function build(User $user): array
    {
        $settings = self::normalizeSettings($user->profile_settings ?? []);

        $avatar = [
            'type' => data_get($settings, 'avatar.type', 'regular'),
            'id'   => data_get($settings, 'avatar.id'),
            'name' => data_get($settings, 'avatar.name'),
            'url'  => self::normalizeAvatarPath(
                data_get($settings, 'avatar.url', 'images/avatars/standard/standard1.png')
            ),
        ];

        $strategicAvatar = [
            'id'   => data_get($settings, 'strategic_avatar.id'),
            'name' => data_get($settings, 'strategic_avatar.name'),
            'url'  => self::normalizeAvatarPath(data_get($settings, 'strategic_avatar.url')),
        ];

        $botProfile = BotProfile::find($user->id);
        $botQualifyCount = BotQualificationEvent::where('user_id', $user->id)->count();

        $botTier = 'none';
        if ($botQualifyCount >= 200) {
            $botTier = 'gold';
        } elseif ($botQualifyCount >= 50) {
            $botTier = 'silver';
        } elseif ($botQualifyCount >= 10) {
            $botTier = 'bronze';
        }

        return [
            'user_id' => $user->id,
            'player_code' => $user->player_code,
            'profile_completed' => (bool) ($user->profile_completed ?? false),

            'identity' => [
                'display_name' => self::resolveDisplayName($user, $settings),
                'email'        => $user->email,
                'country'      => strtoupper((string) data_get($settings, 'country', '')),
                'language'     => $user->preferred_language ?? config('languages.default', 'fr'),
            ],

            'avatar' => $avatar,

            'strategic_avatar' => $strategicAvatar,

            'theme' => [
                'style' => data_get($settings, 'theme.style', 'Classique'),
                'decor' => data_get($settings, 'theme.decor'),
            ],

            'sound' => [
                'ambiance'         => (bool) data_get($settings, 'sound.ambiance', true),
                'buzzer'           => (bool) data_get($settings, 'sound.buzzer', true),
                'results'          => (bool) data_get($settings, 'sound.results', true),
                'buzzer_id'        => data_get($settings, 'sound.buzzer_id', 'buzzer_default_1'),
                'music_id'         => data_get($settings, 'sound.music_id', 'strategybuzzer'),
                'correct_sound_id' => data_get($settings, 'sound.correct_sound_id', 'correct_default'),
                'wrong_sound_id'   => data_get($settings, 'sound.wrong_sound_id', 'wrong_default'),
                'gameplay_music_id'=> data_get($settings, 'gameplay.music_id', data_get($settings, 'sound.music_id', 'strategybuzzer')),
            ],

            'progression' => [
                'solo_level'   => max(1, (int) data_get($settings, 'choix_niveau', 1)),
                'duo_level'    => max(0, (int) data_get($settings, 'gm.duo_level', 0)),
                'league_level' => max(0, (int) data_get($settings, 'gm.league_level', 0)),
                'gm_grade'     => (string) data_get($settings, 'gm.grade', 'Rookie'),
            ],

            'visibility' => [
                'show_in_league' => data_get($settings, 'show_in_league', 'Oui'),
                'show_online'    => (bool) data_get($settings, 'show_online', true),
            ],

            'bot' => [
                'is_active'           => (bool) ($botProfile?->is_active ?? false),
                'qualified_count'     => $botQualifyCount,
                'tier'                => $botTier,
                'bot_avatar_slug'     => $botProfile?->bot_avatar_slug,
                'stake_enabled'       => (bool) ($botProfile?->stake_enabled ?? false),
                'max_stake_per_match' => (int) ($botProfile?->max_stake_per_match ?? 0),
                'times_used_as_bot'   => (int) ($botProfile?->times_used_as_bot ?? 0),
                'bot_wins'            => (int) ($botProfile?->bot_wins ?? 0),
                'bot_losses'          => (int) ($botProfile?->bot_losses ?? 0),
                'coins_earned_for_owner' => (int) ($botProfile?->coins_earned_for_owner ?? 0),
            ],
        ];
    }

    private static function normalizeSettings(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private static function resolveDisplayName(User $user, array $settings): string
    {
        $pseudonym = trim((string) data_get($settings, 'pseudonym', ''));
        if ($pseudonym !== '') {
            return $pseudonym;
        }

        return trim((string) ($user->name ?? 'Joueur')) ?: 'Joueur';
    }

    private static function normalizeAvatarPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $fixed = preg_replace('#^Images/#', 'images/', $path);

        if (str_starts_with($fixed, 'http://') || str_starts_with($fixed, 'https://') || str_starts_with($fixed, '/')) {
            return $fixed;
        }

        return ltrim($fixed, '/');
    }
}
