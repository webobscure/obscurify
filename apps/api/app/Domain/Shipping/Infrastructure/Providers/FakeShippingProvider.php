<?php

namespace App\Domain\Shipping\Infrastructure\Providers;

use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\Exceptions\MalformedShippingWebhookPayloadException;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Support\ShipmentCreationResult;
use App\Domain\Shipping\Support\ShippingRate;
use App\Domain\Shipping\Support\ShippingRateContext;
use App\Domain\Shipping\Support\TrackingWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Makes no external HTTP requests — everything it does is local and
 * deterministic. Behaves conceptually like a real carrier (quote a rate,
 * register a shipment, hand back tracking, resolve status changes later
 * via an async, signed webhook) so the rest of the Shipping module never
 * needs to know it isn't real. Mirrors FakePaymentProvider's shape.
 *
 * Only registered when `commerce.shipping.fake.enabled` is true — see
 * ShippingServiceProvider.
 */
final class FakeShippingProvider implements ShippingProviderContract
{
    public const string CODE = 'fake';

    private const string SIGNATURE_HEADER = 'X-Fake-Shipping-Signature';

    public function code(): string
    {
        return self::CODE;
    }

    /**
     * Deterministic rule (spec section 8): each method's own flat
     * price_amount plus a configurable provider markup — not scattered
     * hardcoded constants, and not weight/dimension-based, since every
     * ShippingMethod price is flat this milestone (see ShippingRateContext
     * docblock for why). $context is accepted for contract-shape parity
     * with a real provider that *would* price by destination, but this
     * fake doesn't vary price by destination — zone/method eligibility
     * already filtered $methods down to what's offered there before this
     * is ever called.
     *
     * @param  Collection<int, ShippingMethod>  $methods
     * @return Collection<int, ShippingRate>
     */
    public function calculateRates(Collection $methods, ShippingRateContext $context): Collection
    {
        $markupPercent = (int) config('commerce.shipping.fake.rate_markup_percent');

        return $methods
            ->filter(fn (ShippingMethod $method) => $method->provider === self::CODE)
            ->map(function (ShippingMethod $method) use ($markupPercent) {
                $price = (int) round($method->price_amount * (100 + $markupPercent) / 100);

                return new ShippingRate(
                    provider: self::CODE,
                    serviceCode: $method->service_code,
                    methodId: $method->id,
                    name: $method->name,
                    priceAmount: $price,
                    currency: $method->currency,
                    estimatedDaysMin: $method->estimated_days_min,
                    estimatedDaysMax: $method->estimated_days_max,
                );
            })
            ->values();
    }

    /**
     * Generates an external id and a deterministic fake tracking number —
     * never moves the Shipment's own status, matching
     * FakePaymentProvider::createPayment()'s division of responsibility.
     *
     * @param  Collection<int, ShipmentItem>  $items
     */
    public function createShipment(Shipment $shipment, Collection $items): ShipmentCreationResult
    {
        $externalShipmentId = 'fake_ship_'.(string) Str::ulid();
        $trackingNumber = 'FAKE'.strtoupper(Str::random(10));

        return new ShipmentCreationResult(
            externalShipmentId: $externalShipmentId,
            trackingNumber: $trackingNumber,
            trackingUrl: "/fake-shipments/{$externalShipmentId}",
        );
    }

    public function cancelShipment(Shipment $shipment): void
    {
        // Nothing external to call — cancellation is a synchronous,
        // merchant-initiated action handled entirely by CancelShipment;
        // this exists for contract completeness, same as
        // FakePaymentProvider::cancelPayment().
    }

    /**
     * Not exercised this milestone — the fake provider is webhook-driven
     * only (spec section 22); tracking history comes entirely from
     * TrackingEvent rows written by ProcessShippingWebhook. Implemented
     * for contract completeness, same as FakePaymentProvider's no-op
     * methods.
     *
     * @return Collection<int, TrackingWebhookEvent>
     */
    public function getTracking(Shipment $shipment): Collection
    {
        return new Collection;
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($request->getContent()), $signature);
    }

    public function parseWebhook(Request $request): TrackingWebhookEvent
    {
        $data = json_decode($request->getContent(), true);

        if (! is_array($data)) {
            throw MalformedShippingWebhookPayloadException::make('body is not a JSON object.');
        }

        foreach (['event_id', 'external_shipment_id', 'event_type', 'status', 'timestamp'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw MalformedShippingWebhookPayloadException::make("missing field \"{$field}\".");
            }
        }

        if (! is_numeric($data['timestamp'])) {
            throw MalformedShippingWebhookPayloadException::make('"timestamp" is not numeric.');
        }

        return new TrackingWebhookEvent(
            eventId: (string) $data['event_id'],
            externalShipmentId: (string) $data['external_shipment_id'],
            eventType: (string) $data['event_type'],
            status: (string) $data['status'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            occurredAt: Carbon::createFromTimestamp((int) $data['timestamp']),
        );
    }

    /**
     * Dev/test-only harness: builds and signs a webhook payload for a
     * simulated shipment status change. A real provider would never expose
     * this — only our own fake shipment control page needs it — which is
     * exactly why it lives here and not on ShippingProviderContract.
     * Mirrors FakePaymentProvider::simulateWebhookPayload().
     *
     * @return array{payload: string, signature: string}
     */
    public function simulateWebhookPayload(string $externalShipmentId, string $outcome): array
    {
        [$status, $description, $location] = match ($outcome) {
            'in_transit' => ['in_transit', 'Shipment picked up by carrier.', 'Sorting facility'],
            'delivered' => ['delivered', 'Shipment delivered.', 'Destination'],
            'failed' => ['failed', 'Delivery attempt failed.', null],
            'cancelled' => ['cancelled', 'Shipment cancelled by carrier.', null],
            default => throw new InvalidArgumentException("Unknown fake shipping outcome \"{$outcome}\"."),
        };

        $payload = [
            'event_id' => (string) Str::ulid(),
            'external_shipment_id' => $externalShipmentId,
            'event_type' => 'shipment.updated',
            'status' => $status,
            'description' => $description,
            'location' => $location,
            'timestamp' => now()->timestamp,
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return ['payload' => $raw, 'signature' => $this->sign($raw)];
    }

    private function sign(string $rawPayload): string
    {
        return hash_hmac('sha256', $rawPayload, config('commerce.shipping.fake.secret'));
    }
}
