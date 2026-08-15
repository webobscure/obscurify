<?php

namespace App\Domain\Promotions\Support;

use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use App\Domain\CustomerIntelligence\Support\SegmentMembership;
use App\Domain\Promotions\Enums\PromotionRuleType;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionRule;
use App\Domain\Promotions\Models\PromotionUsage;
use Illuminate\Support\Carbon;

/**
 * Every rule on a Promotion must pass — no OR-groups, no nesting. Callers
 * must eager-load `rules` on the Promotion before calling passes()
 * (see PromotionEngine).
 *
 * The four CustomerGroup/CustomerSegment/CustomerTag/CustomerMetric cases
 * are the one place this class reaches outside Cart/Checkout data
 * captured in PromotionContext — always through SegmentMembership, never
 * a direct query against CustomerIntelligence's own tables (spec section
 * 9: "No direct SQL coupling").
 */
final class RuleEngine
{
    public function __construct(private readonly SegmentMembership $segmentMembership) {}

    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        foreach ($promotion->rules as $rule) {
            if (! $this->evaluate($rule, $context)) {
                return false;
            }
        }

        return true;
    }

    private function evaluate(PromotionRule $rule, PromotionContext $context): bool
    {
        $parameters = $rule->parameters;

        return match ($rule->type) {
            PromotionRuleType::MinSubtotal => $context->itemsSubtotal >= (int) ($parameters['amount'] ?? 0),

            PromotionRuleType::Product => $this->anyLineMatches(
                $context,
                fn (PromotionLine $line) => in_array($line->productId, $parameters['product_ids'] ?? [], true)
                    || in_array($line->productVariantId, $parameters['product_ids'] ?? [], true),
            ),

            PromotionRuleType::Collection => $this->anyLineMatches(
                $context,
                fn (PromotionLine $line) => array_intersect($line->collectionIds, $parameters['collection_ids'] ?? []) !== [],
            ),

            PromotionRuleType::Category => $this->anyLineMatches(
                $context,
                fn (PromotionLine $line) => array_intersect($line->categoryIds, $parameters['category_ids'] ?? []) !== [],
            ),

            PromotionRuleType::Customer => $context->customerId !== null
                && in_array($context->customerId, $parameters['customer_ids'] ?? [], true),

            PromotionRuleType::Country => $context->countryCode !== null
                && in_array(strtoupper($context->countryCode), array_map('strtoupper', $parameters['country_codes'] ?? []), true),

            PromotionRuleType::Currency => in_array(
                strtoupper($context->currency),
                array_map('strtoupper', $parameters['currency_codes'] ?? []),
                true,
            ),

            PromotionRuleType::OrderQuantity => $this->withinRange($context->totalQuantity(), $parameters),

            PromotionRuleType::OrderTotal => $this->withinRange($context->orderTotal(), $parameters),

            PromotionRuleType::DateRange => $this->withinDateRange($context->now, $parameters),

            PromotionRuleType::UsageLimit => $this->withinUsageLimit($rule, $context, $parameters),

            PromotionRuleType::CustomerGroup => $this->segmentMembership->isCustomerIdInAnyGroup(
                $context->customerId,
                $parameters['group_ids'] ?? [],
            ),

            PromotionRuleType::CustomerSegment => $this->segmentMembership->isCustomerIdInAnySegment(
                $context->customerId,
                $parameters['segment_ids'] ?? [],
            ),

            PromotionRuleType::CustomerTag => $this->segmentMembership->customerIdHasAnyTag(
                $context->customerId,
                $parameters['tag_slugs'] ?? [],
            ),

            PromotionRuleType::CustomerMetric => $this->segmentMembership->evaluateCustomerMetricCondition(
                $context->customerId,
                SegmentRuleField::from($parameters['field']),
                SegmentRuleOperator::from($parameters['operator']),
                $parameters['value'] ?? null,
            ),
        };
    }

    private function anyLineMatches(PromotionContext $context, callable $predicate): bool
    {
        foreach ($context->lines as $line) {
            if ($predicate($line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function withinRange(int $value, array $parameters): bool
    {
        if (isset($parameters['min']) && $value < (int) $parameters['min']) {
            return false;
        }

        if (isset($parameters['max']) && $value > (int) $parameters['max']) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function withinDateRange(Carbon $now, array $parameters): bool
    {
        if (isset($parameters['starts_at']) && $now->lt(Carbon::parse($parameters['starts_at']))) {
            return false;
        }

        if (isset($parameters['ends_at']) && $now->gt(Carbon::parse($parameters['ends_at']))) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function withinUsageLimit(PromotionRule $rule, PromotionContext $context, array $parameters): bool
    {
        $maxUses = $parameters['max_uses'] ?? null;
        $maxUsesPerCustomer = $parameters['max_uses_per_customer'] ?? null;

        if ($maxUses !== null) {
            $total = PromotionUsage::query()->where('promotion_id', $rule->promotion_id)->count();
            if ($total >= (int) $maxUses) {
                return false;
            }
        }

        if ($maxUsesPerCustomer !== null && $context->customerId !== null) {
            $customerUses = PromotionUsage::query()
                ->where('promotion_id', $rule->promotion_id)
                ->where('customer_id', $context->customerId)
                ->count();

            if ($customerUses >= (int) $maxUsesPerCustomer) {
                return false;
            }
        }

        return true;
    }
}
