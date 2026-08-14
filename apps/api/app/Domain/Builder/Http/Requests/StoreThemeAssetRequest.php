<?php

namespace App\Domain\Builder\Http\Requests;

use App\Domain\Themes\Enums\ThemeAssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreThemeAssetRequest extends FormRequest
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
            'type' => ['required', new Enum(ThemeAssetType::class)],
            'file' => ['required', 'file', 'max:20480'],
        ];
    }
}
