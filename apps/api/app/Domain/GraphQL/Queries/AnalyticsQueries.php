<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\Analytics\Application\RunReport;
use App\Domain\Analytics\Enums\ReportType;
use App\Domain\GraphQL\Directives\DirectiveEnforcer;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLActorType;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\Type;

/**
 * `analytics` — spec section 3: "Analytics (merchant only)", enforced
 * via `@auth(role: MERCHANT)` (DirectiveEnforcer::requireRole, see that
 * class's docblock for why webonyx needs an explicit wrapper rather
 * than automatic directive execution). Calls the exact same RunReport
 * service ReportController::store does.
 */
final class AnalyticsQueries
{
    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('analytics', [
            'type' => $types->get('AnalyticsReport'),
            'args' => [
                'reportType' => Type::nonNull(Type::string()),
                'filters' => $types->get('JSON'),
                'columns' => Type::listOf(Type::string()),
            ],
            'resolve' => DirectiveEnforcer::requireRole(GraphQLActorType::Merchant, function (mixed $root, array $args, GraphQLContext $context) {
                $reportType = ReportType::tryFrom($args['reportType']);

                if ($reportType === null) {
                    throw new GraphQLUserError("Unknown report type \"{$args['reportType']}\".");
                }

                return app(RunReport::class)->handle($reportType, $args['filters'] ?? [], $args['columns'] ?? []);
            }),
        ]);
    }
}
