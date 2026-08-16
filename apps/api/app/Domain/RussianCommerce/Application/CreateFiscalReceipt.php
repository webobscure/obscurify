<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Payments\Models\Payment;
use App\Domain\RussianCommerce\Enums\FiscalReceiptItemPaymentMethod;
use App\Domain\RussianCommerce\Enums\FiscalReceiptItemPaymentSubject;
use App\Domain\RussianCommerce\Enums\FiscalReceiptOperation;
use App\Domain\RussianCommerce\Enums\FiscalReceiptStatus;
use App\Domain\RussianCommerce\Enums\VatRate;
use App\Domain\RussianCommerce\Exceptions\FiscalizationNotConfiguredException;
use App\Domain\RussianCommerce\Models\FiscalizationProvider;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Domain\RussianCommerce\Models\FiscalReceiptItem;
use App\Domain\RussianCommerce\Models\OrderFiscalSnapshot;
use App\Domain\RussianCommerce\Support\FiscalizationProviderRegistry;
use App\Domain\RussianCommerce\Support\ResolveProductFiscalProfile;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Spec section 14/15 — builds a FiscalReceipt + FiscalReceiptItem rows
 * from a completed Order and submits it to the store's active
 * FiscalizationProvider. Never marks the receipt Fiscalized itself
 * (that's only ever ProcessFiscalizationCallback's job) — this class
 * only ever leaves a receipt in Pending (should be unreachable once
 * this method returns), Processing (provider accepted it, awaiting
 * async confirmation), or Failed (provider rejected the submission
 * outright, e.g. a malformed line item).
 *
 * Uses the Order's own OrderFiscalSnapshot for seller_inn/seller_kpp —
 * never the live StoreLegalProfile — so a receipt for an old order
 * still reports the seller identity that was true when the order was
 * placed, even if the merchant's legal details changed since (spec
 * section 11).
 */
