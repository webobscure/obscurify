<?php

namespace App\Domain\Apps\Models;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Enums\AppType;
use App\Domain\Stores\Models\Store;
use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Deliberately NOT `BelongsToTenant`: a Public App's `store_id` is null
 * (platform-level, installable by any store), and even a Private App
 * must remain readable by the OAuth token-exchange flow before a
 * TenantContext for the installing store necessarily matches the
 * creating store. Every write path that should be store-scoped (the
 * admin "my apps" list) filters `store_id` explicitly instead of
 * relying on a global scope — see AppController.
 *
 * @property string $id
 * @property string|null $store_id
 * @property AppType $type
 * @property string $name
 * @property string $slug
 * @property string|null $developer
 * @property string|null $description
 * @property array<int, string> $redirect_urls
 * @property array<int, string> $requested_scopes
 * @property AppStatus $status
 */
class App extends Model
{
    /** @use HasFactory<AppFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): AppFactory
    {
        return AppFactory::new();
    }

    protected $fillable = [
        'store_id',
        'type',
        'name',
        'slug',
        'developer',
        'description',
        'redirect_urls',
        'requested_scopes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => AppType::class,
            'redirect_urls' => 'array',
            'requested_scopes' => 'array',
            'status' => AppStatus::class,
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
     * @return HasOne<OAuthClient, $this>
     */
    public function oauthClient(): HasOne
    {
        return $this->hasOne(OAuthClient::class);
    }

    /**
     * @return HasMany<InstalledApp, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(InstalledApp::class);
    }
}
