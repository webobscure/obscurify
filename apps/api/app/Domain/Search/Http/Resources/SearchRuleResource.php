<?php

namespace App\Domain\Search\Http\Resources;

use App\Domain\Search\Models\SearchRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SearchRule
 */
final class SearchRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'keyword' => $this->keyword,
            'action' => $this->action->value,
            'product_id' => $this->product_id,
            'boost_amount' => $this->boost_amount,
            'is_active' => $this->is_active,
            'position' => $this->position,
            'created_at' => $this->created_at,
        ];
    }
}
