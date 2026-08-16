<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\GraphQL\Mutations\CartMutations;
use App\Domain\GraphQL\Mutations\CheckoutMutations;
use App\Domain\GraphQL\Mutations\CustomerAuthMutations;
use App\Domain\GraphQL\Mutations\CustomerProfileMutations;
use App\Domain\GraphQL\Mutations\NotificationMutations;
use App\Domain\GraphQL\Mutations\SearchTrackingMutations;

/**
 * Every built-in top-level Mutation field (spec section 4), registered
 * once at boot before the schema is built.
 */
final class RegisterGraphQLMutations
{
    public function handle(MutationFieldRegistry $mutations, TypeRegistry $types): void
    {
        CartMutations::register($mutations, $types);
        CheckoutMutations::register($mutations, $types);
        CustomerAuthMutations::register($mutations, $types);
        CustomerProfileMutations::register($mutations, $types);
        NotificationMutations::register($mutations, $types);
        SearchTrackingMutations::register($mutations, $types);
    }
}
