<?php

namespace App\Domain\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerAddressRequest extends FormRequest
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
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'country_code' => ['required', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'is_default_billing' => ['sometimes', 'boolean'],
            'is_default_shipping' => ['sometimes', 'boolean'],
        ];
    }
}
