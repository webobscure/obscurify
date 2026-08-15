<?php

namespace App\Domain\Automation\Http\Resources;

use App\Domain\Automation\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `version` is not a real Eloquent relation on Workflow — it is
 * whichever WorkflowVersion the caller actually wants to show (the
 * editable draft, for the editor; a specific historical version, for
 * the version-history view) — attached via
 * `$workflow->setRelation('version', $version)` by the controller
 * before serializing, never inferred here. `setRelation` works for any
 * key regardless of whether a matching relation method exists, which is
 * exactly the "virtual relation" this needs.
 *
 * @mixin Workflow
 */
final class WorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'published_version_id' => $this->published_version_id,
            'version' => $this->when($this->resource->relationLoaded('version'), fn () => $this->resource->getRelation('version') !== null ? new WorkflowVersionResource($this->resource->getRelation('version')) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
