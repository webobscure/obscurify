<?php

namespace App\Domain\CustomerIntelligence\Http\Requests;

use App\Domain\CustomerIntelligence\Enums\CustomerSegmentStatus;
use App\Domain\CustomerIntelligence\Http\Requests\Concerns\ValidatesSegmentRuleTree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerSegmentRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', Rule::in(array_column(CustomerSegmentStatus::cases(), 'value'))],
            'rules' => ['sometimes', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateRuleTree($validator));
    }
}
