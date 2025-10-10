<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AvatarCatalog;

class AvatarController extends Controller
{
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
        $selectedStd   = (string) data_get($settings, 'avatar.url', '');                 // chemin image (ex: images/avatars/standard/standard1.png)
        $selectedStrat = (string) data_get($settings, 'strategic_avatar.id', '');        // slug (ex: mathematicien)

        // Déblocages (ancienne/actuelle structures tolérées)
        $unlockedRaw   = (array) data_get($settings, 'unlocked', []);                    // parfois un flat array
        $unlockedAv    = (array) data_get($settings, 'unlocked_avatars', []);            // notre clé explicite
        $questsDone    = (array) data_get($settings, 'quests_completed', []);            // quêtes
        $unlockedStrategiques = array_values(array_unique(array_merge(
            array_intersect(self::STRATEGIQUE_ORDER, $unlockedRaw),
            $unlockedAv
        )));
        $unlockedPacks = array_values(array_intersect(self::PACKS, $unlockedRaw));
        
        // Débloquer "portraits" par défaut pour tous les utilisateurs (pack gratuit de base)
        if (!in_array('portraits', $unlockedPacks, true)) {
            $unlockedPacks[] = 'portraits';
        }

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

        // Vue avatars
        return view('avatars', [
            'coins'          => $coins,
            'groups'         => ['stratégique' => $cards],
            'unlockedPacks'  => $unlockedPacks,
            // sélection actuelle (séparée pour éviter les collisions slug vs chemin)
            'selected'       => $selectedStd,       // utilisé par tes carrousels "standards/packs"
            'selectedStrat'  => $selectedStrat,     // à utiliser côté grille stratégique si besoin
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

        // 1) Standard / Pack (on détecte un chemin d’image)
        if (strpos($value, '/') !== false || preg_match('#\.(png|jpg|jpeg|webp)$#i', $value)) {

            $current = (string) data_get($settings, 'avatar.url', '');
            if ($current === $value) {
                // toggle off (si tu veux le garder, sinon supprime ce bloc)
                data_set($settings, 'avatar', [
                    'type' => 'regular',
                    'id'   => null,
                    'name' => null,
                    'url'  => null,
                ]);
                session(['selected_avatar' => 'default']); // Sync avec session
            } else {
                data_set($settings, 'avatar', [
                    'type' => 'regular',
                    'id'   => basename($value),
                    'name' => null,
                    'url'  => $value, // on stocke le chemin relatif; ta vue le normalise
                ]);
                session(['selected_avatar' => basename($value, '.png')]); // Sync avec session
            }
            $changed = true;
        }
        // 2) Stratégique (on détecte un slug connu)
        elseif (in_array($value, self::STRATEGIQUE_ORDER, true)) {

            // doit être débloqué
            $unlockedAv = (array) data_get($settings, 'unlocked_avatars', []);
            $unlockedRaw= (array) data_get($settings, 'unlocked', []);
            $isUnlocked = in_array($value, $unlockedAv, true)
                       || in_array($value, $unlockedRaw, true);

            if (!$isUnlocked) {
                // renvoie à la boutique ciblée (ou simple message)
                return redirect()
                    ->to(route('boutique') . '?stratégique=' . urlencode($value))
                    ->with('error', "Débloque d’abord cet avatar stratégique.");
            }

            $current = (string) data_get($settings, 'strategic_avatar.id', '');
            if ($current === $value) {
                // toggle off (si tu ne veux pas de toggle, supprime ce bloc)
                data_set($settings, 'strategic_avatar', [
                    'id'   => null,
                    'name' => null,
                    'url'  => null,
                ]);
                session(['avatar' => 'Aucun']); // Sync avec session pour SoloController
            } else {
                $img  = self::STRATEGIQUE_IMAGES[$value] ?? 'images/avatars/default.png';
                $name = self::STRATEGIQUE_NAMES[$value] ?? ucfirst($value);
                data_set($settings, 'strategic_avatar', [
                    'id'   => $value,
                    'name' => $name,
                    'url'  => $img, // stocke le chemin relatif; ta vue le normalise
                ]);
                session(['avatar' => $name]); // Sync avec session pour SoloController
            }
            $changed = true;
        }
        // 3) Sinon : valeur inconnue
        else {
            return back()->with('error', 'Sélection invalide.');
        }

        if ($changed) {
            $user->profile_settings = $settings;
            $user->save();
            session()->flash('avatar_updated', true);
        }

        // Redirection douce
        if ($from === 'profile' && app('router')->has('profile.show')) {
            return redirect()->route('profile.show')->with('success', 'Avatar mis à jour.');
        }
        if ($from === 'resume' && app('router')->has('solo.resume')) {
            return redirect()->route('solo.resume')->with('success', 'Avatar mis à jour.');
        }
        if (filter_var($from, FILTER_VALIDATE_URL)) {
            return redirect($from)->with('success', 'Avatar mis à jour.');
        }
        // fallback : retourne sur la page avatars
        return app('router')->has('avatars')
            ? redirect()->route('avatars', ['from' => 'profile'])->with('success', 'Avatar mis à jour.')
            : back()->with('success', 'Avatar mis à jour.');
    }

    /** Achat direct d’un stratégique (optionnel, la boutique gère surtout ça). */
    public function buy(Request $r)
    {
        $r->validate(['avatar' => 'required|string']);
        $user = Auth::user();
        if (!$user) return back()->with('error', 'Veuillez vous connecter.');

        $slug = (string) $r->input('avatar');
        if (!in_array($slug, self::STRATEGIQUE_ORDER, true)) {
            return back()->with('error', 'Avatar inconnu.');
        }

        $settings = (array) ($user->profile_settings ?? []);
        $coins    = (int) $user->coins;

        // prix (catalog > tier)
        $catalog  = class_exists(AvatarCatalog::class) ? (array) AvatarCatalog::get() : [];
        $meta     = (array) data_get($catalog, 'stratégiques.items.' . $slug, []);
        $tier     = (string) ($meta['tier'] ?? (self::STRATEGIQUE_TIERS[$slug] ?? 'Rare'));
        $price    = isset($meta['price']) ? (int) $meta['price'] : $this->defaultPriceForTier($tier);

        if ($coins < $price) {
            return back()->with('error', "Pas assez de pièces d’intelligence.");
        }

        // Débit & unlock
        $user->coins = $coins - $price;
        $unlocked = (array) data_get($settings, 'unlocked_avatars', []);
        if (!in_array($slug, $unlocked, true)) {
            $unlocked[] = $slug;
        }
        data_set($settings, 'unlocked_avatars', array_values($unlocked));

        $user->profile_settings = $settings;
        $user->save();

        return back()->with('success', self::STRATEGIQUE_NAMES[$slug] . " débloqué !");
    }

    /** Prix par défaut selon le tier */
    private function defaultPriceForTier(string $tier): int
    {
        return match ($tier) {
            'Épique'      => 500,
            'Légendaire'  => 900,
            default       => 250, // Rare
        };
    }
}
