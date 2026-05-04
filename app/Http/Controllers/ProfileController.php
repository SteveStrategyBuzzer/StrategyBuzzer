<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Services\AvatarCatalog;
use App\Services\DailyQuestService;
use App\Services\CurrencyDetectionService;
use App\Models\BotProfile;
use App\Models\BotQualificationEvent;
use App\Services\BotQualificationService;

class ProfileController extends Controller
{
    private function defaults(): array
    {
        return [
            'pseudonym' => null,
            'avatar' => ['type' => 'regular','id' => null,'name' => null,'url' => null],
            'strategic_avatar' => ['id' => null,'name' => null,'url' => null],
            'show_in_league' => 'Oui',
            'show_online' => true,
            'language' => 'fr',
            'country' => '',
            // BUG FIX #4/#8: Valeurs par défaut pour musique activée avec pistes sélectionnées
            'sound' => [
                'ambiance' => true,
                'buzzer' => true,
                'results' => true, // Gameplay activé par défaut
                'buzzer_id' => 'buzzer_default_1', // Par défaut: Buzzer 1
                'music_id' => 'strategybuzzer',    // Par défaut: StrategyBuzzer Ambiance
            ],
            'gameplay' => [
                'music_id' => 'strategybuzzer',    // Par défaut: StrategyBuzzer Gameplay
            ],
            'theme' => ['style' => 'Classique','decor' => null],
        ];
    }

    private function readUserSettings(): array
    {
        $user = Auth::user();
        if (!$user) return [];

        try {
            $raw = $user->profile_settings ?? [];
            if (is_string($raw)) return json_decode($raw, true) ?: [];
            if (is_array($raw)) return $raw;
        } catch (\Throwable $e) {
            Log::warning('Profile: lecture profile_settings impossible: ' . $e->getMessage());
        }
        return [];
    }

    private function buildSettings(): array
    {
        $settings = array_replace_recursive($this->defaults(), $this->readUserSettings());

        // Ne plus normaliser la langue ici - utiliser le code directement
        $settings['country'] = strtoupper((string) ($settings['country'] ?? ''));
        $settings['show_in_league'] = in_array($settings['show_in_league'] ?? null, ['Oui','Non'])
            ? $settings['show_in_league'] : 'Oui';
        $settings['show_online'] = (bool) ($settings['show_online'] ?? true);

        $defs = $this->defaults();
        $settings['sound'] = is_array($settings['sound'] ?? null) ? $settings['sound'] : $defs['sound'];
        $settings['theme'] = is_array($settings['theme'] ?? null) ? $settings['theme'] : $defs['theme'];

        $pseudo = trim((string) ($settings['pseudonym'] ?? ''));
        if ($pseudo === '') $pseudo = trim((string) (Auth::user()?->name ?? 'Joueur'));
        $settings['pseudonym'] = $pseudo;

        return $settings;
    }

