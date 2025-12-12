<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;
use App\Services\StripeService;

class CoinsController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    public function checkout(Request $request)
    {
        $request->validate([
            'product_key' => 'required|string',
            'coin_type' => 'required|string|in:intelligence,competence',
            'detected_currency' => 'nullable|string|size:3',
        ]);

        $user = Auth::user();
        if (!$user) {
            return back()->with('error', 'Veuillez vous connecter.');
        }

        $productKey = $request->input('product_key');
        $coinType = $request->input('coin_type');
        $detectedCurrency = strtolower($request->input('detected_currency', 'usd'));
        
        // Liste des devises supportées par Stripe
        $supportedCurrencies = [
            'usd', 'cad', 'eur', 'gbp', 'aud', 'nzd', 'chf', 'jpy', 'cny',
            'sek', 'nok', 'dkk', 'pln', 'czk', 'huf', 'ron', 'inr', 'krw',
            'sgd', 'hkd', 'twd', 'brl', 'mxn', 'zar'
        ];
        
        // Utiliser USD par défaut si la devise n'est pas supportée
        $currency = in_array($detectedCurrency, $supportedCurrencies) ? $detectedCurrency : 'usd';
        
        $packs = $coinType === 'intelligence' 
            ? config('coins.intelligence_packs') 
            : config('coins.competence_packs');
        
        $pack = collect($packs)->firstWhere('key', $productKey);

        if (!$pack) {
            return back()->with('error', 'Pack invalide.');
        }

        // Utiliser la devise détectée au lieu de celle du pack (même montant numérique)
        $pack['currency'] = $currency;

        try {
            $session = $this->stripeService->createCheckoutSession($pack, $user->id, null, null, $coinType);

            Payment::create([
                'user_id' => $user->id,
                'stripe_session_id' => $session->id,
                'product_key' => $pack['key'],
                'amount_cents' => $pack['amount_cents'],
                'currency' => $currency,
                'status' => 'pending',
                'metadata' => [
                    'coins' => $pack['coins'],
                    'pack_name' => $pack['name'],
                    'coin_type' => $coinType,
                ],
            ]);

            return redirect($session->url);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la création de la session de paiement: ' . $e->getMessage());
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
            return redirect()->route('boutique')->with('info', 'Paiement en cours de traitement. Vos pièces seront ajoutées sous peu.');
        }

        if ($payment->status === 'completed') {
            $coinType = $payment->metadata['coin_type'] ?? 'intelligence';
            $coinName = $coinType === 'competence' ? 'pièces de compétence' : "pièces d'intelligence";
            return redirect()->route('boutique')->with('success', "Paiement réussi ! {$payment->coins_awarded} {$coinName} ont été ajoutées à votre compte.");
        }

        if ($payment->status === 'failed') {
            return redirect()->route('boutique')->with('error', 'Le paiement a échoué. Veuillez réessayer.');
        }

        return redirect()->route('boutique')->with('info', 'Votre paiement est en cours de traitement. Vos pièces seront ajoutées automatiquement dans quelques instants.');
    }

    public function cancel()
    {
        return redirect()->route('boutique')->with('error', 'Paiement annulé.');
    }
}
