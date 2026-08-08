<?php

namespace App\Domain\Carts\Application;

use App\Domain\Carts\Models\CartItem;

final class RemoveCartItem
{
    public function handle(CartItem $item): void
    {
        $item->delete();
    }
}
