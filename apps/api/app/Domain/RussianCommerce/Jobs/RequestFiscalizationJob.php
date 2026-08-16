<?php

namespace App\Domain\RussianCommerce\Jobs;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\RussianCommerce\Application\CreateFiscalReceipt;
use App\Domain\RussianCommerce\Exceptions\FiscalizationNotConfiguredException;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched by FiscalizationSubscriber from inside
 * ProcessOutboxEventsCommand's tenant scope, run out-of-band on the
 * queue so a payment webhook never waits on a fiscalization submission
 * — mirrors IndexProductJob exactly. Establishes its own TenantContext
 * since CreateFiscalReceipt's writes go through BelongsToTenant.
 *
 * A FiscalizationNotConfiguredException here is a real admin
 * misconfiguration (spec section 15: fiscalization failure never
 * touches Order/Payment) — logged and swallowed rather than left to
 * exhaust the queue's retry budget with the exact same outcome every
 * time; an admin fixing the Fiscalization Settings page and completing
 * a *new* order is what actually recovers this, not a queue retry.
 */
final class RequestFiscalizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $storeId,
        public readonly string $orderId,
        public readonly ?string $paymentId = null,
    ) {}

    public function handle(TenantContext $tenantContext, CreateFiscalReceipt $createFiscalReceipt): void
    {
        $store = Store::query()->find($this->storeId);

        if ($store === null) {
            return;
        }

        $tenantContext->scope($store, function () use ($createFiscalReceipt) {
            $order = Order::query()->with('items')->find($this->orderId);

            if ($order === null) {
                return;
            }

            $payment = $this->paymentId !== null ? Payment::query()->find($this->paymentId) : null;

            try {
                $createFiscalReceipt->handle($order, $payment);
            } catch (FiscalizationNotConfiguredException $exception) {
                Log::warning('russian_commerce.fiscalization_not_configured', [
                    'store_id' => $this->storeId,
                    'order_id' => $this->orderId,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }
}
