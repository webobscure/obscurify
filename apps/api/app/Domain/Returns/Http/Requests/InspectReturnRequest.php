<?php

namespace App\Domain\Returns\Http\Requests;

use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnDisposition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InspectReturnRequest extends FormRequest
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
        return [
            'items' => ['required', 'array', 'min:1'],
            // return_item_id ownership and the not-already-inspected check
            // are done in InspectReturn under a row lock, not here.
            'items.*.return_item_id' => ['required', 'string'],
            'items.*.condition' => ['required', 'string', Rule::in(array_column(ReturnCondition::cases(), 'value'))],
            'items.*.photos' => ['sometimes', 'nullable', 'array'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'items.*.disposition' => ['required', 'string', Rule::in(array_column(ReturnDisposition::cases(), 'value'))],
            'items.*.disposition_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
