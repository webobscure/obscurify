<?php

namespace App\Domain\Search\Http\Requests;

use App\Domain\Search\Enums\SearchRuleAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreSearchRuleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'action' => ['required', new Enum(SearchRuleAction::class)],
            'product_id' => ['required', 'string'],
            'boost_amount' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
