<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class AdjustInventoryRequest extends FormRequest
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
            'location_id' => [
                'required',
                'string',
                Rule::exists('locations', 'id')->where('store_id', app(TenantContext::class)->storeId()),
            ],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', new Enum(InventoryMovementReason::class)],
            'reference_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
