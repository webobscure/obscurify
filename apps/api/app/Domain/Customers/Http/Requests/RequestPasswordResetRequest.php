<?php

namespace App\Domain\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestPasswordResetRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
        ];
    }
}
