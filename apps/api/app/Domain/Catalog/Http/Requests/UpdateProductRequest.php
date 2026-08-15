<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Intentionally has no `store_id` rule: ownership is derived from
     * TenantContext and immutable after creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('products', 'slug')
                    ->where('store_id', app(TenantContext::class)->storeId())
                    ->ignore($this->route('product')),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'status' => ['sometimes', new Enum(ProductStatus::class)],
        ];
    }
}
