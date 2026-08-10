<?php

namespace App\Domain\Webhooks\Models;

use App\Domain\Stores\Models\Store;
use App\Domain\Webhooks\Enums\WebhookSubscriptionStatus;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\WebhookSubscriptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription to Platform Events (see docs/architecture/webhooks.md).
 * `owner_type`/`owner_id` distinguish a merchant-created subscription
 * (`owner_type = 'store'`, `owner_id = null`) from an app-owned one
 * (`owner_type = 'app'`, `owner_id` the InstalledApp id) — Milestone 12's
 * AppWebhook manages rows here rather than duplicating the delivery
 * engine. `secret` is always encrypted at rest and never re-exposed by
 * the API past creation (see WebhookSubscriptionResource).
 *
 * @property string $id
 * @property string $store_id
 * @property string $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $target_url
 * @property string $secret
 * @property array<int, string> $event_types
 * @property WebhookSubscriptionStatus $status
 */
class WebhookSubscription extends Model
{
    /** @use HasFactory<WebhookSubscriptionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): WebhookSubscriptionFactory
    {
        return WebhookSubscriptionFactory::new();
    }

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'target_url',
        'secret',
        'event_types',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'event_types' => 'array',
            'status' => WebhookSubscriptionStatus::class,
        ];
    }

    public function subscribesTo(string $eventType): bool
    {
        return in_array('*', $this->event_types, true) || in_array($eventType, $this->event_types, true);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
