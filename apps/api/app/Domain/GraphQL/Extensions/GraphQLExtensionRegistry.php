<?php

namespace App\Domain\GraphQL\Extensions;

/**
 * Boot-time singleton collecting every registered GraphQLExtensionContract
 * — mirrors the codebase's other provider registries (SearchProviderRegistry,
 * NotificationProviderRegistry). `RegisterGraphQLExtensions` reads this
 * *after* the built-in Query/Mutation/Type registries are populated, so
 * an extension can safely reference a core type (e.g. "Product") without
 * caring about registration order relative to the built-ins.
 */
final class GraphQLExtensionRegistry
{
    /** @var list<GraphQLExtensionContract> */
    private array $extensions = [];

    public function register(GraphQLExtensionContract $extension): void
    {
        $this->extensions[] = $extension;
    }

    /**
     * @return list<GraphQLExtensionContract>
     */
    public function all(): array
    {
        return $this->extensions;
    }
}
