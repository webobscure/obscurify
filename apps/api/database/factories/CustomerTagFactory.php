<?php

namespace Database\Factories;

use App\Domain\CustomerIntelligence\Models\CustomerTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerTag>
 *
 * Deliberately has no `store_id` state: CustomerTag::creating() always
 * forces it from TenantContext, same as CustomerFactory.
 */
class CustomerTagFactory extends Factory
{
    protected $model = CustomerTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_system' => false,
        ];
    }
}
