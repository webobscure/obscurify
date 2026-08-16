<?php

namespace App\Domain\RussianCommerce\Support;

use App\Domain\RussianCommerce\Enums\LegalEntityType;

/**
 * Real ФНС (Federal Tax Service) checksum algorithms — not just a
 * digit-count check. A LegalEntity's INN is 10 digits with one check
 * digit; an IndividualEntrepreneur/SelfEmployed person's INN is 12
 * digits with two check digits. KPP has no checksum, only a fixed
 * 9-digit format, and only ever applies to a LegalEntity (spec section
 * 1: "kpp nullable").
 */
final class InnKppValidator
{
    private const array INN10_WEIGHTS = [2, 4, 10, 3, 5, 9, 4, 6, 8];

    private const array INN12_WEIGHTS_11 = [7, 2, 4, 10, 3, 5, 9, 4, 6, 8];

    private const array INN12_WEIGHTS_12 = [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8];

    public function isValidInn(string $inn, LegalEntityType $type): bool
    {
        return match ($type) {
            LegalEntityType::LegalEntity => $this->isValidInn10($inn),
            LegalEntityType::IndividualEntrepreneur, LegalEntityType::SelfEmployed => $this->isValidInn12($inn),
        };
    }

    public function isValidInn10(string $inn): bool
    {
        if (! preg_match('/^\d{10}$/', $inn)) {
            return false;
        }

        $digits = array_map('intval', str_split($inn));
        $check = $this->checksum(array_slice($digits, 0, 9), self::INN10_WEIGHTS);

        return $check === $digits[9];
    }

    public function isValidInn12(string $inn): bool
    {
        if (! preg_match('/^\d{12}$/', $inn)) {
            return false;
        }

        $digits = array_map('intval', str_split($inn));
        $check11 = $this->checksum(array_slice($digits, 0, 10), self::INN12_WEIGHTS_11);
        $check12 = $this->checksum(array_slice($digits, 0, 11), self::INN12_WEIGHTS_12);

        return $check11 === $digits[10] && $check12 === $digits[11];
    }

    /**
     * KPP has no checksum — a fixed 9-digit format is the whole check
     * (tax authority code + reason code + sequential number).
     */
    public function isValidKpp(string $kpp): bool
    {
        return (bool) preg_match('/^\d{9}$/', $kpp);
    }

    /**
     * OGRN (13 digits, legal entities) / OGRNIP (15 digits, individual
     * entrepreneurs) — format-only validation this milestone; both have
     * their own checksum algorithms a future milestone can add if a real
     * registry-lookup integration ever needs it.
     */
    public function isValidOgrn(string $ogrn): bool
    {
        return (bool) preg_match('/^\d{13}$/', $ogrn);
    }

    public function isValidOgrnip(string $ogrnip): bool
    {
        return (bool) preg_match('/^\d{15}$/', $ogrnip);
    }

    /**
     * @param  list<int>  $digits
     * @param  list<int>  $weights
     */
    private function checksum(array $digits, array $weights): int
    {
        $sum = 0;

        foreach ($digits as $i => $digit) {
            $sum += $digit * $weights[$i];
        }

        return ($sum % 11) % 10;
    }
}
