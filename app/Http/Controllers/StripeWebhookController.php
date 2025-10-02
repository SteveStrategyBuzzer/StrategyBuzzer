<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

            $payment = Payment::where('stripe_session_id', $session->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                Log::warning('Payment not found for session', ['session_id' => $session->id]);
                return response()->json(['status' => 'payment_not_found'], 404);
            }

            if ($payment->status === 'completed') {
                Log::info('Payment already processed', ['payment_id' => $payment->id]);
                return response()->json(['status' => 'already_processed'], 200);
            }

            try {
                $payment->update([
                    'stripe_payment_intent_id' => $session->payment_intent,
                ]);

                $coinsToAward = (int) ($payment->metadata['coins'] ?? 0);

                if ($coinsToAward > 0) {
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
                    ]);
                } else {
                    Log::error('Invalid coins amount', ['payment_id' => $payment->id]);
                    $payment->markAsFailed();
                }
            } catch (\Exception $e) {
                Log::error('Error processing payment', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                $payment->markAsFailed();
                return response()->json(['error' => 'Processing failed'], 500);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
