<?php

namespace App\Domain\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSearchSynonymRequest extends FormRequest
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
            'term' => ['sometimes', 'string', 'max:255'],
            'synonyms' => ['sometimes', 'array', 'min:1'],
            'synonyms.*' => ['string', 'max:255'],
            'is_bidirectional' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