    /** Affichage profil */
    public function show()
    {
        $settings = $this->buildSettings();

        // Ajout des infos sur l’avatar stratégique choisi
        $stratName   = data_get($settings, 'strategic_avatar.name');
        $stratSlug   = data_get($settings, 'strategic_avatar.id');
        $stratUrl    = data_get($settings, 'strategic_avatar.url');
        $stratTier   = null;
        $stratSkills = [];

        $catalog = AvatarCatalog::get();
        if (!empty($stratSlug) && isset($catalog['stratégiques']['items'][$stratSlug])) {
            $stratData  = $catalog['stratégiques']['items'][$stratSlug];
            $stratTier  = $stratData['tier'] ?? null;
            $stratSkills = $stratData['skills_short'] ?? $stratData['skills'] ?? [];
            if (!$stratUrl) $stratUrl = asset($stratData['path']);
            if (!$stratName) $stratName = $stratData['name'];
        }

        $routes = [
            'avatar'   => Route::has('avatar'),
            'boutique' => Route::has('boutique'),
            'delete'   => Route::has('account.delete.show'),
            'update'   => Route::has('profile.update'),
        ];

        $currentCountry = strtoupper((string) data_get($settings, 'country', ''));

        // Auto-détection du pays via IP si non encore défini — sauvegarde immédiate
        $suggestedCountry = '';
        if ($currentCountry === '') {
            $detectedCountry = app(CurrencyDetectionService::class)->detectCountry() ?? '';
            if ($detectedCountry !== '') {
                // Sauvegarder directement dans profile_settings
                $user = Auth::user();
                if ($user) {
                    $settings['country'] = $detectedCountry;
                    $user->profile_settings = $settings;
                    $user->save();
                }
                $currentCountry    = $detectedCountry;
                $suggestedCountry  = $detectedCountry;
            }
        }
        
        // Récupérer le joueur pour afficher son code
        $player = Auth::user();
        
        // Vérifier si un avatar est sélectionné
        $hasAvatar = !empty(data_get($settings, 'avatar.url'));

        $botProfile = BotProfile::find($player?->id);
        $botQualifyCount = $player ? BotQualificationEvent::where('user_id', $player->id)->count() : 0;
        $botTier = 'none';
        if ($botQualifyCount >= 200) $botTier = 'gold';
        elseif ($botQualifyCount >= 50) $botTier = 'silver';
        elseif ($botQualifyCount >= 10) $botTier = 'bronze';

        $unlockedStrategicAvatars = $this->getUnlockedStrategicAvatars($player);

        return response()->view('profile', compact(
            'settings','routes','currentCountry','suggestedCountry',
            'stratName','stratUrl','stratTier','stratSkills','player','hasAvatar',
            'botProfile','botQualifyCount','botTier','unlockedStrategicAvatars'
        ))->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /** Mise à jour profil */
    public function update(Request $request)
    {
        Log::debug('⏺ Entrée dans update()');
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $request->merge(['country' => strtoupper($request->input('country', ''))]);

        $supportedLangCodes = array_keys(config('languages.supported', ['fr' => []]));
        
        $data = $request->validate([
            'pseudonym' => 'nullable|string|max:10',
            'show_in_league' => 'nullable|in:Oui,Non',
            'show_online' => 'nullable|boolean',
            'language' => 'nullable|in:' . implode(',', $supportedLangCodes),
            'country' => 'nullable|string|max:2',
            'sound.buzzer_id' => 'nullable|string|max:64',
            'sound.music_id' => 'nullable|string|max:64',
            'options.ambiance' => 'nullable|boolean',
            'options.results' => 'nullable|boolean',
            'gameplay.music_id' => 'nullable|string|max:64',
            'theme.style' => 'nullable|string|max:32',
            'theme.decor' => 'nullable|string|max:64',
            'avatar.type' => 'nullable|in:regular,strategic',
            'avatar.id' => 'nullable|string|max:64',
            'avatar.name' => 'nullable|string|max:64',
            'avatar.url' => 'nullable|url',
            'strategic_avatar.id' => 'nullable|string|max:64',
            'strategic_avatar.name' => 'nullable|string|max:64',
            'strategic_avatar.url' => 'nullable|url',
            'bot_active' => 'nullable|boolean',
            'bot_avatar_slug' => 'nullable|string|max:64',
            'bot_stake_enabled' => 'nullable|boolean',
            'bot_max_stake' => 'nullable|integer|min:0|max:500',
        ]);

        $data['show_online'] = $request->boolean('show_online');
        
        // Normaliser les booleans options -> sound
        if ($request->has('options')) {
            if (!isset($data['sound'])) $data['sound'] = [];
            $data['sound']['ambiance'] = $request->boolean('options.ambiance');
            $data['sound']['results'] = $request->boolean('options.results');
            unset($data['options']);
        }
        
        // Capturer l'avatar actuel avant modification (pour détecter un changement)
        $oldAvatarUrl  = data_get($this->buildSettings(), 'avatar.url');
        $oldStratSlug  = data_get($this->buildSettings(), 'strategic_avatar.id');

        $settings = array_replace_recursive($this->buildSettings(), $data);

        try {
            // Sauvegarder la langue dans le champ dédié preferred_language
            if (isset($data['language'])) {
                $user->preferred_language = $data['language'];
            }
            
            // Synchroniser le champ name avec le pseudonyme (limité à 10 caractères)
            if (isset($data['pseudonym']) && !empty(trim($data['pseudonym']))) {
                $user->name = mb_substr(trim($data['pseudonym']), 0, 10);
            }
            
            $user->profile_settings = $settings;
            
            // Vérifier que les champs obligatoires sont remplis avant de marquer comme complété
            $hasAvatar = !empty(data_get($settings, 'avatar.url'));
            $hasPseudonym = !empty(trim((string) data_get($settings, 'pseudonym', '')));
            
            // Marquer comme complété uniquement si avatar ET pseudonym sont présents
            $user->profile_completed = $hasAvatar && $hasPseudonym;
            
            $user->save();

            $botProfile = BotProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['is_active' => false]
            );

            $wantsActive = $request->boolean('bot_active');
            $qualifyCount = BotQualificationEvent::where('user_id', $user->id)->count();
            $botProfile->is_active = $wantsActive && $qualifyCount >= 10;

            $botProfile->stake_enabled = $request->boolean('bot_stake_enabled');
            $botProfile->max_stake_per_match = max(0, min(500, (int) $request->input('bot_max_stake', 0)));

            $slugInput = $request->input('bot_avatar_slug') ?: null;
            if ($slugInput) {
                $unlockedSlugs = array_keys($this->getUnlockedStrategicAvatars($user));
                $botProfile->bot_avatar_slug = in_array($slugInput, $unlockedSlugs, true) ? $slugInput : null;
            } else {
                $botProfile->bot_avatar_slug = null;
            }

            $botProfile->save();

            // Quête quotidienne : changer d'avatar (condition : avoir joué au moins 1 match aujourd'hui)
            $newAvatarUrl = data_get($settings, 'avatar.url');
            $newStratSlug = data_get($settings, 'strategic_avatar.id');
            $avatarChanged = ($newAvatarUrl !== $oldAvatarUrl) || ($newStratSlug !== $oldStratSlug);
            if ($avatarChanged) {
                try {
                    $playedToday = \App\Models\DuoMatch::where(function ($q) use ($user) {
                            $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
                        })->where('status', 'completed')->whereDate('updated_at', today())->exists()
                        || \App\Models\LeagueIndividualMatch::where(function ($q) use ($user) {
                            $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
                        })->where('status', 'completed')->whereDate('updated_at', today())->exists();

                    if ($playedToday) {
                        app(DailyQuestService::class)->checkAndCompleteDailyQuest(
                            $user, 'daily_change_avatar', ['avatar_changed' => true]
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('DailyQuest avatar_changed failed: ' . $e->getMessage());
                }
            }
            
            // Réponse AJAX si requête AJAX
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profil sauvegardé automatiquement',
                    'profile_completed' => $user->profile_completed
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('❌ Erreur de sauvegarde', ['exception' => $e->getMessage()]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de sauvegarde'
                ], 500);
            }
        }

        return redirect()->route('profile.show')->with('status', 'Profil mis à jour.');
    }

