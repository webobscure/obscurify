<?php

namespace App\Domain\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSearchClickRequest extends FormRequest
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
            'search_query_id' => ['sometimes', 'nullable', 'string'],
            'product_id' => ['required', 'string'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
