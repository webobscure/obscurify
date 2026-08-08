<?php

namespace App\Domain\Collections\Http\Requests;

use App\Domain\Collections\Enums\CollectionStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCollectionRequest extends FormRequest
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
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('collections', 'slug')
                    ->where('store_id', app(TenantContext::class)->storeId()),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', new Enum(CollectionStatus::class)],
        ];
    }
}
