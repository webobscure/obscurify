<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\NotificationRecipient;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Mirrors CustomerNotificationController/
 * CustomerNotificationPreferenceController exactly — the `notifications`
 * query returns NotificationRecipient rows (the customer's own read
 * state), not raw Notification rows, matching REST.
 */
final class NotificationTypes
{
    public static function notification(): ObjectType
    {
        return new ObjectType([
            'name' => 'Notification',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'channel' => Type::nonNull(Type::string()),
                'eventType' => Type::string(),
                'subject' => Type::string(),
                'bodyText' => Type::nonNull(Type::string()),
            ],
            'resolveField' => fn (Notification $notification, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'id' => $notification->id,
                'channel' => $notification->channel->value,
                'eventType' => $notification->event_type,
                'subject' => $notification->subject,
                'bodyText' => $notification->body_text,
                default => null,
            },
        ]);
    }

    public static function notificationRecipient(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'NotificationRecipient',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'notification' => $types->get('Notification'),
                'readAt' => $types->get('DateTime'),
            ],
            'resolveField' => function (NotificationRecipient $recipient, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id' => $recipient->id,
                    'notification' => $recipient->relationLoaded('notification') ? $recipient->notification : null,
                    'readAt' => $recipient->read_at,
                    default => null,
                };
            },
        ]);
    }

    public static function notificationConnection(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'NotificationConnection',
            'fields' => [
                'data' => Type::listOf($types->get('NotificationRecipient')),
                'pageInfo' => $types->get('PageInfo'),
            ],
        ]);
    }

    public static function notificationPreference(): ObjectType
    {
        return new ObjectType([
            'name' => 'NotificationPreference',
            'fields' => fn () => [
                'emailEnabled' => Type::nonNull(Type::boolean()),
                'smsEnabled' => Type::nonNull(Type::boolean()),
                'pushEnabled' => Type::nonNull(Type::boolean()),
                'marketingOptIn' => Type::nonNull(Type::boolean()),
                'transactionalOnly' => Type::nonNull(Type::boolean()),
                'quietHoursStart' => Type::string(),
                'quietHoursEnd' => Type::string(),
                'quietHoursTimezone' => Type::string(),
            ],
            'resolveField' => fn (NotificationPreference $preference, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'emailEnabled' => $preference->email_enabled,
                'smsEnabled' => $preference->sms_enabled,
                'pushEnabled' => $preference->push_enabled,
                'marketingOptIn' => $preference->marketing_opt_in,
                'transactionalOnly' => $preference->transactional_only,
                'quietHoursStart' => $preference->quiet_hours_start,
                'quietHoursEnd' => $preference->quiet_hours_end,
                'quietHoursTimezone' => $preference->quiet_hours_timezone,
                default => null,
            },
        ]);
    }
}
