<?php

namespace App\Domain\Notifications\Http\Requests;

use App\Domain\Notifications\Enums\NotificationChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreNotificationTemplateRequest extends FormRequest
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
            'key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', new Enum(NotificationChannelType::class)],
            'locale' => ['sometimes', 'string', 'max:16'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body_text' => ['required', 'string'],
            'body_html' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
