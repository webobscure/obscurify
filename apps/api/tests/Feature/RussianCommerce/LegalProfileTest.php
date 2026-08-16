<?php

use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->actingAs($this->user, 'sanctum');
});

it('returns null when no legal profile has been configured yet', function () {
    $this->getJson('/api/v1/russian-commerce/legal-profile', tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('creates a legal entity profile with a valid INN and KPP', function () {
    // updateOrCreate's model wasRecentlyCreated on this first call, so
    // JsonResource auto-reports 201, exactly like a real create.
    $response = $this->putJson('/api/v1/russian-commerce/legal-profile', [
        'legal_entity_type' => 'legal_entity',
        'legal_name' => 'OOO Test',
        'inn' => '7707083893',
        'kpp' => '770701001',
    ], tenantHeader($this->store))->assertCreated();

    expect($response->json('data.legal_entity_type'))->toBe('legal_entity')
        ->and($response->json('data.inn'))->toBe('7707083893')
        ->and($response->json('data.kpp'))->toBe('770701001');

    app(TenantContext::class)->scope($this->store, function () {
        expect(StoreLegalProfile::query()->where('store_id', $this->store->id)->count())->toBe(1);
    });
});

it('rejects a legal entity profile with an invalid INN checksum', function () {
    $this->putJson('/api/v1/russian-commerce/legal-profile', [
        'legal_entity_type' => 'legal_entity',
        'legal_name' => 'OOO Test',
        'inn' => '1234567890',
        'kpp' => '770701001',
    ], tenantHeader($this->store))->assertUnprocessable()->assertJsonValidationErrors('inn');
});

it('rejects a legal entity profile with a missing KPP', function () {
    $this->putJson('/api/v1/russian-commerce/legal-profile', [
        'legal_entity_type' => 'legal_entity',
        'legal_name' => 'OOO Test',
        'inn' => '7707083893',
    ], tenantHeader($this->store))->assertUnprocessable()->assertJsonValidationErrors('kpp');
});

it('creates an individual entrepreneur profile with a 12-digit INN and no KPP', function () {
    $response = $this->putJson('/api/v1/russian-commerce/legal-profile', [
        'legal_entity_type' => 'individual_entrepreneur',
        'legal_name' => 'IP Ivanov',
        'inn' => '500100732259',
    ], tenantHeader($this->store))->assertCreated();

    expect($response->json('data.kpp'))->toBeNull();
});

it('silently drops a supplied KPP for a non-legal-entity profile', function () {
    $response = $this->putJson('/api/v1/russian-commerce/legal-profile', [
        'legal_entity_type' => 'self_employed',
        'legal_name' => 'Ivanov I.I.',
        'inn' => '500100732259',
        'kpp' => '770701001',
    ], tenantHeader($this->store))->assertCreated();

    expect($response->json('data.kpp'))->toBeNull();
});

it('upserts rather than duplicates the profile on a second update', function () {
    $this->putJson('/api/v1/russian-commerce/legal-profile', [
        'legal_entity_type' => 'legal_entity',
        'legal_name' => 'OOO Test',
        'inn' => '7707083893',
        'kpp' => '770701001',
    ], tenantHeader($this->store))->assertCreated();

    $this->putJson('/api/v1/russian-commerce/legal-profile', [
        'legal_entity_type' => 'legal_entity',
        'legal_name' => 'OOO Test Renamed',
        'inn' => '7707083893',
        'kpp' => '770701001',
    ], tenantHeader($this->store))->assertOk()->assertJsonPath('data.legal_name', 'OOO Test Renamed');

    app(TenantContext::class)->scope($this->store, function () {
        expect(StoreLegalProfile::query()->where('store_id', $this->store->id)->count())->toBe(1);
    });
});
