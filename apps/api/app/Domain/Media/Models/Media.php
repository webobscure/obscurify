<?php

namespace App\Domain\Media\Models;

use App\Domain\Media\Enums\MediaEntityType;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property MediaEntityType $entity_type
 * @property string $entity_id
 * @property MediaType $type
 * @property string $disk
 * @property string $path
 * @property string|null $alt
 * @property int $position
 * @property array<string, mixed>|null $metadata
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }

    protected $fillable = [
        'entity_type',
        'entity_id',
        'type',
        'disk',
        'path',
        'alt',
        'position',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => MediaEntityType::class,
            'type' => MediaType::class,
            'position' => 'integer',
            'metadata' => 'array',
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
