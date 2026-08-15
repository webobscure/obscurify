<?php

namespace App\Domain\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreNotificationProviderRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'array'],
        ];
    }
}
