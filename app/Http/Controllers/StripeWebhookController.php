<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Models\PurchaseIntent;
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

        if (!is_string($sigHeader) || trim($sigHeader) === '') {
            Log::warning('Stripe webhook missing signature header', [
                'ip' => $request->ip(),
                'ua' => (string) $request->userAgent(),
            ]);

            return response()->json(['error' => 'Missing signature'], 400);
        }

        try {
            $event = $this->stripeService->validateWebhookSignature($payload, $sigHeader);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $eventId = $event->id ?? null;
        $eventType = $event->type ?? 'unknown';
        $sessionId = data_get($event, 'data.object.id');

        $dedupeOk = false;
        $payloadForDb = $payload;

        try {
            json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $t) {
            $payloadForDb = json_encode([
                '_invalid_json' => true,
                '_raw' => $payload,
            ], JSON_UNESCAPED_SLASHES);
        }

        if (is_string($eventId) && $eventId !== '') {
            try {
                DB::table('stripe_webhook_events')->upsert(
                    [[
                        'event_id' => $eventId,
                        'type' => $eventType,
                        'stripe_session_id' => is_string($sessionId) ? $sessionId : null,
                        'payload' => $payloadForDb,
                        'processed_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]],
                    ['event_id'],
                    ['type', 'stripe_session_id', 'payload', 'updated_at']
                );

                $alreadyProcessed = DB::table('stripe_webhook_events')
                    ->where('event_id', $eventId)
                    ->whereNotNull('processed_at')
                    ->exists();

                if ($alreadyProcessed) {
                    Log::info('Stripe webhook event already processed (dedupe)', [
                        'event_id' => $eventId,
                        'type' => $eventType,
                    ]);

                    return response()->json(['status' => 'success'], 200);
                }

                $dedupeOk = true;
            } catch (\Exception $e) {
                Log::error('Failed to write stripe webhook dedupe record (MUST RETRY)', [
                    'event_id' => $eventId,
                    'type' => $eventType,
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'Dedupe write failed'], 500);
            }
        } else {
            Log::error('Stripe webhook missing event id', [
                'type' => $eventType,
                'session_id' => $sessionId,
            ]);

            return response()->json(['error' => 'Missing event id'], 400);
        }

        $processedSuccessfully = false;

        try {
            if ($eventType === 'checkout.session.completed') {
                $session = $event->data->object;

                DB::transaction(function () use ($session, &$processedSuccessfully) {
                    $payment = Payment::where('stripe_session_id', $session->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$payment) {
                        Log::warning('Payment not found for session (orphan webhook)', [
                            'session_id' => $session->id,
                        ]);

                        $processedSuccessfully = true;
                        return;
                    }

                    if ($payment->status === 'completed') {
                        Log::info('Payment already processed', [
                            'payment_id' => $payment->id,
                        ]);

                        $processedSuccessfully = true;
                        return;
                    }

                    $sessionAmount = (int) ($session->amount_total ?? 0);
                    $sessionCurrency = strtolower((string) ($session->currency ?? ''));
                    $sessionUserId = (int) ($session->metadata->user_id ?? 0);
                    $sessionProductKey = (string) ($session->metadata->product_key ?? '');
                    $purchaseIntentId = (int) ($session->metadata->purchase_intent_id ?? 0);

                    if (
                        $sessionAmount !== (int) $payment->amount_cents ||
                        $sessionCurrency !== strtolower((string) $payment->currency) ||
                        $sessionUserId !== (int) $payment->user_id ||
                        $sessionProductKey !== (string) $payment->product_key
                    ) {
                        Log::error('Stripe webhook mismatch against Payment', [
                            'payment_id' => $payment->id,
                            'session_id' => $session->id,
                            'session_amount' => $sessionAmount,
                            'payment_amount' => $payment->amount_cents,
                            'session_currency' => $sessionCurrency,
                            'payment_currency' => $payment->currency,
                            'session_user_id' => $sessionUserId,
                            'payment_user_id' => $payment->user_id,
                            'session_product_key' => $sessionProductKey,
                            'payment_product_key' => $payment->product_key,
                        ]);

                        $payment->markAsFailed();
                        $processedSuccessfully = true;
                        return;
                    }

                    $purchaseIntent = PurchaseIntent::where('id', $purchaseIntentId)
                        ->where('user_id', $payment->user_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$purchaseIntent) {
                        Log::error('PurchaseIntent not found for Stripe webhook', [
                            'payment_id' => $payment->id,
                            'purchase_intent_id' => $purchaseIntentId,
                            'session_id' => $session->id,
                        ]);

                        $payment->markAsFailed();
                        $processedSuccessfully = true;
                        return;
                    }

                    if ((string) ($purchaseIntent->stripe_session_id ?? '') !== (string) $session->id) {
                        Log::error('PurchaseIntent stripe_session_id mismatch against Stripe session', [
                            'payment_id' => $payment->id,
                            'purchase_intent_id' => $purchaseIntent->id,
                            'purchase_intent_stripe_session_id' => $purchaseIntent->stripe_session_id,
                            'stripe_session_id' => $session->id,
                        ]);

                        $payment->markAsFailed();
                        $processedSuccessfully = true;
                        return;
                    }

                    if ($purchaseIntent->status === 'fulfilled') {
                        Log::info('PurchaseIntent already fulfilled', [
                            'payment_id' => $payment->id,
                            'purchase_intent_id' => $purchaseIntent->id,
                            'session_id' => $session->id,
                        ]);

                        $processedSuccessfully = true;
                        return;
                    }

                    if (
                        (int) $purchaseIntent->amount_cents !== (int) $payment->amount_cents ||
                        strtolower((string) $purchaseIntent->currency) !== strtolower((string) $payment->currency) ||
                        (string) $purchaseIntent->product_key !== (string) $payment->product_key
                    ) {
                        Log::error('PurchaseIntent mismatch against Payment', [
                            'payment_id' => $payment->id,
                            'purchase_intent_id' => $purchaseIntent->id,
                        ]);

                        $payment->markAsFailed();
                        $processedSuccessfully = true;
                        return;
                    }

                    $payment->update([
                        'stripe_payment_intent_id' => $session->payment_intent,
                    ]);

                    $user = $payment->user;
                    $productKey = (string) $purchaseIntent->product_key;

                    if ($productKey === 'master_mode') {
                        $user->master_purchased = true;
                        $user->save();

                        $purchaseIntent->update([
                            'status' => 'fulfilled',
                            'fulfilled_at' => now(),
                        ]);

                        $payment->markAsCompleted(0);
                        $processedSuccessfully = true;
                        return;
                    }

                    if ($productKey === 'duo_mode') {
                        $user->duo_purchased = true;
                        $user->save();

                        $purchaseIntent->update([
                            'status' => 'fulfilled',
                            'fulfilled_at' => now(),
                        ]);

                        $payment->markAsCompleted(0);
                        $processedSuccessfully = true;
                        return;
                    }

                    if ($productKey === 'league_mode') {
                        $user->league_purchased = true;
                        $user->save();

                        $purchaseIntent->update([
                            'status' => 'fulfilled',
                            'fulfilled_at' => now(),
                        ]);

                        $payment->markAsCompleted(0);
                        $processedSuccessfully = true;
                        return;
                    }

                    $coinsToDeliver = (int) ($purchaseIntent->coins_to_deliver ?? 0);
                    $coinType = (string) ($purchaseIntent->coin_type ?? 'intelligence');

                    if ($coinsToDeliver <= 0) {
                        Log::error('Invalid coins_to_deliver in PurchaseIntent', [
                            'payment_id' => $payment->id,
                            'purchase_intent_id' => $purchaseIntent->id,
                        ]);

                        $payment->markAsFailed();
                        $processedSuccessfully = true;
                        return;
                    }

                    $this->coinLedgerService->credit(
                        $user,
                        $coinsToDeliver,
                        'stripe_purchase',
                        'Payment',
                        $payment->id,
                        $coinType
                    );

                    $purchaseIntent->update([
                        'status' => 'fulfilled',
                        'fulfilled_at' => now(),
                    ]);

                    $payment->markAsCompleted($coinsToDeliver);
                    $processedSuccessfully = true;
                });
            } else {
                $processedSuccessfully = true;
            }
        } catch (\Exception $e) {
            Log::error('Error processing stripe webhook (MUST RETRY)', [
                'event_id' => $eventId,
                'type' => $eventType,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        } finally {
            if ($dedupeOk && $processedSuccessfully && is_string($eventId) && $eventId !== '') {
                try {
                    DB::table('stripe_webhook_events')
                        ->where('event_id', $eventId)
                        ->update([
                            'processed_at' => now(),
                            'updated_at' => now(),
                        ]);
                } catch (\Exception $e) {
                    Log::error('Failed to update processed_at for stripe webhook event (MUST RETRY)', [
                        'event_id' => $eventId,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json(['error' => 'Failed to mark processed'], 500);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
