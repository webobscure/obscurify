<?php

namespace App\Domain\RussianCommerce\Http\Requests;

use App\Domain\RussianCommerce\Enums\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFiscalizationSettingsRequest extends FormRequest
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
            'active_provider_id' => ['sometimes', 'nullable', 'string'],
            'default_vat_rate' => ['sometimes', Rule::enum(VatRate::class)],
            'receipts_required' => ['sometimes', 'boolean'],
        ];
    }
}
