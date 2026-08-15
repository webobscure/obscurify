<?php

namespace App\Domain\Automation\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A minimal admin-facing inbox row, created by the "Create internal
 * notification" automation action. No other notification/mail/push
 * system exists in this codebase yet, so this is not a wrapper around
 * anything — just a readable/dismissible record.
 *
 * @property string $id
 * @property string $store_id
 * @property string $title
 * @property string|null $body
 * @property string $level
 * @property string|null $related_type
 * @property string|null $related_id
 * @property string|null $workflow_execution_id
 * @property Carbon|null $read_at
 */
class InternalNotification extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'title',
        'body',
        'level',
        'related_type',
        'related_id',
        'workflow_execution_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
