<?php

namespace App\Domain\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBlogRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
        ];
    }
}
