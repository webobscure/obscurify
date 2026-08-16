<?php

namespace App\Domain\Localization\Http\Controllers;

use App\Domain\Localization\Application\UpdateStoreLocaleSettings;
use App\Domain\Localization\Http\Requests\UpdateStoreLocaleSettingsRequest;
use App\Domain\Localization\Http\Resources\StoreLocaleSettingsResource;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;

final class StoreLocaleSettingsController extends Controller
{
    public function show(TenantContext $tenantContext): StoreLocaleSettingsResource
    {
        return new StoreLocaleSettingsResource($tenantContext->store());
    }

    public function update(
        UpdateStoreLocaleSettingsRequest $request,
        TenantContext $tenantContext,
        UpdateStoreLocaleSettings $action,
    ): StoreLocaleSettingsResource {
        return new StoreLocaleSettingsResource($action->handle($tenantContext->store(), $request->validated()));
    }
}
