<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store's own configured instance of a provider `code` (spec section
 * 2) — "fake" is the only code actually resolvable through
 * NotificationProviderRegistry today; every other code in
 * FUTURE_CODES is a selectable placeholder (spec: "Future providers")
 * that fails at send time with UnknownNotificationProviderException,
 * the identical failure mode PaymentProviderRegistry/
 * ShippingProviderRegistry already use for a disabled/unimplemented
 * provider.
 *
 * @property string $id
 * @property string $store_id
 * @property string $code
 * @property string $name
 * @property bool $is_enabled
 * @property array<string, mixed>|null $config
 */
class NotificationProvider extends Model
{
    use BelongsToTenant, HasUlids;

    public const string FAKE = 'fake';

    /**
     * @var string[]
     */
    public const array FUTURE_CODES = ['smtp', 'mailgun', 'resend', 'ses', 'twilio', 'telegram', 'whatsapp', 'firebase_push', 'apps_sdk'];

    protected $fillable = [
        'code',
        'name',
        'is_enabled',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'array',
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
