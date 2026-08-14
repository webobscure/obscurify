<?php

namespace App\Domain\Apps\Http\Controllers;

use App\Domain\Apps\Application\BeginAuthorization;
use App\Domain\Apps\Application\ExchangeAuthorizationCode;
use App\Domain\Apps\Application\RefreshAppToken;
use App\Domain\Apps\Application\RevokeAppToken;
use App\Domain\Apps\Exceptions\OAuthErrorException;
use App\Domain\Apps\Http\Requests\BeginAuthorizationRequest;
use App\Domain\Apps\Models\OAuthClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OAuth 2.1, Authorization Code + PKCE only (spec section 3 — no
 * implicit flow). `authorize()` runs authenticated in the admin (the
 * `tenant` middleware has already resolved TenantContext from the
 * merchant's active store); `token()`/`revoke()` are called by the
 * third-party app's own server and carry no session — see
 * ExchangeAuthorizationCode/RefreshAppToken/RevokeAppToken for how they
 * resolve tenant from the grant/token itself instead.
 */
final class OAuthController extends Controller
{
    /**
     * The consent screen's own data: which app is asking, for which
     * scopes — the admin UI calls this before rendering an approve/deny
     * screen, then POSTs to `authorize()` below on approval.
     */
    public function show(Request $request): JsonResponse
    {
        $clientId = $request->query('client_id');
        $client = $clientId !== null ? OAuthClient::query()->where('client_id', $clientId)->with('app')->first() : null;

        if ($client === null || $client->app === null) {
            throw OAuthErrorException::invalidClient('Unknown client_id.');
        }

        return response()->json(['data' => [
            'app_name' => $client->app->name,
            'developer' => $client->app->developer,
            'requested_scopes' => array_filter(explode(' ', (string) $request->query('scope', ''))),
        ]]);
    }

    public function approve(BeginAuthorizationRequest $request, BeginAuthorization $action): JsonResponse
    {
        $data = $request->validated();
        $scopes = array_values(array_filter(explode(' ', $data['scope'])));

        $result = $action->handle(
            $data['client_id'],
            $data['redirect_uri'],
            $scopes,
            $data['code_challenge'],
            $data['code_challenge_method'],
        );

        $redirectUrl = $data['redirect_uri'].(str_contains($data['redirect_uri'], '?') ? '&' : '?')
            .http_build_query(array_filter(['code' => $result['code'], 'state' => $data['state'] ?? null]));

        return response()->json(['data' => ['redirect_url' => $redirectUrl]]);
    }

    public function token(Request $request, ExchangeAuthorizationCode $exchange, RefreshAppToken $refresh): JsonResponse
    {
        $grantType = (string) $request->input('grant_type');

        $tokens = match ($grantType) {
            'authorization_code' => $exchange->handle(
                (string) $request->input('client_id'),
                (string) $request->input('client_secret'),
                (string) $request->input('code'),
                (string) $request->input('code_verifier'),
                (string) $request->input('redirect_uri'),
            ),
            'refresh_token' => $refresh->handle(
                (string) $request->input('client_id'),
                (string) $request->input('client_secret'),
                (string) $request->input('refresh_token'),
            ),
            default => throw OAuthErrorException::unsupportedGrantType(),
        };

        return response()->json($tokens);
    }

    public function revoke(Request $request, RevokeAppToken $action): JsonResponse
    {
        $action->handle(
            (string) $request->input('client_id'),
            (string) $request->input('client_secret'),
            (string) $request->input('token'),
        );

        return response()->json([], 200);
    }
}
