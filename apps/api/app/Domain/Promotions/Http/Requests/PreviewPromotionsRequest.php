<?php

namespace App\Domain\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewPromotionsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shipping_amount' => ['sometimes', 'integer', 'min:0'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'customer_id' => ['sometimes', 'nullable', 'string'],
            'discount_code' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
