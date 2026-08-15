<?php

namespace Database\Factories;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSession>
 *
 * Deliberately has no `store_id` state: CustomerSession::creating()
 * always forces it from TenantContext, same as CustomerFactory.
 */
class CustomerSessionFactory extends Factory
{
    protected $model = CustomerSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ];
    }
}
