<?php

namespace App\Domain\Cms\Http\Requests;

use App\Domain\Cms\Enums\MenuItemTargetType;
use App\Domain\Cms\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreMenuItemRequest extends FormRequest
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
        /** @var Menu $menu */
        $menu = $this->route('menu');

        return [
            'label' => ['required', 'string', 'max:255'],
            'target_type' => ['required', new Enum(MenuItemTargetType::class)],
            'target_id' => ['required_unless:target_type,url', 'nullable', 'string'],
            'url' => ['required_if:target_type,url', 'nullable', 'string', 'max:2048'],
            'parent_id' => ['nullable', Rule::exists('menu_items', 'id')->where('menu_id', $menu->id)],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
