<?php

namespace App\Domain\Automation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RollbackWorkflowRequest extends FormRequest
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
            'version_id' => ['required', 'string'],
        ];
    }
}
