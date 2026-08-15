<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Models\NotificationDelivery;

/**
 * Provider-neutral send boundary (spec section 2) — mirrors
 * PaymentProviderContract/ShippingProviderContract's own "only the
 * operations every real provider needs" discipline. Deliberately
 * synchronous and outcome-only: no real provider is integrated this
 * milestone (spec: "Do NOT integrate with real email/SMS providers
 * yet"), so there is no async webhook-confirmation half of this
 * contract the way Payments has — see docs/adr/027-notification-center.md.
 */
interface NotificationProviderContract
{
    /**
     * Registry key, e.g. "fake", "smtp".
     */
    public function code(): string;

    /**
     * @param  array<string, mixed>  $providerConfig  the owning NotificationProvider row's own `config`
     */
    public function send(NotificationDelivery $delivery, RenderedNotificationMessage $message, array $providerConfig): NotificationSendResult;
}
