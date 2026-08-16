<?php

namespace App\Domain\RussianCommerce\Http\Requests;

use App\Domain\RussianCommerce\Enums\RussianPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePaymentMethodSettingsRequest extends FormRequest
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
            'enabled_methods' => ['required', 'array'],
            'enabled_methods.*' => [Rule::enum(RussianPaymentMethod::class)],
        ];
    }
}
