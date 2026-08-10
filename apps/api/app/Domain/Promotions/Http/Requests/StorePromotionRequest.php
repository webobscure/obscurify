<?php

namespace App\Domain\Promotions\Http\Requests;

use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Enums\PromotionRuleType;
use App\Domain\Promotions\Enums\PromotionStackingMode;
use App\Domain\Promotions\Enums\PromotionStatus;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StorePromotionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'trigger_type' => ['sometimes', new Enum(PromotionTriggerType::class)],
            'stacking_mode' => ['sometimes', new Enum(PromotionStackingMode::class)],
            'priority' => ['sometimes', 'integer'],
            'status' => ['sometimes', new Enum(PromotionStatus::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],

            'rules' => ['sometimes', 'array'],
            'rules.*.type' => ['required_with:rules', new Enum(PromotionRuleType::class)],
            'rules.*.parameters' => ['sometimes', 'array'],

            'actions' => ['sometimes', 'array'],
            'actions.*.type' => ['required_with:actions', new Enum(PromotionActionType::class)],
            'actions.*.parameters' => ['sometimes', 'array'],
        ];
    }
}
