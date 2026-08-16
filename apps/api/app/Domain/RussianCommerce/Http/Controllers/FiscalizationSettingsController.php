<?php

namespace App\Domain\RussianCommerce\Http\Controllers;

use App\Domain\RussianCommerce\Application\EnsureDefaultRussianCommerceSetup;
use App\Domain\RussianCommerce\Application\UpdateFiscalizationSettings;
use App\Domain\RussianCommerce\Http\Requests\UpdateFiscalizationSettingsRequest;
use App\Domain\RussianCommerce\Http\Resources\FiscalizationSettingsResource;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;

/**
 * Spec section 17's "Tax / VAT Settings" + "Fiscalization Settings"
 * admin pages — one row per store, seeded lazily (see
 * EnsureDefaultRussianCommerceSetup) so this never 404s.
 */
final class FiscalizationSettingsController extends Controller
{
    public function show(TenantContext $tenantContext, EnsureDefaultRussianCommerceSetup $ensureDefaults): FiscalizationSettingsResource
    {
        $ensureDefaults->handle($tenantContext->store());

        $settings = FiscalizationSettings::query()
            ->where('store_id', $tenantContext->storeId())
            ->with('activeProvider')
            ->firstOrFail();

        return new FiscalizationSettingsResource($settings);
    }

    public function update(
        UpdateFiscalizationSettingsRequest $request,
        TenantContext $tenantContext,
        UpdateFiscalizationSettings $action,
    ): FiscalizationSettingsResource {
        $settings = FiscalizationSettings::query()->where('store_id', $tenantContext->storeId())->firstOrFail();

        return new FiscalizationSettingsResource($action->handle($settings, $request->validated())->load('activeProvider'));
    }
}
