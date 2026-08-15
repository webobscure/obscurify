<?php

namespace App\Domain\Analytics\Http\Requests;

use App\Domain\Analytics\Enums\ExportFormat;
use App\Domain\Analytics\Enums\ExportRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreReportExportRequest extends FormRequest
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
            'format' => ['required', new Enum(ExportFormat::class)],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'recurrence' => ['sometimes', 'nullable', new Enum(ExportRecurrence::class)],
        ];
    }
}
