<?php

namespace App\Domain\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RejectReturnRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
