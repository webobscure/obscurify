<?php

namespace App\Domain\Cms\Http\Requests;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
            'sections' => ['sometimes', 'array'],
            'page_template_id' => [
                'sometimes',
                'nullable',
                Rule::exists('page_templates', 'id')->where('store_id', app(TenantContext::class)->storeId()),
            ],
        ];
    }
}
