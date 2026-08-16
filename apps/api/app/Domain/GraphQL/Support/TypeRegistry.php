<?php

namespace App\Domain\GraphQL\Support;

use GraphQL\Type\Definition\Type;
use InvalidArgumentException;
use RuntimeException;

/**
 * A named-type registry, mirroring the provider-registry idiom already
 * used across this codebase (SearchProviderRegistry,
 * NotificationProviderRegistry) — a boot-time singleton, populated by
 * `RegisterGraphQLTypes` and extensible by Apps SDK extensions (spec
 * section 8: "Apps SDK can register ... Types").
 *
 * Types are registered as lazy factories, not built eagerly: webonyx
 * object types reference each other inside their own `fields` closures
 * (e.g. ProductType's `variants` field needs ProductVariantType, which
 * doesn't exist yet at ProductType's own construction time) — the
 * standard code-first webonyx pattern for breaking circular references.
 * Each factory runs at most once; the built Type is memoized.
 */
final class TypeRegistry
{
    /** @var array<string, callable(): Type> */
    private array $factories = [];

    /** @var array<string, Type> */
    private array $built = [];

    public function register(string $name, callable $factory): void
    {
        if (isset($this->factories[$name])) {
            throw new InvalidArgumentException("GraphQL type \"{$name}\" is already registered.");
        }

        $this->factories[$name] = $factory;
    }

    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }

    public function get(string $name): Type
    {
        if (isset($this->built[$name])) {
            return $this->built[$name];
        }

        if (! isset($this->factories[$name])) {
            throw new RuntimeException("GraphQL type \"{$name}\" is not registered.");
        }

        $type = ($this->factories[$name])();
        $this->built[$name] = $type;

        return $type;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->factories);
    }
}
