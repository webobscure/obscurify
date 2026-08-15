<?php

namespace App\Domain\Analytics\Http\Requests;

use App\Domain\Analytics\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreReportRequest extends FormRequest
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
            'report_type' => ['required', new Enum(ReportType::class)],
            'filters' => ['sometimes', 'array'],
            'filters.from' => ['sometimes', 'date'],
            'filters.to' => ['sometimes', 'date'],
            'columns' => ['sometimes', 'array'],
            'saved_report_id' => ['sometimes', 'string'],
        ];
    }
}
