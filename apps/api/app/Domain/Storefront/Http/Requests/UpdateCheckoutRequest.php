<?php

namespace App\Domain\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately has no store_id/customer_id/price/total fields — none of
 * that is ever accepted from the client (see spec section 33).
 */
final class UpdateCheckoutRequest extends FormRequest
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
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            'shipping_address' => ['sometimes', 'array'],
            'shipping_address.first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_address.last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_address.phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'shipping_address.country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'shipping_address.region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_address.city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_address.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'shipping_address.address_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_address.address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],

            'billing_same_as_shipping' => ['sometimes', 'boolean'],

            'billing_address' => ['sometimes', 'array'],
            'billing_address.first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'billing_address.country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'billing_address.region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'billing_address.address_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address.address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
