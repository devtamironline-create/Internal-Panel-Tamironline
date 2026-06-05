<?php

namespace Modules\Identity\Support;

/**
 * Single source of truth for Iranian mobile number normalization.
 *
 * Accepts: 09xxxxxxxxx | +989xxxxxxxxx | 00989xxxxxxxxx | 989xxxxxxxxx | 9xxxxxxxxx
 * With Persian (۰۱۲...) or Arabic (٠١٢...) digits.
 *
 * Returns: 09xxxxxxxxx (11-digit canonical form) or null if invalid.
 */
final class PhoneNormalizer
{
    public static function normalize(?string $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        // Persian / Arabic digits → English
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $val = str_replace($fa, $en, str_replace($ar, $en, trim($raw)));

        // strip non-digits
        $val = preg_replace('/[^0-9]/', '', (string) $val) ?? '';

        // strip country code variants
        if (str_starts_with($val, '0098')) {
            $val = substr($val, 4);
        } elseif (str_starts_with($val, '98')) {
            $val = substr($val, 2);
        }

        // 10-digit form (9xxxxxxxxx) → prepend 0
        if (preg_match('/^9\d{9}$/', $val)) {
            $val = '0'.$val;
        }

        return preg_match('/^09\d{9}$/', $val) ? $val : null;
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }
}
