<?php

namespace App\Domain\Fulfillment\Http\Requests;

use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Support\ShippingProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Backs POST /fulfillments/{id}/complete — the one endpoint where
 * Fulfillment's HTTP layer necessarily knows about Shipping (it needs a
 * provider code to create the Shipment), the same cross-domain shape
 * StoreOrderShipmentRequest had before Milestone 7.
 *
 * `simulate` is a dev/test-only shipment-creation failure trigger (spec
 * section 15) — validated here only when
 * commerce.shipping.fake.failure_simulation.enabled, so the field is
 * rejected outright (unknown key) rather than silently ignored anywhere
 * that flag is off, including production by default.
 */
final class CompleteFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $registeredProviders = app(ShippingProviderRegistry::class)->registeredCodes();

        $rules = [
            'provider' => ['required', 'string', Rule::in($registeredProviders)],
        ];

        if ((bool) config('commerce.shipping.fake.failure_simulation.enabled')) {
            $rules['simulate'] = ['nullable', 'string', Rule::in([
                FakeShippingProvider::SIMULATE_CREATION_FAILURE,
                FakeShippingProvider::SIMULATE_CREATION_TIMEOUT,
            ])];
        }

        return $rules;
    }
}
