<?php

namespace App\Domain\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Merchant Admin Products editor's Inventory section (docs/design/
 * DESIGN_SYSTEM.md Products redesign) needs the inventory items for a
 * specific set of variants, not the whole store's inventory one page at
 * a time — `?product_variant_id[]=` scopes the list to exactly that and
 * skips pagination, since the result is bounded by however many ids the
 * caller passed (one product's variants), not by store-wide inventory
 * size. Omitting the filter keeps today's unfiltered, paginated
 * behavior unchanged for any other caller.
 */
final class IndexInventoryRequest extends FormRequest
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
            'product_variant_id' => ['sometimes', 'array'],
            'product_variant_id.*' => ['string'],
        ];
    }
}
