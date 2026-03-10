<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use Illuminate\Http\Request;

class CurrencyResolver
{
    public function resolve(Request $request): array
    {
        $ip = $request->ip();

        $defaultCurrency = config('currency.default', 'USD');
        $supported = config('currency.supported', ['USD']);
        $countryMap = config('currency.country_map', []);
        $dbPath = config('currency.geoip_country_db');

        $country = null;
        $currency = $defaultCurrency;
        $source = 'fallback';

        try {
            if (is_string($dbPath) && file_exists($dbPath)) {
                $reader = new Reader($dbPath);
                $country = $reader->country($ip)->country->isoCode ?: null;
                $source = 'geoip';
            } else {
                $source = 'geoip_db_missing';
            }
        } catch (\Throwable $e) {
            $source = 'geoip_error';
            $country = null;
        }

        if ($country && isset($countryMap[$country])) {
            $currency = $countryMap[$country];
            $source = 'country_map';
        }

        if (!in_array($currency, $supported, true)) {
            $currency = $defaultCurrency;
            $source = 'unsupported_fallback';
        }

        return [
            'ip' => $ip,
            'country' => $country,
            'currency' => $currency,
            'source' => $source,
        ];
    }
}
