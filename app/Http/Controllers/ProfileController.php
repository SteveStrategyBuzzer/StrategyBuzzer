<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Services\AvatarCatalog;

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
            'language' => 'Français',
            'country' => '',
            'sound' => ['ambiance' => true,'buzzer' => true,'results' => false,'buzzer_id' => null,'music_id' => null],
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

        $settings['language'] = in_array($settings['language'] ?? null, ['Français','Anglais'])
            ? $settings['language'] : 'Français';
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
            $stratSkills = $stratData['skills'] ?? [];
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
        
        // Récupérer le joueur pour afficher son code
        $player = Auth::user();
        
        // Vérifier si un avatar est sélectionné
        $hasAvatar = !empty(data_get($settings, 'avatar.url'));

        return view('profile', compact(
            'settings','routes','currentCountry',
            'stratName','stratUrl','stratTier','stratSkills','player','hasAvatar'
        ));
    }

    /** Mise à jour profil */
    public function update(Request $request)
    {
        Log::debug('⏺ Entrée dans update()');
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $request->merge(['country' => strtoupper($request->input('country', ''))]);

        $data = $request->validate([
            'pseudonym' => 'nullable|string|max:24',
            'show_in_league' => 'nullable|in:Oui,Non',
            'show_online' => 'nullable|boolean',
            'language' => 'nullable|in:Français,Anglais',
            'country' => 'nullable|string|max:2',
            'sound.buzzer_id' => 'nullable|string|max:64',
            'sound.music_id' => 'nullable|string|max:64',
            'theme.style' => 'nullable|string|max:32',
            'theme.decor' => 'nullable|string|max:64',
            'avatar.type' => 'nullable|in:regular,strategic',
            'avatar.id' => 'nullable|string|max:64',
            'avatar.name' => 'nullable|string|max:64',
            'avatar.url' => 'nullable|url',
            'strategic_avatar.id' => 'nullable|string|max:64',
            'strategic_avatar.name' => 'nullable|string|max:64',
            'strategic_avatar.url' => 'nullable|url',
        ]);

        $data['show_online'] = $request->boolean('show_online');
        $settings = array_replace_recursive($this->buildSettings(), $data);

        try {
            $user->profile_settings = $settings;
            
            // Vérifier que les champs obligatoires sont remplis avant de marquer comme complété
            $hasAvatar = !empty(data_get($settings, 'avatar.url'));
            $hasPseudonym = !empty(trim((string) data_get($settings, 'pseudonym', '')));
            
            // Marquer comme complété uniquement si avatar ET pseudonym sont présents
            $user->profile_completed = $hasAvatar && $hasPseudonym;
            
            $user->save();
        } catch (\Throwable $e) {
            Log::error('❌ Erreur de sauvegarde', ['exception' => $e->getMessage()]);
        }

        return redirect()->route('profile.show')->with('status', 'Profil mis à jour.');
    }
}
