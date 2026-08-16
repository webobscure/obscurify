<?php

namespace App\Domain\GraphQL\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by GraphQLAuthenticator before schema execution ever starts —
 * a real HTTP 401, not a GraphQL `errors[]` entry, matching how every
 * REST auth guard in this codebase fails (see AuthenticateCustomerToken/
 * AuthenticateAppToken).
 */
final class GraphQLUnauthenticatedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error' => 'unauthorized',
        ], 401);
    }
}
