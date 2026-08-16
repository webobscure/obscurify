<?php

namespace App\Domain\Orders\Models;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Customers\Models\Customer;
use App\Domain\Financial\Models\FinancialEvent;
use App\Domain\Financial\Models\LedgerTransaction;
use App\Domain\Financial\Models\Refund;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Promotions\Models\DiscountApplication;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Domain\RussianCommerce\Models\OrderFiscalSnapshot;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OrderFactory;
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
 * @property int $number
 * @property string|null $customer_id
 * @property string|null $checkout_id
 * @property string $currency
 * @property int $items_subtotal_amount
 * @property int $shipping_amount
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $total_amount
 * @property OrderStatus $order_status
 * @property FinancialStatus $financial_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $cancelled_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected $fillable = [
        'number',
        'customer_id',
        'checkout_id',
        'currency',
        'items_subtotal_amount',
        'shipping_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'order_status',
        'financial_status',
        'fulfillment_status',
        'email',
        'phone',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'items_subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'order_status' => OrderStatus::class,
            'financial_status' => FinancialStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
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

    /**
     * @return BelongsTo<Checkout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /**
     * @return HasOne<OrderAddress, $this>
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', AddressType::Shipping);
    }

    /**
     * @return HasOne<OrderAddress, $this>
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', AddressType::Billing);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<OrderShippingLine, $this>
     */
    public function shippingLine(): HasOne
    {
        return $this->hasOne(OrderShippingLine::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * @return HasMany<Fulfillment, $this>
     */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    /**
     * @return HasMany<InventoryReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /**
     * @return HasMany<ReturnRequest, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return HasMany<LedgerTransaction, $this>
     */
    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    /**
     * @return HasMany<FinancialEvent, $this>
     */
    public function financialEvents(): HasMany
    {
        return $this->hasMany(FinancialEvent::class);
    }

    /**
     * Russian Commerce Foundation (spec section 11) — null for orders
     * placed before/without a StoreLegalProfile; see
     * BuildOrderFiscalSnapshot.
     *
     * @return HasOne<OrderFiscalSnapshot, $this>
     */
    public function fiscalSnapshot(): HasOne
    {
        return $this->hasOne(OrderFiscalSnapshot::class);
    }

    /**
     * @return HasMany<FiscalReceipt, $this>
     */
    public function fiscalReceipts(): HasMany
    {
        return $this->hasMany(FiscalReceipt::class);
    }

    /**
     * The Order's immutable discount snapshot (spec section 8) — see
     * DiscountApplication.
     *
     * @return HasMany<DiscountApplication, $this>
     */
    public function discountApplications(): HasMany
    {
        return $this->hasMany(DiscountApplication::class);
    }
}
