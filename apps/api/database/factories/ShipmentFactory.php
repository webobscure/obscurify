<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => FakeShippingProvider::CODE,
            'external_shipment_id' => 'fake_ship_'.(string) Str::ulid(),
            'status' => ShipmentStatus::Created,
            'tracking_number' => strtoupper(fake()->bothify('FAKE########')),
            'tracking_url' => null,
            'metadata' => null,
        ];
    }
}
