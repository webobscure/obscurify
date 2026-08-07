<?php

namespace App\Shared\Tenancy\Http\Middleware;

use App\Domain\Stores\Enums\StoreUserStatus;
use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Models\StoreUser;
use App\Shared\Tenancy\Contracts\StoreCandidateResolver;
use App\Shared\Tenancy\Exceptions\TenantAccessDeniedException;
use App\Shared\Tenancy\Exceptions\TenantContextMissingException;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and validates the active tenant for tenant-required routes.
 *
 * The candidate store id (from the resolver) is never trusted on its own:
 * TenantContext is only established after confirming the authenticated
 * user has an active membership in that store.
 */
final class EnsureTenantContext
{
    public function __construct(
        private readonly StoreCandidateResolver $resolver,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw TenantContextMissingException::noActiveStore();
        }

        $candidateStoreId = $this->resolver->resolve($request);

        if ($candidateStoreId === null) {
            throw TenantContextMissingException::noActiveStore();
        }

        $isMember = StoreUser::query()
            ->where('store_id', $candidateStoreId)
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active)
            ->exists();

        if (! $isMember) {
            throw TenantAccessDeniedException::forStore($candidateStoreId);
        }

        $store = Store::query()->find($candidateStoreId);

        if ($store === null) {
            throw TenantContextMissingException::noActiveStore();
        }

        $this->tenantContext->set($store);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
