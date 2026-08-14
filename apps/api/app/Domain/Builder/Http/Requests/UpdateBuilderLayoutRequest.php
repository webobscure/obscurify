<?php

namespace App\Domain\Builder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBuilderLayoutRequest extends FormRequest
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
            'sections' => ['present', 'array'],
        ];
    }
}
