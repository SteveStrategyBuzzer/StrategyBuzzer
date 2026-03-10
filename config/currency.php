<?php

return [

    // Default fallback if something goes wrong
    'default' => 'USD',

    // Realistic reference currency (your "true" pricing base)
    'base' => 'CAD',

    // Currencies that stay 1:1 nominal (marketing choice)
    // Ex: 20.00 CAD -> 20.00 USD/EUR/GBP (no FX conversion)
    'nominal_currencies' => [
        'CAD',
        'USD',
        'EUR',
        'GBP',
    ],

    // Supported currencies in the app (add more as you expand)
    'supported' => [
        'USD',
        'CAD',
        'EUR',
        'GBP',

        // Converted currencies (examples; you can add/remove)
        'THB',
        'MXN',
        'BRL',
        'AUD',
        'NZD',
        'SGD',
        'HKD',
        'INR',
        'CHF',
        'SEK',
        'NOK',
        'DKK',
        'PLN',
        'CZK',
        'ZAR',
    ],

    // Country -> currency mapping for GeoIP
    'country_map' => [
        'CA' => 'CAD',
        'US' => 'USD',
        'GB' => 'GBP',
        'UK' => 'GBP',

        // Eurozone (explicit list)
        'FR' => 'EUR',
        'BE' => 'EUR',
        'DE' => 'EUR',
        'ES' => 'EUR',
        'IT' => 'EUR',
        'NL' => 'EUR',
        'PT' => 'EUR',
        'IE' => 'EUR',
        'AT' => 'EUR',
        'FI' => 'EUR',
        'GR' => 'EUR',
        'LU' => 'EUR',

        // Examples (optional)
        'TH' => 'THB',
        'MX' => 'MXN',
        'BR' => 'BRL',
        'AU' => 'AUD',
        'NZ' => 'NZD',
        'SG' => 'SGD',
        'HK' => 'HKD',
        'IN' => 'INR',
        'CH' => 'CHF',
        'SE' => 'SEK',
        'NO' => 'NOK',
        'DK' => 'DKK',
        'PL' => 'PLN',
        'CZ' => 'CZK',
        'ZA' => 'ZAR',
    ],

    // GeoIP DB path
    'geoip_country_db' => storage_path('app/private/geoip/GeoLite2-Country.mmdb'),

    // Currency symbols (display only)
    'symbols' => [
        'USD' => '$',
        'CAD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'THB' => '฿',
        'MXN' => 'MX$',
        'BRL' => 'R$',
        'AUD' => '$',
        'NZD' => '$',
        'SGD' => 'S$',
        'HKD' => 'HK$',
        'INR' => '₹',
        'CHF' => 'CHF ',
        'SEK' => 'kr ',
        'NOK' => 'kr ',
        'DKK' => 'kr ',
        'PLN' => 'zł',
        'CZK' => 'Kč ',
        'ZAR' => 'R',
    ],

    // Minor units (number of decimals). Most are 2.
    // If you later add JPY/KRW => 0.
    'minor_units' => [
        'USD' => 2,
        'CAD' => 2,
        'EUR' => 2,
        'GBP' => 2,
        'THB' => 2,
        'MXN' => 2,
        'BRL' => 2,
        'AUD' => 2,
        'NZD' => 2,
        'SGD' => 2,
        'HKD' => 2,
        'INR' => 2,
        'CHF' => 2,
        'SEK' => 2,
        'NOK' => 2,
        'DKK' => 2,
        'PLN' => 2,
        'CZK' => 2,
        'ZAR' => 2,
    ],

    // FX rates from BASE (CAD) -> TARGET
    // IMPORTANT: These are MANUAL starter values.
    // Replace with real rates (or we automate later).
    // Meaning: 1 CAD = X TARGET
    'fx_rates' => [
        'THB' => 26.50,
        'MXN' => 12.50,
        'BRL' => 3.70,

        'AUD' => 1.12,
        'NZD' => 1.20,
        'SGD' => 0.99,
        'HKD' => 5.75,
        'INR' => 61.00,

        'CHF' => 0.65,
        'SEK' => 7.20,
        'NOK' => 7.40,
        'DKK' => 5.00,
        'PLN' => 2.90,
        'CZK' => 17.00,
        'ZAR' => 13.50,
    ],

];
