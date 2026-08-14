<?php

namespace App\Domain\Apps\Http\Resources;

use App\Domain\Apps\Models\AppExtension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppExtension
 */
final class AppExtensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installed_app_id' => $this->installed_app_id,
            'extension_point' => $this->extension_point->value,
            'config' => $this->config,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
