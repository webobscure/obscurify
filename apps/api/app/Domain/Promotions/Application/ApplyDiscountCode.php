<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Exceptions\DiscountCodeInvalidException;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Support\AppliedDiscount;
use App\Domain\Promotions\Support\PromotionEngine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validates a code is usable *and* that this exact cart actually earns a
 * discount from it before persisting anything (spec section 10) — a code
 * that's active/unexpired/under its usage limit but whose Promotion rules
 * the cart doesn't meet (e.g. minimum subtotal) is rejected the same way
 * an unknown code is, rather than silently "applying" for $0.
 */
final class ApplyDiscountCode
{
    public function __construct(
        private readonly BuildPromotionContext $buildPromotionContext,
        private readonly PromotionEngine $promotionEngine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Checkout $checkout, string $code): Checkout
    {
        return DB::transaction(function () use ($checkout, $code) {
            $locked = Checkout::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CheckoutStatus::Open) {
                throw ValidationException::withMessages(['checkout' => 'This checkout is not open.']);
            }

            $discountCode = DiscountCode::findByCode($code);
            $this->assertCodeUsable($discountCode);

            $cart = $locked->cart()->with('items.variant')->firstOrFail();
            $context = $this->buildPromotionContext->handle($cart, $locked, $locked->shipping_amount, $discountCode);
            $result = $this->promotionEngine->handle($context);

            $codeApplied = $result->applied->contains(
                fn (AppliedDiscount $applied) => $applied->discountCode?->id === $discountCode->id,
            );

            if (! $codeApplied) {
                throw DiscountCodeInvalidException::make("this order doesn't meet this code's requirements.");
            }

            $locked->update([
                'discount_code_id' => $discountCode->id,
                'items_subtotal_amount' => $context->itemsSubtotal,
                'discount_amount' => $result->discountAmount,
                'total_amount' => $context->itemsSubtotal + $locked->shipping_amount - $result->discountAmount + $locked->tax_amount,
            ]);

            $this->recordOutboxEvent->handle('PromotionApplied', 'Checkout', $locked->id, [
                'checkout_id' => $locked->id,
                'store_id' => $locked->store_id,
                'promotion_id' => $discountCode->promotion_id,
                'discount_code_id' => $discountCode->id,
                'code' => $discountCode->code,
                'discount_amount' => $result->discountAmount,
            ]);

            return $locked->fresh(['addresses']);
        });
    }

    private function assertCodeUsable(?DiscountCode $discountCode): void
    {
        if ($discountCode === null || $discountCode->status !== DiscountCodeStatus::Active) {
            throw DiscountCodeInvalidException::make('this discount code is not valid.');
        }

        if ($discountCode->isExpired()) {
            throw DiscountCodeInvalidException::make('this discount code has expired.');
        }

        if (! $discountCode->hasUsesRemaining()) {
            throw DiscountCodeInvalidException::make('this discount code has reached its usage limit.');
        }
    }
}
