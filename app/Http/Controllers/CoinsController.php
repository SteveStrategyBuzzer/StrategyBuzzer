<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\PurchaseIntent;
use App\Services\StripeService;
use App\Support\Currency;

class CoinsController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * POST /coins/checkout
     * Currency is server-only (session based).
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'product_key' => 'required|string',
            'coin_type'   => 'required|string|in:intelligence,competence',
        ]);

        $user = Auth::user();
        if (!$user) {
            return back()->with('error', 'Veuillez vous connecter.');
        }

        $productKey = (string) $request->input('product_key');
        $coinType   = (string) $request->input('coin_type');

        $currency = Currency::fromSession($request->session());
        $currencyLower = strtolower($currency);

        $packs = $coinType === 'intelligence'
            ? config('coins.intelligence_packs', [])
            : config('coins.competence_packs', []);

        $pack = collect($packs)->firstWhere('key', $productKey);

        if (!$pack) {
            return back()->with('error', 'Pack invalide.');
        }

        $baseCents = (int) ($pack['amount_cents'] ?? 0);
        if ($baseCents <= 0) {
            return back()->with('error', 'Pack invalide (prix).');
        }

        $coinsToDeliver = (int) ($pack['coins'] ?? 0);
        if ($coinsToDeliver <= 0) {
            return back()->with('error', 'Pack invalide (coins).');
        }

        $amountCents = Currency::convertBaseCentsTo($currency, $baseCents);

        try {
            $purchaseIntent = PurchaseIntent::create([
                'user_id'          => $user->id,
                'product_key'      => $pack['key'],
                'product_type'     => 'coins_pack',
                'coin_type'        => $coinType,
                'coins_to_deliver' => $coinsToDeliver,
                'amount_cents'     => $amountCents,
                'currency'         => $currencyLower,
                'status'           => 'created',
                'metadata'         => [
                    'pack_name'      => $pack['name'] ?? null,
                    'base_cents'     => $baseCents,
                    'catalog_source' => 'config/coins.php',
                ],
            ]);

            $pack['currency'] = $currencyLower;
            $pack['amount_cents'] = $amountCents;

            $session = $this->stripeService->createCheckoutSession(
                $pack,
                (int) $user->id,
                null,
                null,
                $coinType,
                (int) $purchaseIntent->id
            );

            $purchaseIntent->update([
                'stripe_session_id' => $session->id,
                'status' => 'checkout_created',
            ]);

            Payment::create([
                'user_id'           => $user->id,
                'stripe_session_id' => $session->id,
                'product_key'       => $pack['key'],
                'amount_cents'      => $amountCents,
                'currency'          => $currencyLower,
                'status'            => 'pending',
                'metadata'          => [
                    'coins'               => $coinsToDeliver,
                    'pack_name'           => $pack['name'] ?? null,
                    'coin_type'           => $coinType,
                    'purchase_intent_id'  => $purchaseIntent->id,
                ],
            ]);

            return redirect($session->url);

        } catch (\Exception $e) {
            Log::warning('Coins checkout failed', [
                'user_id'  => $user->id ?? null,
                'product'  => $productKey,
                'coinType' => $coinType,
                'currency' => $currencyLower,
                'err'      => $e->getMessage(),
            ]);

            return back()->with('error', 'Erreur lors de la création de la session de paiement.');
        }
    }

    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('boutique')->with('error', 'Session invalide.');
        }

        $payment = Payment::where('stripe_session_id', $sessionId)->first();

        if (!$payment) {
            return redirect()->route('boutique')->with('info', 'Paiement en cours de traitement.');
        }

        if ($payment->status === 'completed') {
            return redirect()->route('boutique')->with('success', 'Paiement réussi !');
        }

        if ($payment->status === 'failed') {
            return redirect()->route('boutique')->with('error', 'Le paiement a échoué.');
        }

        return redirect()->route('boutique')->with('info', 'Paiement en cours de traitement.');
    }

    public function cancel()
    {
        return redirect()->route('boutique')->with('error', 'Paiement annulé.');
    }
}
