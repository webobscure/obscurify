<?php

namespace App\Domain\CustomerIntelligence\Http\Requests;

use App\Domain\CustomerIntelligence\Http\Requests\Concerns\ValidatesSegmentRuleTree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerSegmentRequest extends FormRequest
{
    use ValidatesSegmentRuleTree;

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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['sometimes', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateRuleTree($validator));
    }
}
