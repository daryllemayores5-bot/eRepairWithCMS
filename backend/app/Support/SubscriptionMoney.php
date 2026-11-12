<?php

namespace App\Support;

use InvalidArgumentException;

/** Monetary calculations use integer centavos, never floating point. */
class SubscriptionMoney
{
    public static function cents(string $amount): int
    {
        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/D', $amount, $parts)) {
            throw new InvalidArgumentException('Use a nonnegative peso amount with at most two decimal places.');
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(string $amount): string
    {
        $cents = self::cents($amount);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) intdiv($cents, 100));

        return '₱'.$whole.'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
