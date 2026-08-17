<?php

use App\Domain\Apps\Http\Middleware\AuthenticateAppToken;
use App\Domain\Apps\Http\Middleware\EnsureAppScope;
use App\Domain\Customers\Http\Middleware\AuthenticateCustomerToken;
use App\Shared\Localization\Http\Middleware\ResolveRequestLocale;
use App\Shared\Tenancy\Http\Middleware\EnsureStorefrontTenantContext;
use App\Shared\Tenancy\Http\Middleware\EnsureTenantContext;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => EnsureTenantContext::class,
            'storefront.tenant' => EnsureStorefrontTenantContext::class,
            'app-token' => AuthenticateAppToken::class,
            'app-scope' => EnsureAppScope::class,
            'customer-token' => AuthenticateCustomerToken::class,
        ]);

        // Runs before every route-specific middleware (auth, tenant) on
        // every API request — see ResolveRequestLocale's own docblock
        // for why this only ever establishes the request-wide baseline,
        // refined later once a tenant is resolved.
        $middleware->api(append: [
            ResolveRequestLocale::class,
        ]);

        // EnsureTenantContext/EnsureStorefrontTenantContext must run after
        // authentication but before route model binding, so tenant-scoped
        // models never resolve a binding query before TenantContext is
        // populated. Laravel only guarantees relative order for middleware
        // listed here — a middleware absent from this list is NOT
        // guaranteed to run before every listed one just because it was
        // registered first (a real bug caught here: ResolveRequestLocale,
        // registered via $middleware->api(append:...) above, was actually
        // executing AFTER EnsureTenantContext despite the append/route
        // registration order suggesting otherwise, silently clobbering
        // EnsureTenantContext's own locale refinement back to the
        // request-wide baseline on every tenant-scoped request). It must
        // be listed here, ahead of both tenant-context middlewares, for
        // its "runs first, refined later" docblock to actually hold.
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            ResolveRequestLocale::class,
            EnsureTenantContext::class,
            EnsureStorefrontTenantContext::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Laravel's default handler preserves ModelNotFoundException's
        // message ("No query results for model [App\Domain\Catalog\Models\
        // Product].") even with app.debug off, converting it into a 404
        // that still carries the fully-qualified class name. Every
        // hand-thrown not-found in this codebase is deliberately
        // message-free (see StorefrontCartController) — normalize the
        // framework-thrown ones to match, since this is reachable
        // unauthenticated on the storefront (product/collection/cart-item
        // lookups, route-model-binding misses) and hands an anonymous
        // caller the internal module map as free reconnaissance.
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => __('exceptions.not_found'), 'error' => 'not_found'], 404);
        });

        // Thrown by Illuminate\Http\Middleware\ValidatePostSize before the
        // request ever reaches routing/validation, whenever Content-Length
        // exceeds php.ini's post_max_size (see infra/docker/api/uploads.ini).
        // Laravel's own message ("The POST data is too large.") doesn't say
        // what the actual limit is or that it's a file upload problem —
        // surface something a merchant can act on instead.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => __('exceptions.upload_too_large', ['max' => '25 MB']),
                'error' => 'upload_too_large',
            ], 413);
        });
    })->create();
