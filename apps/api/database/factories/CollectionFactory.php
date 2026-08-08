<?php

namespace Database\Factories;

use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Collections\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collection>
 *
 * Deliberately has no `store_id` state: Collection::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 999999),
            'description' => fake()->sentence(),
            'status' => CollectionStatus::Active,
        ];
    }
}
