<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\GraphQL\Directives\AuthDirective;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

/**
 * Assembles the single public Schema from TypeRegistry +
 * QueryFieldRegistry + MutationFieldRegistry — a boot-time singleton,
 * built once and memoized (schema construction walks every registered
 * type's field closures, not free). Apps SDK extensions (spec section 8)
 * register into the three registries *before* this is first called
 * (`RegisterGraphQLTypes`/`RegisterGraphQLQueries`/
 * `RegisterGraphQLMutations`/`RegisterGraphQLExtensions`, all run in
 * `GraphQLServiceProvider::boot()`), so an extension never needs to
 * rebuild or patch an already-built schema.
 */
final class SchemaRegistry
{
    private ?Schema $schema = null;

    public function __construct(
        private readonly TypeRegistry $types,
        private readonly QueryFieldRegistry $queries,
        private readonly MutationFieldRegistry $mutations,
    ) {}

    public function schema(): Schema
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        // Root types must be resolvable through the *same* typeLoader as
        // every other named type — webonyx's schema validator checks that
        // typeLoader('Query') returns the identical instance passed to
        // setQuery(), so "Query"/"Mutation" are registered into
        // TypeRegistry itself rather than built as untracked standalone
        // ObjectType instances.
        $this->types->register('Query', fn () => new ObjectType([
            'name' => 'Query',
            'fields' => fn () => $this->queries->all(),
        ]));

        $this->types->register('Mutation', fn () => new ObjectType([
            'name' => 'Mutation',
            'fields' => fn () => $this->mutations->all(),
        ]));

        $this->schema = new Schema(
            SchemaConfig::create()
                ->setQuery($this->types->get('Query'))
                ->setMutation($this->types->get('Mutation'))
                ->setTypeLoader(fn (string $name) => $this->types->has($name) ? $this->types->get($name) : null)
                // Passing an explicit directives list replaces webonyx's
                // own defaults entirely (same "explicit overrides
                // defaults" behavior as getStandardValidationRules() —
                // see QueryLimits) — built-ins are included explicitly so
                // @skip/@include/@deprecated keep working alongside @auth.
                ->setDirectives([...array_values(Directive::builtInDirectives()), AuthDirective::make()])
        );

        return $this->schema;
    }
}
