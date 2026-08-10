<?php

namespace App\Domain\Promotions\Support;

use App\Domain\Promotions\Enums\DiscountApplicationTarget;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use Illuminate\Support\Collection;

/**
 * Computes each of a Promotion's actions against a PromotionContext.
 * Percentage/fixed amounts are always calculated against the *original*
 * context figures (subtotal / line totals), never against a
 * running-already-discounted balance — deliberately, so stacked
 * promotions don't compound off each other's output in a way that's
 * order-of-application-sensitive (see docs/architecture/promotions.md).
 * Callers must eager-load `actions` on the Promotion before calling
 * apply() (see PromotionEngine).
 */
final class ActionEngine
{
    /**
     * @return Collection<int, AppliedDiscount>
     */
    public function apply(Promotion $promotion, PromotionContext $context, ?DiscountCode $discountCode): Collection
    {
        $results = new Collection;

        foreach ($promotion->actions as $action) {
            $results = $results->merge($this->applyOne($promotion, $action, $context, $discountCode));
        }

        return $results;
    }

    /**
     * @return Collection<int, AppliedDiscount>
     */
    private function applyOne(Promotion $promotion, PromotionAction $action, PromotionContext $context, ?DiscountCode $discountCode): Collection
    {
        return match ($action->type) {
            PromotionActionType::FreeShipping => $this->freeShipping($promotion, $action, $context, $discountCode),
            PromotionActionType::OrderDiscount => $this->orderDiscount($promotion, $action, $context, $discountCode),
            PromotionActionType::PercentageOff, PromotionActionType::FixedAmountOff, PromotionActionType::LineItemDiscount => $this->targetedDiscount($promotion, $action, $context, $discountCode),
            PromotionActionType::FreeProduct => $this->freeProduct($promotion, $action, $context, $discountCode),
        };
    }

    /**
     * @return Collection<int, AppliedDiscount>
     */
    private function freeShipping(Promotion $promotion, PromotionAction $action, PromotionContext $context, ?DiscountCode $discountCode): Collection
    {
        if ($context->shippingAmount <= 0) {
            return new Collection;
        }

        return new Collection([
            new AppliedDiscount($promotion, $discountCode, $action->type, DiscountApplicationTarget::Shipping, $context->shippingAmount),
        ]);
    }

    /**
     * @return Collection<int, AppliedDiscount>
     */
    private function orderDiscount(Promotion $promotion, PromotionAction $action, PromotionContext $context, ?DiscountCode $discountCode): Collection
    {
        $amount = $this->calculateAmount($action->parameters, $context->itemsSubtotal);

        if ($amount <= 0) {
            return new Collection;
        }

        return new Collection([
            new AppliedDiscount($promotion, $discountCode, $action->type, DiscountApplicationTarget::Order, $amount),
        ]);
    }

    /**
     * Percentage/fixed/line-item actions with an optional product/
     * collection/category selector: no selector and no explicit target
     * means "whole order"; a selector (or LineItemDiscount, whose whole
     * purpose is targeting) means "matching cart lines only".
     *
     * @return Collection<int, AppliedDiscount>
     */
    private function targetedDiscount(Promotion $promotion, PromotionAction $action, PromotionContext $context, ?DiscountCode $discountCode): Collection
    {
        $parameters = $action->parameters;
        $hasSelector = ! empty($parameters['product_ids']) || ! empty($parameters['collection_ids']) || ! empty($parameters['category_ids']);
        $target = $parameters['target'] ?? ($action->type === PromotionActionType::LineItemDiscount || $hasSelector ? 'line_items' : 'order');

        if ($target === 'order') {
            return $this->orderDiscount($promotion, $action, $context, $discountCode);
        }

        $results = new Collection;

        foreach ($context->lines as $line) {
            if (! $this->lineMatchesSelector($line, $parameters)) {
                continue;
            }

            $amount = min($this->calculateAmount($parameters, $line->lineTotal()), $line->lineTotal());

            if ($amount <= 0) {
                continue;
            }

            $results->push(new AppliedDiscount(
                $promotion,
                $discountCode,
                $action->type,
                DiscountApplicationTarget::LineItem,
                $amount,
                $line->productVariantId,
            ));
        }

        return $results;
    }

    /**
     * "Buy X Get Y" free item — pair with an OrderQuantity/Product rule
     * for the "Buy X" half; this is the "Get Y" half.
     *
     * @return Collection<int, AppliedDiscount>
     */
    private function freeProduct(Promotion $promotion, PromotionAction $action, PromotionContext $context, ?DiscountCode $discountCode): Collection
    {
        $variantId = $action->parameters['product_variant_id'] ?? null;
        $wantQuantity = (int) ($action->parameters['quantity'] ?? 1);

        if ($variantId === null) {
            return new Collection;
        }

        $line = collect($context->lines)->first(fn (PromotionLine $line) => $line->productVariantId === $variantId);

        if ($line === null) {
            return new Collection;
        }

        $freeQuantity = min($wantQuantity, $line->quantity);
        $amount = $freeQuantity * $line->unitPrice;

        if ($amount <= 0) {
            return new Collection;
        }

        return new Collection([
            new AppliedDiscount($promotion, $discountCode, $action->type, DiscountApplicationTarget::LineItem, $amount, $variantId),
        ]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function lineMatchesSelector(PromotionLine $line, array $parameters): bool
    {
        $productIds = $parameters['product_ids'] ?? [];
        $collectionIds = $parameters['collection_ids'] ?? [];
        $categoryIds = $parameters['category_ids'] ?? [];

        if ($productIds === [] && $collectionIds === [] && $categoryIds === []) {
            return true;
        }

        if (in_array($line->productId, $productIds, true) || in_array($line->productVariantId, $productIds, true)) {
            return true;
        }

        if (array_intersect($line->collectionIds, $collectionIds) !== []) {
            return true;
        }

        return array_intersect($line->categoryIds, $categoryIds) !== [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function calculateAmount(array $parameters, int $base): int
    {
        if (isset($parameters['percent'])) {
            return (int) floor($base * ((int) $parameters['percent']) / 100);
        }

        if (isset($parameters['amount'])) {
            return min((int) $parameters['amount'], $base);
        }

        return 0;
    }
}
