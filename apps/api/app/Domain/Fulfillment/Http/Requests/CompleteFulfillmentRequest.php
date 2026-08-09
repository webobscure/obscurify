<?php

namespace App\Domain\Fulfillment\Http\Requests;

use App\Domain\Shipping\Support\ShippingProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Backs POST /fulfillments/{id}/complete — the one endpoint where
 * Fulfillment's HTTP layer necessarily knows about Shipping (it needs a
 * provider code to create the Shipment), the same cross-domain shape
 * StoreOrderShipmentRequest had before Milestone 7.
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

        return [
            'provider' => ['required', 'string', Rule::in($registeredProviders)],
        ];
    }
}
