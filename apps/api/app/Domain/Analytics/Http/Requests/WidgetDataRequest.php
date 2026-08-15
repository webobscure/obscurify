<?php

namespace App\Domain\Analytics\Http\Requests;

use App\Domain\Analytics\Enums\TimeDimension;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class WidgetDataRequest extends FormRequest
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
            'time_dimension' => ['sometimes', new Enum(TimeDimension::class)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ];
    }
}
