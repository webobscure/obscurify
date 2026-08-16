<?php

namespace App\Domain\GraphQL\Http\Controllers;

use App\Domain\GraphQL\DataLoaders\CategoryLoader;
use App\Domain\GraphQL\DataLoaders\CollectionLoader;
use App\Domain\GraphQL\DataLoaders\CustomerLoader;
use App\Domain\GraphQL\DataLoaders\OrderLoader;
use App\Domain\GraphQL\DataLoaders\ProductLoader;
use App\Domain\GraphQL\DataLoaders\VariantLoader;
use App\Domain\GraphQL\Support\CartCookie;
use App\Domain\GraphQL\Support\GraphQLAuthenticator;
use App\Domain\GraphQL\Support\QueryLimits;
use App\Domain\GraphQL\Support\SchemaRegistry;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use GraphQL\Error\ClientAware;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The single public GraphQL endpoint (spec section 1: "GraphQL becomes
 * the public storefront and developer API"). Authenticates+resolves the
 * tenant once (GraphQLAuthenticator — see its docblock for the four-guard
 * consolidation), then executes the whole document inside
 * `TenantContext::scope()` so every resolver's ambient tenant-scoped
 * Eloquent query is correctly bounded, exactly like a REST request
 * already is via middleware — this is the same mechanism, just invoked
 * once around a GraphQL document instead of once around a REST action.
 */
final class GraphQLController extends Controller
{
    /**
     * Bindings whose whole point is per-request state (cart identity,
     * DataLoader batch caches — see each class's own docblock) must
     * never survive into a second request on the same container. Under
     * real PHP-FPM that's automatic (the container is torn down after
     * every request); it is *not* automatic inside a single test method
     * simulating several `postJson()` calls back to back, nor would it
     * be under Octane's long-lived worker processes — so these are
     * force-reset explicitly rather than trusted to fall out of scope.
     */
    private const array PER_REQUEST_SINGLETONS = [
        CartCookie::class,
        ProductLoader::class,
        VariantLoader::class,
        CollectionLoader::class,
        CategoryLoader::class,
        CustomerLoader::class,
        OrderLoader::class,
    ];

    public function handle(Request $request, GraphQLAuthenticator $authenticator, SchemaRegistry $schemaRegistry, TenantContext $tenantContext): JsonResponse
    {
        foreach (self::PER_REQUEST_SINGLETONS as $binding) {
            app()->forgetInstance($binding);
        }

        $query = $request->input('query');

        if (! is_string($query) || $query === '') {
            return response()->json(['errors' => [['message' => 'A "query" field is required.']]], 400);
        }

        $variables = $request->input('variables');
        $variables = is_array($variables) ? $variables : null;
        $operationName = $request->input('operationName');
        $operationName = is_string($operationName) ? $operationName : null;

        $context = $authenticator->authenticate($request);

        $result = $tenantContext->scope($context->store, function () use ($schemaRegistry, $query, $context, $variables, $operationName) {
            $previousTimeLimit = ini_get('max_execution_time');
            set_time_limit(QueryLimits::TIMEOUT_SECONDS);

            try {
                return GraphQL::executeQuery(
                    $schemaRegistry->schema(),
                    $query,
                    null,
                    $context,
                    $variables,
                    $operationName,
                    null,
                    QueryLimits::validationRules(),
                );
            } finally {
                set_time_limit((int) $previousTimeLimit);
            }
        });

        $result->setErrorsHandler(function (array $errors, callable $formatter) {
            foreach ($errors as $error) {
                $previous = $error instanceof Error ? $error->getPrevious() : null;

                // Only genuinely unexpected exceptions are worth a log
                // entry — GraphQLUserError/ValidationException etc. are
                // intentional, already-client-safe errors (see
                // GraphQLUserError's own docblock), not something to
                // flag as a bug every time a caller looks up a missing id.
                if ($previous !== null && ! ($previous instanceof Error) && ! ($previous instanceof ClientAware)) {
                    Log::error('GraphQL resolver error', ['message' => $previous->getMessage(), 'exception' => $previous::class]);
                }
            }

            return array_map($formatter, $errors);
        });

        $debug = app()->environment('local', 'testing') ? DebugFlag::INCLUDE_DEBUG_MESSAGE : DebugFlag::NONE;

        $response = response()->json($result->toArray($debug));

        $cartCookie = app(CartCookie::class)->cookie();

        if ($cartCookie !== null) {
            $response->headers->setCookie($cartCookie);
        }

        return $response;
    }
}
