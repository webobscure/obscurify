<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Enums\VatRate;
use App\Domain\RussianCommerce\Models\FiscalizationProvider;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use App\Domain\RussianCommerce\Models\PaymentMethodSettings;
use App\Domain\RussianCommerce\Support\Providers\FakeFiscalizationProvider;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;

/**
 * Idempotently seeds a store's default Russian Commerce setup:
 * FiscalizationSettings (receipts NOT required, no active provider —
 * unlike Search/Notifications, there's no always-safe fiscalization
 * provider to default to, so this stays off until a merchant/admin
 * configures a real one) and PaymentMethodSettings (no methods
 * enabled). Only when the fake fiscalization provider is actually
 * registered (config('russian_commerce.fake_fiscalization.enabled'),
 * dev/test only — see RussianCommerceServiceProvider) does this also
 * seed and activate a "fake" FiscalizationProvider row, mirroring how
 * Payments' fake provider only ever appears in non-production
 * environments. Called both from `russian-commerce:install` and lazily
 * by the admin settings endpoints, the same "auto-create on first
 * read" convention Milestones 20-22 established.
 */
final class EnsureDefaultRussianCommerceSetup
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Store $store): void
    {
        $this->tenantContext->scope($store, function () use ($store) {
            $settings = FiscalizationSettings::query()->firstOrCreate(
                ['store_id' => $store->id],
                ['default_vat_rate' => VatRate::None->value, 'receipts_required' => false],
            );

            PaymentMethodSettings::query()->firstOrCreate(
                ['store_id' => $store->id],
                ['enabled_methods' => []],
            );

            if (! config('russian_commerce.fake_fiscalization.enabled')) {
                return;
            }

            $fakeProvider = FiscalizationProvider::query()->firstOrCreate(
                ['store_id' => $store->id, 'code' => FakeFiscalizationProvider::CODE],
                ['name' => 'Fake Fiscalization Provider', 'is_enabled' => true, 'config' => []],
            );

            if ($settings->active_provider_id === null) {
                $settings->update(['active_provider_id' => $fakeProvider->id]);
            }
        });
    }
}
