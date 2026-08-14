<?php

namespace App\Domain\Cms\Models;

use App\Domain\Cms\Enums\PageVersionStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * An immutable-once-published snapshot of a page's content (ADR-019's
 * versioning pattern, reused as-is). See the migration's docblock for
 * the draft/publish/rollback lifecycle.
 *
 * @property string $id
 * @property string $store_id
 * @property string $page_id
 * @property string|null $created_from_version_id
 * @property int $version_number
 * @property PageVersionStatus $status
 * @property Carbon|null $published_at
 * @property array<int, array<string, mixed>> $sections
 */
class PageVersion extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'page_id',
        'created_from_version_id',
        'version_number',
        'status',
        'published_at',
        'sections',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'status' => PageVersionStatus::class,
            'published_at' => 'datetime',
            'sections' => 'array',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === PageVersionStatus::Draft;
    }

    /**
     * @throws ValidationException if this version is no longer a draft
     *                             (published versions are immutable).
     */
    public function assertEditable(): void
    {
        if (! $this->isDraft()) {
            throw ValidationException::withMessages(['page_version' => 'A published page version is immutable — edit the current draft instead.']);
        }
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<PageVersion, $this>
     */
    public function createdFrom(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'created_from_version_id');
    }
}
