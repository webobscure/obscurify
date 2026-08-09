<?php

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Shipping\Exceptions\ShippingRateCalculationFailedException;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Support\PickupPoint;
use App\Domain\Shipping\Support\ShipmentWeightCalculator;
use App\Domain\Shipping\Support\ShippingRateContext;
use Illuminate\Support\Str;

/**
 * Exercises FakeShippingProvider and ShipmentWeightCalculator directly (no
 * HTTP, no tenant scoping — the ShippingMethod/ProductVariant fixtures
 * here are never persisted) so assertions can reach ShippingRate::
 * $metadata, which StorefrontShippingRateResource deliberately never
 * exposes over HTTP (see ShippingRateTest).
 */
function fakeMethod(string $serviceCode): ShippingMethod
{
    $method = new ShippingMethod([
        'name' => ucfirst($serviceCode),
        'code' => $serviceCode,
        'provider' => FakeShippingProvider::CODE,
        'service_code' => $serviceCode,
        'price_amount' => 1,
        'currency' => 'RUB',
    ]);
    $method->id = (string) Str::ulid();

    return $method;
}

it('prices a domestic (RU) destination with no international surcharge', function () {
    $provider = new FakeShippingProvider;
    $context = new ShippingRateContext(countryCode: 'RU', region: null, postalCode: null, currency: 'RUB', weightKg: 0.0);

    $rates = $provider->calculateRates(collect([fakeMethod('standard')]), $context);

    // base_price_amount for 'standard' (config/commerce.php) is 30000 —
    // no weight, no international surcharge, no markup by default.
    expect($rates->first()->priceAmount)->toBe(30000)
        ->and($rates->first()->metadata['international'])->toBeFalse();
});

it('applies the international surcharge for a non-domestic destination', function () {
    $provider = new FakeShippingProvider;
    $context = new ShippingRateContext(countryCode: 'US', region: null, postalCode: null, currency: 'RUB', weightKg: 0.0);

    $rates = $provider->calculateRates(collect([fakeMethod('standard')]), $context);

    // 30000 * 1.5 (default 50% surcharge) = 45000.
    expect($rates->first()->priceAmount)->toBe(45000)
        ->and($rates->first()->metadata['international'])->toBeTrue();
});

it('increases price with billable weight, rounded up to the next whole kg', function () {
    $provider = new FakeShippingProvider;

    $light = $provider->calculateRates(
        collect([fakeMethod('standard')]),
        new ShippingRateContext(countryCode: 'RU', region: null, postalCode: null, currency: 'RUB', weightKg: 0.4),
    )->first();

    $heavy = $provider->calculateRates(
        collect([fakeMethod('standard')]),
        new ShippingRateContext(countryCode: 'RU', region: null, postalCode: null, currency: 'RUB', weightKg: 2.1),
    )->first();

    // 0.4kg rounds up to 1 billable kg: 30000 + 1*5000 = 35000.
    // 2.1kg rounds up to 3 billable kg: 30000 + 3*5000 = 45000.
    expect($light->priceAmount)->toBe(35000)
        ->and($light->metadata['billable_weight_kg'])->toBe(1)
        ->and($heavy->priceAmount)->toBe(45000)
        ->and($heavy->metadata['billable_weight_kg'])->toBe(3);
});

it('falls back to the ShippingMethod flat price for an unconfigured service code', function () {
    $provider = new FakeShippingProvider;
    $method = fakeMethod('custom-white-glove');
    $method->price_amount = 77777;

    $context = new ShippingRateContext(countryCode: 'RU', region: null, postalCode: null, currency: 'RUB', weightKg: 5.0);

    $rates = $provider->calculateRates(collect([$method]), $context);

    // No entry in commerce.shipping.fake.services for this code — weight
    // is simply not applied, the method's own flat price is used as-is.
    expect($rates->first()->priceAmount)->toBe(77777);
});

