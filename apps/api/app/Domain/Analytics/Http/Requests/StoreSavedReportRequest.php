<?php

namespace App\Domain\Analytics\Http\Requests;

use App\Domain\Analytics\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreSavedReportRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'report_type' => ['required', new Enum(ReportType::class)],
            'filters' => ['sometimes', 'array'],
            'columns' => ['sometimes', 'array'],
        ];
    }
}
