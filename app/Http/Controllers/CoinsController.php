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
        ]);

        $user = Auth::user();
        if (!$user) {
            return back()->with('error', 'Veuillez vous connecter.');
        }

        $productKey = $request->input('product_key');
        $packs = config('coins.packs');
        $pack = collect($packs)->firstWhere('key', $productKey);

        if (!$pack) {
            return back()->with('error', 'Pack invalide.');
        }

        try {
            $session = $this->stripeService->createCheckoutSession($pack, $user->id);

            Payment::create([
                'user_id' => $user->id,
                'stripe_session_id' => $session->id,
                'product_key' => $pack['key'],
                'amount_cents' => $pack['amount_cents'],
                'currency' => $pack['currency'],
                'status' => 'pending',
                'metadata' => [
                    'coins' => $pack['coins'],
                    'pack_name' => $pack['name'],
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
            return redirect()->route('boutique')->with('success', "Paiement réussi ! {$payment->coins_awarded} pièces d'intelligence ont été ajoutées à votre compte.");
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
