<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Services\StripeService;
use App\Services\CoinLedgerService;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private CoinLedgerService $coinLedgerService
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->validateWebhookSignature($payload, $sigHeader);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            try {
                DB::transaction(function () use ($session) {
                    $payment = Payment::where('stripe_session_id', $session->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$payment) {
                        Log::warning('Payment not found for session', ['session_id' => $session->id]);
                        throw new \Exception('Payment not found');
                    }

                    if ($payment->status === 'completed') {
                        Log::info('Payment already processed', ['payment_id' => $payment->id]);
                        return;
                    }

                    $payment->update([
                        'stripe_payment_intent_id' => $session->payment_intent,
                    ]);

                    $coinsToAward = (int) ($session->metadata->coins ?? 0);

                    if ($coinsToAward > 0) {
                        $user = $payment->user;
                        $user->intelligence_pieces = ($user->intelligence_pieces ?? 0) + $coinsToAward;
                        $user->save();

                        $this->coinLedgerService->credit(
                            $payment->user,
                            $coinsToAward,
                            'stripe_purchase',
                            'Payment',
                            $payment->id
                        );

                        $payment->markAsCompleted($coinsToAward);

                        Log::info('Coins credited successfully', [
                            'payment_id' => $payment->id,
                            'user_id' => $payment->user_id,
                            'coins' => $coinsToAward,
                            'intelligence_pieces' => $user->intelligence_pieces,
                            'session_id' => $session->id,
                        ]);
                    } else {
                        Log::error('Invalid coins amount from Stripe metadata', [
                            'payment_id' => $payment->id,
                            'session_metadata' => $session->metadata,
                        ]);
                        $payment->markAsFailed();
                        throw new \Exception('Invalid coins amount');
                    }
                });
            } catch (\Exception $e) {
                Log::error('Error processing payment', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json(['error' => 'Processing failed'], 500);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
