<?php

namespace App\Domain\Cms\Models;

use App\Domain\Cms\Enums\BlogPostStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single post. See the migration's docblock for why this is not
 * versioned like Page/Theme.
 *
 * @property string $id
 * @property string $store_id
 * @property string $blog_id
 * @property string|null $author_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body
 * @property BlogPostStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property string|null $featured_image_path
 */
class BlogPost extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'blog_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'published_at',
        'scheduled_at',
        'featured_image_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Blog, $this>
     */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
