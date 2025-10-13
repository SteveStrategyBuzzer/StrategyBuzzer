<?php  

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AvatarCatalog;

class BoutiqueController extends Controller
{
    /**
     * GET /boutique
     */
    public function index(Request $request)
    {
        $catalog  = AvatarCatalog::get();
        $user     = Auth::user();

        $coins    = $user?->coins ?? 0;
        $settings = (array) ($user?->profile_settings ?? []);
        $unlocked = $settings['unlocked_avatars'] ?? []; // ✅ corrigé : anciennement 'unlocked'

        $itemSlug        = $request->query('item');
        $strategiqueSlug = $request->query('stratégique');

        $context = [
            'coins'     => $coins,
            'unlocked'  => $unlocked,
            'catalog'   => $catalog,
            'mode'      => null,
            'entity'    => null,
            'slug'      => null,
            'coinPacks' => config('coins.packs', []),
            'pricing'   => [
                'pack'        => [],
                'buzzer'      => [],
                'stratégique' => [],
            ],
        ];

        // Packs
        foreach ($catalog as $slug => $entry) {
            if (is_array($entry) && isset($entry['price'])) {
                $context['pricing']['pack'][$slug] = (int) $entry['price'];
            }
        }
        // Buzzers
        foreach (($catalog['buzzers']['items'] ?? []) as $slug => $bz) {
            if (isset($bz['price'])) {
                $context['pricing']['buzzer'][$slug] = (int) $bz['price'];
            }
        }
        // Stratégiques
        foreach (($catalog['stratégiques']['items'] ?? []) as $slug => $a) {
            if (isset($a['price'])) {
                $context['pricing']['stratégique'][$slug] = (int) $a['price'];
            }
        }

        // Cible onglet
        if ($strategiqueSlug) {
            $stratégiques = $catalog['stratégiques']['items'] ?? [];
            if (isset($stratégiques[$strategiqueSlug])) {
                $context['mode']   = 'stratégique';
                $context['entity'] = $stratégiques[$strategiqueSlug];
                $context['slug']   = $strategiqueSlug;
            }
        } elseif ($itemSlug) {
            if (isset($catalog[$itemSlug])) {
                $context['mode']   = 'pack';
                $context['entity'] = $catalog[$itemSlug];
                $context['slug']   = $itemSlug;
            }
        }

        return response()
            ->view('boutique', $context)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * POST /boutique/purchase
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'kind'     => 'required|string|in:pack,buzzer,stratégique,life,master',
            'target'   => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $kind   = $request->input('kind');
        $target = $request->input('target');
        $qty    = max(1, (int) $request->input('quantity', 1));

        $user = Auth::user();
        if (!$user) {
            return back()->with('error', "Veuillez vous connecter.");
        }

        return DB::transaction(function () use ($user, $kind, $target, $qty) {
            $user->lockForUpdate()->find($user->id);
            
            $coins    = $user->coins;
            $settings = (array) ($user->profile_settings ?? []);
            $unlocked = $settings['unlocked_avatars'] ?? [];
            $catalog  = AvatarCatalog::get();

            $unitPrice = 0;

            switch ($kind) {
                case 'pack':
                    if (!isset($catalog[$target])) {
                        return back()->with('error', "Pack invalide.");
                    }
                    $unitPrice = $catalog[$target]['price'] ?? 300;
                    break;
                case 'buzzer':
                    $bz = $catalog['buzzers']['items'][$target] ?? null;
                    if (!$bz) {
                        return back()->with('error', "Buzzer invalide.");
                    }
                    $unitPrice = $bz['price'] ?? 80;
                    break;
                case 'stratégique':
                    $strategique = $catalog['stratégiques']['items'][$target] ?? null;
                    if (!$strategique) {
                        return back()->with('error', "Avatar stratégique invalide.");
                    }
                    if (isset($strategique['price'])) {
                        $unitPrice = (int) $strategique['price'];
                    } else {
                        $tier = $strategique['tier'] ?? 'Rare';
                        $map  = ['Rare' => 500, 'Épique' => 1000, 'Légendaire' => 1500];
                        $unitPrice = $map[$tier] ?? 500;
                    }
                    break;
                case 'life':
                    $unitPrice = 120;
                    break;
                case 'master':
                    if ($user->master_purchased ?? false) {
                        return back()->with('error', "Vous avez déjà débloqué le mode Maître du Jeu.");
                    }
                    $unitPrice = 1000;
                    break;
            }

            $total = $unitPrice * $qty;

            if ($total > $coins) {
                return back()->with('error', "Pièces d'intelligence insuffisantes pour cet achat.");
            }

            $user->coins -= $total;

            if ($kind === 'master') {
                $user->master_purchased = true;
                $user->save();
                
                \App\Models\CoinLedger::create([
                    'user_id' => $user->id,
                    'delta' => -$total,
                    'reason' => 'master_mode_purchase',
                    'ref_type' => null,
                    'ref_id' => null,
                    'balance_after' => $user->coins,
                ]);
                
                return back()->with('success', "Mode Maître du Jeu débloqué avec succès !");
            }

            if ($kind === 'life') {
                $user->lives += $qty;
                $user->save();
                
                \App\Models\CoinLedger::create([
                    'user_id' => $user->id,
                    'delta' => -$total,
                    'reason' => 'life_purchase',
                    'ref_type' => null,
                    'ref_id' => null,
                    'balance_after' => $user->coins,
                ]);
                
                return back()->with('success', "Achat réussi : +{$qty} vie(s) !");
            }

            if ($target && !in_array($target, $unlocked, true)) {
                $unlocked[] = $target;
                $settings['unlocked_avatars'] = $unlocked;
            }

            $user->profile_settings = $settings;
            $user->save();
            
            \App\Models\CoinLedger::create([
                'user_id' => $user->id,
                'delta' => -$total,
                'reason' => $kind . '_purchase',
                'ref_type' => null,
                'ref_id' => null,
                'balance_after' => $user->coins,
            ]);

            return back()->with('success', "Achat réussi, élément débloqué !");
        });
    }
}
