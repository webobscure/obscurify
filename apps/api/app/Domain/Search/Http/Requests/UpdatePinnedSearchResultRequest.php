<?php

namespace App\Domain\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePinnedSearchResultRequest extends FormRequest
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
            'keyword' => ['sometimes', 'string', 'max:255'],
            'product_id' => ['sometimes', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
