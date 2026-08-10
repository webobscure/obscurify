<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Promotions\Models\DiscountCode;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveDiscountCode
{
    public function __construct(
        private readonly RecalculateCheckoutTotals $recalculateCheckoutTotals,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Checkout $checkout): Checkout
    {
        return DB::transaction(function () use ($checkout) {
            $locked = Checkout::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CheckoutStatus::Open) {
                throw ValidationException::withMessages(['checkout' => 'This checkout is not open.']);
            }

            $previousDiscountCodeId = $locked->discount_code_id;

            $locked->update(['discount_code_id' => null]);
            $this->recalculateCheckoutTotals->handle($locked);

            if ($previousDiscountCodeId !== null) {
                $discountCode = DiscountCode::query()->find($previousDiscountCodeId);

                $this->recordOutboxEvent->handle('PromotionRemoved', 'Checkout', $locked->id, [
                    'checkout_id' => $locked->id,
                    'store_id' => $locked->store_id,
                    'discount_code_id' => $previousDiscountCodeId,
                    'promotion_id' => $discountCode?->promotion_id,
                ]);
            }

            return $locked->fresh(['addresses']);
        });
    }
}
