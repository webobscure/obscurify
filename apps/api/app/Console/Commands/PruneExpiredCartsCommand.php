<?php

namespace App\Console\Commands;

use App\Domain\Carts\Models\Cart;
use Illuminate\Console\Command;

/**
 * Maintenance sweep across all stores — not a per-tenant business
 * operation, so it deliberately bypasses Cart's BelongsToTenant scope
 * (see ARCHITECTURE.md section 10.6: console jobs operating on tenant
 * data must be explicit about it) rather than looping every store just
 * to delete rows that are expired regardless of tenant.
 */
class PruneExpiredCartsCommand extends Command
{
    protected $signature = 'carts:prune-expired';

    protected $description = 'Delete guest carts past their expires_at';

    public function handle(): int
    {
        $deleted = Cart::withoutGlobalScopes()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Pruned {$deleted} expired cart(s).");

        return self::SUCCESS;
    }
}
