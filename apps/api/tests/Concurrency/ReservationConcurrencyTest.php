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
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Locations\Models\Location;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->variant, $this->checkoutOne, $this->checkoutTwo] = app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'status' => ProductStatus::Active, 'price_amount' => 1000]);
        $item = InventoryItem::factory()->create(['product_variant_id' => $variant->id, 'tracked' => true]);
        $location = Location::factory()->create();

        // Exactly one unit of stock — only one of the two competing
        // checkouts below can possibly succeed.
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 1, 'reserved' => 0]);

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
                'currency' => $this->store->default_currency,
                'items_subtotal_amount' => 1000,
                'total_amount' => 1000,
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

        return [$variant, ...$checkouts];
    });
});

afterEach(function () {
    // Genuinely committed rows (no RefreshDatabase in this suite —
    // see Pest.php), so cleanup is manual; deleting the store cascades
    // everything else (checkouts, orders, reservations, inventory, ...).
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous checkout completions win the last unit of stock', function () {
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

    expect(current($failed)['error'])->toContain('stock');

    app(TenantContext::class)->scope($this->store, function () use ($succeeded) {
        expect(Order::query()->count())->toBe(1)
            ->and(Order::query()->firstOrFail()->id)->toBe(current($succeeded)['value']);

        $reservations = InventoryReservation::query()->get();
        expect($reservations)->toHaveCount(1)
            ->and($reservations->first()->order_id)->toBe(current($succeeded)['value']);

        $level = InventoryLevel::query()->firstOrFail();
        expect($level->on_hand)->toBe(1)
            ->and($level->reserved)->toBe(1);
    });
});
