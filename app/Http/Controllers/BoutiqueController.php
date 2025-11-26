<?php  

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AvatarCatalog;
use App\Services\StripeService;
use App\Models\Payment;

class BoutiqueController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}
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
     * GET /boutique/{category}
     */
    public function category(Request $request, string $category)
    {
        $validCategories = ['packs', 'musiques', 'buzzers', 'strategiques', 'master', 'coins', 'vies'];
        
        if (!in_array($category, $validCategories)) {
            return redirect()->route('boutique');
        }

        $catalog  = AvatarCatalog::get();
        $user     = Auth::user();
        $coins    = $user?->coins ?? 0;
        $settings = (array) ($user?->profile_settings ?? []);
        $unlocked = $settings['unlocked_avatars'] ?? [];
        $masterPurchased = $user && ($user->master_purchased ?? false);

        $context = [
            'category'        => $category,
            'coins'           => $coins,
            'unlocked'        => $unlocked,
            'catalog'         => $catalog,
            'coinPacks'       => config('coins.packs', []),
            'masterPurchased' => $masterPurchased,
            'pricing'         => [
                'pack'        => [],
                'buzzer'      => [],
                'stratégique' => [],
            ],
        ];

        // Build pricing arrays
        foreach ($catalog as $slug => $entry) {
            if (is_array($entry) && isset($entry['price'])) {
                $context['pricing']['pack'][$slug] = (int) $entry['price'];
            }
        }
        foreach (($catalog['buzzers']['items'] ?? []) as $slug => $bz) {
            if (isset($bz['price'])) {
                $context['pricing']['buzzer'][$slug] = (int) $bz['price'];
            }
        }
        foreach (($catalog['stratégiques']['items'] ?? []) as $slug => $a) {
            if (isset($a['price'])) {
                $context['pricing']['stratégique'][$slug] = (int) $a['price'];
            }
        }

        return response()
            ->view('boutique_category', $context)
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
            'kind'     => 'required|string|in:pack,buzzer,stratégique,life',
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
            }

            $total = $unitPrice * $qty;

            if ($total > $coins) {
                return back()->with('error', "Pièces d'intelligence insuffisantes pour cet achat.");
            }

            $user->coins -= $total;

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

    /**
     * POST /master/checkout
     * Créer une session Stripe pour acheter le mode Maître du Jeu (29.99$)
     */
    public function masterCheckout(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return back()->with('error', 'Veuillez vous connecter.');
        }

        if ($user->master_purchased ?? false) {
            return back()->with('info', 'Vous avez déjà débloqué le mode Maître du Jeu.');
        }

        try {
            $masterProduct = [
                'key' => 'master_mode',
                'name' => 'Mode Maître du Jeu',
                'amount_cents' => 2999, // $29.99
                'currency' => 'usd',
                'coins' => 0,
            ];

            $session = $this->stripeService->createCheckoutSession($masterProduct, $user->id);

            Payment::create([
                'user_id' => $user->id,
                'stripe_session_id' => $session->id,
                'product_key' => 'master_mode',
                'amount_cents' => 2999,
                'currency' => 'usd',
                'status' => 'pending',
                'metadata' => [
                    'product_type' => 'master_mode',
                    'product_name' => 'Mode Maître du Jeu',
                ],
            ]);

            return redirect($session->url);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la création de la session de paiement: ' . $e->getMessage());
        }
    }

    /**
     * GET /master/success
     */
    public function masterSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');
        
        if (!$sessionId) {
            return redirect()->route('boutique')->with('error', 'Session invalide.');
        }

        $payment = Payment::where('stripe_session_id', $sessionId)->first();

        if (!$payment) {
            return redirect()->route('boutique')->with('info', 'Paiement en cours de traitement. Le mode Maître du Jeu sera débloqué sous peu.');
        }

        if ($payment->status === 'completed') {
            return redirect()->route('boutique')->with('success', 'Mode Maître du Jeu débloqué avec succès ! Vous pouvez maintenant créer vos propres parties.');
        }

        if ($payment->status === 'failed') {
            return redirect()->route('boutique')->with('error', 'Le paiement a échoué. Veuillez réessayer.');
        }

        return redirect()->route('boutique')->with('info', 'Votre paiement est en cours de traitement. Le mode sera débloqué automatiquement dans quelques instants.');
    }

    /**
     * GET /master/cancel
     */
    public function masterCancel()
    {
        return redirect()->route('boutique')->with('error', 'Achat du mode Maître du Jeu annulé.');
    }
}
