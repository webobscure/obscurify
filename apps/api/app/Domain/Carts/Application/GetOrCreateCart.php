<?php

namespace App\Domain\Carts\Application;

use App\Domain\Carts\Models\Cart;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

final class GetOrCreateCart
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Cart::class uses BelongsToTenant, so this lookup is already
     * confined to the active store — a token issued by another store's
     * cart simply won't match anything here (never leaks that cart), and
     * a fresh one is created instead.
     */
    public function handle(?string $token): Cart
    {
        if ($token !== null) {
            $cart = Cart::query()->where('token', $token)->first();

            if ($cart !== null && ! $cart->isExpired()) {
                return $cart;
            }
        }

        return Cart::query()->create([
            'token' => Str::random(48),
            'currency' => $this->tenantContext->store()->default_currency,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);
    }
}
