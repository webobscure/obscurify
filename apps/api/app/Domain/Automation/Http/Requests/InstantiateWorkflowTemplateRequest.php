<?php

namespace App\Domain\Automation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InstantiateWorkflowTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
