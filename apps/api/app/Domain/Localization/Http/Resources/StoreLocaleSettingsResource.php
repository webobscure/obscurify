<?php

namespace App\Domain\Localization\Http\Resources;

use App\Domain\Localization\Models\StoreSupportedLocale;
use App\Domain\Stores\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Store
 */
final class StoreLocaleSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'default_locale' => $this->default_locale,
            'admin_locale' => $this->admin_locale,
            'storefront_locale' => $this->storefront_locale,
            'supported_locales' => StoreSupportedLocale::query()->where('store_id', $this->id)->pluck('locale_code')->all(),
        ];
    }
}
