<?php

namespace App\Domain\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSavedReportRequest extends FormRequest
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
            'filters' => ['sometimes', 'array'],
            'columns' => ['sometimes', 'array'],
        ];
    }
}
