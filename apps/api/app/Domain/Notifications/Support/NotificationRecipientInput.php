<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Enums\NotificationRecipientType;

/**
 * One recipient to fan a Notification out to. `customer()`'s address
 * override lets a caller (e.g. an automation action) target a
 * customer's alternate address without touching NotificationDispatcher's
 * own default resolution (Customer.email for Email, Customer.phone for
 * Sms); `adHoc()` is a raw address with no Customer row at all (an
 * admin-composed one-off, or a channel like Webhook that has no
 * customer-facing address concept).
 */
final readonly class NotificationRecipientInput
{
    private function __construct(
        public NotificationRecipientType $type,
        public ?string $customerId,
        public ?string $addressOverride,
    ) {}

    public static function customer(string $customerId, ?string $addressOverride = null): self
    {
        return new self(NotificationRecipientType::Customer, $customerId, $addressOverride);
    }

    public static function adHoc(?string $address): self
    {
        return new self(NotificationRecipientType::AdHoc, null, $address);
    }
}
