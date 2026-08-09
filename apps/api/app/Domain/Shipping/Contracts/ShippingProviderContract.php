<?php

namespace App\Domain\Shipping\Contracts;

use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Support\ShipmentCreationResult;
use App\Domain\Shipping\Support\ShippingRate;
use App\Domain\Shipping\Support\ShippingRateContext;
use App\Domain\Shipping\Support\TrackingWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Provider-neutral shipping carrier boundary. Deliberately not modeled
 * around any one real carrier's naming (e.g. CDEK's own API shape) — only
 * the operations every provider we plan to integrate needs are here; do
 * not add speculative methods ahead of a concrete provider that needs
 * them (same discipline as PaymentProviderContract).
 */
interface ShippingProviderContract
{
    /**
     * Registry key, e.g. "fake", "cdek".
     */
    public function code(): string;

    /**
     * Prices every given method against the destination in $context. A
     * method this provider can't currently quote (e.g. destination out of
     * carrier range) is simply omitted from the result, not an error —
     * "no methods available" is a caller-level concern (spec section 43),
     * not a per-method one.
     *
     * @param  Collection<int, ShippingMethod>  $methods
     * @return Collection<int, ShippingRate>
     */
    public function calculateRates(Collection $methods, ShippingRateContext $context): Collection;

    /**
     * Registers the shipment with the provider and returns tracking
     * details. Must not itself move the Shipment's status — CreateShipment
     * does that once this returns successfully, the same division of
     * responsibility as PaymentProviderContract::createPayment().
     *
     * @param  Collection<int, ShipmentItem>  $items
     */
    public function createShipment(Shipment $shipment, Collection $items): ShipmentCreationResult;

    public function cancelShipment(Shipment $shipment): void;

    /**
     * Current tracking snapshot, for a provider that supports polling in
     * addition to webhooks. Fake/webhook-only providers may return an
     * empty collection — tracking history in that case comes entirely
     * from parseWebhook() → TrackingEvent rows.
     *
     * @return Collection<int, TrackingWebhookEvent>
     */
    public function getTracking(Shipment $shipment): Collection;

    /**
     * Constant-time signature check against the raw request. Must be
     * called, and must pass, before parseWebhook() is trusted.
     */
    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): TrackingWebhookEvent;
}
