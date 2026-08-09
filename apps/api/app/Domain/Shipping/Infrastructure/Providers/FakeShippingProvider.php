<?php

namespace App\Domain\Shipping\Infrastructure\Providers;

use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\Exceptions\MalformedShippingWebhookPayloadException;
use App\Domain\Shipping\Exceptions\ShipmentCreationFailedException;
use App\Domain\Shipping\Exceptions\ShippingRateCalculationFailedException;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Support\PickupPoint;
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
 * The reference ShippingProviderContract implementation — makes no
 * external HTTP requests, everything it does is local and deterministic,
 * but behaves conceptually like a real asynchronous carrier integration:
 * quote a weight/destination-aware rate, register a shipment, hand back
 * tracking, resolve every status change later via a signed webhook, never
 * through a direct model write. Mirrors FakePaymentProvider's shape.
 *
 * Only registered when `commerce.shipping.fake.enabled` is true — see
 * ShippingServiceProvider.
 */
final class FakeShippingProvider implements ShippingProviderContract
{
    public const string CODE = 'fake';

    private const string SIGNATURE_HEADER = 'X-Fake-Shipping-Signature';

    /**
     * Magic, dev/test-only trigger values (spec section 15) — never
     * interpreted unless commerce.shipping.fake.failure_simulation.enabled,
     * which is itself allowlisted to local/testing the same way
     * commerce.shipping.fake.enabled is. A real provider has no equivalent
     * concept; this exists purely so error-handling paths (checkout rate
     * failures, shipment-creation failures) are exercisable without
     * fabricating a broken network condition.
     */
    private const string SIMULATE_RATE_FAILURE_POSTAL_CODE = 'SIMFAIL-RATE';

    private const string SIMULATE_RATE_TIMEOUT_POSTAL_CODE = 'SIMFAIL-TIMEOUT';

    public const string SIMULATE_CREATION_FAILURE = 'creation_failure';

    public const string SIMULATE_CREATION_TIMEOUT = 'creation_timeout';

    public function code(): string
    {
        return self::CODE;
    }

    /**
     * Deterministic rule (spec section 2/8): for a service_code with an
     * entry in commerce.shipping.fake.services, price = base_price_amount
     * + price_per_kg_amount * ceil($context->weightKg) (billable weight
     * rounds up to the next whole kg, the common carrier convention),
     * with an international surcharge when the destination isn't
     * commerce.shipping.fake.domestic_country_code, then the existing
     * flat rate_markup_percent on top. A method whose service_code has no
     * entry in that table falls back to its own flat price_amount
     * unmodified (except for the markup) — the escape hatch for a
     * merchant-defined custom service this fake config doesn't know
     * about.
     *
     * @param  Collection<int, ShippingMethod>  $methods
     * @return Collection<int, ShippingRate>
     */
    public function calculateRates(Collection $methods, ShippingRateContext $context): Collection
    {
        $this->maybeSimulateRateFailure($context);

        $markupPercent = (int) config('commerce.shipping.fake.rate_markup_percent');
        $domesticCountry = (string) config('commerce.shipping.fake.domestic_country_code');
        $surchargePercent = (int) config('commerce.shipping.fake.international_surcharge_percent');
        $isInternational = strcasecmp($context->countryCode, $domesticCountry) !== 0;
        $billableKg = (int) ceil($context->weightKg);

        return $methods
            ->filter(fn (ShippingMethod $method) => $method->provider === self::CODE)
            ->map(function (ShippingMethod $method) use ($markupPercent, $isInternational, $surchargePercent, $billableKg, $context) {
                $service = $method->service_code !== null
                    ? config("commerce.shipping.fake.services.{$method->service_code}")
                    : null;

                if ($service !== null) {
                    $price = $service['base_price_amount'] + $billableKg * $service['price_per_kg_amount'];
                    $name = $service['name'];
                    $estimatedDaysMin = $service['estimated_days_min'];
                    $estimatedDaysMax = $service['estimated_days_max'];
                } else {
                    $price = $method->price_amount;
                    $name = $method->name;
                    $estimatedDaysMin = $method->estimated_days_min;
                    $estimatedDaysMax = $method->estimated_days_max;
                }

                if ($isInternational) {
                    $price = (int) round($price * (100 + $surchargePercent) / 100);
                }

                $price = (int) round($price * (100 + $markupPercent) / 100);

                $metadata = [
                    'weight_kg' => $context->weightKg,
                    'billable_weight_kg' => $billableKg,
                    'international' => $isInternational,
                ];

                if ($method->service_code === 'pickup') {
                    $metadata['pickup_points'] = $this->listPickupPoints($context)
                        ->map(fn (PickupPoint $point) => [
                            'id' => $point->id,
                            'name' => $point->name,
                            'address' => $point->address,
                            'city' => $point->city,
                            'country_code' => $point->countryCode,
                            'postal_code' => $point->postalCode,
                            'opening_hours' => $point->openingHours,
                            'latitude' => $point->latitude,
                            'longitude' => $point->longitude,
                        ])
                        ->values()
                        ->all();
                }

                return new ShippingRate(
                    provider: self::CODE,
                    serviceCode: $method->service_code,
                    methodId: $method->id,
                    name: $name,
                    priceAmount: $price,
                    currency: $method->currency,
                    estimatedDaysMin: $estimatedDaysMin,
                    estimatedDaysMax: $estimatedDaysMax,
                    metadata: $metadata,
                );
            })
            ->values();
    }

