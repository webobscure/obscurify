<?php

namespace App\Domain\Notifications\Support\Providers;

use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Models\NotificationProvider;
use App\Domain\Notifications\Support\NotificationProviderContract;
use App\Domain\Notifications\Support\NotificationSendResult;
use App\Domain\Notifications\Support\RenderedNotificationMessage;
use Illuminate\Support\Str;

/**
 * Makes no external HTTP/SMTP/SMS calls — everything is local and
 * deterministic, the default reference implementation for every
 * channel (spec: "Implement provider abstractions with a
 * FakeNotificationProvider as the default reference implementation").
 * Unlike FakePaymentProvider/FakeShippingProvider (async, resolved
 * later via a signed webhook), a notification send is synchronous by
 * nature even for real providers — an SMTP/API call either succeeds or
 * fails on the spot — so this fake resolves immediately too.
 *
 * Deterministic, test-controllable failure: an address ending in
 * `@fail.test`, or exactly the reserved sentinel phone number
 * `+10000000000`, always fails. Everything else always succeeds. A
 * null address (the webhook channel has none — the provider itself
 * owns the destination) always succeeds.
 */
final class FakeNotificationProvider implements NotificationProviderContract
{
    public const string CODE = 'fake';

    private const string FAIL_EMAIL_SUFFIX = '@fail.test';

    private const string FAIL_PHONE = '+10000000000';

    public function code(): string
    {
        return self::CODE;
    }

    public function send(NotificationDelivery $delivery, RenderedNotificationMessage $message, array $providerConfig): NotificationSendResult
    {
        if ($this->isReservedFailureAddress($message->address)) {
            return NotificationSendResult::failure('The fake provider was asked to simulate a failed delivery for this address.');
        }

        return NotificationSendResult::success(
            externalId: NotificationProvider::FAKE.'_'.(string) Str::ulid(),
            responseMeta: ['simulated' => true],
        );
    }

    private function isReservedFailureAddress(?string $address): bool
    {
        if ($address === null) {
            return false;
        }

        return str_ends_with($address, self::FAIL_EMAIL_SUFFIX) || $address === self::FAIL_PHONE;
    }
}
