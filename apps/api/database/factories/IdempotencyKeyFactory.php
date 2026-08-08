<?php

namespace Database\Factories;

use App\Shared\Commerce\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyKey>
 *
 * Deliberately has no `store_id` state: IdempotencyKey::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation' => 'checkout.complete',
            'key' => Str::uuid()->toString(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
