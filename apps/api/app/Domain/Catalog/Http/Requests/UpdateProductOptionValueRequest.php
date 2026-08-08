<?php

namespace App\Domain\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductOptionValueRequest extends FormRequest
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
            'value' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('product_option_values', 'value')
                    ->where('product_option_id', $this->route('option')->id)
                    ->ignore($this->route('value')),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
