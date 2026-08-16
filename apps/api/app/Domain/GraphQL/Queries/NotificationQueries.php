<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\Customers\Models\Customer;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\GraphQL\Types\CommonTypes;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\NotificationRecipient;
use GraphQL\Type\Definition\Type;

/**
 * `notifications`/`notificationPreferences` — mirrors
 * CustomerNotificationController::index/
 * CustomerNotificationPreferenceController::show. `notificationPreferences`
 * isn't in spec section 3's query list verbatim, but REST already has a
 * read path (CustomerNotificationPreferenceController::show) alongside
 * its mutation — omitting the GraphQL read half while shipping the write
 * half (spec section 4: "Notification Preferences") would leave a caller
 * unable to render a settings form without falling back to REST.
 */
final class NotificationQueries
{
    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('notifications', [
            'type' => $types->get('NotificationConnection'),
            'args' => ['page' => Type::int(), 'perPage' => Type::int()],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = self::requireCustomer($context);

                $recipients = NotificationRecipient::query()
                    ->where('customer_id', $customer->id)
                    ->with('notification')
                    ->orderByDesc('created_at')
                    ->paginate($args['perPage'] ?? 15, ['*'], 'page', $args['page'] ?? 1);

                return ['data' => $recipients->items(), 'pageInfo' => CommonTypes::resolvePageInfo($recipients)];
            },
        ]);

        $queries->register('notificationPreferences', [
            'type' => $types->get('NotificationPreference'),
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = self::requireCustomer($context);

                return NotificationPreference::query()->where('customer_id', $customer->id)->first()
                    ?? new NotificationPreference(['customer_id' => $customer->id]);
            },
        ]);
    }

    public static function requireCustomer(GraphQLContext $context): Customer
    {
        if (! $context->isCustomer()) {
            throw GraphQLUserError::forbidden(__('graphql.must_be_logged_in_as_customer'));
        }

        return $context->requireCustomer();
    }
}
