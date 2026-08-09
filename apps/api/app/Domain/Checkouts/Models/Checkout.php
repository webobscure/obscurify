<?php

namespace App\Domain\Checkouts\Models;

use App\Domain\Carts\Models\Cart;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Customers\Models\Customer;
use App\Domain\Shipping\Models\ShippingQuote;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CheckoutFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $cart_id
 * @property string|null $customer_id
 * @property string|null $email
 * @property string|null $phone
 * @property string $currency
 * @property int $items_subtotal_amount
 * @property int $shipping_amount
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $total_amount
 * @property CheckoutStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $completed_at
 */
class Checkout extends Model
{
    /** @use HasFactory<CheckoutFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CheckoutFactory
    {
        return CheckoutFactory::new();
    }

    protected $fillable = [
        'cart_id',
        'customer_id',
        'shipping_quote_id',
        'email',
        'phone',
        'currency',
        'items_subtotal_amount',
        'shipping_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'items_subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'status' => CheckoutStatus::class,
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CheckoutAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CheckoutAddress::class);
    }

    /**
     * @return HasOne<CheckoutAddress, $this>
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(CheckoutAddress::class)->where('type', AddressType::Shipping);
    }

    /**
     * @return HasOne<CheckoutAddress, $this>
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(CheckoutAddress::class)->where('type', AddressType::Billing);
    }

    /**
     * @return BelongsTo<ShippingQuote, $this>
     */
    public function shippingQuote(): BelongsTo
    {
        return $this->belongsTo(ShippingQuote::class);
    }
}
