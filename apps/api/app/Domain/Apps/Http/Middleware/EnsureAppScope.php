<?php

namespace App\Domain\Apps\Http\Middleware;

use App\Domain\Apps\Support\CurrentAppContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `->middleware('app-scope:orders.read')` — checks the *token's own*
 * scope list (set at issuance from the OAuth grant, not re-read from
 * AppPermission on every request) matches. Must run after
 * AuthenticateAppToken.
 */
final class EnsureAppScope
{
    public function __construct(private readonly CurrentAppContext $currentAppContext) {}

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        if (! $this->currentAppContext->hasScope($scope)) {
            return response()->json([
                'message' => "This token does not have the required scope: {$scope}.",
                'error' => 'insufficient_scope',
            ], 403);
        }

        return $next($request);
    }
}
