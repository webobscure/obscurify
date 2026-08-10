<?php

use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Checkouts\Application\CompleteCheckout;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Locations\Models\Location;
use App\Domain\Orders\Models\Order;
use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Promotions\Enums\PromotionStatus;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionAction;
use App\Domain\Promotions\Models\PromotionUsage;
use App\Models\User;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Verifies spec section 13's usage-limit/single-use-coupon concurrency
 * requirement against a real Postgres connection per side (see
 * ReservationConcurrencyTest for the identical fork-based pattern this
 * mirrors). Plenty of stock on both sides — the contested resource here
 * is the DiscountCode row's usage_limit, not inventory.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->checkoutOne, $this->checkoutTwo, $this->discountCode] = app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'status' => ProductStatus::Active, 'price_amount' => 1000]);
        $item = InventoryItem::factory()->create(['product_variant_id' => $variant->id, 'tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 10, 'reserved' => 0]);

        $promotion = Promotion::factory()->create([
            'trigger_type' => PromotionTriggerType::Code,
            'status' => PromotionStatus::Active,
        ]);
        PromotionAction::query()->create([
            'promotion_id' => $promotion->id,
            'type' => PromotionActionType::FixedAmountOff->value,
            'parameters' => ['amount' => 100],
        ]);

        // Single-use: exactly one of the two concurrent completions below
        // can possibly redeem it.
        $discountCode = DiscountCode::query()->create([
            'promotion_id' => $promotion->id,
            'code' => 'RACE10',
            'usage_limit' => 1,
            'status' => DiscountCodeStatus::Active->value,
        ]);

        $checkouts = [];
        foreach (range(1, 2) as $i) {
            $cart = Cart::query()->create([
                'token' => Str::random(48),
                'currency' => $this->store->default_currency,
                'status' => 'active',
                'expires_at' => now()->addDays(30),
            ]);
            CartItem::query()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

            $checkout = Checkout::query()->create([
                'cart_id' => $cart->id,
                'discount_code_id' => $discountCode->id,
                'currency' => $this->store->default_currency,
                'items_subtotal_amount' => 1000,
                'discount_amount' => 100,
                'total_amount' => 900,
                'status' => CheckoutStatus::Open->value,
                'expires_at' => now()->addMinutes(60),
            ]);
            CheckoutAddress::query()->create([
                'checkout_id' => $checkout->id,
                'type' => AddressType::Shipping->value,
                'first_name' => 'Concurrent', 'last_name' => "Buyer {$i}", 'country_code' => 'US', 'city' => 'Testville',
            ]);

            $checkouts[] = $checkout;
        }

        return [...$checkouts, $discountCode];
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous checkout completions redeem a single-use discount code', function () {
    $completeCheckout = function (string $checkoutId) {
        return app(TenantContext::class)->scope($this->store, function () use ($checkoutId) {
            $checkout = Checkout::query()->whereKey($checkoutId)->firstOrFail();
            $order = app(CompleteCheckout::class)->handle($checkout);

            return $order->id;
        });
    };

    $results = runConcurrently([
        fn () => $completeCheckout($this->checkoutOne->id),
        fn () => $completeCheckout($this->checkoutTwo->id),
    ]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    $failed = array_filter($results, fn ($r) => ! $r['ok']);

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    expect(current($failed)['error'])->toContain('discount code');

    app(TenantContext::class)->scope($this->store, function () use ($succeeded) {
        // The loser's completion rolled back entirely — not "an order
        // without the discount", genuinely no order at all.
        expect(Order::query()->count())->toBe(1)
            ->and(Order::query()->firstOrFail()->id)->toBe(current($succeeded)['value'])
            ->and(Order::query()->firstOrFail()->discount_amount)->toBe(100);

        expect(PromotionUsage::query()->count())->toBe(1);

        $code = DiscountCode::query()->firstOrFail();
        expect($code->usage_count)->toBe(1)
            ->and($code->hasUsesRemaining())->toBeFalse();
    });
});
