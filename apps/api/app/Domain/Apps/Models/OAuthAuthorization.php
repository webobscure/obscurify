<?php

namespace App\Domain\Apps\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single-use authorization code grant — see the migration's docblock.
 *
 * @property string $id
 * @property string $store_id
 * @property string $oauth_client_id
 * @property string $installed_app_id
 * @property string $code_hash
 * @property string $code_challenge
 * @property string $code_challenge_method
 * @property string $redirect_uri
 * @property array<int, string> $scope
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class OAuthAuthorization extends Model
{
    use BelongsToTenant, HasUlids;

    const UPDATED_AT = null;

    protected $table = 'oauth_authorizations';

    protected $fillable = [
        'oauth_client_id',
        'installed_app_id',
        'code_hash',
        'code_challenge',
        'code_challenge_method',
        'redirect_uri',
        'scope',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<OAuthClient, $this>
     */
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class);
    }

    /**
     * @return BelongsTo<InstalledApp, $this>
     */
    public function installedApp(): BelongsTo
    {
        return $this->belongsTo(InstalledApp::class);
    }
}
