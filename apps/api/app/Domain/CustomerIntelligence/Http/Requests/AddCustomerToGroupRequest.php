<?php

namespace App\Domain\CustomerIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddCustomerToGroupRequest extends FormRequest
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
            'customer_id' => ['required', 'string'],
        ];
    }
}
