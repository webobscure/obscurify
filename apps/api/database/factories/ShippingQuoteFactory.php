<?php

namespace Database\Factories;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\ShippingQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingQuote>
 */
class ShippingQuoteFactory extends Factory
{
    protected $model = ShippingQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checkout_id' => Checkout::factory(),
            'shipping_method_id' => null,
            'provider' => FakeShippingProvider::CODE,
            'service_code' => 'standard',
            'name' => 'Standard Shipping',
            'price_amount' => 50000,
            'currency' => 'RUB',
            'estimated_days_min' => 3,
            'estimated_days_max' => 5,
            'expires_at' => now()->addMinutes(15),
            'metadata' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
