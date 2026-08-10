<?php

use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Application\ApproveReturn;
use App\Domain\Returns\Application\CompleteReturn;
use App\Domain\Returns\Application\InspectReturn;
use App\Domain\Returns\Application\ReceiveReturn;
use App\Domain\Returns\Application\RequestReturn;
use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnDisposition as ReturnDispositionValue;
use App\Domain\Returns\Enums\ReturnReason;
use App\Domain\Returns\Models\ReturnDisposition;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Returns & Reverse Logistics Core (spec section 16): real PostgreSQL
 * concurrency — the same OrderItem must never be claimed by two
 * simultaneous return requests beyond what was actually shipped, and the
 * same disposition must never be applied (restocked) twice.
 */
beforeEach(function () {
    $this->withCredentials();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    domainForStore($this->store, 'concurrency-store.localhost');

    [$this->product, $this->variant] = productWithStock($this->store, 10);
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous return requests claim a scarce shipped quantity, never exceeding it', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('concurrency-store.localhost', $this->variant->id, $this->store, 2);
    shipViaFulfillment($this->user, $this->store, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();

    $order = app(TenantContext::class)->scope($this->store, fn () => Order::query()->whereKey($orderId)->firstOrFail());

    // Only 2 units were shipped — two simultaneous requests for 2 each
    // (4 total) can never both fit.
    $request = function () use ($order, $orderItemId) {
        return app(TenantContext::class)->scope($this->store, function () use ($order, $orderItemId) {
            $return = app(RequestReturn::class)->handle($order, [
                ['order_item_id' => $orderItemId, 'quantity' => 2, 'reason' => ReturnReason::Other->value],
            ], null, null);

            return $return->status->value;
        });
    };

    $results = runConcurrently([$request, $request]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    $failed = array_filter($results, fn ($r) => ! $r['ok']);

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    expect(current($failed)['error'])->toContain('return more');

    app(TenantContext::class)->scope($this->store, function () use ($orderItemId) {
        // Never oversubscribed: total quantity across every non-rejected,
        // non-cancelled ReturnRequest for this OrderItem never exceeds
        // what was actually shipped (2), regardless of which call won.
        $claimedTotal = (int) ReturnItem::query()
            ->where('order_item_id', $orderItemId)
            ->whereHas('returnRequest', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))
            ->sum('quantity');

        expect($claimedTotal)->toBe(2);
        expect(ReturnRequest::query()->count())->toBe(1);
    });
});

it('never restocks a return twice under two simultaneous complete calls', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('concurrency-store.localhost', $this->variant->id, $this->store, 2);
    shipViaFulfillment($this->user, $this->store, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();

    $returnId = app(TenantContext::class)->scope($this->store, function () use ($orderId, $orderItemId) {
        $order = Order::query()->whereKey($orderId)->firstOrFail();

        $return = app(RequestReturn::class)->handle($order, [
            ['order_item_id' => $orderItemId, 'quantity' => 2, 'reason' => ReturnReason::Other->value],
        ], null, null);

        $return = app(ApproveReturn::class)->handle($return);
        $return = app(ReceiveReturn::class)->handle($return);
        $returnItemId = $return->items->first()->id;

        app(InspectReturn::class)->handle($return, [
            ['return_item_id' => $returnItemId, 'condition' => ReturnCondition::New->value, 'disposition' => ReturnDispositionValue::Restock->value],
        ], null);

        return $return->id;
    });

    $complete = function () use ($returnId) {
        return app(TenantContext::class)->scope($this->store, function () use ($returnId) {
            $return = ReturnRequest::query()->whereKey($returnId)->firstOrFail();

            $return = app(CompleteReturn::class)->handle($return);

            return $return->status->value;
        });
    };

    $results = runConcurrently([$complete, $complete]);

    // Both calls succeed without error — the second is a genuine no-op
    // (the state machine allows same-state `completed -> completed`, and
    // applyDisposition() skips anything already applied), not a race that
    // errors one side out.
    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(2);

    app(TenantContext::class)->scope($this->store, function () use ($returnId) {
        $disposition = ReturnDisposition::query()
            ->whereHas('returnItem', fn ($q) => $q->where('return_request_id', $returnId))
            ->firstOrFail();

        expect($disposition->applied_at)->not->toBeNull();

        // Restocked exactly once: on_hand only reflects a single +2, and
        // exactly one movement row exists for it — never double-applied.
        expect((int) InventoryMovement::query()->where('reason', 'return_restocked')->sum('quantity_delta'))->toBe(2);
        expect(InventoryMovement::query()->where('reason', 'return_restocked')->count())->toBe(1);

        $level = InventoryLevel::query()->firstOrFail();
        expect($level->on_hand)->toBe(10); // 10 - 2 shipped + 2 restocked
    });
});
