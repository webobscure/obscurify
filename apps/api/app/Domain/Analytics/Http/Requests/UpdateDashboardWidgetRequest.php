<?php

namespace App\Domain\Analytics\Http\Requests;

use App\Domain\Analytics\Enums\DashboardWidgetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateDashboardWidgetRequest extends FormRequest
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
            'type' => ['sometimes', new Enum(DashboardWidgetType::class)],
            'title' => ['sometimes', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
