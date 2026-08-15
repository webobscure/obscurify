<?php

namespace App\Domain\Automation\Support;

use App\Domain\Apps\Enums\ExtensionPoint;
use App\Domain\Apps\Models\AppExtension;
use App\Domain\Automation\Enums\WorkflowActionType;
use App\Domain\Automation\Models\InternalNotification;
use App\Domain\Automation\Models\Task;
use App\Domain\Automation\Models\WorkflowAction;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\CustomerIntelligence\Application\AddCustomerToGroup;
use App\Domain\CustomerIntelligence\Application\AssignCustomerTag;
use App\Domain\CustomerIntelligence\Application\RemoveCustomerFromGroup;
use App\Domain\CustomerIntelligence\Application\RemoveCustomerTag;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerTag;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Promotions\Application\CreateDiscountCode;
use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Dispatches one WorkflowAction to its concrete handler (spec section
 * 5). Every built-in action either reuses an existing application
 * service directly (Customer Intelligence's tag/group actions, exactly
 * as Promotions already reuses SegmentMembership — no new business
 * logic reinvented here) or is a small, self-contained side effect
 * (Task/InternalNotification, both new in this milestone since nothing
 * comparable existed). `AppAction` is the Apps SDK escape hatch (spec
 * section 10) — no core change needed for a new one, only a new
 * AppExtension row at ExtensionPoint::AutomationAction.
 *
 * Config values may reference an earlier action's own output within the
 * same execution via `{{steps.<action_id>.output.<key>}}` (e.g.
 * ExpireDiscount referencing the discount_code_id a prior
 * CreateDiscountCode step produced) — resolved by `interpolate()` before
 * dispatch.
 */
