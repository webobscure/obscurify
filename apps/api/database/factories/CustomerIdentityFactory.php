<?php

namespace Database\Factories;

use App\Domain\Customers\Enums\CustomerIdentityType;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<CustomerIdentity>
 *
 * Deliberately has no `store_id` state: CustomerIdentity::creating()
 * always forces it from TenantContext, same as CustomerFactory.
 */
class CustomerIdentityFactory extends Factory
{
    protected $model = CustomerIdentity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => CustomerIdentityType::EmailPassword->value,
            'identifier' => fake()->unique()->safeEmail(),
            'secret_hash' => Hash::make('password'),
            'failed_attempts' => 0,
            'locked_until' => null,
        ];
    }
}
