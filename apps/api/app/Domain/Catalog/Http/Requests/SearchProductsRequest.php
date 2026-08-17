<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Merchant Admin Products list (docs/design/DESIGN_SYSTEM.md Products
 * redesign) — every filter is optional and ANDed together, same
 * convention as SearchCustomersRequest. `collection_id` isn't validated
 * against `exists:collections,id` here on purpose: a nonexistent/foreign
 * id just yields zero matches via whereHas, which is simpler and no less
 * safe than a 422 for what's a pure read filter.
 */
final class SearchProductsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', new Enum(ProductStatus::class)],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collection_id' => ['sometimes', 'nullable', 'string'],
            'sort' => ['sometimes', 'nullable', 'in:created_at,-created_at,updated_at,-updated_at,title,-title'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
