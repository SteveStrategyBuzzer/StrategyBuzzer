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
    private const COUNTRY_SESSION_KEY = 'detected_country';
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

    /**
     * Détecte le pays depuis l'IP et le met en session.
     * Appel unique : utilise le cache session si déjà détecté.
     */
    public function detectCountry(?string $ip = null): ?string
    {
        if (Session::has(self::COUNTRY_SESSION_KEY)) {
            return Session::get(self::COUNTRY_SESSION_KEY);
        }

        try {
            $ipAddress = $ip ?? request()->ip();
            if (in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                return null;
            }

            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ipAddress}", [
                'fields' => 'countryCode,status',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    $countryCode = strtoupper($data['countryCode'] ?? '');
                    if ($countryCode) {
                        Session::put(self::COUNTRY_SESSION_KEY, $countryCode);
                        return $countryCode;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Country detection failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Retourne le code pays détecté par IP (ex: 'CA', 'FR', 'US').
     * Retourne null si non détecté.
     */
    public function getCountry(): ?string
    {
        return Session::get(self::COUNTRY_SESSION_KEY);
    }

    public function resetCurrency(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::COUNTRY_SESSION_KEY);
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
                    // Mémoriser le code pays en session pour le profil
                    if ($countryCode) {
                        Session::put(self::COUNTRY_SESSION_KEY, $countryCode);
                    }
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
