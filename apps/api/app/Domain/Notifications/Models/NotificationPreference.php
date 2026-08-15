<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (store, customer) — spec section 6. Only the three
 * channel-enabled flags are actually enforced by NotificationDispatcher
 * (see docs/architecture/notifications.md); `marketing_opt_in`,
 * `transactional_only`, and the quiet-hours columns are stored and
 * settable but not enforced this milestone — there is no marketing-
 * campaign feature to gate (spec: "Do NOT implement marketing
 * campaigns") and quiet hours are explicitly "structure only" (spec
 * section 6).
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property bool $email_enabled
 * @property bool $sms_enabled
 * @property bool $push_enabled
 * @property bool $marketing_opt_in
 * @property bool $transactional_only
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property string|null $quiet_hours_timezone
 */
class NotificationPreference extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'customer_id',
        'email_enabled',
        'sms_enabled',
        'push_enabled',
        'marketing_opt_in',
        'transactional_only',
        'quiet_hours_start',
        'quiet_hours_end',
        'quiet_hours_timezone',
    ];

    /**
     * Mirrors the migration's own column defaults — a DB default only
     * applies on INSERT, not to an in-memory `new NotificationPreference()`
     * instance, and both preference controllers construct exactly that
     * as a read-only "no row yet" fallback (spec: sensible defaults
     * before a customer has ever touched their preferences).
     */
    protected $attributes = [
        'email_enabled' => true,
        'sms_enabled' => false,
        'push_enabled' => false,
        'marketing_opt_in' => false,
        'transactional_only' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'marketing_opt_in' => 'boolean',
            'transactional_only' => 'boolean',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
