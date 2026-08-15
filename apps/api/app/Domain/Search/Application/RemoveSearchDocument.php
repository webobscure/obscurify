<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Search\Support\SearchProviderRegistry;

final class RemoveSearchDocument
{
    public function __construct(private readonly SearchProviderRegistry $registry) {}

    public function handle(string $storeId, string $productId): void
    {
        $settings = SearchSettings::query()->where('store_id', $storeId)->first();
        $code = $settings?->activeProviderCode() ?? SearchProvider::DATABASE;

        if ($this->registry->has($code)) {
            $this->registry->resolve($code)->delete($storeId, $productId);
        }

        SearchDocument::query()->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->delete();
    }
}
