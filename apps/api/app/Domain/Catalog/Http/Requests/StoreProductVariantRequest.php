<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Intentionally has no `product_id`/`store_id` rules: ownership is
     * derived from the route and TenantContext, never accepted from
     * client input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $storeId = app(TenantContext::class)->storeId();

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_variants', 'sku')->where('store_id', $storeId),
            ],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'compare_at_price_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cost_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', new Enum(ProductStatus::class)],
            'option_value_ids' => ['sometimes', 'array'],
            'option_value_ids.*' => [
                'string',
                Rule::exists('product_option_values', 'id')->where('store_id', $storeId),
            ],
        ];
    }
}
