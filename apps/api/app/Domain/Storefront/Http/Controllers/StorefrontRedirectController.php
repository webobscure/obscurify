<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Cms\Models\Redirect;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resolves a not-found storefront path against the store's manual
 * redirects. The API itself never issues an HTTP 301/302 — the
 * storefront SPA has already rendered its own routing by the time a
 * path is known to be unresolvable, so it calls this endpoint and
 * performs the redirect client-side rather than the API racing a
 * server-side `Location` header against a request the Nuxt app already
 * owns.
 */
final class StorefrontRedirectController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $path = $request->query('path', '');

        $redirect = Redirect::query()->where('from_path', $path)->first();

        if ($redirect === null) {
            return response()->json(['message' => 'No redirect for this path.'], 404);
        }

        return response()->json(['data' => [
            'to_path' => $redirect->to_path,
            'status_code' => $redirect->status_code,
        ]]);
    }
}
