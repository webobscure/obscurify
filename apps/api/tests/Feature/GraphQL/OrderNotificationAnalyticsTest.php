<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationRecipient;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-orders.localhost';
    domainForStore($this->store, $this->host);
});

function graphqlRegisterCustomer(string $host, string $email): string
{
    $response = graphqlRequest($host, "mutation { registerCustomer(email: \"{$email}\", password: \"super-secret-1\") { accessToken customer { id } } }");

    return $response->json('data.registerCustomer.accessToken');
}

it('lets a customer see only their own orders, and a merchant see every order in the store', function () {
    $token = graphqlRegisterCustomer($this->host, 'buyer@example.test');

    $customerId = app(TenantContext::class)->scope($this->store, fn () => Customer::query()->where('email', 'buyer@example.test')->first()->id);

    app(TenantContext::class)->scope($this->store, function () use ($customerId) {
        Order::query()->create([
            'number' => 1, 'customer_id' => $customerId, 'currency' => 'USD',
            'items_subtotal_amount' => 1000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 1000, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'email' => 'buyer@example.test',
        ]);
        Order::query()->create([
            'number' => 2, 'customer_id' => null, 'currency' => 'USD',
            'items_subtotal_amount' => 2000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 2000, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'email' => 'someoneelse@example.test',
        ]);
    });

    $asCustomer = graphqlRequest($this->host, 'query { orders { data { number } pageInfo { total } } }', [], authHeader($token));
    expect(collect($asCustomer->json('data.orders.data'))->pluck('number')->all())->toBe([1]);

    $asMerchant = $this->postJson('http://api.localhost/api/graphql', ['query' => 'query { orders { data { number } pageInfo { total } } }'], array_merge(['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken], tenantHeader($this->store)));
    expect($asMerchant->json('data.orders.pageInfo.total'))->toBe(2);
});

it('lets a customer read and mark their own notifications read, and manage notification preferences', function () {
    $token = graphqlRegisterCustomer($this->host, 'notif-buyer@example.test');
    $customer = app(TenantContext::class)->scope($this->store, fn () => Customer::query()->where('email', 'notif-buyer@example.test')->first());

    $recipientId = app(TenantContext::class)->scope($this->store, function () use ($customer) {
        $notification = Notification::query()->create([
            'channel' => 'in_app', 'body_text' => 'Your order shipped!', 'triggered_by' => 'admin', 'status' => 'delivered',
        ]);

        return NotificationRecipient::query()->create([
            'notification_id' => $notification->id, 'recipient_type' => 'customer', 'customer_id' => $customer->id,
        ])->id;
    });

    $list = graphqlRequest($this->host, 'query { notifications { data { id readAt notification { bodyText } } } }', [], authHeader($token));
    expect($list->json('data.notifications.data.0.notification.bodyText'))->toBe('Your order shipped!');
    expect($list->json('data.notifications.data.0.readAt'))->toBeNull();

    $markRead = graphqlRequest($this->host, "mutation { markNotificationRead(recipientId: \"{$recipientId}\") { readAt } }", [], authHeader($token));
    expect($markRead->json('data.markNotificationRead.readAt'))->not->toBeNull();

    $prefs = graphqlRequest($this->host, 'mutation { updateNotificationPreferences(emailEnabled: false, marketingOptIn: true) { emailEnabled marketingOptIn } }', [], authHeader($token));
    expect($prefs->json('data.updateNotificationPreferences.emailEnabled'))->toBeFalse();
    expect($prefs->json('data.updateNotificationPreferences.marketingOptIn'))->toBeTrue();
});

it('enforces the merchant-only @auth directive on the analytics query', function () {
    $token = graphqlRegisterCustomer($this->host, 'analytics-buyer@example.test');

    $asCustomer = graphqlRequest($this->host, 'query { analytics(reportType: "orders") { status } }', [], authHeader($token));
    expect($asCustomer->json('data.analytics'))->toBeNull();
    expect($asCustomer->json('errors.0.message'))->toBe('This field requires the "merchant" role.');

    $asGuest = graphqlRequest($this->host, 'query { analytics(reportType: "orders") { status } }');
    expect($asGuest->json('errors.0.message'))->toBe('This field requires the "merchant" role.');
});
