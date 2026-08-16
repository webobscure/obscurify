<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Models\FiscalizationProvider;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;

final class DeleteFiscalizationProvider
{
    public function handle(FiscalizationProvider $provider): void
    {
        // A provider currently active on FiscalizationSettings must be
        // detached first (the settings FK is nullOnDelete, so the
        // migration itself wouldn't error) — but leaving it silently
        // orphaned would mean receipts_required could stay true with no
        // active provider, exactly the misconfiguration
        // FiscalizationNotConfiguredException exists to catch loudly.
        // Clearing it here surfaces that state immediately instead.
        FiscalizationSettings::query()
            ->where('active_provider_id', $provider->id)
            ->update(['active_provider_id' => null]);

        $provider->delete();
    }
}
