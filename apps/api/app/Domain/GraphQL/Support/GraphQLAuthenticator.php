<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\Apps\Enums\AppTokenType;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Customers\Enums\CustomerStatus;
use App\Domain\Customers\Enums\CustomerTokenType;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAccessToken;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\GraphQL\Exceptions\GraphQLUnauthenticatedException;
use App\Domain\Stores\Enums\StoreUserStatus;
use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Models\StoreUser;
use App\Models\User;
use App\Shared\Tenancy\Exceptions\TenantAccessDeniedException;
use App\Shared\Tenancy\Exceptions\TenantContextMissingException;
use App\Shared\Tenancy\Resolvers\DomainStoreCandidateResolver;
use App\Shared\Tenancy\Resolvers\HeaderStoreCandidateResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the single POST /graphql endpoint's caller into one
 * GraphQLContext — the consolidation point for all four REST auth
 * guards (spec section 5), since GraphQL has no per-route middleware
 * stack to attach them to individually. Every lookup here intentionally
 * mirrors an existing middleware's own query exactly (see class docblocks
 * on EnsureTenantContext/AuthenticateAppToken/AuthenticateCustomerToken/
 * EnsureStorefrontTenantContext) rather than calling into them, since
 * middleware only runs against `$next($request)` in the normal HTTP
 * pipeline and this needs the resolved identity *before* deciding how
 * to execute the GraphQL document.
 *
 * Resolution order: merchant (Sanctum) → app (AppToken bearer) →
 * storefront guest/customer (hostname + optional CustomerAccessToken
 * bearer). A Sanctum personal-access-token string and an App/Customer
 * token string never collide — Sanctum's format requires a `{id}|token`
 * prefix that a plain `hash('sha256', $bearer)` lookup can never match,
 * and vice versa — so trying each guard in turn is safe.
 */
final class GraphQLAuthenticator
{
    public function __construct(
        private readonly HeaderStoreCandidateResolver $headerResolver,
        private readonly DomainStoreCandidateResolver $domainResolver,
    ) {}

    public function authenticate(Request $request): GraphQLContext
    {
        $merchant = Auth::guard('sanctum')->user();

        // The 'sanctum' guard's provider is configured to App\Models\User
        // (config/auth.php) so this is always a User at runtime; the
        // instanceof narrows Authenticatable to that concrete type for
        // static analysis rather than asserting/casting it away.
        if ($merchant instanceof User) {
            return $this->authenticateMerchant($request, $merchant);
        }

        $bearer = $request->bearerToken();

        if ($bearer !== null && $bearer !== '') {
            $appContext = $this->tryAuthenticateApp($bearer);

            if ($appContext !== null) {
                return $appContext;
            }
        }

        return $this->authenticateStorefront($request, $bearer);
    }

    private function authenticateMerchant(Request $request, User $merchant): GraphQLContext
    {
        $candidateStoreId = $this->headerResolver->resolve($request);

        if ($candidateStoreId === null) {
            throw TenantContextMissingException::noActiveStore();
        }

        $isMember = StoreUser::query()
            ->where('store_id', $candidateStoreId)
            ->where('user_id', $merchant->id)
            ->where('status', StoreUserStatus::Active)
            ->exists();

        if (! $isMember) {
            throw TenantAccessDeniedException::forStore($candidateStoreId);
        }

        $store = Store::query()->find($candidateStoreId);

        if ($store === null) {
            throw TenantContextMissingException::noActiveStore();
        }

        return new GraphQLContext(GraphQLActorType::Merchant, $store, user: $merchant);
    }

    private function tryAuthenticateApp(string $bearer): ?GraphQLContext
    {
        $token = AppToken::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $bearer))
            ->where('type', AppTokenType::Access->value)
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return null;
        }

        $installedApp = InstalledApp::withoutGlobalScopes()->find($token->installed_app_id);
        $store = $installedApp !== null ? Store::query()->find($installedApp->store_id) : null;

        if ($installedApp === null || $store === null || $installedApp->status->value !== 'active') {
            return null;
        }

        return new GraphQLContext(GraphQLActorType::App, $store, installedApp: $installedApp, appToken: $token);
    }

    private function authenticateStorefront(Request $request, ?string $bearer): GraphQLContext
    {
        $storeId = $this->domainResolver->resolve($request);

        if ($storeId === null) {
            throw new NotFoundHttpException('No store is registered for this hostname.');
        }

        $store = Store::query()->find($storeId);

        if ($store === null) {
            throw new NotFoundHttpException('No store is registered for this hostname.');
        }

        if ($bearer === null || $bearer === '') {
            return new GraphQLContext(GraphQLActorType::Guest, $store);
        }

        // CustomerAccessToken rows are tenant-scoped by BelongsToTenant,
        // whose global scope reads TenantContext — but TenantContext
        // isn't set yet at this point in resolution (that happens once,
        // around the whole request, in GraphQLController). Scope this
        // one lookup explicitly rather than relying on ambient state
        // that doesn't exist yet.
        $token = CustomerAccessToken::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->where('token_hash', hash('sha256', $bearer))
            ->where('type', CustomerTokenType::Access->value)
            ->first();

        if ($token === null || ! $token->isUsable()) {
            throw new GraphQLUnauthenticatedException('Invalid or expired token.');
        }

        $session = CustomerSession::withoutGlobalScopes()->find($token->customer_session_id);

        if ($session === null || ! $session->isUsable()) {
            throw new GraphQLUnauthenticatedException('Invalid or expired token.');
        }

        $customer = Customer::withoutGlobalScopes()->find($token->customer_id);

        if ($customer === null || $customer->status !== CustomerStatus::Active) {
            throw new GraphQLUnauthenticatedException('Invalid or expired token.');
        }

        $session->update(['last_used_at' => now()]);

        return new GraphQLContext(GraphQLActorType::Customer, $store, customer: $customer, customerSession: $session);
    }
}
