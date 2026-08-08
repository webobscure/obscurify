<?php

namespace App\Domain\Inventory\Support;

use App\Domain\Catalog\Models\ProductVariant;

/**
 * Available quantity for a variant, aggregated across all locations —
 * there is no "ship from" location selection at this milestone.
 *
 * `available` is null when the variant isn't inventory-tracked (or has no
 * InventoryItem at all, which shouldn't happen since one is always
 * auto-provisioned on variant creation, but is treated the same way
 * defensively): untracked means unlimited, not zero.
 */
final class VariantAvailability
{
    private function __construct(
        public readonly bool $tracked,
        public readonly ?int $available,
        public readonly bool $inStock,
    ) {}

    public static function for(ProductVariant $variant): self
    {
        $item = $variant->relationLoaded('inventoryItem')
            ? $variant->inventoryItem
            : $variant->inventoryItem()->with('levels')->first();

        if ($item === null || ! $item->tracked) {
            return new self(tracked: false, available: null, inStock: true);
        }

        $levels = $item->relationLoaded('levels') ? $item->levels : $item->levels()->get();

        $available = (int) $levels->sum(fn ($level) => $level->on_hand - $level->reserved);

        return new self(tracked: true, available: $available, inStock: $available > 0);
    }
}
