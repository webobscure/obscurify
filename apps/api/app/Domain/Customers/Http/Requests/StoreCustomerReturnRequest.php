<?php

namespace App\Domain\Customers\Http\Requests;

use App\Domain\Returns\Enums\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Same item-level shape as the admin's StoreReturnRequest, minus
 * `customer_id` — the caller is always whoever the bearer token
 * authenticates as (RequestCustomerReturn), never a value from the body.
 */
final class StoreCustomerReturnRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['required', 'string', Rule::in(array_column(ReturnReason::cases(), 'value'))],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
