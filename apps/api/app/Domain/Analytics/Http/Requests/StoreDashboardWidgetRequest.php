<?php

namespace App\Domain\Analytics\Http\Requests;

use App\Domain\Analytics\Enums\DashboardWidgetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreDashboardWidgetRequest extends FormRequest
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
            'type' => ['required', new Enum(DashboardWidgetType::class)],
            'title' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'config.metric_key' => ['sometimes', 'string'],
            'config.time_dimension' => ['sometimes', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