    /** Sauvegarde AJAX de la config bot uniquement */
    public function updateBotConfig(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false], 401);

        $data = $request->validate([
            'bot_active'        => 'nullable|boolean',
            'bot_avatar_slug'   => 'nullable|string|max:64',
            'bot_stake_enabled' => 'nullable|boolean',
            'bot_max_stake'     => 'nullable|integer|min:0|max:500',
        ]);

        $botProfile = BotProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['is_active' => false]
        );

        $wantsActive  = $request->boolean('bot_active');
        $qualifyCount = BotQualificationEvent::where('user_id', $user->id)->count();
        $botProfile->is_active = $wantsActive && $qualifyCount >= 10;

        $botProfile->stake_enabled        = $request->boolean('bot_stake_enabled');
        $botProfile->max_stake_per_match  = max(0, min(500, (int) $request->input('bot_max_stake', 0)));

        $slugInput = $request->input('bot_avatar_slug') ?: null;
        if ($slugInput) {
            $unlockedSlugs = array_keys($this->getUnlockedStrategicAvatars($user));
            $botProfile->bot_avatar_slug = in_array($slugInput, $unlockedSlugs, true) ? $slugInput : null;
        } else {
            $botProfile->bot_avatar_slug = null;
        }

        $botProfile->save();

        return response()->json([
            'success'       => true,
            'is_active'     => $botProfile->is_active,
            'qualify_count' => $qualifyCount,
            'qualified'     => $qualifyCount >= 10,
        ]);
    }

    private function getUnlockedStrategicAvatars($user): array
    {
        if (!$user) return [];
        $settings = $user->profile_settings ?? [];
        $catalog = AvatarCatalog::getStrategiques();
        $userUnlocked = (array) data_get($settings, 'unlocked_avatars', []);
        $result = [];
        foreach ($catalog as $slug => $avatar) {
            if (in_array($slug, $userUnlocked)) {
                $result[$slug] = $avatar;
            }
        }
        return $result;
    }
}
