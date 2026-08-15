<?php

namespace App\Domain\Automation\Http\Requests;

use App\Domain\Automation\Http\Requests\Concerns\ValidatesWorkflowActionList;
use App\Domain\Automation\Http\Requests\Concerns\ValidatesWorkflowConditionTree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkflowRequest extends FormRequest
{
    use ValidatesWorkflowActionList, ValidatesWorkflowConditionTree;

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
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'trigger' => ['sometimes', 'nullable', 'array'],
            'trigger.event_type' => ['required_with:trigger', 'string', 'max:100'],
            'conditions' => ['sometimes', 'array'],
            'actions' => ['sometimes', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateConditionTree($validator);
            $this->validateActionList($validator);
        });
    }
}
