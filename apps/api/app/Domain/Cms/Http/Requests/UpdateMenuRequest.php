<?php

namespace App\Domain\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMenuRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'handle' => ['sometimes', 'string', 'max:255', 'alpha_dash'],
        ];
    }
}
