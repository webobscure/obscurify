<?php

namespace App\Domain\Promotions\Application;

use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use App\Domain\Promotions\Models\PromotionRule;
use Illuminate\Support\Facades\DB;

final class UpdatePromotion
{
    /**
     * Wholesale-replaces rules/actions when provided (mirrors
     * UpdateShippingZone/ShippingZoneRegion) — safe because nothing
     * references a specific rule/action row's id; PromotionUsage and
     * DiscountApplication only ever reference the Promotion itself.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Promotion $promotion, array $data): Promotion
    {
        $rules = $data['rules'] ?? null;
        $actions = $data['actions'] ?? null;
        unset($data['rules'], $data['actions']);

        return DB::transaction(function () use ($promotion, $data, $rules, $actions) {
            $promotion->update($data);

            if ($rules !== null) {
                $promotion->rules()->delete();

                foreach ($rules as $rule) {
                    PromotionRule::query()->create([
                        'promotion_id' => $promotion->id,
                        'type' => $rule['type'],
                        'parameters' => $rule['parameters'] ?? [],
                    ]);
                }
            }

            if ($actions !== null) {
                $promotion->actions()->delete();

                foreach ($actions as $action) {
                    PromotionAction::query()->create([
                        'promotion_id' => $promotion->id,
                        'type' => $action['type'],
                        'parameters' => $action['parameters'] ?? [],
                    ]);
                }
            }

            return $promotion->load(['rules', 'actions']);
        });
    }
}
