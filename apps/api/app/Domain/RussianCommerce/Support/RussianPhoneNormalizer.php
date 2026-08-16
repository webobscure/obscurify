<?php

namespace App\Domain\RussianCommerce\Support;

/**
 * Spec section 3 — canonical form is always `+7XXXXXXXXXX` (12
 * characters, no separators). Identity/comparison/lookup must always
 * use this normalized form, never a display-formatted string (spec:
 * "Do not use display formatting as identity") — `format()` exists
 * purely for UI rendering and is never what gets stored or compared.
 */
final class RussianPhoneNormalizer
{
    /**
     * Accepts `8XXXXXXXXXX`, `7XXXXXXXXXX`, `+7XXXXXXXXXX`, and the same
     * with common separators (spaces, dashes, parens) — anything else
     * (wrong digit count, a non-Russian country code) returns null
     * rather than guessing.
     */
    public function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+7'.$digits;
        }

        return null;
    }

    public function isValid(string $raw): bool
    {
        return $this->normalize($raw) !== null;
    }

    /**
     * `+7 (XXX) XXX-XX-XX` — display only, never used for identity/lookup.
     */
    public function format(string $normalized): string
    {
        if (! preg_match('/^\+7(\d{3})(\d{3})(\d{2})(\d{2})$/', $normalized, $m)) {
            return $normalized;
        }

        return "+7 ({$m[1]}) {$m[2]}-{$m[3]}-{$m[4]}";
    }
}
