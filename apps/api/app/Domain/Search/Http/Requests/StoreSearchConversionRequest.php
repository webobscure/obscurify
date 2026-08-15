<?php

namespace App\Domain\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSearchConversionRequest extends FormRequest
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
            'search_query_id' => ['required', 'string'],
            'product_id' => ['required', 'string'],
            'order_id' => ['required', 'string'],
        ];
    }
}
