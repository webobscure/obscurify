<?php

namespace App\Domain\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductOptionRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('product_options', 'name')
                    ->where('product_id', $this->route('product')->id)
                    ->ignore($this->route('option')),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
