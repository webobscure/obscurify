<?php

namespace Database\Factories;

use App\Domain\CustomerIntelligence\Enums\CustomerSegmentStatus;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSegment>
 *
 * Deliberately has no `store_id` state: CustomerSegment::creating()
 * always forces it from TenantContext, same as CustomerFactory.
 */
class CustomerSegmentFactory extends Factory
{
    protected $model = CustomerSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'status' => CustomerSegmentStatus::Active->value,
        ];
    }
}
