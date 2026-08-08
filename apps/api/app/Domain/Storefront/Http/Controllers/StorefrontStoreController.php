<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Storefront\Http\Resources\StorefrontStoreResource;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;

final class StorefrontStoreController extends Controller
{
    public function show(TenantContext $tenantContext): StorefrontStoreResource
    {
        return new StorefrontStoreResource($tenantContext->store());
    }
}
