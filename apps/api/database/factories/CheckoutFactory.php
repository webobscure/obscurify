<?php

namespace Database\Factories;

use App\Domain\Carts\Models\Cart;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checkout>
 *
 * Deliberately has no `store_id` state: Checkout::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class CheckoutFactory extends Factory
{
    protected $model = Checkout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'currency' => 'RUB',
            'status' => CheckoutStatus::Open,
            'expires_at' => now()->addMinutes(60),
        ];
    }
}
