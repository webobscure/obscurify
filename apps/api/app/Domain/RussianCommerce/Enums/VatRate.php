<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Spec section 4 — the closed set of Russian VAT rates (54-FZ "ставка
 * НДС"). `None` (merchant not a VAT payer, e.g. simplified taxation) is
 * distinct from `Zero` (a real 0% rate applied to specific goods/export)
 * — both charge no VAT amount, but a fiscal receipt must report which
 * one, since they mean different things to a tax authority.
 * `percentage()` is null for `None` precisely because "no VAT" has no
 * rate to compute against, not a 0-shaped rate.
 */
enum VatRate: string
{
    case None = 'none';
    case Zero = 'vat_0';
    case Five = 'vat_5';
    case Seven = 'vat_7';
    case Ten = 'vat_10';
    case Twenty = 'vat_20';

    public function percentage(): ?float
    {
        return match ($this) {
            self::None => null,
            self::Zero => 0.0,
            self::Five => 5.0,
            self::Seven => 7.0,
            self::Ten => 10.0,
            self::Twenty => 20.0,
        };
    }

    /**
     * The 54-FZ fiscal receipt "vat_code" a real OFD/cash-register
     * provider expects (1=20%, 2=10%, 3=20/120 calculated, 4=10/110
     * calculated, 5=0%, 6=no VAT, 7=5%, 8=7%, 9/10=5/105 and 7/107
     * calculated). This milestone only ever needs the non-calculated
     * codes (a receipt line always states price as VAT-inclusive and
     * this is the rate applied, not a derived fraction) — the
     * calculated-fraction codes are reserved for when a real provider
     * integration needs them.
     */
    public function fiscalVatCode(): int
    {
        return match ($this) {
            self::None => 6,
            self::Zero => 5,
            self::Five => 7,
            self::Seven => 8,
            self::Ten => 2,
            self::Twenty => 1,
        };
    }

    /**
     * The VAT portion of a VAT-inclusive amount (Russian receipts
     * always state the customer-facing price as already including
     * VAT), rounded to the nearest minor unit.
     */
    public function amountFromInclusiveTotal(int $inclusiveAmount): int
    {
        $percentage = $this->percentage();

        if ($percentage === null || $percentage === 0.0) {
            return 0;
        }

        return (int) round($inclusiveAmount * $percentage / (100 + $percentage));
    }
}
