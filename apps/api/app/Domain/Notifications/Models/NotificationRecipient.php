<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Enums\NotificationRecipientType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Who should receive one Notification — decoupled from the per-channel
 * delivery attempt (NotificationDelivery) so read/unread state (spec
 * section 10: customer portal history) lives once per recipient, not
 * once per delivery attempt.
 *
 * @property string $id
 * @property string $store_id
 * @property string $notification_id
 * @property NotificationRecipientType $recipient_type
 * @property string|null $customer_id
 * @property string|null $address
 * @property Carbon|null $read_at
 */
class NotificationRecipient extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'notification_id',
        'recipient_type',
        'customer_id',
        'address',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_type' => NotificationRecipientType::class,
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

    /**
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'recipient_id');
    }
}
