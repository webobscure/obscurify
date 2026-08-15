<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowVariableSource;
use App\Domain\Automation\Enums\WorkflowVariableType;
use App\Domain\Automation\Models\WorkflowTemplate;
use App\Domain\Automation\Models\WorkflowVariable;

/**
 * Idempotently seeds the two global catalogs (spec sections 7/9):
 * built-in workflow variables (the Customer/Order/Payment/Shipment/
 * Return/Inventory/Store/Trigger fields a condition or action config
 * can reference) and the 8 starter templates. Safe to run repeatedly —
 * `firstOrCreate` keyed on each row's natural key — so it can run from
 * both DatabaseSeeder (fresh installs) and the `automation:install`
 * command (existing installs picking up a platform upgrade).
 */
final class RegisterBuiltInAutomationCatalog
{
    public function handle(): void
    {
        $this->registerVariables();
        $this->registerTemplates();
    }

    private function registerVariables(): void
    {
        foreach ($this->variables() as $variable) {
            WorkflowVariable::query()->firstOrCreate(
                ['source' => $variable['source'], 'key' => $variable['key']],
                ['label' => $variable['label'], 'type' => $variable['type'], 'event_types' => null],
            );
        }
    }

    /**
     * @return list<array{source: string, key: string, label: string, type: string}>
     */
    private function variables(): array
    {
        $customer = WorkflowVariableSource::Customer->value;
        $order = WorkflowVariableSource::Order->value;
        $payment = WorkflowVariableSource::Payment->value;
        $shipment = WorkflowVariableSource::Shipment->value;
        $return = WorkflowVariableSource::ReturnRequest->value;
        $inventory = WorkflowVariableSource::Inventory->value;
        $store = WorkflowVariableSource::Store->value;
        $trigger = WorkflowVariableSource::Trigger->value;

        $string = WorkflowVariableType::String->value;
        $number = WorkflowVariableType::Number->value;
        $boolean = WorkflowVariableType::Boolean->value;
        $date = WorkflowVariableType::Date->value;
        $enum = WorkflowVariableType::Enum->value;
        $collection = WorkflowVariableType::Collection->value;

        return [
            ['source' => $customer, 'key' => 'id', 'label' => 'Customer ID', 'type' => $string],
            ['source' => $customer, 'key' => 'email', 'label' => 'Customer email', 'type' => $string],
            ['source' => $customer, 'key' => 'status', 'label' => 'Customer status', 'type' => $enum],
            ['source' => $customer, 'key' => 'first_name', 'label' => 'Customer first name', 'type' => $string],
            ['source' => $customer, 'key' => 'last_name', 'label' => 'Customer last name', 'type' => $string],
            ['source' => $customer, 'key' => 'date_of_birth', 'label' => 'Customer date of birth', 'type' => $date],
            ['source' => $customer, 'key' => 'verified_at', 'label' => 'Customer verified at', 'type' => $date],

            ['source' => $order, 'key' => 'id', 'label' => 'Order ID', 'type' => $string],
            ['source' => $order, 'key' => 'number', 'label' => 'Order number', 'type' => $number],
            ['source' => $order, 'key' => 'total_amount', 'label' => 'Order total (minor units)', 'type' => $number],
            ['source' => $order, 'key' => 'currency', 'label' => 'Order currency', 'type' => $string],
            ['source' => $order, 'key' => 'order_status', 'label' => 'Order status', 'type' => $enum],
            ['source' => $order, 'key' => 'financial_status', 'label' => 'Order financial status', 'type' => $enum],
            ['source' => $order, 'key' => 'fulfillment_status', 'label' => 'Order fulfillment status', 'type' => $enum],
            ['source' => $order, 'key' => 'email', 'label' => 'Order contact email', 'type' => $string],

            ['source' => $payment, 'key' => 'id', 'label' => 'Payment ID', 'type' => $string],
            ['source' => $payment, 'key' => 'status', 'label' => 'Payment status', 'type' => $enum],
            ['source' => $payment, 'key' => 'amount', 'label' => 'Payment amount (minor units)', 'type' => $number],
            ['source' => $payment, 'key' => 'provider', 'label' => 'Payment provider', 'type' => $string],

            ['source' => $shipment, 'key' => 'id', 'label' => 'Shipment ID', 'type' => $string],
            ['source' => $shipment, 'key' => 'status', 'label' => 'Shipment status', 'type' => $enum],
            ['source' => $shipment, 'key' => 'tracking_number', 'label' => 'Shipment tracking number', 'type' => $string],
            ['source' => $shipment, 'key' => 'provider', 'label' => 'Shipment carrier', 'type' => $string],

            ['source' => $return, 'key' => 'id', 'label' => 'Return ID', 'type' => $string],
            ['source' => $return, 'key' => 'status', 'label' => 'Return status', 'type' => $enum],
            ['source' => $return, 'key' => 'number', 'label' => 'Return number', 'type' => $number],

            ['source' => $inventory, 'key' => 'id', 'label' => 'Inventory item ID', 'type' => $string],
            ['source' => $inventory, 'key' => 'low_stock_threshold', 'label' => 'Low stock threshold', 'type' => $number],

            ['source' => $store, 'key' => 'id', 'label' => 'Store ID', 'type' => $string],
            ['source' => $store, 'key' => 'name', 'label' => 'Store name', 'type' => $string],

            ['source' => $trigger, 'key' => 'event_type', 'label' => 'Trigger event type', 'type' => $string],
            ['source' => $trigger, 'key' => 'payload', 'label' => 'Raw trigger payload', 'type' => $collection],
        ];
    }