final class CreateFiscalReceipt
{
    public function __construct(
        private readonly ResolveProductFiscalProfile $resolveProductFiscalProfile,
        private readonly FiscalizationProviderRegistry $registry,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * Returns null when the store has no OrderFiscalSnapshot for this
     * order, or that snapshot doesn't require a receipt — Russian
     * Commerce stays an opt-in bolt-on (see BuildOrderFiscalSnapshot).
     *
     * @throws FiscalizationNotConfiguredException
     */
    public function handle(Order $order, ?Payment $payment = null): ?FiscalReceipt
    {
        $snapshot = OrderFiscalSnapshot::query()->where('order_id', $order->id)->first();

        if ($snapshot === null || ! $snapshot->receipt_required) {
            return null;
        }

        $settings = FiscalizationSettings::query()->where('store_id', $order->store_id)->first();
        $providerRow = $settings?->active_provider_id !== null
            ? FiscalizationProvider::query()->find($settings->active_provider_id)
            : null;

        if ($providerRow === null || ! $providerRow->is_enabled) {
            throw FiscalizationNotConfiguredException::forStore($order->store_id);
        }

        $provider = $this->registry->resolve($providerRow->code);

        return DB::transaction(function () use ($order, $payment, $snapshot, $provider, $providerRow) {
            $receipt = FiscalReceipt::query()->create([
                'order_id' => $order->id,
                'payment_id' => $payment?->id,
                'operation' => FiscalReceiptOperation::Sale->value,
                'status' => FiscalReceiptStatus::Pending->value,
                'provider' => $providerRow->code,
                'seller_inn' => $snapshot->seller_inn,
                'seller_kpp' => $snapshot->seller_kpp,
                'customer_email' => $order->email,
                'customer_phone' => $order->phone,
                'currency' => $order->currency,
                'total_amount' => $order->total_amount,
            ]);

            foreach ($order->items as $item) {
                $this->createReceiptItem($receipt, $item);
            }

            // Shipping is its own taxable line (spec section 6 models
            // every line as commodity/service/work/...) rather than
            // silently folded into the last product line, so
            // item amounts always sum to total_amount.
            if ($order->shipping_amount > 0) {
                FiscalReceiptItem::query()->create([
                    'fiscal_receipt_id' => $receipt->id,
                    'name' => 'Shipping',
                    'quantity' => 1,
                    'price_amount' => $order->shipping_amount,
                    'amount' => $order->shipping_amount,
                    'vat_rate' => VatRate::None->value,
                    'payment_method' => FiscalReceiptItemPaymentMethod::FullPayment->value,
                    'payment_subject' => FiscalReceiptItemPaymentSubject::Service->value,
                ]);
            }

            try {
                $submission = $provider->submitReceipt($receipt->fresh(['items']));

                $receipt->update([
                    'status' => FiscalReceiptStatus::Processing->value,
                    'external_receipt_id' => $submission->externalReceiptId,
                    'attempt_count' => $receipt->attempt_count + 1,
                ]);

                $this->recordOutboxEvent->handle('FiscalReceiptCreated', 'FiscalReceipt', $receipt->id, [
                    'receipt_id' => $receipt->id,
                    'store_id' => $receipt->store_id,
                    'order_id' => $order->id,
                    'provider' => $receipt->provider,
                    'external_receipt_id' => $receipt->external_receipt_id,
                ]);
            } catch (Throwable $exception) {
                // Submission-time rejection (spec section 15) — the Order/
                // Payment are never touched here; only this receipt's own
                // status records the failure.
                $receipt->update([
                    'status' => FiscalReceiptStatus::Failed->value,
                    'error_message' => $exception->getMessage(),
                    'attempt_count' => $receipt->attempt_count + 1,
                ]);

                $this->recordOutboxEvent->handle('FiscalReceiptFailed', 'FiscalReceipt', $receipt->id, [
                    'receipt_id' => $receipt->id,
                    'store_id' => $receipt->store_id,
                    'order_id' => $order->id,
                    'error_message' => $exception->getMessage(),
                ]);
            }

            return $receipt->fresh(['items']);
        });
    }

    private function createReceiptItem(FiscalReceipt $receipt, OrderItem $item): void
    {
        $profile = $this->resolveFiscalProfile($item);

        FiscalReceiptItem::query()->create([
            'fiscal_receipt_id' => $receipt->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'name' => $item->variant_title !== null
                ? "{$item->product_title} — {$item->variant_title}"
                : $item->product_title,
            'quantity' => $item->quantity,
            'price_amount' => $item->unit_price_amount,
            'amount' => $item->line_total_amount,
            'vat_rate' => $profile['vat_rate']->value,
            // Foundation-only simplification (spec section 6): every line
            // of a fully-paid order reports FullPayment; split/deposit
            // flows that would use Prepayment/Advance/Credit aren't
            // implemented by any checkout path yet.
            'payment_method' => FiscalReceiptItemPaymentMethod::FullPayment->value,
            'payment_subject' => $profile['payment_subject']->value,
            'unit_of_measure' => $profile['unit_of_measure'],
        ]);
    }

    /**
     * @return array{vat_rate: VatRate, payment_subject: FiscalReceiptItemPaymentSubject, unit_of_measure: string|null}
     */
    private function resolveFiscalProfile(OrderItem $item): array
    {
        if ($item->product_variant_id !== null) {
            $variant = ProductVariant::query()->find($item->product_variant_id);

            if ($variant !== null) {
                return $this->resolveProductFiscalProfile->handle($variant);
            }
        }

        // The variant was deleted since the order was placed
        // (product_variant_id is a nullOnDelete reference, spec section
        // 6) — falls back to the same plain-commodity default
        // ResolveProductFiscalProfile itself uses when no profile
        // exists anywhere.
        return [
            'vat_rate' => VatRate::None,
            'payment_subject' => FiscalReceiptItemPaymentSubject::Commodity,
            'unit_of_measure' => null,
        ];
    }
}
