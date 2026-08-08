<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderAddress;
use App\Shared\Commerce\Enums\AddressType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderAddress>
 *
 * Deliberately has no `store_id` state: OrderAddress::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class OrderAddressFactory extends Factory
{
    protected $model = OrderAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => AddressType::Shipping,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('+7 (9##) ###-##-##'),
            'country_code' => 'RU',
            'region' => fake()->state(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
        ];
    }
}
