<?php

namespace App\Providers;

use App\Domain\GraphQL\DataLoaders\CategoryLoader;
use App\Domain\GraphQL\DataLoaders\CollectionLoader;
use App\Domain\GraphQL\DataLoaders\CustomerLoader;
use App\Domain\GraphQL\DataLoaders\OrderLoader;
use App\Domain\GraphQL\DataLoaders\ProductLoader;
use App\Domain\GraphQL\DataLoaders\VariantLoader;
use App\Domain\GraphQL\Extensions\GraphQLExtensionRegistry;
use App\Domain\GraphQL\Support\CartCookie;
use App\Domain\GraphQL\Support\GraphQLAuthenticator;
use App\Domain\GraphQL\Support\MutationFieldRegistry;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\RegisterGraphQLExtensions;
use App\Domain\GraphQL\Support\RegisterGraphQLMutations;
use App\Domain\GraphQL\Support\RegisterGraphQLQueries;
use App\Domain\GraphQL\Support\RegisterGraphQLTypes;
use App\Domain\GraphQL\Support\SchemaRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Every binding below is a singleton meaning "one per request" under
 * PHP-FPM (the container itself is torn down at the end of every
 * request) — critical for the DataLoaders and CartCookie specifically,
 * whose whole point depends on accumulating state across a single
 * request's resolvers and never leaking into the next one (see
 * DataLoader's own docblock).
 *
 * Boot order matters: types before queries/mutations (fields reference
 * types by name), and extensions last (see RegisterGraphQLExtensions'
 * own docblock for why).
 */
class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(QueryFieldRegistry::class);
        $this->app->singleton(MutationFieldRegistry::class);
        $this->app->singleton(SchemaRegistry::class);
        $this->app->singleton(GraphQLExtensionRegistry::class);
        $this->app->singleton(GraphQLAuthenticator::class);
        $this->app->singleton(CartCookie::class);

        $this->app->singleton(ProductLoader::class);
        $this->app->singleton(VariantLoader::class);
        $this->app->singleton(CollectionLoader::class);
        $this->app->singleton(CategoryLoader::class);
        $this->app->singleton(CustomerLoader::class);
        $this->app->singleton(OrderLoader::class);
    }

    public function boot(): void
    {
        $this->app->make(RegisterGraphQLTypes::class)->handle($this->app->make(TypeRegistry::class));

        $this->app->make(RegisterGraphQLQueries::class)->handle(
            $this->app->make(QueryFieldRegistry::class),
            $this->app->make(TypeRegistry::class),
        );

        $this->app->make(RegisterGraphQLMutations::class)->handle(
            $this->app->make(MutationFieldRegistry::class),
            $this->app->make(TypeRegistry::class),
        );

        $this->app->make(RegisterGraphQLExtensions::class)->handle(
            $this->app->make(GraphQLExtensionRegistry::class),
            $this->app->make(TypeRegistry::class),
            $this->app->make(QueryFieldRegistry::class),
            $this->app->make(MutationFieldRegistry::class),
        );
    }
}
