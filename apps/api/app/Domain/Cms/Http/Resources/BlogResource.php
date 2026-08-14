<?php

namespace App\Domain\Cms\Http\Resources;

use App\Domain\Cms\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Blog
 */
final class BlogResource extends JsonResource
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
            'posts_count' => $this->whenCounted('posts'),
            'created_at' => $this->created_at,
        ];
    }
}
