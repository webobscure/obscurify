<?php

namespace App\Domain\Promotions\Http\Resources;

use App\Domain\Promotions\Models\PromotionAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromotionAction
 */
final class PromotionActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'parameters' => $this->parameters,
        ];
    }
}
