<?php

namespace App\Domain\GraphQL\Mutations;

use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Queries\NotificationQueries;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\MutationFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\Notifications\Application\MarkNotificationRead;
use App\Domain\Notifications\Application\UpdateNotificationPreference;
use App\Domain\Notifications\Models\NotificationRecipient;
use GraphQL\Type\Definition\Type;

/**
 * `updateNotificationPreferences`/`markNotificationRead` — mirrors
 * CustomerNotificationPreferenceController::update/
 * CustomerNotificationController::markRead exactly, including the
 * ownership check on markRead.
 */
final class NotificationMutations
{
    public static function register(MutationFieldRegistry $mutations, TypeRegistry $types): void
    {
        $mutations->register('updateNotificationPreferences', [
            'type' => $types->get('NotificationPreference'),
            'args' => [
                'emailEnabled' => Type::boolean(),
                'smsEnabled' => Type::boolean(),
                'pushEnabled' => Type::boolean(),
                'marketingOptIn' => Type::boolean(),
                'transactionalOnly' => Type::boolean(),
                'quietHoursStart' => Type::string(),
                'quietHoursEnd' => Type::string(),
                'quietHoursTimezone' => Type::string(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = NotificationQueries::requireCustomer($context);

                $data = array_filter([
                    'email_enabled' => $args['emailEnabled'] ?? null,
                    'sms_enabled' => $args['smsEnabled'] ?? null,
                    'push_enabled' => $args['pushEnabled'] ?? null,
                    'marketing_opt_in' => $args['marketingOptIn'] ?? null,
                    'transactional_only' => $args['transactionalOnly'] ?? null,
                    'quiet_hours_start' => $args['quietHoursStart'] ?? null,
                    'quiet_hours_end' => $args['quietHoursEnd'] ?? null,
                    'quiet_hours_timezone' => $args['quietHoursTimezone'] ?? null,
                ], fn ($v) => $v !== null);

                return app(UpdateNotificationPreference::class)->handle($customer, $data);
            },
        ]);

        $mutations->register('markNotificationRead', [
            'type' => $types->get('NotificationRecipient'),
            'args' => ['recipientId' => Type::nonNull(Type::id())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = NotificationQueries::requireCustomer($context);

                $recipient = NotificationRecipient::query()->find($args['recipientId']);

                if ($recipient === null || $recipient->customer_id !== $customer->id) {
                    throw GraphQLUserError::notFound('Notification');
                }

                return app(MarkNotificationRead::class)->handle($recipient);
            },
        ]);
    }
}
