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

    public function createCheckoutSession(array $pack, int $userId): Session
    {
        $successUrl = str_replace(
            '{CHECKOUT_SESSION_ID}',
            '{CHECKOUT_SESSION_ID}',
            config('coins.urls.success')
        );

        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $pack['currency'],
                    'product_data' => [
                        'name' => $pack['name'],
                        'description' => $pack['coins'] . ' pièces d\'intelligence',
                    ],
                    'unit_amount' => $pack['amount_cents'],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => config('coins.urls.cancel'),
            'metadata' => [
                'user_id' => $userId,
                'product_key' => $pack['key'],
                'coins' => $pack['coins'],
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
