<?php

namespace App\Domain\Customers\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * One message for "no such identity", "wrong password", and "account
 * disabled" alike — distinguishing them in the response would let a
 * caller enumerate registered emails. AccountLocked is the sole
 * exception: once locked, the account is genuinely unusable for a while
 * regardless of password correctness, so there is no enumeration risk
 * left to protect and a clearer message helps a legitimate customer
 * understand what's happening.
 */
final class InvalidCredentialsException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('These credentials do not match our records.');
    }

    public static function accountLocked(): self
    {
        return new self('This account is temporarily locked due to too many failed login attempts. Try again later.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'error' => 'unauthorized'], 401);
    }
}
