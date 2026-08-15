<?php

namespace App\Domain\CustomerIntelligence\Http\Requests;

use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Http\Requests\Concerns\ValidatesSegmentRuleTree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerGroupRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(array_column(CustomerGroupType::cases(), 'value'))],
            'rules' => ['sometimes', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') === CustomerGroupType::Manual->value && $this->filled('rules')) {
                $validator->errors()->add('rules', 'A manual group cannot have rules — add members explicitly instead.');

                return;
            }

            $this->validateRuleTree($validator);
        });
    }
}