    private function registerTemplates(): void
    {
        foreach ($this->templates() as $template) {
            WorkflowTemplate::query()->firstOrCreate(
                ['key' => $template['key']],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'category' => $template['category'],
                    'definition' => $template['definition'],
                ],
            );
        }
    }

    /**
     * @return list<array{key: string, name: string, description: string, category: string, definition: array<string, mixed>}>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 'welcome-customer',
                'name' => 'Welcome customer',
                'description' => 'Notify staff whenever a new customer registers.',
                'category' => 'customers',
                'definition' => [
                    'trigger' => ['event_type' => 'CustomerCreated'],
                    'conditions' => [],
                    'actions' => [
                        ['type' => 'send_in_app_notification', 'config' => ['body_text' => 'New customer registered: a new customer just created an account.']],
                    ],
                ],
            ],
            [
                'key' => 'vip-customer',
                'name' => 'VIP customer',
                'description' => 'Notify staff and generate a reward discount code when a customer reaches VIP status. Select a promotion for the discount code before publishing.',
                'category' => 'customers',
                'definition' => [
                    'trigger' => ['event_type' => 'CustomerBecameVip'],
                    'conditions' => [],
                    'actions' => [
                        ['type' => 'send_in_app_notification', 'config' => ['body_text' => 'New VIP customer: a customer just reached VIP status.']],
                        ['type' => 'create_discount_code', 'config' => ['promotion_id' => null, 'code_prefix' => 'VIP']],
                    ],
                ],
            ],
            [
                'key' => 'low-inventory-alert',
                'name' => 'Low inventory alert',
                'description' => 'Notify staff and create a restock task when an item drops below its threshold.',
                'category' => 'inventory',
                'definition' => [
                    'trigger' => ['event_type' => 'InventoryBelowThreshold'],
                    'conditions' => [],
                    'actions' => [
                        ['type' => 'send_in_app_notification', 'config' => ['body_text' => 'Low stock alert: an item has fallen below its restock threshold.']],
                        ['type' => 'create_task', 'config' => ['title' => 'Reorder stock', 'description' => 'Review and reorder this low-stock item.']],
                    ],
                ],
            ],
            [
                'key' => 'abandoned-payment',
                'name' => 'Abandoned payment',
                'description' => 'Follow up a couple of hours after a payment attempt fails.',
                'category' => 'payments',
                'definition' => [
                    'trigger' => ['event_type' => 'PaymentFailed'],
                    'conditions' => [],
                    'actions' => [
                        ['type' => 'delay', 'config' => ['delay_type' => 'hours', 'value' => 2]],
                        ['type' => 'send_in_app_notification', 'config' => ['body_text' => "Payment failed: a customer's payment failed a couple of hours ago — consider following up."]],
                    ],
                ],
            ],
            [
                'key' => 'shipment-delivered',
                'name' => 'Shipment delivered',
                'description' => 'Prompt a review request a day after delivery.',
                'category' => 'shipping',
                'definition' => [
                    'trigger' => ['event_type' => 'ShipmentDelivered'],
                    'conditions' => [],
                    'actions' => [
                        ['type' => 'delay', 'config' => ['delay_type' => 'hours', 'value' => 24]],
                        ['type' => 'create_task', 'config' => ['title' => 'Request a review', 'description' => 'Follow up with the customer for a product review.']],
                    ],
                ],
            ],
            [
                'key' => 'refund-completed',
                'name' => 'Refund completed',
                'description' => 'Notify staff whenever a refund finishes processing.',
                'category' => 'payments',
                'definition' => [
                    'trigger' => ['event_type' => 'RefundCompleted'],
                    'conditions' => [],
                    'actions' => [
                        ['type' => 'send_in_app_notification', 'config' => ['body_text' => 'Refund completed: a refund was just completed.']],
                    ],
                ],
            ],
            [
                'key' => 'high-value-order',
                'name' => 'High value order',
                'description' => 'Flag orders above a value threshold for personal follow-up. Adjust the threshold before publishing.',
                'category' => 'orders',
                'definition' => [
                    'trigger' => ['event_type' => 'OrderCreated'],
                    'conditions' => [
                        ['variable_key' => 'order.total_amount', 'operator' => 'greater_than', 'value' => 50000],
                    ],
                    'actions' => [
                        ['type' => 'send_in_app_notification', 'config' => ['body_text' => 'High value order: a high-value order just came in.']],
                        ['type' => 'create_task', 'config' => ['title' => 'White-glove follow-up', 'description' => 'Consider personally following up with this customer.']],
                    ],
                ],
            ],
            [
                'key' => 'back-in-stock',
                'name' => 'Back in stock',
                'description' => 'Notify staff when a previously out-of-stock item is restocked.',
                'category' => 'inventory',
                'definition' => [
                    'trigger' => ['event_type' => 'ProductBackInStock'],
                    'conditions' => [],
                    'actions' => [
                        ['type' => 'send_in_app_notification', 'config' => ['body_text' => 'Back in stock: an item is back in stock.']],
                    ],
                ],
            ],
        ];
    }
}
