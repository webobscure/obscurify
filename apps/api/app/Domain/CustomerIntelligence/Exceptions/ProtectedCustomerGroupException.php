<?php

namespace App\Domain\CustomerIntelligence\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a merchant tries to delete or rename a `protected`
 * CustomerGroup — spec section 2's "Protected system groups" (see
 * docs/adr/024-customer-intelligence.md for what "protected" guards
 * against here).
 */
final class ProtectedCustomerGroupException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function cannotDelete(): self
    {
        return new self('This is a protected system group and cannot be deleted.');
    }

    public static function cannotRename(): self
    {
        return new self('This is a protected system group and cannot be renamed.');
    }

    public static function cannotChangeType(): self
    {
        return new self('A protected system group\'s type cannot be changed.');
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'error' => 'protected_group'], 422);
    }
}
