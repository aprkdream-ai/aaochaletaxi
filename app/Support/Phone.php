<?php

namespace App\Support;

class Phone
{
    /** Normalise Indian mobile numbers to 10 digits (strips +91, 0, spaces, dashes). */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits === '' ? null : $digits;
    }

    public static function isValid(?string $phone): bool
    {
        return (bool) preg_match('/^[6-9]\d{9}$/', (string) $phone);
    }
}
