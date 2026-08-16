<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Mirrors StorefrontCartController exactly. `lineTotal` is computed
 * here (price_amount * quantity) rather than stored — the same
 * derived-not-persisted value REST's cart resource computes on read.
 */
final class CartTypes
{
    public static function cartItem(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'CartItem',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'variant' => $types->get('ProductVariant'),
                'quantity' => Type::nonNull(Type::int()),
                'lineTotal' => $types->get('Money'),
            ],
            'resolveField' => function (CartItem $item, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id' => $item->id,
                    'variant' => $item->relationLoaded('variant') ? $item->variant : null,
                    'quantity' => $item->quantity,
                    'lineTotal' => $item->relationLoaded('variant') && $item->variant !== null
                        ? ['amount' => $item->variant->price_amount * $item->quantity, 'currency' => $item->variant->currency]
                        : null,
                    default => null,
                };
            },
        ]);
    }

    public static function cart(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Cart',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'token' => Type::nonNull(Type::string()),
                'currency' => Type::nonNull(Type::string()),
                'items' => Type::listOf($types->get('CartItem')),
                'itemCount' => Type::nonNull(Type::int()),
                'subtotal' => $types->get('Money'),
            ],
            'resolveField' => function (Cart $cart, array $args, mixed $context, ResolveInfo $info) {
                $items = $cart->relationLoaded('items') ? $cart->items : collect();

                return match ($info->fieldName) {
                    'id' => $cart->id,
                    'token' => $cart->token,
                    'currency' => $cart->currency,
                    'items' => $items->all(),
                    'itemCount' => (int) $items->sum('quantity'),
                    'subtotal' => ['amount' => (int) $items->sum(fn (CartItem $i) => $i->relationLoaded('variant') && $i->variant !== null ? $i->variant->price_amount * $i->quantity : 0), 'currency' => $cart->currency],
                    default => null,
                };
            },
        ]);
    }
}
