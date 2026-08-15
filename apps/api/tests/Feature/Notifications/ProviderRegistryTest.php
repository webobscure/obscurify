<?php

use App\Domain\Notifications\Exceptions\UnknownNotificationProviderException;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Support\NotificationProviderRegistry;
use App\Domain\Notifications\Support\Providers\FakeNotificationProvider;
use App\Domain\Notifications\Support\RenderedNotificationMessage;

it('has the fake provider registered by default in this environment', function () {
    $registry = app(NotificationProviderRegistry::class);

    expect($registry->has('fake'))->toBeTrue();
    expect($registry->resolve('fake'))->toBeInstanceOf(FakeNotificationProvider::class);
});

it('throws for an unregistered provider code', function () {
    $registry = app(NotificationProviderRegistry::class);

    expect($registry->has('twilio'))->toBeFalse();
    expect(fn () => $registry->resolve('twilio'))->toThrow(UnknownNotificationProviderException::class);
});

it('the fake provider succeeds for an ordinary address', function () {
    $provider = new FakeNotificationProvider;
    $delivery = new NotificationDelivery;
    $message = new RenderedNotificationMessage('customer@example.test', 'Subject', 'Body', null);

    $result = $provider->send($delivery, $message, []);

    expect($result->success)->toBeTrue();
    expect($result->externalId)->toStartWith('fake_');
});

it('the fake provider fails deterministically for the reserved failure email suffix', function () {
    $provider = new FakeNotificationProvider;
    $delivery = new NotificationDelivery;
    $message = new RenderedNotificationMessage('anything@fail.test', 'Subject', 'Body', null);

    $result = $provider->send($delivery, $message, []);

    expect($result->success)->toBeFalse();
    expect($result->errorMessage)->not->toBeNull();
});

it('the fake provider fails deterministically for the reserved failure phone number', function () {
    $provider = new FakeNotificationProvider;
    $delivery = new NotificationDelivery;
    $message = new RenderedNotificationMessage('+10000000000', null, 'Body', null);

    expect($provider->send($delivery, $message, [])->success)->toBeFalse();
});

it('the fake provider succeeds for a null address (e.g. the webhook channel)', function () {
    $provider = new FakeNotificationProvider;
    $delivery = new NotificationDelivery;
    $message = new RenderedNotificationMessage(null, null, 'Body', null);

    expect($provider->send($delivery, $message, [])->success)->toBeTrue();
});
