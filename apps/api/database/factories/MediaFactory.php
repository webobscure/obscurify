<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Media\Enums\MediaEntityType;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 *
 * Deliberately has no `store_id` state: Media::creating() always forces it
 * from TenantContext, same as ProductFactory.
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => MediaEntityType::Product,
            'entity_id' => Product::factory(),
            'type' => MediaType::Image,
            'disk' => 's3',
            'path' => 'products/'.fake()->uuid().'.jpg',
            'alt' => fake()->sentence(3),
            'position' => 0,
            'metadata' => null,
        ];
    }
}
