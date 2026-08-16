<?php

namespace App\Domain\GraphQL\Directives;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;

/**
 * `@auth(role: MERCHANT)` — declared on the schema for introspection/
 * documentation (spec section 8: "Apps SDK can register ... Directives",
 * spec section 3: "Analytics (merchant only)"), applied to the
 * `analytics` query field. webonyx's code-first executor does not
 * auto-enforce directive semantics the way an SDL-first/Apollo server
 * would — actual enforcement is `DirectiveEnforcer::requireRole()`,
 * a resolver-wrapping helper applied at field-registration time. This
 * class exists so `@auth` is a real, inspectable part of the schema
 * (visible in introspection and the playground), not just an internal
 * convention.
 */
final class AuthDirective
{
    public const string NAME = 'auth';

    public static function make(): Directive
    {
        return new Directive([
            'name' => self::NAME,
            'description' => 'Marks a field as requiring the given actor role (see spec section 5: Guest/Customer/Merchant/App).',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
            'args' => [
                ['name' => 'role', 'type' => Type::nonNull(self::roleEnum())],
            ],
        ]);
    }

    public static function roleEnum(): EnumType
    {
        static $type = null;

        return $type ??= new EnumType([
            'name' => 'GraphQLRole',
            'values' => ['GUEST', 'CUSTOMER', 'MERCHANT', 'APP'],
        ]);
    }
}
