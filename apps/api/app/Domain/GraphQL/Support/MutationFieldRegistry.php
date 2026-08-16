<?php

namespace App\Domain\GraphQL\Support;

use InvalidArgumentException;

/**
 * Top-level `Mutation` field registry — the write-side counterpart to
 * QueryFieldRegistry (spec section 4: Cart/Checkout/Customer Login/.../
 * Search Tracking), equally open to Apps SDK extensions (spec section 8).
 */
final class MutationFieldRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $fields = [];

    /**
     * @param  array<string, mixed>  $config  webonyx field config: type, args, resolve, description, deprecationReason
     */
    public function register(string $name, array $config): void
    {
        if (isset($this->fields[$name])) {
            throw new InvalidArgumentException("GraphQL mutation field \"{$name}\" is already registered.");
        }

        $this->fields[$name] = $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->fields;
    }
}
