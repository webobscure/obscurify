<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Collections\Models\CollectionProduct;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Support\PromotionContext;
use App\Domain\Promotions\Support\PromotionEngine;
use App\Domain\Promotions\Support\PromotionEvaluationResult;
use App\Domain\Promotions\Support\PromotionLine;

/**
 * Admin-only "what would apply" tool (spec section 9) — builds a
 * PromotionContext from a hypothetical set of variant/quantity lines
 * instead of a real Cart, then delegates to the same PromotionEngine
 * Checkout uses. Never persists anything.
 */
final class PreviewPromotions
{
    public function __construct(private readonly PromotionEngine $promotionEngine) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PromotionEvaluationResult
    {
        $variantIds = collect($data['items'])->pluck('product_variant_id');
        $variants = ProductVariant::query()->whereIn('id', $variantIds)->get()->keyBy('id');

        $productIds = $variants->pluck('product_id')->unique()->values();
        $collectionsByProduct = CollectionProduct::query()->whereIn('product_id', $productIds)->get()->groupBy('product_id');
        $categoriesByProduct = ProductCategory::query()->whereIn('product_id', $productIds)->get()->groupBy('product_id');

        $lines = [];
        $currency = null;

        foreach ($data['items'] as $item) {
            $variant = $variants->get($item['product_variant_id']);

            if ($variant === null) {
                continue;
            }

            $currency ??= $variant->currency;

            $lines[] = new PromotionLine(
                productId: $variant->product_id,
                productVariantId: $variant->id,
                quantity: (int) $item['quantity'],
                unitPrice: $variant->price_amount,
                collectionIds: ($collectionsByProduct->get($variant->product_id) ?? collect())->pluck('collection_id')->all(),
                categoryIds: ($categoriesByProduct->get($variant->product_id) ?? collect())->pluck('category_id')->all(),
            );
        }

        $itemsSubtotal = 0;
        foreach ($lines as $line) {
            $itemsSubtotal += $line->lineTotal();
        }

        $discountCode = ! empty($data['discount_code']) ? DiscountCode::findByCode($data['discount_code']) : null;

        $context = new PromotionContext(
            lines: $lines,
            itemsSubtotal: $itemsSubtotal,
            shippingAmount: (int) ($data['shipping_amount'] ?? 0),
            currency: $currency ?? 'RUB',
            countryCode: $data['country_code'] ?? null,
            customerId: $data['customer_id'] ?? null,
            customerEmail: null,
            appliedDiscountCode: $discountCode,
            now: now(),
        );

        return $this->promotionEngine->handle($context);
    }
}
