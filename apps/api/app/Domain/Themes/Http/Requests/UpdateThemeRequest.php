<?php

namespace App\Domain\Themes\Http\Requests;

use App\Domain\Themes\Enums\ThemeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateThemeRequest extends FormRequest
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
            'status' => ['sometimes', new Enum(ThemeStatus::class)],
        ];
    }
}
