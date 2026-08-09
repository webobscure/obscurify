<?php

use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Shipping\Application\CreateShipment;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->orderId, $this->orderItemId] = app(TenantContext::class)->scope($this->store, function () {
        $order = Order::factory()->create(['financial_status' => FinancialStatus::Paid]);

        // Exactly 2 ordered — two concurrent 2-unit shipment requests can
        // together only ever satisfy one of them without overshipping.
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2]);

        return [$order->id, $item->id];
    });
});

afterEach(function () {
    // Genuinely committed rows (no RefreshDatabase in this suite — see
    // Pest.php); deleting the store cascades everything else.
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous shipment-create requests for the same OrderItem win, never overshipping', function () {
    $createShipment = function () {
        return app(TenantContext::class)->scope($this->store, function () {
            $order = Order::query()->whereKey($this->orderId)->firstOrFail();

            $shipment = app(CreateShipment::class)->handle($order, 'fake', [
                ['order_item_id' => $this->orderItemId, 'quantity' => 2],
            ]);

            return $shipment->id;
        });
    };

    $results = runConcurrently([$createShipment, $createShipment]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    $failed = array_filter($results, fn ($r) => ! $r['ok']);

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    expect(current($failed)['error'])->toContain('ship');

    app(TenantContext::class)->scope($this->store, function () use ($succeeded) {
        expect(Shipment::query()->count())->toBe(1)
            ->and(Shipment::query()->firstOrFail()->id)->toBe(current($succeeded)['value']);

        expect((int) ShipmentItem::query()->where('order_item_id', $this->orderItemId)->sum('quantity'))->toBe(2);
    });
});
