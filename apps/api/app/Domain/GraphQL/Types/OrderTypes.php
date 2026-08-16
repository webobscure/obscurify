<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\GraphQL\DataLoaders\CustomerLoader;
use App\Domain\GraphQL\DataLoaders\ProductLoader;
use App\Domain\GraphQL\DataLoaders\VariantLoader;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Mirrors CustomerOrderController (customer-facing) — a merchant-side
 * `orders`/`order` query reuses the identical Order type, tenant
 * isolation being the only boundary that differs (see OrderQueries).
 */
final class OrderTypes
{
    /**
     * `AddressSnapshot` is shared by both `Order.shippingAddress`/
     * `billingAddress` (OrderAddress model) and `Checkout.shippingAddress`/
     * `billingAddress` (CheckoutAddress model, see CheckoutTypes) — both
     * are "one row per type" snapshots with an identical field set, so
     * the resolver deliberately takes `mixed` rather than a single
     * model's type, since either model satisfies it identically via
     * plain property access.
     */
    public static function orderAddress(): ObjectType
    {
        return new ObjectType([
            'name' => 'AddressSnapshot',
            'fields' => fn () => [
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'phone' => Type::string(),
                'countryCode' => Type::string(),
                'region' => Type::string(),
                'city' => Type::string(),
                'postalCode' => Type::string(),
                'addressLine1' => Type::string(),
                'addressLine2' => Type::string(),
            ],
            'resolveField' => fn (mixed $address, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'firstName' => $address->first_name,
                'lastName' => $address->last_name,
                'countryCode' => $address->country_code,
                'postalCode' => $address->postal_code,
                'addressLine1' => $address->address_line1,
                'addressLine2' => $address->address_line2,
                default => $address->{$info->fieldName},
            },
        ]);
    }

    public static function orderItem(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'OrderItem',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'productTitle' => Type::nonNull(Type::string()),
                'variantTitle' => Type::string(),
                'sku' => Type::string(),
                'quantity' => Type::nonNull(Type::int()),
                'unitPrice' => $types->get('Money'),
                'lineTotal' => $types->get('Money'),
                'product' => $types->get('Product'),
                'variant' => $types->get('ProductVariant'),
            ],
            'resolveField' => function (OrderItem $item, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id' => $item->id,
                    'productTitle' => $item->product_title,
                    'variantTitle' => $item->variant_title,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unitPrice' => ['amount' => $item->unit_price_amount, 'currency' => $item->currency],
                    'lineTotal' => ['amount' => $item->line_total_amount, 'currency' => $item->currency],
                    // A line item snapshots product_title/sku/etc at
                    // order time (never mutates), but the *live* product/
                    // variant record is a genuine GraphQL-only N+1 risk —
                    // an order with N items resolving `product`/`variant`
                    // each becomes N (or 2N) queries without batching.
                    'product' => $item->product_id === null ? null : app(ProductLoader::class)->load($item->product_id),
                    'variant' => $item->product_variant_id === null ? null : app(VariantLoader::class)->load($item->product_variant_id),
                    default => null,
                };
            },
        ]);
    }

    public static function order(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Order',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'number' => Type::nonNull(Type::int()),
                'email' => Type::string(),
                'orderStatus' => Type::nonNull(Type::string()),
                'financialStatus' => Type::nonNull(Type::string()),
                'fulfillmentStatus' => Type::nonNull(Type::string()),
                'total' => $types->get('Money'),
                'items' => Type::listOf($types->get('OrderItem')),
                'shippingAddress' => $types->get('AddressSnapshot'),
                'billingAddress' => $types->get('AddressSnapshot'),
                'customer' => $types->get('Customer'),
                'createdAt' => $types->get('DateTime'),
            ],
            'resolveField' => function (Order $order, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id', 'number', 'email' => $order->{$info->fieldName},
                    'orderStatus' => $order->order_status->value,
                    'financialStatus' => $order->financial_status->value,
                    'fulfillmentStatus' => $order->fulfillment_status->value,
                    'total' => ['amount' => $order->total_amount, 'currency' => $order->currency],
                    'items' => $order->relationLoaded('items') ? $order->items->all() : [],
                    'shippingAddress' => $order->relationLoaded('shippingAddress') ? $order->shippingAddress : null,
                    'billingAddress' => $order->relationLoaded('billingAddress') ? $order->billingAddress : null,
                    // The archetypal DataLoader case (spec section 6):
                    // a merchant's `orders { data { customer { email } } }`
                    // over N orders becomes exactly one batched customer
                    // query instead of N.
                    'customer' => $order->customer_id === null ? null : app(CustomerLoader::class)->load($order->customer_id),
                    'createdAt' => $order->created_at,
                    default => null,
                };
            },
        ]);
    }

    public static function orderConnection(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'OrderConnection',
            'fields' => [
                'data' => Type::listOf($types->get('Order')),
                'pageInfo' => $types->get('PageInfo'),
            ],
        ]);
    }
}
