<?php

namespace App\Http\Controllers;

use App\Traits\LogsCriticalAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AvatarCatalog;
use App\Services\CoinLedgerService;

class AvatarController extends Controller
{
    use LogsCriticalAction;

    public function __construct(
        private CoinLedgerService $coinLedgerService
    ) {}

    /** Slugs canoniques (ordre d’affichage) */
    private const STRATEGIQUE_ORDER = [
        'mathematicien','scientifique','explorateur','defenseur',
        'comedienne','magicienne','challenger','historien',
        'ia-junior','stratege','sprinteur','visionnaire',
    ];

    /** Noms FR jolis (pas de slug à l’écran) */
    private const STRATEGIQUE_NAMES = [
        'mathematicien' => 'Mathématicien',
        'scientifique'  => 'Scientifique',
        'explorateur'   => 'Explorateur',
        'defenseur'     => 'Défenseur',
        'comedienne'    => 'Comédienne',
        'magicienne'    => 'Magicienne',
        'challenger'    => 'Challenger',
        'historien'     => 'Historien',
        'ia-junior'     => 'IA Junior',
        'stratege'      => 'Stratège',
        'sprinteur'     => 'Sprinteur',
        'visionnaire'   => 'Visionnaire',
    ];

    /** Tiers (Rare / Épique / Légendaire) */
    private const STRATEGIQUE_TIERS = [
        'mathematicien' => 'Rare',
        'scientifique'  => 'Rare',
        'explorateur'   => 'Rare',
        'defenseur'     => 'Rare',
        'comedienne'    => 'Épique',
        'magicienne'    => 'Épique',
        'challenger'    => 'Épique',
        'historien'     => 'Épique',
        'ia-junior'     => 'Légendaire',
        'stratege'      => 'Légendaire',
        'sprinteur'     => 'Légendaire',
        'visionnaire'   => 'Légendaire',
    ];

    /** Images officielles (dans /public/images/avatars) */
    private const STRATEGIQUE_IMAGES = [
        'mathematicien' => 'images/avatars/mathematicien.png',
        'scientifique'  => 'images/avatars/scientifique.png',
        'explorateur'   => 'images/avatars/explorateur.png',
        'defenseur'     => 'images/avatars/defenseur.png',
        'comedienne'    => 'images/avatars/comedienne.png',
        'magicienne'    => 'images/avatars/magicienne.png',
        'challenger'    => 'images/avatars/challenger.png',
        'historien'     => 'images/avatars/historien.png',
        'ia-junior'     => 'images/avatars/ia-junior.png',
        'stratege'      => 'images/avatars/stratege.png',
        'sprinteur'     => 'images/avatars/sprinteur.png',
        'visionnaire'   => 'images/avatars/visionnaire.png',
    ];

    /** Slugs des packs (pour l’état “débloqué”) */
    private const PACKS = [
        'portraits','cartoon','animal','mythique','paysage','objet','clown','musicien','automobile',
    ];

