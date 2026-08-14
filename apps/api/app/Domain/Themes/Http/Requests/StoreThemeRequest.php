<?php

namespace App\Domain\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreThemeRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
        ];
    }
}
