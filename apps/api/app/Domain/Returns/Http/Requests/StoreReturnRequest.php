<?php

namespace App\Domain\Returns\Http\Requests;

use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReturnRequest extends FormRequest
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
            'customer_id' => ['sometimes', 'nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            // order_item_id ownership and the shipped-minus-already-returned
            // quantity ceiling are checked in RequestReturn under a row
            // lock, not here — same division of responsibility as
            // StoreFulfillmentRequest.
            'items.*.order_item_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['required', 'string', Rule::in(array_column(ReturnReason::cases(), 'value'))],
            'items.*.condition' => ['sometimes', 'nullable', 'string', Rule::in(array_column(ReturnCondition::cases(), 'value'))],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
