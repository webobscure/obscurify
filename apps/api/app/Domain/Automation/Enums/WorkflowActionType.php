<?php

namespace App\Domain\Automation\Enums;

/**
 * Every built-in action from spec section 5, plus `AppAction` — the
 * escape hatch for an app-registered action (config references the
 * contributing AppExtension; see WorkflowActionExecutor and spec
 * section 10: "Future actions should plug in through Apps SDK").
 *
 * Milestone 21 (Notification Center) replaced `CreateInternalNotification`
 * with five real notification actions (spec section 8: "Replace the
 * minimal InternalNotification action with real notification
 * actions") — each creates a real Notification via NotificationDispatcher
 * instead of a dead-end, unreadable inbox row. `SendInAppNotification`
 * is its direct successor.
 */
enum WorkflowActionType: string
{
    case AddCustomerTag = 'add_customer_tag';
    case RemoveCustomerTag = 'remove_customer_tag';
    case AddCustomerToGroup = 'add_customer_to_group';
    case RemoveCustomerFromGroup = 'remove_customer_from_group';
    case CreateDiscountCode = 'create_discount_code';
    case ExpireDiscount = 'expire_discount';
    case PublishEvent = 'publish_event';
    case CallAppWebhook = 'call_app_webhook';
    case SendEmailNotification = 'send_email_notification';
    case SendSmsNotification = 'send_sms_notification';
    case SendPushNotification = 'send_push_notification';
    case SendInAppNotification = 'send_in_app_notification';
    case SendWebhookNotification = 'send_webhook_notification';
    case UpdateCustomerMetadata = 'update_customer_metadata';
    case UpdateOrderMetadata = 'update_order_metadata';
    case CreateTask = 'create_task';
    case Delay = 'delay';
    case AppAction = 'app_action';
}
