<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\GraphQL\Extensions\Examples\AppHealthExtension;
use App\Domain\GraphQL\Extensions\GraphQLExtensionRegistry;

/**
 * Runs *after* RegisterGraphQLTypes/Queries/Mutations
 * (GraphQLServiceProvider::boot() controls the order) — an extension's
 * `queries()`/`mutations()` config can reference its own or a built-in
 * type by name via TypeRegistry::get(), which only works once that name
 * is registered. Per extension, types() is merged into TypeRegistry
 * before queries()/mutations() are even called, so an extension's own
 * type is always available to its own fields regardless of extension
 * registration order.
 */
final class RegisterGraphQLExtensions
{
    public function handle(GraphQLExtensionRegistry $extensions, TypeRegistry $types, QueryFieldRegistry $queries, MutationFieldRegistry $mutations): void
    {
        // Registered here as the one reference implementation — a real
        // deployment would register third-party extensions the same way,
        // typically from a dedicated service provider per app.
        $extensions->register(new AppHealthExtension);

        foreach ($extensions->all() as $extension) {
            foreach ($extension->types() as $name => $factory) {
                $types->register($name, fn () => $factory($types));
            }

            foreach ($extension->queries() as $name => $config) {
                $queries->register($name, $config);
            }

            foreach ($extension->mutations() as $name => $config) {
                $mutations->register($name, $config);
            }
        }
    }
}
