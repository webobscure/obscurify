<?php

namespace App\Domain\Cms\Http\Requests;

use App\Domain\Cms\Enums\MenuItemTargetType;
use App\Domain\Cms\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateMenuItemRequest extends FormRequest
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
        /** @var MenuItem $menuItem */
        $menuItem = $this->route('menuItem');

        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'target_type' => ['sometimes', new Enum(MenuItemTargetType::class)],
            'target_id' => ['sometimes', 'nullable', 'string'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'parent_id' => [
                'sometimes',
                'nullable',
                Rule::exists('menu_items', 'id')->where('menu_id', $menuItem->menu_id),
                Rule::notIn([$menuItem->id]),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
