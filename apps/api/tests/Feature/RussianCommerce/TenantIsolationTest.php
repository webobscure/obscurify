<?php

use App\Domain\RussianCommerce\Application\CreateOrUpdateLegalProfile;
use App\Domain\RussianCommerce\Application\EnsureDefaultRussianCommerceSetup;
use App\Domain\RussianCommerce\Application\UpdateFiscalizationSettings;
use App\Domain\RussianCommerce\Models\FiscalizationProvider;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    app(TenantContext::class)->scope($this->storeA, function () {
        app(EnsureDefaultRussianCommerceSetup::class)->handle($this->storeA);
        app(CreateOrUpdateLegalProfile::class)->handle($this->storeA, [
            'legal_entity_type' => 'legal_entity',
            'legal_name' => 'OOO Store A',
            'inn' => '7707083893',
            'kpp' => '770701001',
        ]);
    });

    app(TenantContext::class)->scope($this->storeB, function () {
        app(EnsureDefaultRussianCommerceSetup::class)->handle($this->storeB);
        app(CreateOrUpdateLegalProfile::class)->handle($this->storeB, [
            'legal_entity_type' => 'individual_entrepreneur',
            'legal_name' => 'IP Store B',
            'inn' => '500100732259',
        ]);
    });
});

it('never returns another store\'s legal profile', function () {
    $responseA = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/russian-commerce/legal-profile', tenantHeader($this->storeA))
        ->assertOk();

    expect($responseA->json('data.legal_name'))->toBe('OOO Store A');

    $responseB = $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/russian-commerce/legal-profile', tenantHeader($this->storeB))
        ->assertOk();

    expect($responseB->json('data.legal_name'))->toBe('IP Store B')
        ->and($responseB->json('data.legal_name'))->not->toBe($responseA->json('data.legal_name'));
});

it('never lists, shows, updates, or deletes another store\'s fiscalization providers', function () {
    $providerA = app(TenantContext::class)->scope(
        $this->storeA,
        fn () => FiscalizationProvider::query()->where('code', 'fake')->firstOrFail(),
    );

    $listB = $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/russian-commerce/fiscalization-providers', tenantHeader($this->storeB));

    $listB->assertJsonMissing(['id' => $providerA->id]);

    $this->actingAs($this->userB, 'sanctum')
        ->patchJson("/api/v1/russian-commerce/fiscalization-providers/{$providerA->id}", ['name' => 'Hijacked'], tenantHeader($this->storeB))
        ->assertNotFound();

    $this->actingAs($this->userB, 'sanctum')
        ->deleteJson("/api/v1/russian-commerce/fiscalization-providers/{$providerA->id}", [], tenantHeader($this->storeB))
        ->assertNotFound();

    app(TenantContext::class)->scope($this->storeA, function () use ($providerA) {
        expect(FiscalizationProvider::query()->whereKey($providerA->id)->firstOrFail()->name)->not->toBe('Hijacked');
    });
});

it('never lets a store update another store\'s fiscalization settings', function () {
    $settingsA = app(TenantContext::class)->scope(
        $this->storeA,
        fn () => FiscalizationSettings::query()->where('store_id', $this->storeA->id)->firstOrFail(),
    );

    // Store B updates its OWN settings — this must never affect Store
    // A's row, since UpdateFiscalizationSettings always resolves the
    // settings row from the caller's own active tenant, never a
    // caller-supplied id.
    $this->actingAs($this->userB, 'sanctum')
        ->patchJson('/api/v1/russian-commerce/fiscalization-settings', ['receipts_required' => true], tenantHeader($this->storeB))
        ->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () use ($settingsA) {
        expect(FiscalizationSettings::query()->whereKey($settingsA->id)->firstOrFail()->receipts_required)->toBeFalse();
    });
});

it('never lets a store see another store\'s fiscal receipts', function () {
    $this->withCredentials();
    domainForStore($this->storeA, 'rc-iso-a.localhost');
    domainForStore($this->storeB, 'rc-iso-b.localhost');

    [, $variantA] = productWithStock($this->storeA, 10);

    app(TenantContext::class)->scope($this->storeA, function () {
        $settings = FiscalizationSettings::query()->where('store_id', $this->storeA->id)->firstOrFail();
        app(UpdateFiscalizationSettings::class)->handle($settings, ['receipts_required' => true]);
    });

    payAndFiscalize('rc-iso-a.localhost', $variantA->id);

    $listB = $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/russian-commerce/fiscal-receipts', tenantHeader($this->storeB))
        ->assertOk();

    expect($listB->json('data'))->toHaveCount(0);
});
