<?php

namespace App\Domain\Promotions\Enums;

/**
 * Parameter shape per type (validated in StorePromotionRequest /
 * UpdatePromotionRequest), amounts always in minor currency units:
 *
 *  - PercentageOff: {percent: int 1-100, target?: 'order'|'line_items', product_ids?: string[], collection_ids?: string[], category_ids?: string[]}
 *  - FixedAmountOff: {amount: int, target?: 'order'|'line_items', product_ids?: string[], collection_ids?: string[], category_ids?: string[]}
 *  - FreeShipping: {}
 *  - FreeProduct: {product_variant_id: string, quantity: int}     "Buy X Get Y" free item — pair with an OrderQuantity/Product rule for X
 *  - LineItemDiscount: {percent?: int, amount?: int, product_ids?: string[], collection_ids?: string[], category_ids?: string[]}
 *  - OrderDiscount: {percent?: int, amount?: int}                 alias of PercentageOff/FixedAmountOff with target=order, kept distinct per spec section 4
 */
enum PromotionActionType: string
{
    case PercentageOff = 'percentage_off';
    case FixedAmountOff = 'fixed_amount_off';
    case FreeShipping = 'free_shipping';
    case FreeProduct = 'free_product';
    case LineItemDiscount = 'line_item_discount';
    case OrderDiscount = 'order_discount';
}
