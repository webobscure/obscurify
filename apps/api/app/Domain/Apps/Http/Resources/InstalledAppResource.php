<?php

namespace App\Domain\Apps\Http\Resources;

use App\Domain\Apps\Models\InstalledApp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InstalledApp
 */
final class InstalledAppResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'app_id' => $this->app_id,
            'app' => $this->whenLoaded('app', fn () => [
                'id' => $this->app->id,
                'name' => $this->app->name,
                'slug' => $this->app->slug,
                'developer' => $this->app->developer,
                'type' => $this->app->type->value,
            ]),
            'status' => $this->status->value,
            'scopes' => $this->whenLoaded('permissions', fn () => $this->permissions->whereNull('revoked_at')->pluck('scope')->values()),
            'installed_at' => $this->installed_at,
            'uninstalled_at' => $this->uninstalled_at,
        ];
    }
}
