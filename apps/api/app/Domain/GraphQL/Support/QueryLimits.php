<?php

namespace App\Domain\GraphQL\Support;

use GraphQL\GraphQL;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Spec section 11: complexity analysis, depth limits, cost limits,
 * query timeout. webonyx's `QueryComplexity` rule *is* both "complexity
 * analysis" and "cost limits" — every field defaults to a cost of 1
 * (configurable per-field via a `complexity` callback in that field's
 * config, unused by any built-in field here since the defaults are
 * already a meaningful bound), summed and rejected past the threshold —
 * treating these as two names for one mechanism rather than building a
 * second, parallel cost system. `DisableIntrospection` is added only
 * outside local/testing (spec section 10: "Production can disable
 * introspection").
 */
final class QueryLimits
{
    public const int MAX_DEPTH = 12;

    public const int MAX_COMPLEXITY = 1000;

    public const int TIMEOUT_SECONDS = 10;

    /**
     * @return list<ValidationRule>
     */
    public static function validationRules(): array
    {
        $rules = GraphQL::getStandardValidationRules();
        $rules[] = new QueryDepth(self::MAX_DEPTH);
        $rules[] = new QueryComplexity(self::MAX_COMPLEXITY);

        if (self::introspectionDisabled()) {
            $rules[] = new DisableIntrospection(DisableIntrospection::ENABLED);
        }

        return $rules;
    }

    public static function introspectionDisabled(): bool
    {
        return (bool) config('graphql.disable_introspection');
    }
}
