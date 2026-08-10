<?php

namespace App\Domain\Financial\Application;

use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Exceptions\RefundOverReceiptException;
use App\Domain\Financial\Models\FinancialEvent;
use App\Domain\Financial\Models\Refund;
use App\Domain\Financial\Models\RefundItem;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\PaymentProviderRegistry;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnItem;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a refund attempt against an Order's most-recent refundable
 * Payment (spec sections 2/8). Supports every combination spec section 9
 * asks for in one call — item lines (tied to already-inspected/
 * dispositioned ReturnItems, spec section 4), a shipping-only portion,
 * and a free-standing manual adjustment — since none of them are
 * mutually exclusive in real life (a merchant might refund one damaged
 * item AND the shipping cost in a single refund). `providerCode`
 * null means a manual refund (spec section 11): no provider call, no
 * webhook to wait for, completed synchronously in this same transaction.
 */
final class RequestRefund
{
    public function __construct(
        private readonly AllocateRefundNumber $allocateRefundNumber,
        private readonly PaymentProviderRegistry $registry,
        private readonly ApplyRefundCompletion $applyRefundCompletion,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  list<array{return_item_id: string, quantity: int, amount: int}>  $itemLines
     */
    public function handle(
        Order $order,
        array $itemLines,
        int $shippingAmount,
        int $adjustmentAmount,
        ?string $reason,
        ?string $providerCode,
    ): Refund {
        return DB::transaction(function () use ($order, $itemLines, $shippingAmount, $adjustmentAmount, $reason, $providerCode) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Most-recent refundable Payment — this codebase only ever
            // creates one live Payment per Order in practice (CreatePayment
            // blocks a second attempt while one is processing/succeeded),
            // so "most recent refundable" and "the" Payment coincide; the
            // ordering exists to be deterministic if that assumption ever
            // changes rather than to encode a real business rule.
            $lockedPayment = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value])
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($lockedPayment === null) {
                throw ValidationException::withMessages([
                    'order' => 'This order has no refundable payment.',
                ]);
            }

            $validatedItems = $this->validateItemLines($lockedOrder, $itemLines);

            if ($shippingAmount > 0) {
                $this->validateShippingAmount($lockedOrder, $shippingAmount);
            }

            $amount = array_sum(array_column($itemLines, 'amount')) + $shippingAmount + $adjustmentAmount;

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund must cover a positive amount.',
                ]);
            }

            $alreadyCommitted = (int) Refund::query()
                ->where('payment_id', $lockedPayment->id)
                ->whereNotIn('status', [RefundStatus::Failed->value, RefundStatus::Cancelled->value])
                ->sum('amount');

            if ($amount > $lockedPayment->captured_amount - $alreadyCommitted) {
                throw RefundOverReceiptException::exceedsAvailableBalance();
            }

            $number = $this->allocateRefundNumber->handle($lockedOrder->store_id);

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_id' => $lockedPayment->id,
                'number' => $number,
                'status' => RefundStatus::Requested->value,
                'currency' => $lockedPayment->currency,
                'amount' => $amount,
                'shipping_amount' => $shippingAmount,
                'adjustment_amount' => $adjustmentAmount,
                'reason' => $reason,
                'provider' => $providerCode,
                'requested_at' => now(),
            ]);

            $items = new Collection;
            foreach ($validatedItems as $entry) {
                $items->push(RefundItem::query()->create([
                    'refund_id' => $refund->id,
                    'return_item_id' => $entry['return_item']->id,
                    'quantity' => $entry['line']['quantity'],
                    'amount' => $entry['line']['amount'],
                ]));
            }

            FinancialEvent::query()->create([
                'order_id' => $lockedOrder->id,
                'type' => 'refund_requested',
                'description' => "Refund #{$refund->number} requested.",
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('RefundRequested', 'Refund', $refund->id, [
                'refund_id' => $refund->id,
                'payment_id' => $lockedPayment->id,
                'order_id' => $lockedOrder->id,
                'store_id' => $refund->store_id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
            ]);

            if ($providerCode === null) {
                return $this->applyRefundCompletion->handle($refund, $lockedPayment, $lockedOrder);
            }

            $provider = $this->registry->resolve($providerCode);
            $externalRefundId = $provider->createRefund($lockedPayment, $refund->amount);

            $refund->update([
                'provider_reference' => $externalRefundId,
                'status' => RefundStatus::Processing->value,
            ]);

            FinancialEvent::query()->create([
                'order_id' => $lockedOrder->id,
                'type' => 'refund_processing',
                'description' => "Refund #{$refund->number} submitted to provider.",
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('RefundProcessing', 'Refund', $refund->id, [
                'refund_id' => $refund->id,
                'order_id' => $lockedOrder->id,
                'store_id' => $refund->store_id,
            ]);

            return $refund->fresh(['items']);
        });
    }

    /**
     * @param  list<array{return_item_id: string, quantity: int, amount: int}>  $itemLines
     * @return list<array{return_item: ReturnItem, line: array{return_item_id: string, quantity: int, amount: int}}>
     */
    private function validateItemLines(Order $order, array $itemLines): array
    {
        $validated = [];

        foreach ($itemLines as $line) {
            // Locking every referenced ReturnItem row is what makes two
            // concurrent refund requests for the same ReturnItem safe —
            // mirrors RequestReturn's own OrderItem locking one layer up.
            $returnItem = ReturnItem::query()
                ->whereKey($line['return_item_id'])
                ->whereHas('returnRequest', fn ($q) => $q->where('order_id', $order->id))
                ->with('returnRequest')
                ->lockForUpdate()
                ->first();

            if ($returnItem === null) {
                throw ValidationException::withMessages([
                    'items' => "Return item \"{$line['return_item_id']}\" does not belong to this order.",
                ]);
            }

            if ($returnItem->returnRequest->status !== ReturnStatus::Completed) {
                throw ValidationException::withMessages([
                    'items' => "Return item \"{$returnItem->id}\" has not completed inspection/disposition yet.",
                ]);
            }

            $alreadyRefunded = (int) RefundItem::query()
                ->where('return_item_id', $returnItem->id)
                ->whereHas('refund', fn ($q) => $q->whereNotIn('status', [RefundStatus::Failed->value, RefundStatus::Cancelled->value]))
                ->sum('quantity');

            if ($line['quantity'] > $returnItem->quantity - $alreadyRefunded) {
                throw RefundOverReceiptException::exceedsReturnedQuantity($returnItem->id);
            }

            $validated[] = ['return_item' => $returnItem, 'line' => $line];
        }

        return $validated;
    }

    private function validateShippingAmount(Order $order, int $shippingAmount): void
    {
        $alreadyRefundedShipping = (int) Refund::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', [RefundStatus::Failed->value, RefundStatus::Cancelled->value])
            ->sum('shipping_amount');

        if ($shippingAmount > $order->shipping_amount - $alreadyRefundedShipping) {
            throw ValidationException::withMessages([
                'shipping_amount' => 'Shipping refund exceeds what was actually charged for shipping.',
            ]);
        }
    }
}
