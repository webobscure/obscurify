<?php

namespace App\Domain\Apps\Models;

use App\Domain\Apps\Enums\AppTokenType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $installed_app_id
 * @property string|null $rotated_from_id
 * @property AppTokenType $type
 * @property string $token_hash
 * @property array<int, string> $scope
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 */
class AppToken extends Model
{
    use BelongsToTenant, HasUlids;

    const UPDATED_AT = null;

    protected $fillable = [
        'installed_app_id',
        'rotated_from_id',
        'type',
        'token_hash',
        'scope',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AppTokenType::class,
            'scope' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<InstalledApp, $this>
     */
    public function installedApp(): BelongsTo
    {
        return $this->belongsTo(InstalledApp::class);
    }

    /**
     * @return BelongsTo<AppToken, $this>
     */
    public function rotatedFrom(): BelongsTo
    {
        return $this->belongsTo(AppToken::class, 'rotated_from_id');
    }
}
