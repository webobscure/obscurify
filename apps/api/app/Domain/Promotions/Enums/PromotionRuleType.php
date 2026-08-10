<?php

namespace App\Domain\Promotions\Enums;

/**
 * All rules on a Promotion are AND-ed by RuleEngine — no boolean grouping.
 * Parameter shape per type (validated in StorePromotionRequest /
 * UpdatePromotionRequest), amounts always in minor currency units:
 *
 *  - MinSubtotal: {amount: int}                         subtotal >= amount
 *  - Product: {product_ids: string[]}                   cart contains one of these products
 *  - Collection: {collection_ids: string[]}              cart contains a product in one of these collections
 *  - Category: {category_ids: string[]}                  cart contains a product in one of these categories
 *  - Customer: {customer_ids: string[]}                  checkout customer/email matches
 *  - Country: {country_codes: string[]}                  shipping address country matches
 *  - Currency: {currency_codes: string[]}                checkout currency matches
 *  - OrderQuantity: {min?: int, max?: int}                total cart quantity within range
 *  - OrderTotal: {min?: int, max?: int}                   subtotal + shipping within range
 *  - DateRange: {starts_at?: string, ends_at?: string}    now() within range (in addition to Promotion.starts_at/ends_at)
 *  - UsageLimit: {max_uses?: int, max_uses_per_customer?: int}   caps redemptions via PromotionUsage, independent of any DiscountCode's own limits
 */
enum PromotionRuleType: string
{
    case MinSubtotal = 'min_subtotal';
    case Product = 'product';
    case Collection = 'collection';
    case Category = 'category';
    case Customer = 'customer';
    case Country = 'country';
    case Currency = 'currency';
    case OrderQuantity = 'order_quantity';
    case OrderTotal = 'order_total';
    case DateRange = 'date_range';
    case UsageLimit = 'usage_limit';
}
