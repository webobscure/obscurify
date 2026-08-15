<?php

namespace App\Domain\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationChannelRequest extends FormRequest
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
            'provider_id' => ['sometimes', 'nullable', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
