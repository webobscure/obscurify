<?php

use App\Domain\Carts\Application\AddCartItem;
use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    [$this->product, $this->variant] = app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'status' => ProductStatus::Active]);

        return [$product, $variant];
    });
    $this->cart = app(TenantContext::class)->scope($this->store, fn () => Cart::query()->create([
        'token' => Str::random(48),
        'currency' => $this->store->default_currency,
        'status' => 'active',
        'expires_at' => now()->addDays(30),
    ]));
});

afterEach(function () {
    // Genuinely committed rows (no RefreshDatabase here — see Pest.php),
    // so cleanup is manual; deleting the store cascades everything else.
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('does not lose updates across many sequential add-to-cart calls for the same variant', function () {
    app(TenantContext::class)->scope($this->store, function () {
        foreach (range(1, 20) as $i) {
            app(AddCartItem::class)->handle($this->cart, $this->variant, 1);
        }
    });

    $item = CartItem::withoutGlobalScopes()
        ->where('cart_id', $this->cart->id)
        ->where('product_variant_id', $this->variant->id)
        ->firstOrFail();

    expect($item->quantity)->toBe(20);
});

it('holds a row lock on the cart item for the duration of an add-to-cart transaction', function () {
    app(TenantContext::class)->scope($this->store, fn () => app(AddCartItem::class)->handle($this->cart, $this->variant, 1));

    $item = CartItem::withoutGlobalScopes()
        ->where('cart_id', $this->cart->id)
        ->where('product_variant_id', $this->variant->id)
        ->firstOrFail();

    config(['database.connections.pgsql_probe' => config('database.connections.pgsql')]);

    DB::transaction(function () use ($item) {
        // Acquire exactly the lock AddCartItem takes, and hold it open
        // for the lifetime of this outer transaction.
        DB::table('cart_items')->where('id', $item->id)->lockForUpdate()->first();

        $probe = DB::connection('pgsql_probe');

        expect(fn () => $probe->select(
            'select * from cart_items where id = ? for update nowait',
            [$item->id],
        ))->toThrow(QueryException::class);
    });

    DB::purge('pgsql_probe');
});
