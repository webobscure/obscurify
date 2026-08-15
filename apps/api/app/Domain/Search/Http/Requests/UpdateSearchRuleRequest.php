<?php

namespace App\Domain\Search\Http\Requests;

use App\Domain\Search\Enums\SearchRuleAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateSearchRuleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'action' => ['sometimes', new Enum(SearchRuleAction::class)],
            'product_id' => ['sometimes', 'string'],
            'boost_amount' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
