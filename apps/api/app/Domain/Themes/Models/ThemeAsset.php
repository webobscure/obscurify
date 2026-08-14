<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Domain\Themes\Enums\ThemeAssetType;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Metadata only — the file itself lives on `disk`/`path` (see the
 * migration's docblock), never in this row.
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_version_id
 * @property ThemeAssetType $type
 * @property string $path
 * @property string $disk
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $checksum
 */
class ThemeAsset extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['theme_version_id', 'type', 'path', 'disk', 'mime_type', 'size', 'checksum'];

    protected function casts(): array
    {
        return [
            'type' => ThemeAssetType::class,
            'size' => 'integer',
        ];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function contents(): string
    {
        return Storage::disk($this->disk)->get($this->path) ?? '';
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ThemeVersion, $this>
     */
    public function themeVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class);
    }
}
