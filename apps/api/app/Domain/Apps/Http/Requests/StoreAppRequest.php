<?php

namespace App\Domain\Apps\Http\Requests;

use App\Domain\Apps\Enums\AppType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreAppRequest extends FormRequest
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
            'type' => ['sometimes', new Enum(AppType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
            'developer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'redirect_urls' => ['required', 'array', 'min:1'],
            'redirect_urls.*' => ['required', 'string', 'url'],
            'requested_scopes' => ['sometimes', 'array'],
            'requested_scopes.*' => ['required', 'string'],
        ];
    }
}
