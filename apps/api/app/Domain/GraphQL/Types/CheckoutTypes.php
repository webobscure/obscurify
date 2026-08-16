<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Shared\Commerce\Enums\AddressType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Mirrors CheckoutResource exactly. `selectedShippingRate` stays JSON
 * (see StorefrontShippingLineResource for the REST equivalent's own
 * heterogeneous shape) rather than a fully modeled type — a scope
 * simplification matching SearchResult.facets/AnalyticsReport.result
 * (see docs/adr/029-graphql-platform.md).
 */
final class CheckoutTypes
{
    public static function checkout(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Checkout',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'email' => Type::string(),
                'phone' => Type::string(),
                'currency' => Type::nonNull(Type::string()),
                'itemsSubtotal' => $types->get('Money'),
                'shippingAmount' => $types->get('Money'),
                'discountAmount' => $types->get('Money'),
                'discountCode' => Type::string(),
                'taxAmount' => $types->get('Money'),
                'total' => $types->get('Money'),
                'status' => Type::nonNull(Type::string()),
                'shippingAddress' => $types->get('AddressSnapshot'),
                'billingAddress' => $types->get('AddressSnapshot'),
                'selectedShippingRate' => $types->get('JSON'),
                'expiresAt' => $types->get('DateTime'),
            ],
            'resolveField' => function (Checkout $checkout, array $args, mixed $context, ResolveInfo $info) {
                $shipping = $checkout->relationLoaded('addresses') ? $checkout->addresses->firstWhere('type', AddressType::Shipping) : null;
                $billing = $checkout->relationLoaded('addresses') ? $checkout->addresses->firstWhere('type', AddressType::Billing) : null;

                return match ($info->fieldName) {
                    'id', 'email', 'phone', 'currency' => $checkout->{$info->fieldName},
                    'itemsSubtotal' => ['amount' => $checkout->items_subtotal_amount, 'currency' => $checkout->currency],
                    'shippingAmount' => ['amount' => $checkout->shipping_amount, 'currency' => $checkout->currency],
                    'discountAmount' => ['amount' => $checkout->discount_amount, 'currency' => $checkout->currency],
                    'discountCode' => $checkout->relationLoaded('discountCode') ? $checkout->discountCode?->code : null,
                    'taxAmount' => ['amount' => $checkout->tax_amount, 'currency' => $checkout->currency],
                    'total' => ['amount' => $checkout->total_amount, 'currency' => $checkout->currency],
                    'status' => $checkout->status->value,
                    'shippingAddress' => $shipping,
                    'billingAddress' => $billing,
                    'selectedShippingRate' => $checkout->relationLoaded('shippingQuote') && $checkout->shippingQuote !== null ? json_decode(json_encode($checkout->shippingQuote), true) : null,
                    'expiresAt' => $checkout->expires_at,
                    default => null,
                };
            },
        ]);
    }
}
