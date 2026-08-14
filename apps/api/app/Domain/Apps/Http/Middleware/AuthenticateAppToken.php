<?php

namespace App\Domain\Apps\Http\Middleware;

use App\Domain\Apps\Enums\AppTokenType;
use App\Domain\Apps\Models\AppToken;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Apps\Support\CurrentAppContext;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates `/api/apps/v1` requests via an AppToken bearer token —
 * the gateway's own auth guard, entirely separate from the merchant
 * admin's Sanctum session (spec section 7: a provider-neutral API for
 * third-party apps, not a second way for a logged-in merchant to call
 * the same endpoints). Resolves and sets TenantContext from the token's
 * own store — an app never sends an X-Store-Id header, its token is
 * already scoped to exactly one store.
 */
final class AuthenticateAppToken
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CurrentAppContext $currentAppContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $bearer === '') {
            return response()->json(['message' => 'Missing bearer token.', 'error' => 'unauthorized'], 401);
        }

        $token = AppToken::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $bearer))
            ->where('type', AppTokenType::Access->value)
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return response()->json(['message' => 'Invalid or expired token.', 'error' => 'unauthorized'], 401);
        }

        $installedApp = InstalledApp::withoutGlobalScopes()->find($token->installed_app_id);
        $store = $installedApp !== null ? Store::query()->find($installedApp->store_id) : null;

        if ($installedApp === null || $store === null || $installedApp->status->value !== 'active') {
            return response()->json(['message' => 'Invalid or expired token.', 'error' => 'unauthorized'], 401);
        }

        $this->tenantContext->set($store);
        $this->currentAppContext->set($installedApp, $token);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
            $this->currentAppContext->clear();
        }
    }
}
