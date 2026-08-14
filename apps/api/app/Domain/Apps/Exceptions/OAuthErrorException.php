<?php

namespace App\Domain\Apps\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Renders as an RFC 6749 §5.2-shaped error body ({error,
 * error_description}) regardless of which OAuth step raised it —
 * BeginAuthorization/ExchangeAuthorizationCode/RefreshAppToken/
 * RevokeAppToken all throw this instead of a generic ValidationException
 * so a third-party app's OAuth client library (which expects this exact
 * shape) can parse the failure the same way it would against any other
 * OAuth 2.1 provider.
 */
final class OAuthErrorException extends RuntimeException
{
    private function __construct(private readonly string $error, string $description, private readonly int $status)
    {
        parent::__construct($description);
    }

    public static function invalidClient(string $description = 'Client authentication failed.'): self
    {
        return new self('invalid_client', $description, 401);
    }

    public static function invalidGrant(string $description = 'The provided authorization grant is invalid, expired, or revoked.'): self
    {
        return new self('invalid_grant', $description, 400);
    }

    public static function invalidRequest(string $description): self
    {
        return new self('invalid_request', $description, 400);
    }

    public static function invalidScope(string $description = 'One or more requested scopes are unknown or not granted.'): self
    {
        return new self('invalid_scope', $description, 400);
    }

    public static function unsupportedGrantType(string $description = 'This grant_type is not supported.'): self
    {
        return new self('unsupported_grant_type', $description, 400);
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => $this->error,
            'error_description' => $this->getMessage(),
        ], $this->status);
    }
}
