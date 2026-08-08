<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCategoryRequest extends FormRequest
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
        $storeId = app(TenantContext::class)->storeId();

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('categories', 'slug')
                    ->where('store_id', $storeId)
                    ->ignore($this->route('category')),
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('categories', 'id')->where('store_id', $storeId),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