    /** Page Avatars (standards + packs + stratégiques) */
    public function index(Request $r)
    {
        $user = Auth::user();
        $coins = $user?->coins ?? 0;
        $settings = (array) ($user?->profile_settings ?? []);

        // Sélections actuelles (on maintient les 2 indépendantes)
        $selectedStd   = (string) data_get($settings, 'avatar.url', '');
        $selectedStrat = (string) data_get($settings, 'strategic_avatar.id', '');

        // Déblocages (ancienne/actuelle structures tolérées)
        $unlockedRaw   = (array) data_get($settings, 'unlocked', []);
        $unlockedAv    = (array) data_get($settings, 'unlocked_avatars', []);
        $questsDone    = (array) data_get($settings, 'quests_completed', []);

        // Source de vérité : table user_avatars (slugs des stratégiques débloqués)
        $unlockedFromDb = [];
        if ($user) {
            $unlockedFromDb = DB::connection('pgsql')
                ->table('user_avatars')
                ->where('user_id', $user->id)
                ->where('unlocked', true)
                ->pluck('avatar_name')
                ->toArray();
        }

        // Fusionner les trois sources pour stratégiques
        $unlockedStrategiques = array_values(array_unique(array_merge(
            array_intersect(self::STRATEGIQUE_ORDER, $unlockedRaw),
            array_intersect(self::STRATEGIQUE_ORDER, $unlockedAv),
            array_intersect(self::STRATEGIQUE_ORDER, $unlockedFromDb)
        )));

        // Fusionner les deux sources pour packs
        $unlockedPacks = array_values(array_unique(array_merge(
            array_intersect(self::PACKS, $unlockedRaw),
            array_intersect(self::PACKS, $unlockedAv),
            array_intersect(self::PACKS, $unlockedFromDb)
        )));

        // Catalog (si service dispo) pour récupérer prix/overrides
        $catalog = class_exists(AvatarCatalog::class) ? (array) AvatarCatalog::get() : [];
        $catStrats = (array) data_get($catalog, 'stratégiques.items', []);

        // Construit les cartes stratégiques
        $cards = [];
        foreach (self::STRATEGIQUE_ORDER as $slug) {
            $meta      = $catStrats[$slug] ?? [];
            $name      = (string) ($meta['name'] ?? (self::STRATEGIQUE_NAMES[$slug] ?? ucfirst($slug)));
            $tier      = (string) ($meta['tier'] ?? (self::STRATEGIQUE_TIERS[$slug] ?? 'Rare'));
            $price     = isset($meta['price']) ? (int) $meta['price'] : $this->defaultPriceForTier($tier);
            $unlockVia = $meta['unlock_quest'] ?? null;

            $isUnlocked = in_array($slug, $unlockedStrategiques, true)
                       || (!empty($unlockVia) && in_array($unlockVia, $questsDone, true));

            $cards[] = [
                'slug'     => $slug,
                'name'     => $name,
                'tier'     => $tier,
                'price'    => $price,
                'unlocked' => $isUnlocked,
            ];
        }

        // Quête quotidienne : lire la description d'un avatar stratégique
        if ($user) {
            try {
                app(\App\Services\DailyQuestService::class)->checkAndCompleteDailyQuest(
                    $user, 'daily_read_avatar_desc', ['avatar_desc_read' => true]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('DailyQuest avatar_desc_read failed: ' . $e->getMessage());
            }
        }

        // Standard avatars — dynamic list from catalog (auto-discovers all standard*.png)
        $standardImgs = (array) data_get($catalog, 'standards.images', []);
        // Fallback: hardcoded baseline if catalog returned nothing
        if (empty($standardImgs)) {
            $standardImgs = array_map(
                fn($i) => "images/avatars/standard/standard{$i}.png",
                range(1, 8)
            );
        }

        // Vue avatars
        return view('avatars', [
            'coins'          => $coins,
            'groups'         => ['stratégique' => $cards],
            'unlockedPacks'  => $unlockedPacks,
            'selected'       => $selectedStd,
            'selectedStrat'  => $selectedStrat,
            'standardImgs'   => $standardImgs,
        ]);
    }

    /** Sélection (un standard/packs = chemin image | un stratégique = slug) */
    public function select(Request $r)
    {
        $r->validate([
            'avatar' => 'required|string',
            'from'   => 'nullable|string',
        ]);

        $user = Auth::user();
        if (!$user) {
            return back()->with('error', 'Veuillez vous connecter.');
        }

        $value    = trim((string) $r->input('avatar'));
        $from     = (string) $r->input('from', 'profile');
        $settings = (array) ($user->profile_settings ?? []);
        $changed  = false;

        if (strpos($value, '/') !== false || preg_match('#\.(png|jpg|jpeg|webp)$#i', $value)) {
            $current = (string) data_get($settings, 'avatar.url', '');
            if ($current === $value) {
                data_set($settings, 'avatar', [
                    'type' => 'regular',
                    'id'   => null,
                    'name' => null,
                    'url'  => null,
                ]);
                session(['selected_avatar' => 'default']);
            } else {
                data_set($settings, 'avatar', [
                    'type' => 'regular',
                    'id'   => basename($value),
                    'name' => null,
                    'url'  => $value,
                ]);
                session(['selected_avatar' => $value]);
            }
            $changed = true;
        } elseif (in_array($value, self::STRATEGIQUE_ORDER, true)) {
            $unlockedAv = (array) data_get($settings, 'unlocked_avatars', []);
            $unlockedRaw= (array) data_get($settings, 'unlocked', []);
            $isUnlocked = in_array($value, $unlockedAv, true)
                       || in_array($value, $unlockedRaw, true);

            if (!$isUnlocked) {
                return redirect()
                    ->to(route('boutique') . '?stratégique=' . urlencode($value))
                    ->with('error', "Débloque d’abord cet avatar stratégique.");
            }

            $current = (string) data_get($settings, 'strategic_avatar.id', '');
            if ($current === $value) {
                data_set($settings, 'strategic_avatar', [
                    'id'   => null,
                    'name' => null,
                    'url'  => null,
                ]);
                session(['avatar' => 'Aucun']);
            } else {
                $img  = self::STRATEGIQUE_IMAGES[$value] ?? 'images/avatars/default.png';
                $name = self::STRATEGIQUE_NAMES[$value] ?? ucfirst($value);
                data_set($settings, 'strategic_avatar', [
                    'id'   => $value,
                    'name' => $name,
                    'url'  => $img,
                ]);
                session(['avatar' => $name]);
            }
            $changed = true;
        } else {
            return back()->with('error', 'Sélection invalide.');
        }

        if ($changed) {
            $user->profile_settings = $settings;
            $user->save();
            $this->logAction('avatar_select', ['value' => $value, 'from' => $from]);
            session()->flash('avatar_updated', true);

            // Quête avatars_different_2 : changer d'avatar au moins 2 fois
            $freshSettings = (array) ($user->profile_settings ?? []);
            $data = json_decode(json_encode($freshSettings), true);
            $usedAvatars = $data['avatars_used_history'] ?? [];
            $newAvatar = $value;
            if (!in_array($newAvatar, $usedAvatars, true)) {
                $usedAvatars[] = $newAvatar;
                $freshSettings['avatars_used_history'] = $usedAvatars;
                $user->profile_settings = $freshSettings;
                $user->save();
            }
            app(\App\Services\QuestService::class)->checkAndCompleteQuests($user, 'avatars_different_2', [
                'different_avatars_count' => count($usedAvatars),
            ]);
        }

        if ($from === 'profile' && app('router')->has('profile.show')) {
            return redirect()->route('profile.show')->with('success', 'Avatar mis à jour.');
        }
        if ($from === 'resume' && app('router')->has('solo.resume')) {
            return redirect()->route('solo.resume')->with('success', 'Avatar mis à jour.');
        }
        if (filter_var($from, FILTER_VALIDATE_URL)) {
            return redirect($from)->with('success', 'Avatar mis à jour.');
        }

        return app('router')->has('avatars')
            ? redirect()->route('avatars', ['from' => 'profile'])->with('success', 'Avatar mis à jour.')
            : back()->with('success', 'Avatar mis à jour.');
    }

    /** Achat direct d’un stratégique (optionnel, la boutique gère surtout ça). */
    public function buy(Request $r)
    {
        $r->validate(['avatar' => 'required|string']);

        $user = Auth::user();
        if (!$user) {
            return back()->with('error', 'Veuillez vous connecter.');
        }

        $slug = (string) $r->input('avatar');
        if (!in_array($slug, self::STRATEGIQUE_ORDER, true)) {
            return back()->with('error', 'Avatar inconnu.');
        }

        try {
            DB::transaction(function () use ($user, $slug) {
                $user->refresh();

                $settings = (array) ($user->profile_settings ?? []);
                $unlocked = (array) data_get($settings, 'unlocked_avatars', []);

                if (in_array($slug, $unlocked, true)) {
                    throw new \RuntimeException('ALREADY_UNLOCKED');
                }

                $catalog = class_exists(AvatarCatalog::class) ? (array) AvatarCatalog::get() : [];
                $meta    = (array) data_get($catalog, 'stratégiques.items.' . $slug, []);
                $tier    = (string) ($meta['tier'] ?? (self::STRATEGIQUE_TIERS[$slug] ?? 'Rare'));
                $price   = isset($meta['price']) ? (int) $meta['price'] : $this->defaultPriceForTier($tier);

                if (($user->coins ?? 0) < $price) {
                    throw new \RuntimeException('INSUFFICIENT_COINS');
                }

                $this->coinLedgerService->debit(
                    $user,
                    $price,
                    'avatar_unlock:' . $slug,
                    null,
                    null,
                    'intelligence'
                );

                $unlocked[] = $slug;
                data_set($settings, 'unlocked_avatars', array_values(array_unique($unlocked)));

                $user->profile_settings = $settings;
                $user->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'ALREADY_UNLOCKED') {
                return back()->with('info', 'Cet avatar est déjà débloqué.');
            }

            if ($e->getMessage() === 'INSUFFICIENT_COINS') {
                return back()->with('error', "Pas assez de pièces d’intelligence.");
            }

            throw $e;
        }

        // Quêtes avatars débloqués
        $freshSettings = (array) ($user->profile_settings ?? []);
        $unlockedCount = count((array) ($freshSettings['unlocked_avatars'] ?? []));
        $questService  = app(\App\Services\QuestService::class);
        $questService->checkAndCompleteQuests($user, 'avatars_unlocked_10', [
            'unlocked_avatars_count' => $unlockedCount,
        ]);
        $questService->checkAndCompleteQuests($user, 'avatars_unlocked_25', [
            'unlocked_avatars_count' => $unlockedCount,
        ]);

        return back()->with('success', self::STRATEGIQUE_NAMES[$slug] . " débloqué !");
    }

    /** Prix par défaut selon le tier */
    private function defaultPriceForTier(string $tier): int
    {
        return match ($tier) {
            'Épique'      => 500,
            'Légendaire'  => 900,
            default       => 250,
        };
    }
}
