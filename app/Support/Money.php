<?php

namespace App\Support;

/** bcmath helpers for INR amounts stored as DECIMAL(12,2). */
class Money
{
    public static function add(string|int|float ...$amounts): string
    {
        $total = '0.00';
        foreach ($amounts as $amount) {
            $total = bcadd($total, self::norm($amount), 2);
        }

        return $total;
    }

    public static function sub(string|int|float $a, string|int|float $b): string
    {
        return bcsub(self::norm($a), self::norm($b), 2);
    }

    public static function mul(string|int|float $a, string|int|float $b): string
    {
        return self::round(bcmul(self::norm($a, 4), self::norm($b, 4), 6));
    }

    public static function percent(string|int|float $amount, string|int|float $percent): string
    {
        return self::round(bcdiv(bcmul(self::norm($amount, 4), self::norm($percent, 4), 6), '100', 6));
    }

    /** Round half up to 2 decimals. */
    public static function round(string $value): string
    {
        $offset = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd($value, $offset, 2);
    }

    public static function format(string|int|float|null $amount, int $decimals = 2): string
    {
        $amount = (float) ($amount ?? 0);
        $negative = $amount < 0;
        $parts = explode('.', number_format(abs($amount), $decimals, '.', ''));
        $int = $parts[0];
        $dec = $parts[1] ?? null;

        // Indian digit grouping: 12,34,567.00
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }

        return ($negative ? '-' : '').'₹'.$int.($dec !== null ? '.'.$dec : '');
    }

    private static function norm(string|int|float $v, int $scale = 2): string
    {
        return is_string($v) ? $v : number_format((float) $v, $scale, '.', '');
    }
}
