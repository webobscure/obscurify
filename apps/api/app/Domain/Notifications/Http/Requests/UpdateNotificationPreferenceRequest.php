<?php

namespace App\Domain\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationPreferenceRequest extends FormRequest
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
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'transactional_only' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'string', 'max:16'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'string', 'max:16'],
            'quiet_hours_timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
