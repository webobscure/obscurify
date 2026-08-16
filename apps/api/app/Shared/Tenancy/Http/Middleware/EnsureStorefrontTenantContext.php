<?php

namespace App\Shared\Tenancy\Http\Middleware;

use App\Domain\Localization\Support\LocaleResolver;
use App\Domain\Stores\Models\Store;
use App\Shared\Localization\LocaleContext;
use App\Shared\Tenancy\Resolvers\DomainStoreCandidateResolver;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves and validates the active tenant for public storefront routes.
 *
 * Unlike merchant admin's EnsureTenantContext, there is no authenticated
 * user or membership to check: owning the Domain row *is* the
 * authorization. An unresolved hostname is a plain 404 — there is no
 * concept of "select a store" for an anonymous storefront visitor, so the
 * merchant admin's 428 (no tenant selected) doesn't apply here.
 */
final class EnsureStorefrontTenantContext
{
    public function __construct(
        private readonly DomainStoreCandidateResolver $resolver,
        private readonly TenantContext $tenantContext,
        private readonly LocaleResolver $localeResolver,
        private readonly LocaleContext $localeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $storeId = $this->resolver->resolve($request);

        if ($storeId === null) {
            throw new NotFoundHttpException;
        }

        $store = Store::query()->find($storeId);

        if ($store === null) {
            throw new NotFoundHttpException;
        }

        $this->tenantContext->set($store);

        // Refines ResolveRequestLocale's request-wide baseline now that
        // the store is known. Anonymous visitors have no saved
        // preference of their own — a `storefront_locale` cookie (set
        // by the language switcher, see StorefrontLocaleController) is
        // the explicit preference here, ahead of Accept-Language and
        // the store's own storefront_locale default.
        $this->localeContext->set($this->localeResolver->resolveForStore($request, $store, 'storefront', $request->cookie('storefront_locale')));

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
