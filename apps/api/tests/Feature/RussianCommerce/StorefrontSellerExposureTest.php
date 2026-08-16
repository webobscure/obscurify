<?php

use App\Domain\RussianCommerce\Application\CreateOrUpdateLegalProfile;
use App\Domain\RussianCommerce\Application\UpdatePaymentMethodSettings;
use App\Domain\RussianCommerce\Models\PaymentMethodSettings;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    domainForStore($this->store, 'rc-seller.localhost');
});

it('exposes no seller info when the store has no legal profile configured', function () {
    $response = $this->getJson(storefrontUrl('rc-seller.localhost', '/api/v1/storefront/store'))->assertOk();

    expect($response->json('data.seller'))->toBeNull()
        ->and($response->json('data.payment_methods'))->toBe([]);
});

it('exposes only legal_name and inn — never KPP, addresses, or entity type', function () {
    app(TenantContext::class)->scope($this->store, function () {
        app(CreateOrUpdateLegalProfile::class)->handle($this->store, [
            'legal_entity_type' => 'legal_entity',
            'legal_name' => 'OOO Storefront Seller',
            'inn' => '7707083893',
            'kpp' => '770701001',
        ]);
    });

    $response = $this->getJson(storefrontUrl('rc-seller.localhost', '/api/v1/storefront/store'))->assertOk();

    expect($response->json('data.seller'))->toBe(['legal_name' => 'OOO Storefront Seller', 'inn' => '7707083893']);

    $response->assertJsonMissingPath('data.seller.kpp')
        ->assertJsonMissingPath('data.seller.legal_address')
        ->assertJsonMissingPath('data.seller.legal_entity_type');
});

it('exposes the store\'s enabled payment method names', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $settings = PaymentMethodSettings::query()->firstOrCreate(['store_id' => $this->store->id], ['enabled_methods' => []]);
        app(UpdatePaymentMethodSettings::class)->handle($settings, ['bank_card', 'sbp']);
    });

    $response = $this->getJson(storefrontUrl('rc-seller.localhost', '/api/v1/storefront/store'))->assertOk();

    expect($response->json('data.payment_methods'))->toBe(['bank_card', 'sbp']);
});
