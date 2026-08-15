<?php

namespace App\Domain\Search\Jobs;

use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Incremental indexing (spec section 4) — dispatched by
 * SearchIndexingSubscriber from inside ProcessOutboxEventsCommand's
 * tenant scope, run out-of-band on the queue so a product write never
 * waits on a reindex. Establishes its own TenantContext exactly like
 * every other cross-request job in this codebase (DeliverWebhookJob,
 * SendNotificationDeliveryJob, ...), since BuildSearchDocument's writes
 * go through BelongsToTenant.
 */
final class IndexProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $storeId, public readonly string $productId) {}

    public function handle(TenantContext $tenantContext, BuildSearchDocument $buildSearchDocument): void
    {
        $store = Store::query()->find($this->storeId);

        if ($store === null) {
            return;
        }

        $tenantContext->scope($store, function () use ($buildSearchDocument) {
            $product = Product::query()->withTrashed()->find($this->productId);

            if ($product === null) {
                return;
            }

            $buildSearchDocument->handle($product);
        });
    }
}
