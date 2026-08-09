<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ShippingQuoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A checkout's selected shipping rate, persisted at selection time so it
 * can be revalidated (not recalculated) at CompleteCheckout — see
 * RevalidateShippingQuote. Never mutated after creation.
 *
 * @property string $id
 * @property string $store_id
 * @property string $checkout_id
 * @property string|null $shipping_method_id
 * @property string $provider
 * @property string|null $service_code
 * @property string $name
 * @property int $price_amount
 * @property string $currency
 * @property int|null $estimated_days_min
 * @property int|null $estimated_days_max
 * @property Carbon $expires_at
 * @property array<string, mixed>|null $metadata
 */
class ShippingQuote extends Model
{
    /** @use HasFactory<ShippingQuoteFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): ShippingQuoteFactory
    {
        return ShippingQuoteFactory::new();
    }

    protected $fillable = [
        'checkout_id',
        'shipping_method_id',
        'provider',
        'service_code',
        'name',
        'price_amount',
        'currency',
        'estimated_days_min',
        'estimated_days_max',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'estimated_days_min' => 'integer',
            'estimated_days_max' => 'integer',
            'expires_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<Checkout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
