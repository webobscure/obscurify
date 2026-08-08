<?php

namespace App\Domain\Storefront\Http\Requests;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddCartItemRequest extends FormRequest
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
            'variant_id' => [
                'required',
                'string',
                Rule::exists('product_variants', 'id')
                    ->where('store_id', app(TenantContext::class)->storeId())
                    ->where('status', ProductStatus::Active->value)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
