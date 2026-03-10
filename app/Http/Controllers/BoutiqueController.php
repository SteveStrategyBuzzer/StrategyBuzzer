<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AvatarCatalog;
use App\Services\StripeService;
use App\Services\CoinLedgerService;
use App\Models\Payment;
use App\Models\PurchaseIntent;
use App\Support\Currency;

class BoutiqueController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private CoinLedgerService $coinLedgerService
    ) {}

    /**
     * GET /boutique
     */
    public function index(Request $request)
    {
        $catalog  = AvatarCatalog::get();
        $user     = Auth::user();

        $coins           = $user?->coins ?? 0;
        $competenceCoins = $user?->competence_coins ?? 0;
        $settings        = (array) ($user?->profile_settings ?? []);
        $unlocked        = $settings['unlocked_avatars'] ?? [];

        $itemSlug        = $request->query('item');
        $strategiqueSlug = $request->query('stratégique');

        $context = [
            'coins'             => $coins,
            'competenceCoins'   => $competenceCoins,
            'unlocked'          => $unlocked,
            'catalog'           => $catalog,
            'mode'              => null,
            'entity'            => null,
            'slug'              => null,
            'intelligencePacks' => config('coins.intelligence_packs', []),
            'competencePacks'   => config('coins.competence_packs', []),
            'pricing'           => [
                'pack'        => [],
                'buzzer'      => [],
                'stratégique' => [],
            ],
        ];

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
     * GET /boutique/buzzers/{subcategory}
     */
    public function buzzerSubcategory(Request $request, string $subcategory)
    {
        $catalog  = AvatarCatalog::get();
        $user     = Auth::user();
        $coins    = $user?->coins ?? 0;
        $competenceCoins = $user?->competence_coins ?? 0;
        $settings = (array) ($user?->profile_settings ?? []);
        $unlocked = $settings['unlocked_avatars'] ?? [];

        $validSubcategories = ['punchy', 'vintage', 'premium', 'absurde', 'stade', 'discret', 'fun', 'electro', 'laser', 'fart', 'correct', 'incorrect'];

        if (!in_array($subcategory, $validSubcategories, true)) {
            return redirect()->route('boutique.category', 'buzzers');
        }

        $catalogKey = "buzzers_{$subcategory}";
        $buzzerItems = $catalog[$catalogKey]['items'] ?? [];
        $subcategoryLabel = $catalog[$catalogKey]['label'] ?? ucfirst($subcategory);
        $subcategoryIcon = $catalog[$catalogKey]['icon'] ?? '🔊';

        $context = [
            'subcategory'      => $subcategory,
            'subcategoryLabel' => $subcategoryLabel,
            'subcategoryIcon'  => $subcategoryIcon,
            'coins'            => $coins,
            'competenceCoins'  => $competenceCoins,
            'unlocked'         => $unlocked,
            'buzzerItems'      => $buzzerItems,
        ];

        return response()
            ->view('boutique_buzzer_subcategory', $context)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * GET /boutique/{category}
     */
    public function category(Request $request, string $category)
    {
        $validCategories = ['packs', 'musiques', 'buzzers', 'strategiques', 'master', 'coins_intelligence', 'coins_competence', 'vies'];

        if (!in_array($category, $validCategories, true)) {
            return redirect()->route('boutique');
        }

        $catalog  = AvatarCatalog::get();
        $user     = Auth::user();
        $coins    = $user?->coins ?? 0;
        $competenceCoins = $user?->competence_coins ?? 0;
        $settings = (array) ($user?->profile_settings ?? []);
        $unlocked = $settings['unlocked_avatars'] ?? [];
        $masterPurchased = $user && ($user->master_purchased ?? false);
        $duoPurchased = $user && ($user->duo_purchased ?? false);
        $leaguePurchased = $user && ($user->league_purchased ?? false);

        $context = [
            'category'          => $category,
            'coins'             => $coins,
            'competenceCoins'   => $competenceCoins,
            'unlocked'          => $unlocked,
            'catalog'           => $catalog,
            'intelligencePacks' => config('coins.intelligence_packs', []),
            'competencePacks'   => config('coins.competence_packs', []),
            'masterPurchased'   => $masterPurchased,
            'duoPurchased'      => $duoPurchased,
            'leaguePurchased'   => $leaguePurchased,
            'pricing'           => [
                'pack'        => [],
                'buzzer'      => [],
                'stratégique' => [],
            ],
        ];

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
            'kind'     => 'required|string|in:pack,buzzer,stratégique,life,music',
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
            $user->refresh();

            $availableCoins = $user->competence_coins ?? 0;
            $settings = (array) ($user?->profile_settings ?? []);
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
                    $bz = null;
                    $buzzerCats = ['punchy', 'vintage', 'premium', 'absurde', 'stade', 'discret', 'fun', 'electro', 'laser', 'fart', 'correct', 'incorrect'];
                    foreach ($buzzerCats as $cat) {
                        if (isset($catalog["buzzers_{$cat}"]['items'][$target])) {
                            $bz = $catalog["buzzers_{$cat}"]['items'][$target];
                            break;
                        }
                    }
                    if (!$bz) {
                        return back()->with('error', "Buzzer invalide.");
                    }
                    $unitPrice = $bz['price'] ?? 120;
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

                    $currentStrategicAvatar = $user->strategic_avatar ?? null;
                    if ($currentStrategicAvatar && in_array(strtolower($currentStrategicAvatar), ['stratege', 'stratège'], true)) {
                        $tier = $strategique['tier'] ?? 'Rare';
                        $discountMap = ['Rare' => 0.60, 'Épique' => 0.70, 'Légendaire' => 0.80];
                        $multiplier = $discountMap[$tier] ?? 0.80;
                        $unitPrice = (int) round($unitPrice * $multiplier);
                    }
                    break;

                case 'life':
                    $unitPrice = 120;
                    break;

                case 'music':
                    $musicCatalog = [
                        'strategybuzzer' => ['label' => 'StrategyBuzzer', 'price' => 0, 'free' => true],
                        'fun_01' => ['label' => 'Fun 01', 'price' => 200],
                        'chill' => ['label' => 'Chill', 'price' => 200],
                        'punchy' => ['label' => 'Punchy', 'price' => 200],
                    ];
                    if (!isset($musicCatalog[$target])) {
                        return back()->with('error', "Musique invalide.");
                    }
                    $music = $musicCatalog[$target];
                    if ($music['free'] ?? false) {
                        return back()->with('error', "Cette musique est gratuite.");
                    }
                    $unitPrice = $music['price'] ?? 200;
                    break;
            }

            $total = $unitPrice * $qty;

            if ($total > $availableCoins) {
                return back()->with('error', "Pièces de Compétence insuffisantes pour cet achat.");
            }

            $this->coinLedgerService->debit(
                $user,
                $total,
                $kind . '_purchase',
                null,
                null,
                'competence'
            );

            $user->refresh();
            $settings = (array) ($user?->profile_settings ?? []);
            $unlocked = $settings['unlocked_avatars'] ?? [];

            if ($kind === 'life') {
                $user->lives += $qty;
                $user->save();

                return back()->with('success', "Achat réussi : +{$qty} vie(s) !");
            }

            if ($kind === 'music') {
                $musicCatalog = [
                    'strategybuzzer' => ['label' => 'StrategyBuzzer', 'price' => 0],
                    'fun_01' => ['label' => 'Fun 01', 'price' => 200],
                    'chill' => ['label' => 'Chill', 'price' => 200],
                    'punchy' => ['label' => 'Punchy', 'price' => 200],
                ];

                $unlockedMusic = $settings['unlocked']['music'] ?? [['id' => 'strategybuzzer', 'label' => 'StrategyBuzzer']];
                $alreadyOwned = collect($unlockedMusic)->contains('id', $target);

                if (!$alreadyOwned && isset($musicCatalog[$target])) {
                    $unlockedMusic[] = ['id' => $target, 'label' => $musicCatalog[$target]['label']];
                    $settings['unlocked']['music'] = $unlockedMusic;
                }

                $user->profile_settings = $settings;
                $user->save();

                return back()->with('success', "Musique débloquée !");
            }

            if ($target && !in_array($target, $unlocked, true)) {
                $unlocked[] = $target;
                $settings['unlocked_avatars'] = $unlocked;
            }

            $user->profile_settings = $settings;
            $user->save();

            return back()->with('success', "Achat réussi, élément débloqué !");
        });
    }

    /**
     * POST /master/checkout
     * Acheter le mode Maître du Jeu (base CAD + nominal for CAD/USD/EUR/GBP + FX for others)
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

        $currency = Currency::fromSession($request->session());
        $baseCents = 2999;
        $amountCents = Currency::convertBaseCentsTo($currency, $baseCents);

        try {
            $purchaseIntent = PurchaseIntent::create([
                'user_id' => $user->id,
                'product_key' => 'master_mode',
                'product_type' => 'mode_unlock',
                'coin_type' => null,
                'coins_to_deliver' => null,
                'amount_cents' => $amountCents,
                'currency' => strtolower($currency),
                'status' => 'created',
                'metadata' => [
                    'product_name' => 'Mode Maître du Jeu',
                    'base_cents' => $baseCents,
                    'base_currency' => Currency::base(),
                ],
            ]);

            $masterProduct = [
                'key' => 'master_mode',
                'name' => 'Mode Maître du Jeu',
                'amount_cents' => $amountCents,
                'currency' => strtolower($currency),
                'coins' => 0,
            ];

            $session = $this->stripeService->createCheckoutSession(
                $masterProduct,
                (int) $user->id,
                null,
                null,
                null,
                (int) $purchaseIntent->id
            );

            $purchaseIntent->update([
                'stripe_session_id' => $session->id,
                'status' => 'checkout_created',
            ]);

            Payment::create([
                'user_id' => $user->id,
                'stripe_session_id' => $session->id,
                'product_key' => 'master_mode',
                'amount_cents' => $amountCents,
                'currency' => strtolower($currency),
                'status' => 'pending',
                'metadata' => [
                    'product_type' => 'master_mode',
                    'product_name' => 'Mode Maître du Jeu',
                    'base_cents' => $baseCents,
                    'base_currency' => Currency::base(),
                    'purchase_intent_id' => $purchaseIntent->id,
                ],
            ]);

            return redirect($session->url);
        } catch (\Exception $e) {
            Log::warning('Master checkout session failed', [
                'user_id' => $user->id ?? null,
                'currency' => $currency,
                'amount_cents' => $amountCents,
                'err' => $e->getMessage(),
            ]);

            return back()->with('error', 'Erreur lors de la création de la session de paiement: ' . $e->getMessage());
        }
    }

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

    public function masterCancel()
    {
        return redirect()->route('boutique')->with('error', 'Achat du mode Maître du Jeu annulé.');
    }

    /**
     * POST /modes/checkout/{mode}
     */
    public function modeCheckout(Request $request, string $mode)
    {
        $user = Auth::user();
        if (!$user) {
            return back()->with('error', __('Veuillez vous connecter.'));
        }

        $currency = Currency::fromSession($request->session());

        $modeProducts = [
            'duo' => [
                'key' => 'duo_mode',
                'name' => __('Mode Duo'),
                'base_cents' => 1250,
                'purchased_field' => 'duo_purchased',
            ],
            'league' => [
                'key' => 'league_mode',
                'name' => __('Mode Ligue'),
                'base_cents' => 1575,
                'purchased_field' => 'league_purchased',
            ],
        ];

        if (!isset($modeProducts[$mode])) {
            return back()->with('error', __('Mode de jeu invalide.'));
        }

        $product = $modeProducts[$mode];

        if ($user->{$product['purchased_field']} ?? false) {
            return back()->with('info', __('Vous avez déjà débloqué ce mode de jeu.'));
        }

        $baseCents = (int) $product['base_cents'];
        $amountCents = Currency::convertBaseCentsTo($currency, $baseCents);

        try {
            $purchaseIntent = PurchaseIntent::create([
                'user_id' => $user->id,
                'product_key' => $product['key'],
                'product_type' => 'mode_unlock',
                'coin_type' => null,
                'coins_to_deliver' => null,
                'amount_cents' => $amountCents,
                'currency' => strtolower($currency),
                'status' => 'created',
                'metadata' => [
                    'product_name' => $product['name'],
                    'base_cents' => $baseCents,
                    'base_currency' => Currency::base(),
                    'mode' => $mode,
                ],
            ]);

            $session = $this->stripeService->createCheckoutSession([
                'key' => $product['key'],
                'name' => $product['name'],
                'amount_cents' => $amountCents,
                'currency' => strtolower($currency),
                'coins' => 0,
            ], (int) $user->id, null, null, null, (int) $purchaseIntent->id);

            $purchaseIntent->update([
                'stripe_session_id' => $session->id,
                'status' => 'checkout_created',
            ]);

            Payment::create([
                'user_id' => $user->id,
                'stripe_session_id' => $session->id,
                'product_key' => $product['key'],
                'amount_cents' => $amountCents,
                'currency' => strtolower($currency),
                'status' => 'pending',
                'metadata' => [
                    'product_type' => $product['key'],
                    'product_name' => $product['name'],
                    'base_cents' => $baseCents,
                    'base_currency' => Currency::base(),
                    'purchase_intent_id' => $purchaseIntent->id,
                ],
            ]);

            return redirect($session->url);
        } catch (\Exception $e) {
            Log::warning('Mode checkout session failed', [
                'user_id' => $user->id ?? null,
                'mode' => $mode,
                'currency' => $currency,
                'amount_cents' => $amountCents,
                'err' => $e->getMessage(),
            ]);

            return back()->with('error', __('Erreur lors de la création de la session de paiement: ') . $e->getMessage());
        }
    }

    public function modeSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('boutique.category', 'master')->with('error', __('Session invalide.'));
        }

        $payment = Payment::where('stripe_session_id', $sessionId)->first();

        if (!$payment) {
            return redirect()->route('boutique.category', 'master')->with('info', __('Paiement en cours de traitement. Le mode sera débloqué sous peu.'));
        }

        if ($payment->status === 'completed') {
            return redirect()->route('boutique.category', 'master')->with('success', __('Mode de jeu débloqué avec succès !'));
        }

        if ($payment->status === 'failed') {
            return redirect()->route('boutique.category', 'master')->with('error', __('Le paiement a échoué. Veuillez réessayer.'));
        }

        return redirect()->route('boutique.category', 'master')->with('info', __('Votre paiement est en cours de traitement. Le mode sera débloqué automatiquement dans quelques instants.'));
    }

    public function modeCancel()
    {
        return redirect()->route('boutique.category', 'master')->with('error', __('Achat du mode de jeu annulé.'));
    }
}
