<?php

namespace App\Domain\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin customer search (spec section 10): tags, groups, segments,
 * metrics. Every filter is optional and ANDed together — see
 * AdminCustomerController::index().
 */
final class SearchCustomersRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,disabled'],
            'tag' => ['sometimes', 'nullable', 'string'],
            'group_id' => ['sometimes', 'nullable', 'string'],
            'segment_id' => ['sometimes', 'nullable', 'string'],
            'min_total_spent' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_total_spent' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'min_order_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'min_lifetime_value' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
