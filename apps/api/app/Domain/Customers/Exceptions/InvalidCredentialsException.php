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
        return new self(__('auth.invalid_credentials'));
    }

    public static function accountLocked(): self
    {
        return new self(__('auth.account_locked'));
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'error' => 'unauthorized'], 401);
    }
}
