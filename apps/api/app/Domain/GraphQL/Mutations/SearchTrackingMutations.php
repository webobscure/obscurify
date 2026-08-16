<?php

namespace App\Domain\GraphQL\Mutations;

use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\MutationFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\Search\Application\RecordSearchClick;
use App\Domain\Search\Application\RecordSearchConversion;
use App\Domain\Search\Models\SearchQuery as SearchQueryModel;
use GraphQL\Type\Definition\Type;

/**
 * `recordSearchClick`/`recordSearchConversion` — mirrors
 * StorefrontSearchController::click/conversions exactly.
 */
final class SearchTrackingMutations
{
    public static function register(MutationFieldRegistry $mutations, TypeRegistry $types): void
    {
        $mutations->register('recordSearchClick', [
            'type' => Type::boolean(),
            'args' => [
                'searchQueryId' => Type::id(),
                'productId' => Type::nonNull(Type::id()),
                'position' => Type::int(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $searchQuery = isset($args['searchQueryId']) ? SearchQueryModel::query()->find($args['searchQueryId']) : null;

                app(RecordSearchClick::class)->handle($searchQuery, $args['productId'], $args['position'] ?? null);

                return true;
            },
        ]);

        $mutations->register('recordSearchConversion', [
            'type' => Type::boolean(),
            'args' => [
                'searchQueryId' => Type::nonNull(Type::id()),
                'productId' => Type::nonNull(Type::id()),
                'orderId' => Type::nonNull(Type::id()),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $searchQuery = SearchQueryModel::query()->find($args['searchQueryId']);

                if ($searchQuery === null) {
                    throw GraphQLUserError::notFound('Search query');
                }

                app(RecordSearchConversion::class)->handle($searchQuery, $args['productId'], $args['orderId']);

                return true;
            },
        ]);
    }
}
