<?php

namespace App\Domain\CustomerIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignCustomerTagRequest extends FormRequest
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
            'tag_id' => ['required', 'string'],
        ];
    }
}
