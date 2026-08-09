<?php

namespace Database\Factories;

use App\Domain\Shipping\Enums\ShippingMethodStatus;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    protected $model = ShippingMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Standard Shipping',
            'code' => strtolower(fake()->unique()->bothify('method-????')),
            'provider' => FakeShippingProvider::CODE,
            'service_code' => 'standard',
            'status' => ShippingMethodStatus::Active,
            'price_amount' => 50000,
            'currency' => 'RUB',
            'estimated_days_min' => 3,
            'estimated_days_max' => 5,
            'settings' => null,
        ];
    }

    public function express(): self
    {
        return $this->state(fn () => [
            'name' => 'Express Shipping',
            'service_code' => 'express',
            'price_amount' => 150000,
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
        ]);
    }

    public function pickup(): self
    {
        return $this->state(fn () => [
            'name' => 'Pickup Point',
            'service_code' => 'pickup',
            'price_amount' => 30000,
            'estimated_days_min' => 2,
            'estimated_days_max' => 4,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['status' => ShippingMethodStatus::Inactive]);
    }
}
