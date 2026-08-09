<?php

namespace App\Domain\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Identifies *which* previously-quoted rate to select — never a price
 * (spec section 11: "do not trust frontend-submitted shipping price").
 * SelectShippingRate re-derives the actual price server-side from a fresh
 * rate calculation before persisting anything.
 */
final class SelectCheckoutShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:255'],
            'service_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_method_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
