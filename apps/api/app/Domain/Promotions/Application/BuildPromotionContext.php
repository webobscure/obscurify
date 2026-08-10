<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Domain\Collections\Models\CollectionProduct;
use App\Domain\Customers\Models\Customer;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Support\PromotionContext;
use App\Domain\Promotions\Support\PromotionLine;
use App\Shared\Commerce\Enums\AddressType;

/**
 * Assembles PromotionEngine's input from a Cart/Checkout — the only place
 * collection/category membership is looked up for promotion purposes, so
 * RuleEngine/ActionEngine never touch the database per-line.
 */
final class BuildPromotionContext
{
    public function handle(Cart $cart, Checkout $checkout, int $shippingAmount, ?DiscountCode $appliedDiscountCode): PromotionContext
    {
        $items = $cart->relationLoaded('items') ? $cart->items : $cart->items()->with('variant')->get();

        $productIds = $items->pluck('variant.product_id')->filter()->unique()->values();

        $collectionsByProduct = CollectionProduct::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');

        $categoriesByProduct = ProductCategory::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');

        $lines = $items->map(function (CartItem $item) use ($collectionsByProduct, $categoriesByProduct) {
            $variant = $item->variant;

            return new PromotionLine(
                productId: $variant->product_id,
                productVariantId: $variant->id,
                quantity: $item->quantity,
                unitPrice: $variant->price_amount,
                collectionIds: ($collectionsByProduct->get($variant->product_id) ?? collect())->pluck('collection_id')->all(),
                categoryIds: ($categoriesByProduct->get($variant->product_id) ?? collect())->pluck('category_id')->all(),
            );
        })->values()->all();

        $itemsSubtotal = 0;
        foreach ($lines as $line) {
            $itemsSubtotal += $line->lineTotal();
        }

        $shippingAddress = $checkout->relationLoaded('addresses')
            ? $checkout->addresses->firstWhere('type', AddressType::Shipping)
            : CheckoutAddress::query()
                ->where('checkout_id', $checkout->id)
                ->where('type', AddressType::Shipping->value)
                ->first();

        $customer = $checkout->customer_id !== null ? Customer::query()->find($checkout->customer_id) : null;
        $customerEmail = $customer !== null ? $customer->email : $checkout->email;

        return new PromotionContext(
            lines: $lines,
            itemsSubtotal: $itemsSubtotal,
            shippingAmount: $shippingAmount,
            currency: $checkout->currency,
            countryCode: $shippingAddress?->country_code,
            customerId: $customer?->id,
            customerEmail: $customerEmail,
            appliedDiscountCode: $appliedDiscountCode,
            now: now(),
        );
    }
}
