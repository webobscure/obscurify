<?php

namespace App\Domain\Checkouts\Application;

use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\Customers\Application\FindOrCreateCustomer;
use App\Domain\Inventory\Application\ReserveInventory;
use App\Domain\Orders\Application\AllocateOrderNumber;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderAddress;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderShippingLine;
use App\Domain\Promotions\Application\BuildPromotionContext;
use App\Domain\Promotions\Exceptions\DiscountCodeInvalidException;
use App\Domain\Promotions\Models\DiscountApplication;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\PromotionUsage;
use App\Domain\Promotions\Support\AppliedDiscount;
use App\Domain\Promotions\Support\PromotionEngine;
use App\Domain\Shipping\Application\RevalidateShippingQuote;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Commerce\Enums\AddressType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single, transactional use case that turns a Cart into an Order.
 *
 * Everything below runs in one DB::transaction() and nothing here makes
 * an external HTTP call (no payment provider exists yet) — the whole
 * operation either fully commits or fully rolls back. Locking order,
 * matching spec section 27:
 *
 *  1. lock Checkout (and its Cart) row
 *  2. (idempotency is the caller's concern — see
 *     StorefrontCheckoutController — this class assumes it already holds
 *     the idempotency claim)
 *  3. revalidate cart, with cart_items locked
 *  4-5. lock + reserve inventory per line (ReserveInventory)
 *  6. find/create Customer
 *  7. allocate order number + create Order
 *  8. create OrderItem snapshots
 *  9. create OrderAddress snapshots
 *  10. link reservations to the Order
 *  11. mark Checkout completed
 *  12. mark Cart converted
 *  13. (idempotent response persistence is the caller's concern)
 *  + write the OrderCreated outbox event in the same transaction
 */
final class CompleteCheckout
{
    public function __construct(
        private readonly RevalidateCart $revalidateCart,
        private readonly ReserveInventory $reserveInventory,
        private readonly FindOrCreateCustomer $findOrCreateCustomer,
        private readonly AllocateOrderNumber $allocateOrderNumber,
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly RevalidateShippingQuote $revalidateShippingQuote,
        private readonly BuildPromotionContext $buildPromotionContext,
        private readonly PromotionEngine $promotionEngine,
        private readonly RecomputeCustomerMetrics $recomputeCustomerMetrics,
    ) {}

    public function handle(Checkout $checkout): Order
    {
        // Deliberately a separate, already-committed transaction from the
        // one below: marking an expired checkout Expired must survive
        // even though this method is about to throw and reject the
        // request. A ValidationException thrown *inside* DB::transaction()
        // rolls the whole closure back before re-throwing — including any
        // writes that closure itself just made — so doing the expiry
        // write and the rejection in the same transaction would silently
        // discard the status update every time.
        $lockedCheckout = $this->lockAndValidateCheckout($checkout);

        return DB::transaction(function () use ($lockedCheckout) {
            $lockedCheckout = Checkout::query()->whereKey($lockedCheckout->id)->lockForUpdate()->firstOrFail();

            if ($lockedCheckout->status !== CheckoutStatus::Open) {
                throw ValidationException::withMessages([
                    'checkout' => 'This checkout is not open.',
                ]);
            }

            $cart = $lockedCheckout->cart()->lockForUpdate()->firstOrFail();

            $lines = $this->revalidateCart->handle($cart, lock: true);

            $shippingAddress = CheckoutAddress::query()
                ->where('checkout_id', $lockedCheckout->id)
                ->where('type', AddressType::Shipping->value)
                ->first();

            if ($shippingAddress === null) {
                throw ValidationException::withMessages([
                    'shipping_address' => 'A shipping address is required.',
                ]);
            }

            $billingAddress = CheckoutAddress::query()
                ->where('checkout_id', $lockedCheckout->id)
                ->where('type', AddressType::Billing->value)
                ->first() ?? $shippingAddress;

            // Shipping selection is optional at completion (spec section
            // 26 asked us to document, not invent, the fulfillment
            // boundary — and making this mandatory would be a Checkout
            // behavior change the milestone brief explicitly says not to
            // make without a concrete issue forcing it). A checkout that
            // never selected a rate completes with shipping_amount 0 and
            // no OrderShippingLine, exactly like before this milestone.
            $shippingQuote = $lockedCheckout->shipping_quote_id !== null
                ? $this->revalidateShippingQuote->handle($lockedCheckout)
                : null;

            $itemsSubtotal = 0;
            foreach ($lines as $line) {
                $itemsSubtotal += $line['variant']->price_amount * $line['item']->quantity;
            }

            $shippingAmount = $shippingQuote === null ? 0 : $shippingQuote->price_amount;

            // Checkout never calculates its own discount (spec section 7)
            // — PromotionEngine is the sole authority, called fresh here
            // regardless of whatever discount_amount is cached on the
            // Checkout row, the same way shipping is revalidated above
            // rather than trusted. The DiscountCode row (if any) is
            // locked first so two concurrent completions racing the same
            // single-use code serialize on it (spec section 13) — the
            // second sees the first's committed usage_count and
            // PromotionEngine naturally excludes the code as no longer
            // usable.
            $discountCode = $lockedCheckout->discount_code_id !== null
                ? DiscountCode::query()->whereKey($lockedCheckout->discount_code_id)->lockForUpdate()->first()
                : null;

            $promotionContext = $this->buildPromotionContext->handle($cart, $lockedCheckout, $shippingAmount, $discountCode);
            $evaluation = $this->promotionEngine->handle($promotionContext);

            if ($discountCode !== null) {
                $codeWasApplied = $evaluation->applied->contains(
                    fn (AppliedDiscount $applied) => $applied->discountCode?->id === $discountCode->id,
                );

                if (! $codeWasApplied) {
                    throw DiscountCodeInvalidException::make('this discount code is no longer valid for this order.');
                }
            }

            $discountAmount = $evaluation->discountAmount;
            $taxAmount = 0;
            $total = $itemsSubtotal + $shippingAmount - $discountAmount + $taxAmount;

            $reservations = new Collection;
            foreach ($lines as $line) {
                $inventoryItem = $line['variant']->inventoryItem;

                if ($inventoryItem !== null) {
                    $reservations = $reservations->merge(
                        $this->reserveInventory->handle($inventoryItem, $line['item']->quantity, $lockedCheckout),
                    );
                }
            }

            $customer = $this->findOrCreateCustomer->handle([
                'email' => $lockedCheckout->email,
                'phone' => $lockedCheckout->phone,
                'first_name' => $shippingAddress->first_name,
                'last_name' => $shippingAddress->last_name,
            ]);

            $number = $this->allocateOrderNumber->handle($lockedCheckout->store_id);

            $order = Order::query()->create([
                'number' => $number,
                'customer_id' => $customer->id,
                'checkout_id' => $lockedCheckout->id,
                'currency' => $lockedCheckout->currency,
                'items_subtotal_amount' => $itemsSubtotal,
                'shipping_amount' => $shippingAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'order_status' => OrderStatus::Open->value,
                'financial_status' => FinancialStatus::Pending->value,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
                'email' => $lockedCheckout->email,
                'phone' => $lockedCheckout->phone,
            ]);

            $orderItemIdsByVariantId = [];
            foreach ($lines as $line) {
                $orderItem = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']->id,
                    'product_title' => $line['product']->title,
                    'variant_title' => $line['variant']->title,
                    'sku' => $line['variant']->sku,
                    'unit_price_amount' => $line['variant']->price_amount,
                    'quantity' => $line['item']->quantity,
                    'line_total_amount' => $line['variant']->price_amount * $line['item']->quantity,
                    'currency' => $line['variant']->currency,
                ]);

                $orderItemIdsByVariantId[$line['variant']->id] = $orderItem->id;
            }

            OrderAddress::query()->create([
                'order_id' => $order->id,
                'type' => AddressType::Shipping->value,
                ...$this->addressSnapshot($shippingAddress),
            ]);

            OrderAddress::query()->create([
                'order_id' => $order->id,
                'type' => AddressType::Billing->value,
                ...$this->addressSnapshot($billingAddress),
            ]);

            // Snapshot, not a live reference (spec section 14) — copied
            // once here so a later ShippingMethod rename/price change/
            // deletion can never change what this order reports, same
            // reasoning as OrderItem's product_title/unit_price_amount.
            if ($shippingQuote !== null) {
                OrderShippingLine::query()->create([
                    'order_id' => $order->id,
                    'provider' => $shippingQuote->provider,
                    'service_code' => $shippingQuote->service_code,
                    'title' => $shippingQuote->name,
                    'price_amount' => $shippingQuote->price_amount,
                    'currency' => $shippingQuote->currency,
                    'estimated_days_min' => $shippingQuote->estimated_days_min,
                    'estimated_days_max' => $shippingQuote->estimated_days_max,
                    'metadata' => $shippingQuote->metadata,
                ]);
            }

            // Order snapshot (spec section 8): one DiscountApplication per
            // applied action, carrying its own promotion_name/code copy so
            // a later rename/delete of the Promotion or DiscountCode can
            // never change what this Order reports — and one PromotionUsage
            // per applied Promotion, the append-only record concurrency and
            // per-customer limits are checked against.
            $appliedByPromotion = $evaluation->applied->groupBy(fn (AppliedDiscount $applied) => $applied->promotion->id);

            foreach ($appliedByPromotion as $promotionApplications) {
                /** @var AppliedDiscount $first */
                $first = $promotionApplications->first();

                foreach ($promotionApplications as $applied) {
                    DiscountApplication::query()->create([
                        'order_id' => $order->id,
                        'promotion_id' => $applied->promotion->id,
                        'discount_code_id' => $applied->discountCode?->id,
                        'order_item_id' => $applied->productVariantId !== null
                            ? ($orderItemIdsByVariantId[$applied->productVariantId] ?? null)
                            : null,
                        'promotion_name' => $applied->promotion->name,
                        'code' => $applied->discountCode?->code,
                        'action_type' => $applied->actionType->value,
                        'target' => $applied->target->value,
                        'amount' => $applied->amount,
                        'currency' => $lockedCheckout->currency,
                    ]);
                }

                PromotionUsage::query()->create([
                    'promotion_id' => $first->promotion->id,
                    'discount_code_id' => $first->discountCode?->id,
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'amount' => (int) $promotionApplications->sum('amount'),
                ]);
            }

            if ($discountCode !== null) {
                $discountCode->increment('usage_count');

                $this->recordOutboxEvent->handle('DiscountCodeRedeemed', 'Order', $order->id, [
                    'order_id' => $order->id,
                    'store_id' => $order->store_id,
                    'discount_code_id' => $discountCode->id,
                    'code' => $discountCode->code,
                    'promotion_id' => $discountCode->promotion_id,
                ]);
            }

            foreach ($reservations as $reservation) {
                $reservation->update(['order_id' => $order->id]);
            }

            $lockedCheckout->update([
                'status' => CheckoutStatus::Completed->value,
                'completed_at' => now(),
                'customer_id' => $customer->id,
            ]);

            $cart->update(['status' => 'converted']);

            $this->recordOutboxEvent->handle('OrderCreated', 'Order', $order->id, [
                'order_id' => $order->id,
                'store_id' => $order->store_id,
                'number' => $order->number,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
            ]);

            // No internal pub/sub exists to "subscribe" to OrderCreated
            // (see docs/adr/024-customer-intelligence.md) — called
            // directly, same as every other cross-domain reaction in
            // this codebase (e.g. Financial Ledger calling
            // RecomputeOrderFinancialStatus).
            $this->recomputeCustomerMetrics->handle($customer->id);

            return $order->load(['items', 'shippingAddress', 'billingAddress', 'customer', 'shippingLine']);
        });
    }

    /**
     * Locks the checkout, and — if it's expired — commits the Expired
     * status transition on its own before this method returns, then
     * throws only once that write is durable. Returns the still-open,
     * not-expired checkout otherwise; the caller re-locks it inside its
     * own transaction, which also re-checks status to defend against a
     * concurrent completion/expiry landing in the gap between the two.
     */
    private function lockAndValidateCheckout(Checkout $checkout): Checkout
    {
        $outcome = DB::transaction(function () use ($checkout) {
            $locked = Checkout::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CheckoutStatus::Open) {
                return ['ok' => false, 'reason' => 'not_open'];
            }

            if ($locked->isExpired()) {
                $locked->update(['status' => CheckoutStatus::Expired->value]);

                return ['ok' => false, 'reason' => 'expired'];
            }

            return ['ok' => true, 'checkout' => $locked];
        });

        if (! $outcome['ok']) {
            throw ValidationException::withMessages([
                'checkout' => $outcome['reason'] === 'expired'
                    ? 'This checkout has expired.'
                    : 'This checkout is not open.',
            ]);
        }

        return $outcome['checkout'];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressSnapshot(CheckoutAddress $address): array
    {
        return [
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'phone' => $address->phone,
            'country_code' => $address->country_code,
            'region' => $address->region,
            'city' => $address->city,
            'postal_code' => $address->postal_code,
            'address_line1' => $address->address_line1,
            'address_line2' => $address->address_line2,
        ];
    }
}
