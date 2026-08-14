<?php

namespace App\Domain\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRedirectRequest extends FormRequest
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
            'from_path' => ['sometimes', 'string', 'max:2048', 'starts_with:/'],
            'to_path' => ['sometimes', 'string', 'max:2048'],
            'status_code' => ['sometimes', Rule::in([301, 302])],
        ];
    }
}