    /**
     * Static, deterministic network (spec section 5) — filtered to the
     * destination's own country, "matches the destination context" per
     * spec section 6 kept deliberately loose (country-level, not
     * postal-code proximity — no real geocoding). A provider/context with
     * no matching points returns an empty collection, same "omit, don't
     * error" policy as calculateRates().
     *
     * @return Collection<int, PickupPoint>
     */
    public function listPickupPoints(ShippingRateContext $context): Collection
    {
        return collect(config('commerce.shipping.fake.pickup_points', []))
            ->filter(fn (array $point) => strcasecmp($point['country_code'], $context->countryCode) === 0)
            ->map(fn (array $point) => new PickupPoint(
                id: $point['id'],
                name: $point['name'],
                address: $point['address'],
                city: $point['city'],
                countryCode: $point['country_code'],
                postalCode: $point['postal_code'] ?? null,
                openingHours: $point['opening_hours'] ?? null,
                latitude: $point['latitude'] ?? null,
                longitude: $point['longitude'] ?? null,
            ))
            ->values();
    }

    /**
     * Generates an external id and a deterministic fake tracking number —
     * never moves the Shipment's own status, matching
     * FakePaymentProvider::createPayment()'s division of responsibility.
     * Simulates a carrier API response (spec section 8): the metadata a
     * real provider's create-shipment call would typically hand back.
     *
     * @param  Collection<int, ShipmentItem>  $items
     */
    public function createShipment(Shipment $shipment, Collection $items): ShipmentCreationResult
    {
        $this->maybeSimulateCreationFailure($shipment);

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
     * $eventId/$timestamp are overridable so the same harness can build
     * duplicate-event (spec section 12) and out-of-order/stale (spec
     * section 13/14) test payloads without a second code path.
     *
     * @return array{payload: string, signature: string}
     */
    public function simulateWebhookPayload(
        string $externalShipmentId,
        string $outcome,
        ?string $eventId = null,
        ?int $timestamp = null,
    ): array {
        [$status, $description, $location] = match ($outcome) {
            'accepted' => ['accepted', 'Shipment accepted by carrier.', 'Origin warehouse'],
            'in_transit' => ['in_transit', 'Departed origin warehouse.', 'Sorting facility'],
            'out_for_delivery' => ['out_for_delivery', 'Out for delivery.', 'Local depot'],
            'delivered' => ['delivered', 'Shipment delivered.', 'Destination'],
            'delivery_exception' => ['delivery_exception', 'Delivery attempt unsuccessful — recipient unavailable.', 'Destination'],
            'failed' => ['failed', 'Delivery attempt failed.', null],
            'cancelled' => ['cancelled', 'Shipment cancelled by carrier.', null],
            default => throw new InvalidArgumentException("Unknown fake shipping outcome \"{$outcome}\"."),
        };

        $payload = [
            'event_id' => $eventId ?? (string) Str::ulid(),
            'external_shipment_id' => $externalShipmentId,
            'event_type' => 'shipment.updated',
            'status' => $status,
            'description' => $description,
            'location' => $location,
            'timestamp' => $timestamp ?? now()->timestamp,
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return ['payload' => $raw, 'signature' => $this->sign($raw)];
    }

    private function sign(string $rawPayload): string
    {
        return hash_hmac('sha256', $rawPayload, config('commerce.shipping.fake.secret'));
    }

    private function maybeSimulateRateFailure(ShippingRateContext $context): void
    {
        if (! (bool) config('commerce.shipping.fake.failure_simulation.enabled')) {
            return;
        }

        if ($context->postalCode === self::SIMULATE_RATE_FAILURE_POSTAL_CODE) {
            throw ShippingRateCalculationFailedException::make('simulated rate calculation failure.');
        }

        if ($context->postalCode === self::SIMULATE_RATE_TIMEOUT_POSTAL_CODE) {
            throw ShippingRateCalculationFailedException::make('simulated provider timeout.');
        }
    }

    /**
     * Reads the trigger from $shipment->metadata rather than an extra
     * method parameter — the contract's createShipment() signature stays
     * identical for every provider (spec section 1), a real provider
     * simply never populates this metadata key, so it's dead weight to
     * it, not a leaked concept. CreateShipment stashes it there only when
     * the caller explicitly requested it (see CompleteFulfillmentRequest)
     * and only while failure_simulation.enabled.
     */
    private function maybeSimulateCreationFailure(Shipment $shipment): void
    {
        if (! (bool) config('commerce.shipping.fake.failure_simulation.enabled')) {
            return;
        }

        $simulate = $shipment->metadata['simulate'] ?? null;

        if ($simulate === self::SIMULATE_CREATION_FAILURE) {
            throw ShipmentCreationFailedException::make('simulated provider rejection.');
        }

        if ($simulate === self::SIMULATE_CREATION_TIMEOUT) {
            throw ShipmentCreationFailedException::make('simulated provider timeout.');
        }
    }
}
