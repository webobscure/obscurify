<?php

namespace App\Domain\Fulfillment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PackFulfillmentRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.fulfillment_item_id' => ['required', 'string'],
            'items.*.packed_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
