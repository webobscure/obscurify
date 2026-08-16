<?php

use App\Domain\RussianCommerce\Application\CreateOrUpdateLegalProfile;
use App\Domain\RussianCommerce\Models\OrderFiscalSnapshot;
use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->withCredentials();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    domainForStore($this->store, 'rc-snapshot.localhost');

    [$this->product, $this->variant] = productWithStock($this->store, 10);
});

it('snapshots the legal profile that was active at order completion', function () {
    setUpRussianCommerceStore($this->store);

    ['order_id' => $orderId] = completedOrderFor('rc-snapshot.localhost', $this->variant->id);

    app(TenantContext::class)->scope($this->store, function () use ($orderId) {
        $snapshot = OrderFiscalSnapshot::query()->where('order_id', $orderId)->firstOrFail();
        expect($snapshot->seller_legal_name)->toBe('OOO Test Store')
            ->and($snapshot->seller_inn)->toBe('7707083893')
            ->and($snapshot->seller_kpp)->toBe('770701001')
            ->and($snapshot->receipt_required)->toBeTrue();
    });
});

it('creates no snapshot for a store with no legal profile configured', function () {
    ['order_id' => $orderId] = completedOrderFor('rc-snapshot.localhost', $this->variant->id);

    app(TenantContext::class)->scope($this->store, function () use ($orderId) {
        expect(OrderFiscalSnapshot::query()->where('order_id', $orderId)->exists())->toBeFalse();
    });
});

it('never changes a historical order snapshot when the legal profile is edited afterward', function () {
    setUpRussianCommerceStore($this->store);

    ['order_id' => $firstOrderId] = completedOrderFor('rc-snapshot.localhost', $this->variant->id);

    // Merchant re-registers under a different legal entity — a real,
    // if unusual, event (e.g. a business restructuring).
    app(TenantContext::class)->scope($this->store, function () {
        app(CreateOrUpdateLegalProfile::class)->handle($this->store, [
            'legal_entity_type' => 'legal_entity',
            'legal_name' => 'OOO Renamed Successor',
            'inn' => '7707083893',
            'kpp' => '770701001',
        ]);
    });

    // A second cart/checkout is needed since the first was already
    // converted — productWithStock's inventory replenishes naturally
    // via a second purchase of the same in-stock variant.
    ['order_id' => $secondOrderId] = completedOrderFor('rc-snapshot.localhost', $this->variant->id);

    app(TenantContext::class)->scope($this->store, function () use ($firstOrderId, $secondOrderId) {
        $firstSnapshot = OrderFiscalSnapshot::query()->where('order_id', $firstOrderId)->firstOrFail();
        $secondSnapshot = OrderFiscalSnapshot::query()->where('order_id', $secondOrderId)->firstOrFail();

        expect($firstSnapshot->seller_legal_name)->toBe('OOO Test Store')
            ->and($secondSnapshot->seller_legal_name)->toBe('OOO Renamed Successor');

        // The live profile only ever has the latest name — confirms the
        // first snapshot's value came from a frozen copy, not a live
        // join/read of StoreLegalProfile.
        $liveProfile = StoreLegalProfile::query()->where('store_id', $this->store->id)->firstOrFail();
        expect($liveProfile->legal_name)->toBe('OOO Renamed Successor')
            ->and($firstSnapshot->seller_legal_name)->not->toBe($liveProfile->legal_name);
    });
});
