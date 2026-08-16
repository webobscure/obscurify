<?php

namespace App\Domain\RussianCommerce\Http\Controllers;

use App\Domain\RussianCommerce\Application\EnsureDefaultRussianCommerceSetup;
use App\Domain\RussianCommerce\Application\UpdatePaymentMethodSettings;
use App\Domain\RussianCommerce\Http\Requests\UpdatePaymentMethodSettingsRequest;
use App\Domain\RussianCommerce\Http\Resources\PaymentMethodSettingsResource;
use App\Domain\RussianCommerce\Models\PaymentMethodSettings;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;

/**
 * Spec section 17's "Payment Methods" admin settings page.
 */
final class PaymentMethodSettingsController extends Controller
{
    public function show(TenantContext $tenantContext, EnsureDefaultRussianCommerceSetup $ensureDefaults): PaymentMethodSettingsResource
    {
        $ensureDefaults->handle($tenantContext->store());

        $settings = PaymentMethodSettings::query()->where('store_id', $tenantContext->storeId())->firstOrFail();

        return new PaymentMethodSettingsResource($settings);
    }

    public function update(
        UpdatePaymentMethodSettingsRequest $request,
        TenantContext $tenantContext,
        UpdatePaymentMethodSettings $action,
    ): PaymentMethodSettingsResource {
        $settings = PaymentMethodSettings::query()->where('store_id', $tenantContext->storeId())->firstOrFail();

        return new PaymentMethodSettingsResource($action->handle($settings, $request->validated()['enabled_methods']));
    }
}
