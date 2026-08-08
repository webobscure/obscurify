<?php

namespace Database\Factories;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Shared\Commerce\Enums\AddressType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutAddress>
 *
 * Deliberately has no `store_id` state: CheckoutAddress::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class CheckoutAddressFactory extends Factory
{
    protected $model = CheckoutAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checkout_id' => Checkout::factory(),
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
