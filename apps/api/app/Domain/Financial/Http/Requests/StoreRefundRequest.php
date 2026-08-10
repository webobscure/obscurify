<?php

namespace App\Domain\Financial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRefundRequest extends FormRequest
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
            // return_item_id ownership, the returned-minus-already-refunded
            // quantity ceiling, and the payment's remaining refundable
            // balance are all checked in RequestRefund under row locks,
            // not here — same division of responsibility as every other
            // Store*Request in this codebase.
            'items' => ['sometimes', 'array'],
            'items.*.return_item_id' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.amount' => ['required_with:items', 'integer', 'min:1'],
            'shipping_amount' => ['sometimes', 'integer', 'min:0'],
            'adjustment_amount' => ['sometimes', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
            // Absent/null provider = manual refund (spec section 11) — no
            // provider whitelist here, ProcessRefundWebhook's own registry
            // resolve() is where an unknown provider code actually fails.
            'provider' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
