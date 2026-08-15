<?php

namespace App\Domain\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSearchSettingsRequest extends FormRequest
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
            'active_provider_id' => ['sometimes', 'nullable', 'string'],
            'results_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'autocomplete_limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'typo_tolerance_enabled' => ['sometimes', 'boolean'],
            'synonyms_enabled' => ['sometimes', 'boolean'],
            'facets_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
