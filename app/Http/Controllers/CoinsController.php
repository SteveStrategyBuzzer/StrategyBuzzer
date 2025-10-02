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
            $payment = Payment::create([
                'user_id' => $user->id,
                'product_key' => $pack['key'],
                'amount_cents' => $pack['amount_cents'],
                'currency' => $pack['currency'],
                'status' => 'pending',
                'metadata' => [
                    'coins' => $pack['coins'],
                    'pack_name' => $pack['name'],
                ],
            ]);

            $session = $this->stripeService->createCheckoutSession($pack, $user->id);

            $payment->update([
                'stripe_session_id' => $session->id,
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
            return redirect()->route('boutique')->with('error', 'Paiement introuvable.');
        }

        if ($payment->status === 'completed') {
            return redirect()->route('boutique')->with('success', "Paiement réussi ! {$payment->coins_awarded} pièces d'intelligence ont été ajoutées à votre compte.");
        }

        return redirect()->route('boutique')->with('info', 'Votre paiement est en cours de traitement...');
    }

    public function cancel()
    {
        return redirect()->route('boutique')->with('error', 'Paiement annulé.');
    }
}
