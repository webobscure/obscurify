<?php

namespace App\Domain\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately accepts no body fields at all — completion never takes
 * price/total/customer_id/store_id/inventory data from the client
 * (spec section 33). The Idempotency-Key header is checked separately in
 * the controller since it isn't part of the JSON body.
 */
final class CompleteCheckoutRequest extends FormRequest
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
        return [];
    }
}