it('lists RU pickup points for a matching destination and none for a non-matching one', function () {
    $provider = new FakeShippingProvider;

    $ru = $provider->listPickupPoints(new ShippingRateContext(countryCode: 'RU', region: null, postalCode: null, currency: 'RUB'));
    $us = $provider->listPickupPoints(new ShippingRateContext(countryCode: 'US', region: null, postalCode: null, currency: 'USD'));

    expect($ru)->not->toBeEmpty()
        ->and($ru->first())->toBeInstanceOf(PickupPoint::class)
        ->and($ru->every(fn (PickupPoint $p) => $p->countryCode === 'RU'))->toBeTrue()
        ->and($us)->toBeEmpty();
});

it('failure simulation: a magic postal code triggers a rate calculation failure only when enabled', function () {
    config(['commerce.shipping.fake.failure_simulation.enabled' => true]);

    $provider = new FakeShippingProvider;
    $context = new ShippingRateContext(countryCode: 'RU', region: null, postalCode: 'SIMFAIL-RATE', currency: 'RUB');

    expect(fn () => $provider->calculateRates(collect([fakeMethod('standard')]), $context))
        ->toThrow(ShippingRateCalculationFailedException::class);
});

it('failure simulation trigger is inert when failure_simulation is disabled', function () {
    config(['commerce.shipping.fake.failure_simulation.enabled' => false]);

    $provider = new FakeShippingProvider;
    $context = new ShippingRateContext(countryCode: 'RU', region: null, postalCode: 'SIMFAIL-RATE', currency: 'RUB');

    $rates = $provider->calculateRates(collect([fakeMethod('standard')]), $context);

    expect($rates)->not->toBeEmpty();
});

describe('ShipmentWeightCalculator', function () {
    function variantWithWeight(?string $weight, ?string $length = null, ?string $width = null, ?string $height = null): ProductVariant
    {
        $variant = new ProductVariant([
            'title' => 'Test',
            'price_amount' => 1000,
            'currency' => 'RUB',
            'weight' => $weight,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'option_signature' => '',
        ]);
        $variant->id = (string) Str::ulid();

        return $variant;
    }

    it('sums actual weight across quantities', function () {
        $calculator = new ShipmentWeightCalculator;

        $result = $calculator->handle(collect([
            ['variant' => variantWithWeight('1.500'), 'quantity' => 2],
            ['variant' => variantWithWeight('0.500'), 'quantity' => 1],
        ]));

        expect($result->actualKg)->toBe(3.5)
            ->and($result->billableKg)->toBe(3.5);
    });

    it('treats a variant with no weight as 0kg, not an error', function () {
        $calculator = new ShipmentWeightCalculator;

        $result = $calculator->handle(collect([
            ['variant' => variantWithWeight(null), 'quantity' => 3],
        ]));

        expect($result->actualKg)->toBe(0.0)
            ->and($result->billableKg)->toBe(0.0);
    });

    it('uses volumetric weight when it exceeds actual weight (a light but bulky item)', function () {
        $calculator = new ShipmentWeightCalculator;

        // 50x50x50cm = 125000 cm3 / 5000 (default divisor) = 25kg
        // volumetric, vs. 1kg actual — billable should be the volumetric
        // figure.
        $result = $calculator->handle(collect([
            ['variant' => variantWithWeight('1.000', '50', '50', '50'), 'quantity' => 1],
        ]));

        expect($result->actualKg)->toBe(1.0)
            ->and($result->volumetricKg)->toBe(25.0)
            ->and($result->billableKg)->toBe(25.0);
    });

    it('uses actual weight when it exceeds volumetric weight (a small but dense item)', function () {
        $calculator = new ShipmentWeightCalculator;

        // 5x5x5cm = 125 cm3 / 5000 = 0.025kg volumetric, vs. 4kg actual.
        $result = $calculator->handle(collect([
            ['variant' => variantWithWeight('4.000', '5', '5', '5'), 'quantity' => 1],
        ]));

        expect($result->billableKg)->toBe(4.0);
    });
});
