<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('coins.stripe.secret'));
    }

    public function createCheckoutSession(
        array $pack,
        int $userId,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?string $coinType = null,
        ?int $purchaseIntentId = null,
        ?string $idempotencyKey = null
    ): Session {
        $coinTypeLabel = $coinType === 'competence' ? 'pièces de compétence' : "pièces d'intelligence";
        $description = ($pack['coins'] ?? 0) > 0
            ? $pack['coins'] . ' ' . $coinTypeLabel
            : ($pack['name'] ?? 'Achat');

        $defaultSuccessUrl = config('coins.urls.success');
        $defaultCancelUrl = config('coins.urls.cancel');

        if (($pack['key'] ?? null) === 'master_mode') {
            $defaultSuccessUrl = url('/master/success?session_id={CHECKOUT_SESSION_ID}');
            $defaultCancelUrl = url('/master/cancel');
        } elseif (in_array(($pack['key'] ?? null), ['duo_mode', 'league_mode'], true)) {
            $defaultSuccessUrl = url('/modes/success?session_id={CHECKOUT_SESSION_ID}');
            $defaultCancelUrl = url('/modes/cancel');
        }

        $resolvedKey = $idempotencyKey
            ?? ($purchaseIntentId ? "checkout_intent_{$purchaseIntentId}" : "checkout_{$userId}_{$pack['key']}");

        return Session::create(
            [
                'payment_method_types' => ['card'],
                'client_reference_id' => $purchaseIntentId ? (string) $purchaseIntentId : null,
                'line_items' => [[
                    'price_data' => [
                        'currency' => $pack['currency'],
                        'product_data' => [
                            'name' => $pack['name'],
                            'description' => $description,
                        ],
                        'unit_amount' => $pack['amount_cents'],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl ?? $defaultSuccessUrl,
                'cancel_url' => $cancelUrl ?? $defaultCancelUrl,
                'metadata' => [
                    'user_id' => (string) $userId,
                    'product_key' => (string) ($pack['key'] ?? ''),
                    'coin_type' => (string) ($coinType ?? 'intelligence'),
                    'purchase_intent_id' => $purchaseIntentId ? (string) $purchaseIntentId : '',
                ],
            ],
            ['idempotency_key' => $resolvedKey]
        );
    }

    public function validateWebhookSignature(string $payload, string $sigHeader): \Stripe\Event
    {
        $webhookSecret = config('coins.stripe.webhook_secret');

        return \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            $webhookSecret
        );
    }
}
