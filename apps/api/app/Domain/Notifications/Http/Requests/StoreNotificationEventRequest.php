<?php

namespace App\Domain\Notifications\Http\Requests;

use App\Domain\Notifications\Enums\NotificationChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreNotificationEventRequest extends FormRequest
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
            'event_type' => ['required', 'string', 'max:255'],
            'channel' => ['required', new Enum(NotificationChannelType::class)],
            'template_id' => ['required', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
