<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Cms\Models\MenuItem;
use App\Domain\Cms\Support\ResolveMenuItemHref;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
final class StorefrontMenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label,
            'href' => app(ResolveMenuItemHref::class)->handle($this->resource),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
