<?php

namespace App\Domain\Localization\Support;

use App\Domain\Localization\Models\Locale;
use App\Domain\Localization\Models\StoreSupportedLocale;
use App\Domain\Stores\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The fallback chain spec sections 5/7 ask for. Two entry points:
 *
 *  - resolveGlobal(): the request-wide baseline, usable before any
 *    tenant is resolved (login, registration, webhook-adjacent routes).
 *    Explicit override -> Accept-Language -> platform default.
 *  - resolveForStore(): called again once a tenant IS resolved
 *    (EnsureTenantContext/EnsureStorefrontTenantContext), refining the
 *    baseline with the store's own configured admin/storefront
 *    language and supported-locale set. A caller-supplied preference
 *    (explicit override, saved user/customer preference, a storefront
 *    locale cookie) always wins over the store's default — the store's
 *    default is only ever the LAST fallback before the platform
 *    default.
 *
 * Active locale codes are cached (spec section 21's "Performance
 * considerations" — this runs on every single request) with a flat,
 * short TTL and zero invalidation, the same deliberately narrow
 * tradeoff ADR-029 Decision 6 made for GraphQL's `categories` cache:
 * the active locale set changes only via a rare, deliberate admin
 * action, so a brief staleness window has no correctness consequence.
 */
final class LocaleResolver
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @param  ?string  $explicitPreference  A caller-resolved preference (saved user/customer locale, a locale cookie) checked before Accept-Language — null when there isn't one.
     */
    public function resolveGlobal(Request $request, ?string $explicitPreference = null): string
    {
        $active = $this->activeLocaleCodes();

        $override = $this->normalizedOverride($request, $active);

        if ($override !== null) {
            return $override;
        }

        if ($explicitPreference !== null && in_array($explicitPreference, $active, true)) {
            return $explicitPreference;
        }

        $fromHeader = $this->preferredLanguage($request, $active);

        if ($fromHeader !== null) {
            return $fromHeader;
        }

        return $this->platformDefault();
    }

    /**
     * @param  'admin'|'storefront'  $surface
     */
    public function resolveForStore(Request $request, Store $store, string $surface, ?string $explicitPreference = null): string
    {
        $storeSupported = StoreSupportedLocale::query()->where('store_id', $store->id)->pluck('locale_code')->all();
        $active = $storeSupported !== [] ? $storeSupported : $this->activeLocaleCodes();

        $override = $this->normalizedOverride($request, $active);

        if ($override !== null) {
            return $override;
        }

        if ($explicitPreference !== null && in_array($explicitPreference, $active, true)) {
            return $explicitPreference;
        }

        $fromHeader = $this->preferredLanguage($request, $active);

        if ($fromHeader !== null) {
            return $fromHeader;
        }

        // Store.default_locale is non-nullable, so this always resolves
        // to a real value — the "??" here only ever falls through when
        // admin_locale/storefront_locale itself is unset.
        $storeDefault = ($surface === 'admin' ? $store->admin_locale : $store->storefront_locale) ?? $store->default_locale;

        if (in_array($storeDefault, $active, true)) {
            return $storeDefault;
        }

        return $this->platformDefault();
    }

    /**
     * `Request::getPreferredLanguage()` has a real gotcha (Symfony's own
     * implementation): with no Accept-Language header at all, it
     * returns `$locales[0]` — the FIRST entry of whatever list it's
     * given — rather than null, which would otherwise silently skip
     * every fallback step after it (store default, platform default)
     * on the very common case of a request that sends no header. Only
     * consult it when the header is genuinely present.
     *
     * @param  list<string>  $active
     */
    private function preferredLanguage(Request $request, array $active): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        return $request->getPreferredLanguage($active);
    }

    /**
     * @param  list<string>  $active
     */
    private function normalizedOverride(Request $request, array $active): ?string
    {
        $requested = $request->query('locale');

        if (! is_string($requested) || $requested === '') {
            return null;
        }

        return in_array($requested, $active, true) ? $requested : null;
    }

    /**
     * @return list<string>
     */
    private function activeLocaleCodes(): array
    {
        return Cache::remember('localization.active_locale_codes', self::CACHE_TTL_SECONDS, function () {
            $codes = Locale::query()->where('is_active', true)->pluck('code')->all();

            return $codes !== [] ? $codes : [config('app.locale')];
        });
    }

    private function platformDefault(): string
    {
        return Cache::remember('localization.default_locale_code', self::CACHE_TTL_SECONDS, function () {
            return Locale::query()->where('is_default', true)->value('code') ?? config('app.locale');
        });
    }
}
