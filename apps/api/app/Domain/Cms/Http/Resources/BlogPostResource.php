<?php

namespace App\Domain\Cms\Http\Resources;

use App\Domain\Cms\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 */
final class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blog_id' => $this->blog_id,
            'author' => new AuthorResource($this->whenLoaded('author')),
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'status' => $this->status->value,
            'published_at' => $this->published_at,
            'scheduled_at' => $this->scheduled_at,
            'featured_image_path' => $this->featured_image_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
