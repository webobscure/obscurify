<?php

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Models\PaymentTransaction;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * @return array{0: Payment, 1: Order}
 */
function paymentWithDetails(Store $store): array
{
    return app(TenantContext::class)->scope($store, function () {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'paid', 'external_payment_id' => 'fake_'.Str::ulid()]);

        PaymentAttempt::factory()->create(['payment_id' => $payment->id]);
        PaymentTransaction::factory()->create(['payment_id' => $payment->id]);

        return [$payment, $order];
    });
}

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    [$this->paymentA, $this->orderA] = paymentWithDetails($this->storeA);
    [$this->paymentB] = paymentWithDetails($this->storeB);
});

it('lists only the active store payments', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/payments', tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($this->paymentA->id)
        ->and($response->json('data.0.order_number'))->toBe($this->orderA->number);

    $response->assertJsonMissing(['id' => $this->paymentB->id]);
});

it('shows full payment detail: status, provider, amount, attempts, transactions', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/payments/{$this->paymentA->id}", tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data.status'))->toBe('paid')
        ->and($response->json('data.provider'))->toBe('fake')
        ->and($response->json('data.attempts'))->toHaveCount(1)
        ->and($response->json('data.transactions'))->toHaveCount(1);
});

it('does not let Store A view or list a Store B payment', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/payments', tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonMissing(['id' => $this->paymentB->id]);

    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/payments/{$this->paymentB->id}", tenantHeader($this->storeA))
        ->assertNotFound();
});
