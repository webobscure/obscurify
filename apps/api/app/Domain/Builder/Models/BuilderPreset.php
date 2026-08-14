<?php

namespace App\Domain\Builder\Models;

use App\Domain\Builder\Enums\BuilderPresetType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named, ready-to-insert starting configuration for a section or
 * block type — the Section/Block Library. See the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property BuilderPresetType $type
 * @property string $handle
 * @property string $name
 * @property array<string, mixed> $settings
 */
class BuilderPreset extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['type', 'handle', 'name', 'settings'];

    protected function casts(): array
    {
        return [
            'type' => BuilderPresetType::class,
            'settings' => 'array',
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
