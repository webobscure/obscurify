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
use App\Models\User;
use App\Shared\Commerce\Application\IdempotencyKeyStore;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Commerce\Models\IdempotencyKey;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    $this->checkout = app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'status' => ProductStatus::Active, 'price_amount' => 1000]);
        $item = InventoryItem::factory()->create(['product_variant_id' => $variant->id, 'tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 50, 'reserved' => 0]);

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
            'first_name' => 'Concurrent', 'last_name' => 'Buyer', 'country_code' => 'US', 'city' => 'Testville',
        ]);

        return $checkout;
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('executes the completion callback exactly once when two identical requests race on the same Idempotency-Key', function () {
    $key = 'race-key-'.Str::random(8);

    $completeWithKey = function () use ($key) {
        return app(TenantContext::class)->scope($this->store, function () use ($key) {
            $checkout = Checkout::query()->whereKey($this->checkout->id)->firstOrFail();
            $requestHash = hash('sha256', $checkout->id.'|');

            $result = app(IdempotencyKeyStore::class)->handle('checkout.complete', $key, $requestHash, function () use ($checkout) {
                $order = app(CompleteCheckout::class)->handle($checkout);

                return ['status' => 201, 'body' => ['id' => $order->id, 'number' => $order->number]];
            });

            return $result['body'];
        });
    };

    $results = runConcurrently([$completeWithKey, $completeWithKey]);

    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($results[0]['value'])->toBe($results[1]['value']);

    app(TenantContext::class)->scope($this->store, function () use ($results) {
        expect(Order::query()->count())->toBe(1)
            ->and(Order::query()->firstOrFail()->id)->toBe($results[0]['value']['id']);
    });

    IdempotencyKey::withoutGlobalScopes()->where('key', $key)->get()->each(function ($row) {
        expect($row->response_status)->toBe(201);
    });
});
