<?php

namespace App\Domain\GraphQL\Support;

use InvalidArgumentException;

/**
 * Top-level `Query` field registry — every public query (spec section
 * 3: Store/Products/Product/Collections/.../Analytics) registers one
 * entry here, and Apps SDK extensions register into the exact same
 * registry (spec section 8) rather than a separate mechanism, so an
 * app-contributed query is indistinguishable from a built-in one once
 * the schema is built.
 */
final class QueryFieldRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $fields = [];

    /**
     * @param  array<string, mixed>  $config  webonyx field config: type, args, resolve, description, deprecationReason
     */
    public function register(string $name, array $config): void
    {
        if (isset($this->fields[$name])) {
            throw new InvalidArgumentException("GraphQL query field \"{$name}\" is already registered.");
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
