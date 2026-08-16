<?php

namespace App\Shared\Tenancy\Http\Middleware;

use App\Domain\Localization\Support\LocaleResolver;
use App\Domain\Stores\Enums\StoreUserStatus;
use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Models\StoreUser;
use App\Shared\Localization\LocaleContext;
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
        private readonly LocaleResolver $localeResolver,
        private readonly LocaleContext $localeContext,
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

        // Refines ResolveRequestLocale's request-wide baseline now that
        // the user and store are both known (spec section 7: "User
        // language preference, Store default language" — in that
        // priority order, ahead of the store's own default).
        $this->localeContext->set($this->localeResolver->resolveForStore($request, $store, 'admin', $user->locale));

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
