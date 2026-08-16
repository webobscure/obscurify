<?php

use App\Domain\RussianCommerce\Enums\VatRate;

it('reports null percentage for None, distinct from a real 0% Zero rate', function () {
    expect(VatRate::None->percentage())->toBeNull()
        ->and(VatRate::Zero->percentage())->toBe(0.0);
});

it('reports the correct percentage for each rate', function () {
    expect(VatRate::Five->percentage())->toBe(5.0)
        ->and(VatRate::Seven->percentage())->toBe(7.0)
        ->and(VatRate::Ten->percentage())->toBe(10.0)
        ->and(VatRate::Twenty->percentage())->toBe(20.0);
});

it('maps each rate to its real 54-FZ fiscal vat_code', function () {
    expect(VatRate::None->fiscalVatCode())->toBe(6)
        ->and(VatRate::Zero->fiscalVatCode())->toBe(5)
        ->and(VatRate::Five->fiscalVatCode())->toBe(7)
        ->and(VatRate::Seven->fiscalVatCode())->toBe(8)
        ->and(VatRate::Ten->fiscalVatCode())->toBe(2)
        ->and(VatRate::Twenty->fiscalVatCode())->toBe(1);
});

it('computes zero VAT amount for None and Zero rates', function () {
    expect(VatRate::None->amountFromInclusiveTotal(1000))->toBe(0)
        ->and(VatRate::Zero->amountFromInclusiveTotal(1000))->toBe(0);
});

it('back-calculates the VAT portion of a VAT-inclusive total', function () {
    // 1200 inclusive of 20% VAT -> VAT portion is 200 (1200 * 20 / 120).
    expect(VatRate::Twenty->amountFromInclusiveTotal(1200))->toBe(200)
        // 1100 inclusive of 10% VAT -> VAT portion is 100 (1100 * 10 / 110).
        ->and(VatRate::Ten->amountFromInclusiveTotal(1100))->toBe(100);
});

it('rounds the back-calculated VAT amount to the nearest minor unit', function () {
    // 100 inclusive of 20% VAT -> exact would be 16.666...; rounds to 17.
    expect(VatRate::Twenty->amountFromInclusiveTotal(100))->toBe(17);
});
