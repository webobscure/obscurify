<?php

namespace App\Domain\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDashboardRequest extends FormRequest
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
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
