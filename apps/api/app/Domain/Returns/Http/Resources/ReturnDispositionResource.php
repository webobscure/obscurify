<?php

namespace App\Domain\Returns\Http\Resources;

use App\Domain\Returns\Models\ReturnDisposition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnDisposition
 */
final class ReturnDispositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disposition' => $this->disposition->value,
            'notes' => $this->notes,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at,
            'applied_at' => $this->applied_at,
        ];
    }
}
