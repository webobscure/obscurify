<?php

namespace App\Domain\Localization\Application;

use App\Domain\Localization\Models\StoreSupportedLocale;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Spec section 8: "Default language, Supported languages, Admin
 * language, Storefront language" — the whole store-side of the
 * localization model, written atomically so a caller never observes a
 * store with e.g. an admin_locale outside its own just-updated
 * supported set.
 */
final class UpdateStoreLocaleSettings
{
    /**
     * @param  array{default_locale?: string, admin_locale?: ?string, storefront_locale?: ?string, supported_locales?: list<string>}  $data
     */
    public function handle(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            $store->fill([
                'default_locale' => $data['default_locale'] ?? $store->default_locale,
                'admin_locale' => array_key_exists('admin_locale', $data) ? $data['admin_locale'] : $store->admin_locale,
                'storefront_locale' => array_key_exists('storefront_locale', $data) ? $data['storefront_locale'] : $store->storefront_locale,
            ])->save();

            if (array_key_exists('supported_locales', $data)) {
                // Always keep the store's own default_locale in its
                // supported set — a store that doesn't "support" its
                // own default would be a contradiction LocaleResolver
                // can't sensibly fall back through.
                $codes = array_values(array_unique([...$data['supported_locales'], $store->default_locale]));

                StoreSupportedLocale::query()->where('store_id', $store->id)->delete();

                foreach ($codes as $code) {
                    StoreSupportedLocale::query()->create(['store_id' => $store->id, 'locale_code' => $code]);
                }
            }

            return $store->fresh();
        });
    }
}
