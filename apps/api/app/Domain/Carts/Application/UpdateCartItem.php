<?php

namespace App\Domain\Carts\Application;

use App\Domain\Carts\Application\Concerns\ValidatesCartItemQuantity;
use App\Domain\Carts\Models\CartItem;
use Illuminate\Support\Facades\DB;

final class UpdateCartItem
{
    use ValidatesCartItemQuantity;

    public function handle(CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($item, $quantity) {
            $locked = CartItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $this->assertPurchasable($locked->cart, $locked->variant, $quantity);

            $locked->update(['quantity' => $quantity]);

            return $locked;
        });
    }
}
