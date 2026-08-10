<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Promotions\Enums\PromotionStackingMode;
use App\Domain\Promotions\Enums\PromotionStatus;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use App\Domain\Promotions\Models\PromotionRule;
use Illuminate\Support\Facades\DB;

final class CreatePromotion
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Promotion
    {
        $rules = $data['rules'] ?? [];
        $actions = $data['actions'] ?? [];
        unset($data['rules'], $data['actions']);

        $data['trigger_type'] ??= PromotionTriggerType::Automatic->value;
        $data['stacking_mode'] ??= PromotionStackingMode::Stackable->value;
        $data['priority'] ??= 0;
        $data['status'] ??= PromotionStatus::Active->value;

        return DB::transaction(function () use ($data, $rules, $actions) {
            $promotion = Promotion::query()->create($data);

            foreach ($rules as $rule) {
                PromotionRule::query()->create([
                    'promotion_id' => $promotion->id,
                    'type' => $rule['type'],
                    'parameters' => $rule['parameters'] ?? [],
                ]);
            }

            foreach ($actions as $action) {
                PromotionAction::query()->create([
                    'promotion_id' => $promotion->id,
                    'type' => $action['type'],
                    'parameters' => $action['parameters'] ?? [],
                ]);
            }

            return $promotion->load(['rules', 'actions']);
        });
    }
}
