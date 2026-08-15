<?php

namespace App\Domain\CustomerIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerTagRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
