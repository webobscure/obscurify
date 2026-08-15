<?php

namespace App\Domain\Search\Jobs;

use App\Domain\Search\Application\RemoveSearchDocument;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RemoveProductFromIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $storeId, public readonly string $productId) {}

    public function handle(TenantContext $tenantContext, RemoveSearchDocument $removeSearchDocument): void
    {
        $store = Store::query()->find($this->storeId);

        if ($store === null) {
            return;
        }

        $tenantContext->scope($store, fn () => $removeSearchDocument->handle($this->storeId, $this->productId));
    }
}
