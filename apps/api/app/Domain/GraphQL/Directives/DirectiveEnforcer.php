<?php

namespace App\Domain\GraphQL\Directives;

use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLActorType;
use App\Domain\GraphQL\Support\GraphQLContext;

/**
 * Runtime enforcement for `@auth` (see AuthDirective's docblock for why
 * this is separate from the schema-level Directive object). Wraps a
 * field's `resolve` closure so the check runs before the real resolver
 * ever does — used for the one spec-mandated merchant-only query
 * (Analytics) and available to any future field/extension that needs it.
 */
final class DirectiveEnforcer
{
    /**
     * @param  callable(mixed, array<string, mixed>, GraphQLContext, mixed): mixed  $resolver
     */
    public static function requireRole(GraphQLActorType $role, callable $resolver): callable
    {
        return function (mixed $root, array $args, GraphQLContext $context, mixed $info) use ($role, $resolver) {
            if ($context->actor !== $role) {
                throw GraphQLUserError::forbidden(__('graphql.requires_role', ['role' => $role->value]));
            }

            return $resolver($root, $args, $context, $info);
        };
    }
}
