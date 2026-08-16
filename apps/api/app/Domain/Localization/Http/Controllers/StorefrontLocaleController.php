<?php

namespace App\Domain\Localization\Http\Controllers;

use App\Domain\Localization\Models\Locale;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Persists the storefront's language choice (spec section 4: "Persist
 * language preference") as a `storefront_locale` cookie — read by
 * EnsureStorefrontTenantContext on every subsequent request, ahead of
 * Accept-Language and the store's own storefront_locale default. Not
 * httpOnly: the Nuxt storefront's own i18n client reads this same
 * cookie on page load to sync its locale without an extra round-trip.
 *
 * Works identically for anonymous and logged-in visitors (mirrors
 * `storefront_cart_token`'s own cookie-for-everyone shape) — no
 * `customer-token` middleware here. An authenticated customer wanting
 * their choice to also drive notification/email locale (which reads
 * `Customer.locale`, not a cookie — a cookie means nothing outside a
 * browser) sets that through the existing `PATCH /storefront/account`
 * profile endpoint instead.
 */
final class StorefrontLocaleController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString();

        if (! Locale::query()->where('code', $locale)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['locale' => __('validation.exists', ['attribute' => 'locale'])]);
        }

        return response()->json(['data' => ['locale' => $locale]])->withCookie(new Cookie(
            name: 'storefront_locale',
            value: $locale,
            expire: now()->addYear()->getTimestamp(),
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: false,
            sameSite: 'lax',
        ));
    }
}
