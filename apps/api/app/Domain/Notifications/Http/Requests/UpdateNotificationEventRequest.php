<?php

namespace App\Domain\Notifications\Http\Requests;

use App\Domain\Notifications\Enums\NotificationChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateNotificationEventRequest extends FormRequest
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
            'event_type' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', new Enum(NotificationChannelType::class)],
            'template_id' => ['sometimes', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
