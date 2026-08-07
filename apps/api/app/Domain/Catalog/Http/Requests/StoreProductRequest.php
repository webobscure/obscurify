<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Intentionally has no `store_id` rule: ownership is derived from
     * TenantContext, never accepted from client input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('products', 'slug')
                    ->where('store_id', app(TenantContext::class)->storeId()),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', new Enum(ProductStatus::class)],
        ];
    }
}