final class WorkflowActionExecutor
{
    public function __construct(
        private readonly AssignCustomerTag $assignCustomerTag,
        private readonly RemoveCustomerTag $removeCustomerTag,
        private readonly AddCustomerToGroup $addCustomerToGroup,
        private readonly RemoveCustomerFromGroup $removeCustomerFromGroup,
        private readonly CreateDiscountCode $createDiscountCode,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, mixed>>  $priorStepOutputs  keyed by workflow_action_id
     * @return array<string, mixed>
     */
    public function execute(WorkflowAction $action, array $context, WorkflowExecution $execution, array $priorStepOutputs): array
    {
        $config = $this->interpolate($action->config, $priorStepOutputs);

        return match ($action->type) {
            WorkflowActionType::AddCustomerTag => $this->addCustomerTag($config, $context),
            WorkflowActionType::RemoveCustomerTag => $this->removeCustomerTagAction($config, $context),
            WorkflowActionType::AddCustomerToGroup => $this->addCustomerToGroupAction($config, $context),
            WorkflowActionType::RemoveCustomerFromGroup => $this->removeCustomerFromGroupAction($config, $context),
            WorkflowActionType::CreateDiscountCode => $this->createDiscountCodeAction($config),
            WorkflowActionType::ExpireDiscount => $this->expireDiscountAction($config),
            WorkflowActionType::PublishEvent => $this->publishEventAction($config, $execution),
            WorkflowActionType::CallAppWebhook => $this->httpAction($config['target_url'] ?? null, $context, $config['payload'] ?? [], $execution),
            WorkflowActionType::CreateInternalNotification => $this->createInternalNotificationAction($config, $context, $execution),
            WorkflowActionType::UpdateCustomerMetadata => $this->updateCustomerMetadataAction($config, $context),
            WorkflowActionType::UpdateOrderMetadata => $this->updateOrderMetadataAction($config, $context),
            WorkflowActionType::CreateTask => $this->createTaskAction($config, $context, $execution),
            WorkflowActionType::Delay => [],
            WorkflowActionType::AppAction => $this->appAction($config, $context, $execution),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, array<string, mixed>>  $priorStepOutputs
     * @return array<string, mixed>
     */
    private function interpolate(array $config, array $priorStepOutputs): array
    {
        array_walk_recursive($config, function (&$value) use ($priorStepOutputs) {
            if (is_string($value) && preg_match('/^\{\{steps\.([A-Za-z0-9]+)\.output\.([A-Za-z0-9_]+)\}\}$/', $value, $matches) === 1) {
                $value = Arr::get($priorStepOutputs, "{$matches[1]}.{$matches[2]}");
            }
        });

        return $config;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveCustomer(array $context): Customer
    {
        $customerId = Arr::get($context, 'customer.id');

        if (! is_string($customerId)) {
            throw new RuntimeException('This action requires a customer, but the triggering event has none in context.');
        }

        return Customer::query()->findOrFail($customerId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveOrder(array $context): Order
    {
        $orderId = Arr::get($context, 'order.id');

        if (! is_string($orderId)) {
            throw new RuntimeException('This action requires an order, but the triggering event has none in context.');
        }

        return Order::query()->findOrFail($orderId);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function addCustomerTag(array $config, array $context): array
    {
        $tag = CustomerTag::query()->findOrFail($config['tag_id'] ?? null);
        $this->assignCustomerTag->handle($this->resolveCustomer($context), $tag);

        return ['tag_id' => $tag->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function removeCustomerTagAction(array $config, array $context): array
    {
        $tag = CustomerTag::query()->findOrFail($config['tag_id'] ?? null);
        $this->removeCustomerTag->handle($this->resolveCustomer($context), $tag);

        return ['tag_id' => $tag->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function addCustomerToGroupAction(array $config, array $context): array
    {
        $group = CustomerGroup::query()->findOrFail($config['group_id'] ?? null);
        $this->addCustomerToGroup->handle($group, $this->resolveCustomer($context));

        return ['group_id' => $group->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function removeCustomerFromGroupAction(array $config, array $context): array
    {
        $group = CustomerGroup::query()->findOrFail($config['group_id'] ?? null);
        $this->removeCustomerFromGroup->handle($group, $this->resolveCustomer($context));

        return ['group_id' => $group->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function createDiscountCodeAction(array $config): array
    {
        $promotion = Promotion::query()->findOrFail($config['promotion_id'] ?? null);
        $code = Str::upper(($config['code_prefix'] ?? 'AUTO').'-'.Str::random(8));

        $discountCode = $this->createDiscountCode->handle($promotion, [
            'code' => $code,
            'usage_limit' => $config['usage_limit'] ?? null,
            'per_customer_limit' => $config['per_customer_limit'] ?? null,
            'expires_at' => isset($config['expires_in_days']) ? now()->addDays((int) $config['expires_in_days']) : null,
        ]);

        return ['discount_code_id' => $discountCode->id, 'code' => $discountCode->code];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function expireDiscountAction(array $config): array
    {
        $discountCode = DiscountCode::query()->findOrFail($config['discount_code_id'] ?? null);
        $discountCode->update(['status' => DiscountCodeStatus::Inactive->value]);

        return ['discount_code_id' => $discountCode->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function publishEventAction(array $config, WorkflowExecution $execution): array
    {
        $eventType = $config['event_type'] ?? null;

        if (! is_string($eventType) || $eventType === '') {
            throw new RuntimeException('The "Publish event" action requires an event_type.');
        }

        $payload = is_array($config['payload'] ?? null) ? $config['payload'] : [];

        $this->recordOutboxEvent->handle(
            $eventType,
            'Workflow',
            $execution->workflow_id,
            ['store_id' => $execution->store_id, ...$payload],
            $execution->id,
        );

        return ['event_type' => $eventType];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function createInternalNotificationAction(array $config, array $context, WorkflowExecution $execution): array
    {
        $title = $config['title'] ?? null;

        if (! is_string($title) || $title === '') {
            throw new RuntimeException('The "Create internal notification" action requires a title.');
        }

        [$relatedType, $relatedId] = $this->resolveRelated($context);

        $notification = InternalNotification::query()->create([
            'title' => $title,
            'body' => $config['body'] ?? null,
            'level' => $config['level'] ?? 'info',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'workflow_execution_id' => $execution->id,
        ]);

        return ['internal_notification_id' => $notification->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function updateCustomerMetadataAction(array $config, array $context): array
    {
        $customer = $this->resolveCustomer($context);
        $patch = is_array($config['metadata'] ?? null) ? $config['metadata'] : [];
        $customer->update(['metadata' => [...($customer->metadata ?? []), ...$patch]]);

        return ['customer_id' => $customer->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function updateOrderMetadataAction(array $config, array $context): array
    {
        $order = $this->resolveOrder($context);
        $patch = is_array($config['metadata'] ?? null) ? $config['metadata'] : [];
        $order->update(['metadata' => [...($order->metadata ?? []), ...$patch]]);

        return ['order_id' => $order->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function createTaskAction(array $config, array $context, WorkflowExecution $execution): array
    {
        $title = $config['title'] ?? null;

        if (! is_string($title) || $title === '') {
            throw new RuntimeException('The "Create task" action requires a title.');
        }

        [$relatedType, $relatedId] = $this->resolveRelated($context);

        $task = Task::query()->create([
            'title' => $title,
            'description' => $config['description'] ?? null,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'workflow_execution_id' => $execution->id,
            'due_at' => isset($config['due_in_days']) ? now()->addDays((int) $config['due_in_days']) : null,
        ]);

        return ['task_id' => $task->id];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function appAction(array $config, array $context, WorkflowExecution $execution): array
    {
        $extension = AppExtension::query()->findOrFail($config['extension_id'] ?? null);

        if ($extension->extension_point !== ExtensionPoint::AutomationAction) {
            throw new RuntimeException('The referenced extension is not an automation action.');
        }

        $targetUrl = $extension->config['target_url'] ?? null;

        return $this->httpAction($targetUrl, $context, $config['payload'] ?? [], $execution);
    }

    /**
     * The shared HTTP-call body for CallAppWebhook and AppAction — a
     * direct, unsigned POST (unlike DeliverWebhookJob's HMAC-signed
     * subscription deliveries, which authenticate a platform-issued
     * WebhookSubscription secret; here the target_url is app/admin-
     * authored config, not a platform-verified subscription — see
     * docs/adr/025-automation-engine.md for this tradeoff).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function httpAction(mixed $targetUrl, array $context, mixed $payload, WorkflowExecution $execution): array
    {
        if (! is_string($targetUrl) || $targetUrl === '') {
            throw new RuntimeException('This action requires a target_url.');
        }

        try {
            $response = Http::timeout(10)->post($targetUrl, [
                'execution_id' => $execution->id,
                'context' => $context,
                'payload' => is_array($payload) ? $payload : [],
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("Webhook call to {$targetUrl} failed with status {$response->status()}.");
            }

            return ['status_code' => $response->status()];
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException("Webhook call to {$targetUrl} failed: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveRelated(array $context): array
    {
        $orderId = Arr::get($context, 'order.id');

        if (is_string($orderId)) {
            return ['Order', $orderId];
        }

        $customerId = Arr::get($context, 'customer.id');

        if (is_string($customerId)) {
            return ['Customer', $customerId];
        }

        return [null, null];
    }
}
