<?php

namespace App\Domain\Apps\Http\Resources\Gateway;

use App\Domain\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `/api/apps/v1` representation of a Product — deliberately its own
 * shape, decoupled from the admin ProductResource, so the gateway's
 * public contract never accidentally grows or shrinks just because the
 * admin UI's own needs change.
 *
 * @mixin Product
 */
final class GatewayProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'vendor' => $this->vendor,
            'product_type' => $this->product_type,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
