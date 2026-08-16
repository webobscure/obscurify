<?php

namespace App\Domain\GraphQL\DataLoaders;

use App\Domain\GraphQL\Support\DataLoader;
use App\Domain\Orders\Models\Order;
use GraphQL\Deferred;

/**
 * Batches `Order` lookups by id.
 */
final class OrderLoader
{
    private DataLoader $loader;

    public function __construct()
    {
        $this->loader = new DataLoader(function (array $ids) {
            return Order::query()
                ->whereIn('id', $ids)
                ->with(['items', 'shippingAddress', 'billingAddress'])
                ->get()
                ->keyBy('id')
                ->all();
        });
    }

    public function load(string $id): Deferred
    {
        return $this->loader->load($id);
    }
}
