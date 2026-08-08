<?php

namespace Database\Factories;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 *
 * Deliberately has no `store_id` state: CustomerAddress::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
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
