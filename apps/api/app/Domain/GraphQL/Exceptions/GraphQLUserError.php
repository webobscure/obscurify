<?php

namespace App\Domain\GraphQL\Exceptions;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * Any exception thrown mid-resolution that webonyx should surface
 * verbatim in the response's `errors[]` array, rather than masking as
 * "Internal server error" — validation failures, not-found lookups,
 * ownership/authorization checks (e.g. "this order isn't yours", "this
 * field is merchant-only"). Unlike GraphQLUnauthenticatedException
 * (thrown before execution starts, so it's a real HTTP 401), these
 * happen *during* field resolution, where GraphQL's partial-response
 * model means sibling fields can still succeed — so this must be a
 * `errors[]` entry, never an HTTP-level failure.
 */
final class GraphQLUserError extends RuntimeException implements ClientAware
{
    public function __construct(string $message, private readonly string $category = 'user')
    {
        parent::__construct($message);
    }

    public static function notFound(string $subject): self
    {
        return new self("{$subject} not found.", 'not_found');
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.'): self
    {
        return new self($message, 'forbidden');
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
