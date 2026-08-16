<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;

/**
 * `customer` — the currently authenticated customer's own profile,
 * mirroring CustomerAccountController::show. There is no id-argument
 * variant: a GraphQL caller can only ever see their own record, exactly
 * like REST's `/storefront/account` route.
 */
final class CustomerQueries
{
    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('customer', [
            'type' => $types->get('Customer'),
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                if (! $context->isCustomer()) {
                    throw GraphQLUserError::forbidden('You must be logged in as a customer.');
                }

                return $context->requireCustomer()->loadMissing('addresses');
            },
        ]);
    }
}
