<?php

namespace App\Domain\RussianCommerce\Http\Requests;

use App\Domain\RussianCommerce\Enums\FiscalReceiptItemPaymentSubject;
use App\Domain\RussianCommerce\Enums\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductFiscalProfileRequest extends FormRequest
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
            'vat_rate' => ['required', Rule::enum(VatRate::class)],
            'payment_subject' => ['required', Rule::enum(FiscalReceiptItemPaymentSubject::class)],
            'unit_of_measure' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
