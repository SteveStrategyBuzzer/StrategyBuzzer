<?php

namespace App\Support;

use Illuminate\Session\Store as SessionStore;

class Currency
{
    public const DEFAULT = 'USD';

    /**
     * Supported currencies come from config/currency.php to avoid hardcoding.
     */
    public static function supported(): array
    {
        $supported = config('currency.supported', ['USD', 'CAD', 'EUR', 'GBP']);
        return array_values(array_unique(array_map('strtoupper', (array) $supported)));
    }

    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        $default = strtoupper((string) config('currency.default', self::DEFAULT));

        return in_array($code, self::supported(), true) ? $code : $default;
    }

    /**
     * Server-only currency (never trust client)
     */
    public static function fromSession(SessionStore $session): string
    {
        return self::normalize($session->get('currency', config('currency.default', self::DEFAULT)));
    }

    /**
     * Base currency used as "realistic reference" for conversions.
     * Example: CAD
     */
    public static function base(): string
    {
        return self::normalize(config('currency.base', 'CAD'));
    }

    /**
     * Currencies that stay 1:1 nominal (marketing choice).
     * Example: CAD, USD, EUR, GBP
     */
    public static function nominalCurrencies(): array
    {
        $list = config('currency.nominal_currencies', ['CAD', 'USD', 'EUR', 'GBP']);
        $list = array_values(array_unique(array_map('strtoupper', (array) $list)));
        return array_values(array_intersect($list, self::supported()));
    }

    public static function isNominal(string $code): bool
    {
        return in_array(self::normalize($code), self::nominalCurrencies(), true);
    }

    /**
     * Minor units (decimals). Most currencies = 2.
     */
    public static function minorUnits(string $code): int
    {
        $map = (array) config('currency.minor_units', []);
        $c = self::normalize($code);
        $val = $map[$c] ?? 2;
        $val = is_numeric($val) ? (int) $val : 2;
        return max(0, min(3, $val));
    }

    /**
     * FX rates from BASE -> TARGET.
     * Store as: 'THB' => 26.50 (meaning 1 CAD = 26.50 THB)
     */
    public static function fxRateFromBase(string $target): ?float
    {
        $rates = (array) config('currency.fx_rates', []);
        $t = self::normalize($target);

        if (!isset($rates[$t])) {
            return null;
        }

        $r = $rates[$t];
        if (!is_numeric($r)) {
            return null;
        }

        $r = (float) $r;
        return $r > 0 ? $r : null;
    }

    /**
     * RULE YOU REQUESTED:
     * - CAD/USD/EUR/GBP (nominal): fixed 1:1 nominal (0.99 stays 0.99)
     * - Other currencies:
     *    - If currency is weaker than CAD (rate >= 1): adjust upward using FX (keep CAD value)
     *    - If currency is stronger than CAD (rate < 1): DO NOT lower price, keep nominal
     */
    public static function convertBaseCentsTo(string $target, int $baseCents): int
    {
        $target = self::normalize($target);

        // Guard: invalid base
        $baseCents = (int) $baseCents;
        if ($baseCents <= 0) {
            return 1;
        }

        $minor = self::minorUnits($target);
        $factor = 10 ** $minor;

        // Floor in target minor units that represents the same "nominal digits" as base cents.
        // For your current setup (2 decimals everywhere), this equals $baseCents.
        // If later you add JPY(0), this becomes round(0.99 * 1) = 1 JPY minimum instead of 99.
        $baseUnits = $baseCents / 100.0; // base assumed 2 decimals like CAD
        $nominalFloorInTarget = max(1, (int) round($baseUnits * $factor));

        // Nominal currencies: fixed nominal pricing
        if (self::isNominal($target)) {
            return $nominalFloorInTarget;
        }

        $rate = self::fxRateFromBase($target);
        if ($rate === null) {
            // Safety fallback: keep nominal if missing rate
            return $nominalFloorInTarget;
        }

        // If target is stronger than base (rate < 1), do NOT discount below nominal
        if ($rate < 1.0) {
            return $nominalFloorInTarget;
        }

        // Convert upward for weaker currencies (rate >= 1)
        $targetUnits = $baseUnits * $rate;
        $amount = (int) round($targetUnits * $factor);

        // Never allow 0, and never go below the CAD nominal floor expressed in target minor units
        return max($nominalFloorInTarget, $amount);
    }

    public static function symbol(string $code): string
    {
        $c = self::normalize($code);

        $symbols = (array) config('currency.symbols', [
            'USD' => '$',
            'CAD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'THB' => '฿',
            'BRL' => 'R$',
            'MXN' => 'MX$',
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
        ]);

        return $symbols[$c] ?? '$';
    }

    /**
     * Optional label if you want: "CAD $" vs "$"
     */
    public static function label(string $code): string
    {
        $c = self::normalize($code);
        return $c . ' ' . self::symbol($c);
    }
}
