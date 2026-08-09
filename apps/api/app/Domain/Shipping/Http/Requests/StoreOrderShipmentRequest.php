<?php

namespace App\Domain\Shipping\Http\Requests;

use App\Domain\Shipping\Support\ShippingProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrderShipmentRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            // order_item_id ownership (must belong to *this* order) is
            // checked in CreateShipment under a row lock, not here —
            // the same division of responsibility as AdjustInventoryRequest
            // leaving item/location pairing to its action.
            'lines.*.order_item_id' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
