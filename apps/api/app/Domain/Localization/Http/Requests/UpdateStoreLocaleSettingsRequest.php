<?php

namespace App\Domain\Localization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStoreLocaleSettingsRequest extends FormRequest
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
            'default_locale' => ['sometimes', 'string', 'exists:locales,code'],
            'admin_locale' => ['sometimes', 'nullable', 'string', 'exists:locales,code'],
            'storefront_locale' => ['sometimes', 'nullable', 'string', 'exists:locales,code'],
            'supported_locales' => ['sometimes', 'array'],
            'supported_locales.*' => ['string', 'exists:locales,code'],
        ];
    }
}
