<?php

namespace App\Domain\GraphQL\DataLoaders;

use App\Domain\Customers\Models\Customer;
use App\Domain\GraphQL\Support\DataLoader;
use GraphQL\Deferred;

/**
 * Batches `Customer` lookups by id — e.g. a merchant's order list, each
 * order resolving its own `customer` field.
 */
final class CustomerLoader
{
    private DataLoader $loader;

    public function __construct()
    {
        $this->loader = new DataLoader(function (array $ids) {
            return Customer::query()
                ->whereIn('id', $ids)
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
