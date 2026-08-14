<?php

namespace App\Domain\Apps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BeginAuthorizationRequest extends FormRequest
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
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'string', 'url'],
            'scope' => ['required', 'string'],
            'state' => ['sometimes', 'nullable', 'string'],
            'code_challenge' => ['required', 'string'],
            'code_challenge_method' => ['required', 'string'],
        ];
    }
}
