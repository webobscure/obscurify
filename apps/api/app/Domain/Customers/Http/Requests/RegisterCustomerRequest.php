<?php

namespace App\Domain\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterCustomerRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            // Uniqueness against CustomerIdentity is checked and reported
            // by RegisterCustomer itself (a store-scoped, normalized-email
            // check that a plain unique: rule can't express here).
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
