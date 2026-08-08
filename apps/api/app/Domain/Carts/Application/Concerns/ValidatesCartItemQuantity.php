<?php

namespace App\Domain\Carts\Application\Concerns;

use App\Domain\Carts\Models\Cart;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Support\VariantAvailability;
use Illuminate\Validation\ValidationException;

/**
 * Shared invariants for adding/updating a cart line: the variant must
 * belong to the same store as the cart, be purchasable, priced in the
 * cart's currency, and the requested total quantity must not exceed
 * currently available stock (informational only — this is not a
 * reservation, availability can still change before checkout).
 */
trait ValidatesCartItemQuantity
{
    protected function assertPurchasable(Cart $cart, ProductVariant $variant, int $requestedQuantity): void
    {
        // Both are tenant-scoped by BelongsToTenant under the same active
        // TenantContext, so this can only fail if a caller bypasses that
        // scope — defense in depth, matching AdjustInventory's pattern.
        if ($variant->store_id !== $cart->store_id) {
            throw ValidationException::withMessages([
                'variant_id' => 'This variant does not belong to the active store.',
            ]);
        }

        if ($variant->status !== ProductStatus::Active || $variant->product->status !== ProductStatus::Active) {
            throw ValidationException::withMessages([
                'variant_id' => 'This variant is not available for purchase.',
            ]);
        }

        if ($variant->currency !== $cart->currency) {
            throw ValidationException::withMessages([
                'variant_id' => 'This variant is priced in a different currency than the cart.',
            ]);
        }

        $availability = VariantAvailability::for($variant);

        if ($availability->tracked && $requestedQuantity > $availability->available) {
            throw ValidationException::withMessages([
                'quantity' => 'Only '.$availability->available.' unit(s) available.',
            ]);
        }
    }
}
