<?php

namespace App\Shared\Localization\Http\Middleware;

use App\Domain\Localization\Support\LocaleResolver;
use App\Shared\Localization\LocaleContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global (spec section 7) — appended to the whole `api` middleware
 * group (see bootstrap/app.php), so it runs before any route-specific
 * middleware, including `auth:sanctum`/`tenant`/`storefront.tenant`.
 * At this point no user/customer/store is resolved yet, so this only
 * ever establishes the request-wide BASELINE (explicit `?locale=`
 * override -> Accept-Language -> platform default) via
 * LocaleResolver::resolveGlobal().
 *
 * EnsureTenantContext/EnsureStorefrontTenantContext each REFINE this
 * afterward, once a user/customer/store is actually known — see their
 * own docblocks. This two-step split exists purely because of Laravel's
 * real middleware execution order (global group -> route-specific), not
 * because the resolution logic itself is two algorithms.
 */
final class ResolveRequestLocale
{
    public function __construct(
        private readonly LocaleResolver $resolver,
        private readonly LocaleContext $localeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->localeContext->set($this->resolver->resolveGlobal($request));

        try {
            return $next($request);
        } finally {
            $this->localeContext->clear();
        }
    }
}
