<?php

namespace App\Domain\Notifications\Http\Requests;

use App\Domain\Notifications\Enums\NotificationChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreNotificationRequest extends FormRequest
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
            'channel' => ['required', new Enum(NotificationChannelType::class)],
            'customer_id' => ['sometimes', 'nullable', 'string'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'template_id' => ['sometimes', 'nullable', 'string'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body_text' => ['required_without:template_id', 'nullable', 'string'],
            'body_html' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
