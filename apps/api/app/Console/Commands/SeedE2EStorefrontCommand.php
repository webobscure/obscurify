<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Models\ProductVariantOptionValue;
use App\Domain\Domains\Models\Domain;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Locations\Models\Location;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingMethodZone;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Models\ShippingZoneRegion;
use App\Domain\Stores\Application\CreateStore;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent fixture for the Playwright storefront E2E suite — run before
 * the spec so it works from a fresh clone/CI run without any manual
 * tinker step, and re-running it is always a no-op past the first time.
 */
class SeedE2EStorefrontCommand extends Command
{
    protected $signature = 'e2e:seed-storefront';

    protected $description = 'Seed a deterministic store/domain/product/stock fixture for Playwright E2E';

    public function handle(TenantContext $tenantContext, CreateStore $createStore): int
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'e2e@example.test'],
            ['name' => 'E2E', 'password' => Hash::make('password')],
        );

        $store = Store::query()->where('slug', 'e2e-store')->first()
            ?? $createStore->handle($user, ['name' => 'E2E Store', 'slug' => 'e2e-store', 'default_currency' => 'RUB', 'default_locale' => 'ru', 'timezone' => 'Europe/Moscow']);

        $tenantContext->scope($store, function () use ($store) {
            Domain::withoutGlobalScopes()->firstOrCreate(
                ['domain' => 'e2e-storefront.localhost'],
                ['store_id' => $store->id, 'type' => 'primary', 'is_primary' => true],
            );

            $product = Product::query()->firstOrCreate(
                ['slug' => 'e2e-shirt'],
                ['title' => 'E2E Shirt', 'status' => ProductStatus::Active],
            );

            $option = ProductOption::query()->firstOrCreate(
                ['product_id' => $product->id, 'name' => 'Size'],
                ['position' => 0],
            );

            $value = ProductOptionValue::query()->firstOrCreate(
                ['product_option_id' => $option->id, 'value' => 'M'],
                ['position' => 0],
            );

            $variant = ProductVariant::query()->firstOrCreate(
                ['product_id' => $product->id, 'sku' => 'E2E-SHIRT-M'],
                [
                    'title' => 'M',
                    'price_amount' => 2500,
                    'currency' => 'RUB',
                    'status' => ProductStatus::Active,
                    'option_signature' => $value->id,
                ],
            );

            ProductVariantOptionValue::query()->firstOrCreate([
                'product_variant_id' => $variant->id,
                'product_option_value_id' => $value->id,
            ]);

            $item = InventoryItem::query()->firstOrCreate(
                ['product_variant_id' => $variant->id],
                ['tracked' => true, 'requires_shipping' => true],
            );

            $location = Location::query()->firstOrCreate(['name' => 'E2E Warehouse']);

            $level = InventoryLevel::query()->firstOrCreate(
                ['inventory_item_id' => $item->id, 'location_id' => $location->id],
                ['on_hand' => 50, 'reserved' => 0],
            );

            if ($level->on_hand < 10) {
                $level->update(['on_hand' => 50]);
            }

            // Matches the US/Testville address every checkout/payment/
            // shipping E2E spec fills in — a real fake rate is always
            // available for those flows to select.
            $zone = ShippingZone::query()->firstOrCreate(['name' => 'E2E Zone']);
            ShippingZoneRegion::query()->firstOrCreate([
                'shipping_zone_id' => $zone->id,
                'country_code' => 'US',
            ]);

            $method = ShippingMethod::query()->firstOrCreate(
                ['code' => 'e2e-standard'],
                [
                    'name' => 'Standard Shipping',
                    'provider' => FakeShippingProvider::CODE,
                    'service_code' => 'standard',
                    'price_amount' => 50000,
                    'currency' => 'RUB',
                    'estimated_days_min' => 3,
                    'estimated_days_max' => 5,
                ],
            );

            ShippingMethodZone::query()->firstOrCreate([
                'shipping_method_id' => $method->id,
                'shipping_zone_id' => $zone->id,
            ]);

            // Second zone/method for the pickup E2E flow: the fake provider's
            // static pickup-point network is RU-only (see
            // commerce.shipping.fake.pickup_points), so this needs its own
            // domestic-country zone distinct from the US one above.
            $ruZone = ShippingZone::query()->firstOrCreate(['name' => 'E2E RU Zone']);
            ShippingZoneRegion::query()->firstOrCreate([
                'shipping_zone_id' => $ruZone->id,
                'country_code' => 'RU',
            ]);

            $pickupMethod = ShippingMethod::query()->firstOrCreate(
                ['code' => 'e2e-pickup'],
                [
                    'name' => 'Pickup Point',
                    'provider' => FakeShippingProvider::CODE,
                    'service_code' => 'pickup',
                    'price_amount' => 15000,
                    'currency' => 'RUB',
                    'estimated_days_min' => 2,
                    'estimated_days_max' => 4,
                ],
            );

            ShippingMethodZone::query()->firstOrCreate([
                'shipping_method_id' => $pickupMethod->id,
                'shipping_zone_id' => $ruZone->id,
            ]);
        });

        $this->info('E2E storefront fixture ready: e2e-storefront.localhost / e2e-shirt');

        return self::SUCCESS;
    }
}
