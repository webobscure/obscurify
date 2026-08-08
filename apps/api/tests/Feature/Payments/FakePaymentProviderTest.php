<?php

use App\Domain\Payments\Exceptions\MalformedWebhookPayloadException;
use App\Domain\Payments\Infrastructure\Providers\FakePaymentProvider;
use App\Domain\Payments\Jobs\SimulateFakePaymentWebhookJob;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\PaymentProviderRegistry;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->provider = app(FakePaymentProvider::class);
});

it('is registered under the "fake" code when enabled', function () {
    expect(app(PaymentProviderRegistry::class)->has('fake'))->toBeTrue()
        ->and($this->provider->code())->toBe('fake');
});

it('createPayment generates a fake external id and a relative fake-payments redirect, never marking it paid', function () {
    $user = User::factory()->create();
    $store = createStoreForUser($user);

    app(TenantContext::class)->scope($store, function () {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $result = $this->provider->createPayment($payment);

        expect($result->externalPaymentId)->toStartWith('fake_')
            ->and($result->redirectUrl)->toBe("/fake-payments/{$result->externalPaymentId}");
    });
});

it('builds a signed payload per outcome, mapping delayed_success to a succeeded status', function () {
    foreach ([
        'success' => 'succeeded',
        'delayed_success' => 'succeeded',
        'failure' => 'failed',
        'cancelled' => 'cancelled',
        'pending' => 'pending',
    ] as $outcome => $expectedStatus) {
        $sim = $this->provider->simulateWebhookPayload('fake_abc', $outcome, 1000, 'RUB');
        $decoded = json_decode($sim['payload'], true);

        expect($decoded['status'])->toBe($expectedStatus)
            ->and($decoded['external_payment_id'])->toBe('fake_abc')
            ->and($sim['signature'])->toBe(hash_hmac('sha256', $sim['payload'], config('payments.fake.secret')));
    }
});

it('rejects an unknown simulated outcome', function () {
    expect(fn () => $this->provider->simulateWebhookPayload('fake_abc', 'teleport', 1000, 'RUB'))
        ->toThrow(InvalidArgumentException::class);
});

it('verifies a correctly signed request and rejects a tampered or missing signature', function () {
    $sim = $this->provider->simulateWebhookPayload('fake_abc', 'success', 1000, 'RUB');

    $valid = Request::create('/x', 'POST', content: $sim['payload']);
    $valid->headers->set('X-Fake-Signature', $sim['signature']);
    expect($this->provider->verifyWebhook($valid))->toBeTrue();

    $tampered = Request::create('/x', 'POST', content: $sim['payload']);
    $tampered->headers->set('X-Fake-Signature', 'wrong');
    expect($this->provider->verifyWebhook($tampered))->toBeFalse();

    $missing = Request::create('/x', 'POST', content: $sim['payload']);
    expect($this->provider->verifyWebhook($missing))->toBeFalse();
});

it('parses a valid webhook body into a provider-neutral WebhookEvent', function () {
    $sim = $this->provider->simulateWebhookPayload('fake_abc', 'success', 2500, 'USD');
    $request = Request::create('/x', 'POST', content: $sim['payload']);

    $event = $this->provider->parseWebhook($request);

    expect($event->externalPaymentId)->toBe('fake_abc')
        ->and($event->status)->toBe('succeeded')
        ->and($event->amount)->toBe(2500)
        ->and($event->currency)->toBe('USD');
});

it('throws on a malformed webhook body', function () {
    $request = Request::create('/x', 'POST', content: json_encode(['not' => 'a payment event']));

    expect(fn () => $this->provider->parseWebhook($request))->toThrow(MalformedWebhookPayloadException::class);
});

it('throws on a non-JSON webhook body', function () {
    $request = Request::create('/x', 'POST', content: 'not json at all');

    expect(fn () => $this->provider->parseWebhook($request))->toThrow(MalformedWebhookPayloadException::class);
});

it('dispatches a delayed job for the delayed_success outcome instead of processing immediately', function () {
    Queue::fake();

    $user = User::factory()->create();
    $store = createStoreForUser($user);
    domainForStore($store, 'delayed-success.localhost');
    [, $variant] = productWithStock($store, 5);

    $this->withCredentials();
    ['order_id' => $orderId] = completedOrderFor('delayed-success.localhost', $variant->id);

    $payment = $this->postJson(
        storefrontUrl('delayed-success.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'delayed-key'],
    )->assertCreated();

    $externalPaymentId = Str::after($payment->json('data.redirect_url'), '/fake-payments/');

    $this->postJson("/api/v1/fake-payments/{$externalPaymentId}/outcome", ['outcome' => 'delayed_success'])
        ->assertOk()
        ->assertJsonPath('data.dispatched', true);

    Queue::assertPushed(SimulateFakePaymentWebhookJob::class, fn ($job) => $job->externalPaymentId === $externalPaymentId && $job->outcome === 'success');

    app(TenantContext::class)->scope($store, function () use ($externalPaymentId) {
        expect(Payment::query()->where('external_payment_id', $externalPaymentId)->firstOrFail()->status->value)->toBe('processing');
    });
});
