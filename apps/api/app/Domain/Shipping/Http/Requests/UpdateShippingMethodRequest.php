<?php

namespace App\Domain\Shipping\Http\Requests;

use App\Domain\Shipping\Enums\ShippingMethodStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateShippingMethodRequest extends FormRequest
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
        $storeId = app(TenantContext::class)->storeId();
        $methodId = $this->route('method')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('shipping_methods', 'code')->where('store_id', $storeId)->ignore($methodId),
            ],
            'service_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', new Enum(ShippingMethodStatus::class)],
            'price_amount' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'estimated_days_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estimated_days_max' => ['sometimes', 'nullable', 'integer', 'gte:estimated_days_min'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'zone_ids' => ['sometimes', 'array'],
            'zone_ids.*' => [
                'string',
                Rule::exists('shipping_zones', 'id')->where('store_id', $storeId),
            ],
        ];
    }
}
