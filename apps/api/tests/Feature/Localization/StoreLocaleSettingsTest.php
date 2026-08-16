<?php

use App\Domain\Localization\Application\EnsureDefaultLanguages;
use App\Domain\Localization\Application\UpdateStoreLocaleSettings;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    app(EnsureDefaultLanguages::class)->handle();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
});

it('shows the store\'s default locale settings, seeded with the platform default', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/store-locale-settings', tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data.default_locale'))->toBe('ru')
        ->and($response->json('data.admin_locale'))->toBeNull()
        ->and($response->json('data.storefront_locale'))->toBeNull()
        ->and($response->json('data.supported_locales'))->toBe([]);
});

it('updates default/admin/storefront language and the supported set together', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->patchJson('/api/v1/store-locale-settings', [
            'default_locale' => 'ru',
            'admin_locale' => 'de',
            'storefront_locale' => 'en',
            'supported_locales' => ['ru', 'en', 'de'],
        ], tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data.default_locale'))->toBe('ru')
        ->and($response->json('data.admin_locale'))->toBe('de')
        ->and($response->json('data.storefront_locale'))->toBe('en')
        ->and($response->json('data.supported_locales'))->toEqualCanonicalizing(['ru', 'en', 'de']);
});

it('always keeps the store\'s own default_locale in its supported set', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->patchJson('/api/v1/store-locale-settings', [
            'default_locale' => 'ru',
            'supported_locales' => ['en'],
        ], tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data.supported_locales'))->toEqualCanonicalizing(['en', 'ru']);
});

it('rejects an unsupported locale code', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->patchJson('/api/v1/store-locale-settings', ['default_locale' => 'fr'], tenantHeader($this->storeA))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('default_locale');
});

it('never lets one store read or write another store\'s locale settings', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        app(UpdateStoreLocaleSettings::class)->handle($this->storeA, [
            'admin_locale' => 'de',
            'supported_locales' => ['ru', 'de'],
        ]);
    });

    $responseB = $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/store-locale-settings', tenantHeader($this->storeB))
        ->assertOk();

    expect($responseB->json('data.admin_locale'))->toBeNull();

    $this->actingAs($this->userB, 'sanctum')
        ->patchJson('/api/v1/store-locale-settings', ['admin_locale' => 'en'], tenantHeader($this->storeB))
        ->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect($this->storeA->fresh()->admin_locale)->toBe('de');
    });
});
