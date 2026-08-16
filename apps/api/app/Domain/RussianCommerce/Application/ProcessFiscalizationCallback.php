<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Enums\FiscalReceiptStatus;
use App\Domain\RussianCommerce\Exceptions\UnknownFiscalReceiptException;
use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Domain\RussianCommerce\Support\FiscalizationCallbackEvent;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The provider-neutral fiscalization callback handler (spec section 13:
 * "must handle duplicate/replayed callbacks gracefully"). Mirrors
 * ProcessPaymentWebhook's tenant-resolution shape: a callback carries no
 * TenantContext, so store_id is resolved from the (provider,
 * external_receipt_id) → FiscalReceipt mapping first, never from
 * anything in the callback payload itself.
 *
 * Unlike Payments (which has a dedicated payment_webhook_events dedup
 * table because a payment sees many distinct event types over its
 * life), a FiscalReceipt only ever has one meaningful async outcome —
 * so idempotency here is just "is this receipt still in a non-terminal
 * status," locked with lockForUpdate() to serialize concurrent
 * deliveries of the same callback.
 */
final class ProcessFiscalizationCallback
{
    public function __construct(
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @throws UnknownFiscalReceiptException
     */
    public function handle(string $providerCode, FiscalizationCallbackEvent $event): void
    {
        $receipt = FiscalReceipt::withoutGlobalScopes()
            ->where('provider', $providerCode)
            ->where('external_receipt_id', $event->externalReceiptId)
            ->first();

        if ($receipt === null) {
            throw UnknownFiscalReceiptException::forExternalId($providerCode, $event->externalReceiptId);
        }

        $store = Store::query()->findOrFail($receipt->store_id);

        app(TenantContext::class)->scope($store, function () use ($receipt, $event) {
            DB::transaction(function () use ($receipt, $event) {
                $locked = FiscalReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

                if (in_array($locked->status, [FiscalReceiptStatus::Fiscalized, FiscalReceiptStatus::Failed], true)) {
                    // Duplicate/replayed delivery of a callback already
                    // resolved by a prior one — idempotent no-op (spec
                    // section 13).
                    return;
                }

                if ($event->succeeded) {
                    $locked->update([
                        'status' => FiscalReceiptStatus::Fiscalized->value,
                        'fiscalized_at' => now(),
                        'error_message' => null,
                    ]);

                    $this->recordOutboxEvent->handle('FiscalReceiptFiscalized', 'FiscalReceipt', $locked->id, [
                        'receipt_id' => $locked->id,
                        'store_id' => $locked->store_id,
                        'order_id' => $locked->order_id,
                        'external_receipt_id' => $locked->external_receipt_id,
                    ]);

                    return;
                }

                $locked->update([
                    'status' => FiscalReceiptStatus::Failed->value,
                    'error_message' => $event->errorMessage,
                ]);

                $this->recordOutboxEvent->handle('FiscalReceiptFailed', 'FiscalReceipt', $locked->id, [
                    'receipt_id' => $locked->id,
                    'store_id' => $locked->store_id,
                    'order_id' => $locked->order_id,
                    'error_message' => $locked->error_message,
                ]);
            });
        });
    }
}
