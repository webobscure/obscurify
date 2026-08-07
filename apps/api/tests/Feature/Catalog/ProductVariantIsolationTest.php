<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

function variantInStore(Store $store, Product $product, array $overrides = []): ProductVariant
{
    return app(TenantContext::class)->scope(
        $store,
        fn () => ProductVariant::factory()->create(['product_id' => $product->id, ...$overrides]),
    );
}

it('never resolves a Store B variant while Store A is the active tenant', function () {
    $userA = User::factory()->create();
    $storeA = createStoreForUser($userA);
    $productA = app(TenantContext::class)->scope($storeA, fn () => Product::factory()->create());
    variantInStore($storeA, $productA, ['sku' => 'A-SKU-1']);

    $userB = User::factory()->create();
    $storeB = createStoreForUser($userB);
    $productB = app(TenantContext::class)->scope($storeB, fn () => Product::factory()->create());
    $variantB = variantInStore($storeB, $productB, ['sku' => 'B-SKU-1']);

    app(TenantContext::class)->scope($storeA, function () use ($variantB) {
        expect(ProductVariant::find($variantB->id))->toBeNull();
    });

    // Defense in depth: even bypassing the scope, the variant genuinely
    // belongs to Store B, never Store A.
    expect(ProductVariant::withoutGlobalScopes()->find($variantB->id)->store_id)
        ->toBe($storeB->id);
});
