<?php

namespace App\Domain\RussianCommerce\Http\Controllers;

use App\Domain\RussianCommerce\Application\CreateOrUpdateLegalProfile;
use App\Domain\RussianCommerce\Http\Requests\UpdateStoreLegalProfileRequest;
use App\Domain\RussianCommerce\Http\Resources\StoreLegalProfileResource;
use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Spec section 17's "Russian Legal Details" admin settings page — one
 * profile per store, upserted (see CreateOrUpdateLegalProfile).
 */
final class StoreLegalProfileController extends Controller
{
    public function show(TenantContext $tenantContext): StoreLegalProfileResource|JsonResponse
    {
        $profile = StoreLegalProfile::query()->where('store_id', $tenantContext->storeId())->first();

        if ($profile === null) {
            return response()->json(['data' => null]);
        }

        return new StoreLegalProfileResource($profile);
    }

    public function update(
        UpdateStoreLegalProfileRequest $request,
        TenantContext $tenantContext,
        CreateOrUpdateLegalProfile $action,
    ): StoreLegalProfileResource {
        $profile = $action->handle($tenantContext->store(), $request->validated());

        return new StoreLegalProfileResource($profile);
    }
}
