<?php

namespace App\Domain\Promotions\Support;

use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Enums\PromotionStackingMode;
use App\Domain\Promotions\Enums\PromotionStatus;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionUsage;
use Illuminate\Support\Collection;

/**
 * The platform's single source of discount math (spec section 7):
 * Checkout, Draft Orders, Admin, and any future API all call this instead
 * of computing a discount themselves. Input: Cart, Customer, Shipping
 * (captured in PromotionContext). Output: AppliedDiscounts, FinalTotals
 * (PromotionEvaluationResult). Never persists anything — mirrors
 * CalculateShippingRates.
 *
 * Conflict resolution (spec section 5): an eligible 'exclusive' promotion
 * always wins alone — if several are eligible at once, the one with the
 * lowest `priority` number is kept (ties broken by whichever computes the
 * larger discount). With no exclusive eligible, every eligible
 * 'stackable' promotion applies, evaluated in ascending priority order.
 */
final class PromotionEngine
{
    public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly ActionEngine $actionEngine,
    ) {}

    public function handle(PromotionContext $context): PromotionEvaluationResult
    {
        $candidates = Promotion::query()
            ->where('status', PromotionStatus::Active->value)
            ->with(['rules', 'actions'])
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->isWithinDateRange($context->now));

        $eligible = $candidates->filter(fn (Promotion $promotion) => $this->isEligible($promotion, $context));

        if ($eligible->isEmpty()) {
            return new PromotionEvaluationResult(new Collection, 0);
        }

        $selected = $this->resolveStacking($eligible, $context);

        $applied = new Collection;

        foreach ($selected as $promotion) {
            $discountCode = $this->discountCodeFor($promotion, $context);
            $applied = $applied->merge($this->actionEngine->apply($promotion, $context, $discountCode));
        }

        $cap = $context->itemsSubtotal + $context->shippingAmount;
        $discountAmount = min((int) $applied->sum('amount'), $cap);

        return new PromotionEvaluationResult($applied, $discountAmount);
    }

    private function isEligible(Promotion $promotion, PromotionContext $context): bool
    {
        if ($promotion->trigger_type === PromotionTriggerType::Code) {
            $code = $context->appliedDiscountCode;

            if ($code === null || $code->promotion_id !== $promotion->id || ! $this->discountCodeUsable($code, $context)) {
                return false;
            }
        }

        return $this->ruleEngine->passes($promotion, $context);
    }

    private function discountCodeUsable(DiscountCode $code, PromotionContext $context): bool
    {
        if ($code->status !== DiscountCodeStatus::Active || $code->isExpired() || ! $code->hasUsesRemaining()) {
            return false;
        }

        if ($code->per_customer_limit !== null && $context->customerId !== null) {
            $used = PromotionUsage::query()
                ->where('discount_code_id', $code->id)
                ->where('customer_id', $context->customerId)
                ->count();

            if ($used >= $code->per_customer_limit) {
                return false;
            }
        }

        return true;
    }

    private function discountCodeFor(Promotion $promotion, PromotionContext $context): ?DiscountCode
    {
        return $promotion->trigger_type === PromotionTriggerType::Code ? $context->appliedDiscountCode : null;
    }

    /**
     * @param  Collection<int, Promotion>  $eligible
     * @return Collection<int, Promotion>
     */
    private function resolveStacking(Collection $eligible, PromotionContext $context): Collection
    {
        $exclusives = $eligible->filter(fn (Promotion $promotion) => $promotion->stacking_mode === PromotionStackingMode::Exclusive);

        if ($exclusives->isEmpty()) {
            return $eligible->sortBy(fn (Promotion $promotion) => $promotion->priority)->values();
        }

        $best = $exclusives
            ->map(fn (Promotion $promotion) => [
                'promotion' => $promotion,
                'amount' => (int) $this->actionEngine->apply($promotion, $context, $this->discountCodeFor($promotion, $context))->sum('amount'),
            ])
            ->sort(fn (array $a, array $b) => $a['promotion']->priority <=> $b['promotion']->priority ?: $b['amount'] <=> $a['amount'])
            ->first();

        return new Collection([$best['promotion']]);
    }
}
