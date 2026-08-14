<?php

namespace App\Domain\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAuthorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'avatar_path' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
