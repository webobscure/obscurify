<?php

namespace App\Domain\Cms\Models;

use App\Domain\Cms\Enums\SeoSubjectType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SEO fields for one subject. See the migration's docblock for the
 * subject_type/subject_id pattern — deliberately not Eloquent's
 * MorphOne/MorphTo, matching every other owner_type/owner_id-style
 * relation in this codebase (e.g. WebhookSubscription, MenuItem).
 *
 * @property string $id
 * @property string $store_id
 * @property SeoSubjectType $subject_type
 * @property string $subject_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property string|null $og_image
 */
class SeoMetadata extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['subject_type', 'subject_id', 'meta_title', 'meta_description', 'canonical_url', 'og_image'];

    protected function casts(): array
    {
        return ['subject_type' => SeoSubjectType::class];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
