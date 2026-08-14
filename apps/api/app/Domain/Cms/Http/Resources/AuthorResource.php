<?php

namespace App\Domain\Cms\Http\Resources;

use App\Domain\Cms\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Author
 */
final class AuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bio' => $this->bio,
            'avatar_path' => $this->avatar_path,
            'created_at' => $this->created_at,
        ];
    }
}
