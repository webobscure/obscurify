<?php

namespace App\Domain\Webhooks\Http\Requests;

use App\Domain\Webhooks\Enums\WebhookSubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'target_url' => ['sometimes', 'string', 'url', 'max:2048'],
            'event_types' => ['sometimes', 'array', 'min:1'],
            'event_types.*' => ['required', 'string'],
            'status' => ['sometimes', new Enum(WebhookSubscriptionStatus::class)],
        ];
    }
}
