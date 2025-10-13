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

    public function createCheckoutSession(array $pack, int $userId, ?string $successUrl = null, ?string $cancelUrl = null): Session
    {
        $description = $pack['coins'] > 0 
            ? $pack['coins'] . ' pièces d\'intelligence'
            : ($pack['name'] ?? 'Achat');

        $defaultSuccessUrl = config('coins.urls.success');
        $defaultCancelUrl = config('coins.urls.cancel');

        if ($pack['key'] === 'master_mode') {
            $defaultSuccessUrl = url('/master/success?session_id={CHECKOUT_SESSION_ID}');
            $defaultCancelUrl = url('/master/cancel');
        }

        return Session::create([
            'payment_method_types' => ['card'],
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
                'user_id' => $userId,
                'product_key' => $pack['key'],
                'coins' => $pack['coins'] ?? 0,
            ],
        ]);
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
