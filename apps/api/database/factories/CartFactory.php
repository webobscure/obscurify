<?php

namespace Database\Factories;

use App\Domain\Carts\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 *
 * Deliberately has no `store_id` state: Cart::creating() always forces it
 * from TenantContext, same as ProductFactory.
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(48),
            'currency' => 'RUB',
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ];
    }
}
