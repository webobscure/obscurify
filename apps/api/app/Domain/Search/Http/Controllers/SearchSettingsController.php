<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\UpdateSearchSettings;
use App\Domain\Search\Http\Requests\UpdateSearchSettingsRequest;
use App\Domain\Search\Http\Resources\SearchSettingsResource;
use App\Domain\Search\Models\SearchSettings;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;

final class SearchSettingsController extends Controller
{
    public function show(TenantContext $tenantContext, EnsureDefaultSearchSetup $ensureDefaults): SearchSettingsResource
    {
        $ensureDefaults->handle($tenantContext->store());

        $settings = SearchSettings::query()->where('store_id', $tenantContext->storeId())->with('activeProvider')->firstOrFail();

        return new SearchSettingsResource($settings);
    }

    public function update(UpdateSearchSettingsRequest $request, TenantContext $tenantContext, UpdateSearchSettings $action): SearchSettingsResource
    {
        $settings = SearchSettings::query()->where('store_id', $tenantContext->storeId())->firstOrFail();

        return new SearchSettingsResource($action->handle($settings, $request->validated())->load('activeProvider'));
    }
}
