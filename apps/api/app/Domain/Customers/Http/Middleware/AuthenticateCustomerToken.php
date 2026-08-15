<?php

namespace App\Domain\Customers\Http\Middleware;

use App\Domain\Customers\Enums\CustomerStatus;
use App\Domain\Customers\Enums\CustomerTokenType;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAccessToken;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Support\CurrentCustomerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates `/api/v1/storefront/account/*` requests via a
 * CustomerAccessToken bearer token — entirely separate from merchant
 * admin's Sanctum session (spec: "Do NOT mix customer accounts with
 * admin authentication"). Unlike AuthenticateAppToken, this does not set
 * TenantContext itself: it always runs inside a route group already
 * under `storefront.tenant`, which resolves the store from the request
 * hostname. Every query below (CustomerAccessToken, CustomerSession,
 * Customer) goes through BelongsToTenant's global scope, which is
 * already constrained to that resolved store — so a Store-B customer's
 * token simply doesn't resolve at all on Store-A's domain, with no
 * additional manual check required.
 */
final class AuthenticateCustomerToken
{
    public function __construct(private readonly CurrentCustomerContext $currentCustomerContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $bearer === '') {
            return response()->json(['message' => 'Missing bearer token.', 'error' => 'unauthorized'], 401);
        }

        $token = CustomerAccessToken::query()
            ->where('token_hash', hash('sha256', $bearer))
            ->where('type', CustomerTokenType::Access->value)
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return response()->json(['message' => 'Invalid or expired token.', 'error' => 'unauthorized'], 401);
        }

        $session = CustomerSession::query()->find($token->customer_session_id);

        if ($session === null || ! $session->isUsable()) {
            return response()->json(['message' => 'Invalid or expired token.', 'error' => 'unauthorized'], 401);
        }

        $customer = Customer::query()->find($token->customer_id);

        if ($customer === null || $customer->status !== CustomerStatus::Active) {
            return response()->json(['message' => 'Invalid or expired token.', 'error' => 'unauthorized'], 401);
        }

        $session->update(['last_used_at' => now()]);
        $this->currentCustomerContext->set($customer, $session, $token);

        try {
            return $next($request);
        } finally {
            $this->currentCustomerContext->clear();
        }
    }
}
