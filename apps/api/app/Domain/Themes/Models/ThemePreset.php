<?php

namespace App\Domain\Themes\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named bundle of ThemeSetting values a merchant can apply at once.
 *
 * @property string $id
 * @property string $store_id
 * @property string $theme_id
 * @property string $name
 * @property array<string, mixed> $settings
 */
class ThemePreset extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['theme_id', 'name', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Theme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }
}
