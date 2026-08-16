<?php

use App\Domain\Localization\Application\EnsureDefaultLanguages;
use App\Domain\Localization\Application\UpdateStoreLocaleSettings;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Verifies locale resolution through its actual observable effect — the
 * validation error text a request gets back — rather than inspecting
 * LocaleContext state after the fact: ResolveRequestLocale's own
 * `finally` clears LocaleContext back to the platform default once the
 * request finishes, so by the time a test's assertion runs, the
 * mid-request resolved value is already gone. The response body is the
 * only thing that genuinely proves what locale was active *during*
 * request handling.
 */
beforeEach(function () {
    app(EnsureDefaultLanguages::class)->handle();

    // Required for a simulated Pest request's cookies (like
    // storefront_locale below) to actually populate request()->cookies
    // — see MakesHttpRequests::prepareCookiesForJsonRequest(), the same
    // gotcha already documented in the Payments/GraphQL cart tests.
    $this->withCredentials();
});

it('defaults to the platform default locale (ru) with no header and no store', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    // phpunit.xml pins APP_FALLBACK_LOCALE=en for deterministic test
    // assertions elsewhere (see that file's own comment) — the seeded
    // Locale catalog's own is_default row is still 'ru' in production;
    // this test only proves resolution reaches "the platform default,"
    // not a specific hardcoded string.
    expect($response->json('errors.email.0'))->toBe(__('validation.required', ['attribute' => 'email']));
});

it('resolves the global baseline from Accept-Language on an unauthenticated route', function () {
    $response = $this->withHeaders(['Accept-Language' => 'ru'])->postJson('/api/v1/auth/login', []);

    expect($response->json('errors.email.0'))->toBe('Поле email обязательно для заполнения.');
});

it('resolves German from Accept-Language too', function () {
    $response = $this->withHeaders(['Accept-Language' => 'de'])->postJson('/api/v1/auth/login', []);

    expect($response->json('errors.email.0'))->toBe('Das Feld email ist erforderlich.');
});

it('falls back to the fallback locale for a language the platform does not support', function () {
    $response = $this->withHeaders(['Accept-Language' => 'fr'])->postJson('/api/v1/auth/login', []);

    expect($response->json('errors.email.0'))->toBe(__('validation.required', ['attribute' => 'email']));
});

it('lets an explicit ?locale= query override Accept-Language', function () {
    $response = $this->withHeaders(['Accept-Language' => 'de'])->postJson('/api/v1/auth/login?locale=ru', []);

    expect($response->json('errors.email.0'))->toBe('Поле email обязательно для заполнения.');
});

it('ignores an explicit ?locale= for a language the platform does not support', function () {
    $response = $this->postJson('/api/v1/auth/login?locale=fr', []);

    expect($response->json('errors.email.0'))->toBe(__('validation.required', ['attribute' => 'email']));
});

it('refines to the authenticated admin user\'s own saved locale ahead of the store default', function () {
    $user = User::factory()->create(['locale' => 'de']);
    $store = createStoreForUser($user);
    app(TenantContext::class)->scope($store, function () use ($store) {
        app(UpdateStoreLocaleSettings::class)->handle($store, ['admin_locale' => 'ru', 'supported_locales' => ['ru', 'en', 'de']]);
    });

    // A route actually behind `tenant` middleware — EnsureTenantContext
    // is what performs this refinement, so it must be exercised on a
    // route that middleware genuinely wraps.
    $response = $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/store-locale-settings', ['default_locale' => 'not-a-real-locale'], tenantHeader($store));

    expect($response->json('errors.default_locale.0'))->toBe('Der ausgewählte Wert für default locale ist ungültig.');
});

it('falls back to the store\'s own admin_locale when the admin user has no saved preference', function () {
    $user = User::factory()->create(['locale' => null]);
    $store = createStoreForUser($user);
    app(TenantContext::class)->scope($store, function () use ($store) {
        app(UpdateStoreLocaleSettings::class)->handle($store, ['admin_locale' => 'de', 'supported_locales' => ['ru', 'en', 'de']]);
    });

    // The test HTTP client sends its own default `Accept-Language:
    // en-us,en;q=0.5` header (a real browser-simulation default, not
    // something this test controls) — 'en' is itself a supported
    // locale, so left alone it would legitimately win ahead of the
    // store default being tested here. Overriding it to an
    // unsupported language forces resolution past that step, onto the
    // store default this test actually exercises.
    $response = $this->withHeaders(['Accept-Language' => 'fr'])
        ->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/store-locale-settings', ['default_locale' => 'not-a-real-locale'], tenantHeader($store));

    expect($response->json('errors.default_locale.0'))->toBe('Der ausgewählte Wert für default locale ist ungültig.');
});

it('resolves the storefront locale from the storefront_locale cookie ahead of the store default', function () {
    $user = User::factory()->create();
    $store = createStoreForUser($user);
    domainForStore($store, 'rc-locale.localhost');
    app(TenantContext::class)->scope($store, function () use ($store) {
        app(UpdateStoreLocaleSettings::class)->handle($store, ['storefront_locale' => 'en', 'supported_locales' => ['ru', 'en', 'de']]);
    });

    $response = $this->withUnencryptedCookie('storefront_locale', 'de')
        ->getJson(storefrontUrl('rc-locale.localhost', '/api/v1/storefront/store'));

    $response->assertOk();
    expect($response->json('data.default_currency'))->not->toBeNull();

    // Confirmed indirectly: a bad-locale POST on the same request cycle
    // proves which locale actually resolved for this store/cookie combo.
    $badLocale = $this->withUnencryptedCookie('storefront_locale', 'de')
        ->postJson(storefrontUrl('rc-locale.localhost', '/api/v1/storefront/locale'), ['locale' => 'not-a-real-locale']);

    expect($badLocale->json('errors.locale.0'))->toBe('Der ausgewählte Wert für locale ist ungültig.');
});

it('falls back to the store\'s own storefront_locale when no cookie or Accept-Language decides it', function () {
    $user = User::factory()->create();
    $store = createStoreForUser($user);
    domainForStore($store, 'rc-locale-2.localhost');
    app(TenantContext::class)->scope($store, function () use ($store) {
        app(UpdateStoreLocaleSettings::class)->handle($store, ['storefront_locale' => 'de', 'supported_locales' => ['ru', 'en', 'de']]);
    });

    // Same reasoning as the admin_locale fallback test above — the test
    // client's own default Accept-Language must be overridden to an
    // unsupported language so it doesn't win ahead of the store default.
    $response = $this->withHeaders(['Accept-Language' => 'fr'])
        ->postJson(storefrontUrl('rc-locale-2.localhost', '/api/v1/storefront/locale'), ['locale' => 'not-a-real-locale']);

    expect($response->json('errors.locale.0'))->toBe('Der ausgewählte Wert für locale ist ungültig.');
});
