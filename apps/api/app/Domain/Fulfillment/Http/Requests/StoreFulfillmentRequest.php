<?php

namespace App\Domain\Fulfillment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFulfillmentRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            // order_item_id ownership and the ordered-quantity ceiling are
            // checked in CreateFulfillment under a row lock, not here —
            // same division of responsibility as StoreOrderShipmentRequest.
            'items.*.order_item_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
