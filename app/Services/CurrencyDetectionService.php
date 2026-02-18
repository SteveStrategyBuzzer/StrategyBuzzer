<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class CurrencyDetectionService
{
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    private const COUNTRY_CURRENCY_MAP = [
        'US' => 'usd',
        'CA' => 'cad',
        'GB' => 'gbp',
    ];

    private const SESSION_KEY = 'detected_currency';
    private const DEFAULT_CURRENCY = 'usd';

    public function detectCurrency(?string $ip = null): string
    {
        if (Session::has(self::SESSION_KEY)) {
            return Session::get(self::SESSION_KEY);
        }

        $currency = $this->resolveCurrencyFromIp($ip ?? request()->ip());
        Session::put(self::SESSION_KEY, $currency);

        return $currency;
    }

    public function getCurrency(): string
    {
        return Session::get(self::SESSION_KEY, self::DEFAULT_CURRENCY);
    }

    public function resetCurrency(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    private function resolveCurrencyFromIp(string $ip): string
    {
        try {
            if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
                return self::DEFAULT_CURRENCY;
            }

            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'countryCode,status',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    $countryCode = strtoupper($data['countryCode'] ?? '');
                    return $this->mapCountryToCurrency($countryCode);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Currency detection failed', ['error' => $e->getMessage()]);
        }

        return self::DEFAULT_CURRENCY;
    }

    private function mapCountryToCurrency(string $countryCode): string
    {
        if (isset(self::COUNTRY_CURRENCY_MAP[$countryCode])) {
            return self::COUNTRY_CURRENCY_MAP[$countryCode];
        }

        if (in_array($countryCode, self::EU_COUNTRIES)) {
            return 'eur';
        }

        return self::DEFAULT_CURRENCY;
    }
}
