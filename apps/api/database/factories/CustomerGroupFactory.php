<?php

namespace Database\Factories;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupStatus;
use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
 *
 * Deliberately has no `store_id` state: CustomerGroup::creating() always
 * forces it from TenantContext, same as CustomerFactory.
 */
class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'type' => CustomerGroupType::Manual->value,
            'status' => CustomerGroupStatus::Active->value,
        ];
    }

    public function dynamic(): static
    {
        return $this->state(['type' => CustomerGroupType::Dynamic->value]);
    }

    public function protected(): static
    {
        return $this->state(['type' => CustomerGroupType::Protected->value]);
    }
}
