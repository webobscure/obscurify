<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Cms\Enums\SeoSubjectType;
use App\Domain\Cms\Models\BlogPost;
use App\Domain\Cms\Models\SeoMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 */
final class StorefrontBlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seo = SeoMetadata::query()
            ->where('subject_type', SeoSubjectType::BlogPost->value)
            ->where('subject_id', $this->id)
            ->first();

        return [
            'id' => $this->id,
            'blog_id' => $this->blog_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'author' => $this->whenLoaded('author', fn () => [
                'name' => $this->author->name,
                'bio' => $this->author->bio,
                'avatar_path' => $this->author->avatar_path,
            ]),
            'published_at' => $this->published_at,
            'featured_image_path' => $this->featured_image_path,
            'seo' => $seo === null ? null : [
                'meta_title' => $seo->meta_title,
                'meta_description' => $seo->meta_description,
                'canonical_url' => $seo->canonical_url,
                'og_image' => $seo->og_image,
            ],
        ];
    }
}
