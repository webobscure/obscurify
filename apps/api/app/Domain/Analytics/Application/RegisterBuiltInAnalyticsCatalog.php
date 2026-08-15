<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Enums\MetricCalculation;
use App\Domain\Analytics\Enums\MetricCategory;
use App\Domain\Analytics\Enums\MetricUnit;
use App\Domain\Analytics\Models\MetricDefinition;

/**
 * Idempotently seeds the global metric catalog (spec section 3) — safe
 * to run repeatedly (`firstOrCreate` keyed on `key`), from both
 * DatabaseSeeder and `php artisan analytics:install`.
 */
final class RegisterBuiltInAnalyticsCatalog
{
    public function handle(): void
    {
        foreach ($this->metrics() as $metric) {
            MetricDefinition::query()->firstOrCreate(
                ['key' => $metric['key']],
                [
                    'label' => $metric['label'],
                    'description' => $metric['description'],
                    'category' => $metric['category'],
                    'unit' => $metric['unit'],
                    'calculation' => $metric['calculation'],
                ],
            );
        }
    }

    /**
     * @return list<array{key: string, label: string, description: string, category: string, unit: string, calculation: string}>
     */
    private function metrics(): array
    {
        $revenue = MetricCategory::Revenue->value;
        $orders = MetricCategory::Orders->value;
        $customers = MetricCategory::Customers->value;
        $inventory = MetricCategory::Inventory->value;
        $leaderboard = MetricCategory::Leaderboard->value;
        $search = MetricCategory::Search->value;

        $currency = MetricUnit::Currency->value;
        $count = MetricUnit::Count->value;
        $percentage = MetricUnit::Percentage->value;

        $sum = MetricCalculation::Sum->value;
        $countCalc = MetricCalculation::Count->value;
        $derived = MetricCalculation::Derived->value;
        $leaderboardCalc = MetricCalculation::Leaderboard->value;
        $gauge = MetricCalculation::Gauge->value;
        $placeholder = MetricCalculation::Placeholder->value;

        return [
            ['key' => 'gross_revenue', 'label' => 'Gross Revenue', 'description' => 'Total value of paid orders, before refunds.', 'category' => $revenue, 'unit' => $currency, 'calculation' => $sum],
            ['key' => 'net_revenue', 'label' => 'Revenue', 'description' => 'Gross revenue minus refunds.', 'category' => $revenue, 'unit' => $currency, 'calculation' => $derived],
            ['key' => 'refund_amount', 'label' => 'Refund Amount', 'description' => 'Total value of completed refunds.', 'category' => $revenue, 'unit' => $currency, 'calculation' => $sum],
            ['key' => 'order_count', 'label' => 'Orders', 'description' => 'Orders created.', 'category' => $orders, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'paid_order_count', 'label' => 'Paid Orders', 'description' => 'Orders whose payment was confirmed.', 'category' => $orders, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'refund_count', 'label' => 'Refunds', 'description' => 'Completed refunds.', 'category' => $orders, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'return_count', 'label' => 'Returns', 'description' => 'Completed returns.', 'category' => $orders, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'average_order_value', 'label' => 'Average Order Value', 'description' => 'Gross revenue divided by paid orders.', 'category' => $orders, 'unit' => $currency, 'calculation' => $derived],
            ['key' => 'conversion_rate', 'label' => 'Conversion Rate', 'description' => 'Not yet available — requires storefront visit tracking, out of scope for this milestone.', 'category' => $orders, 'unit' => $percentage, 'calculation' => $placeholder],
            ['key' => 'new_customers', 'label' => 'New Customers', 'description' => 'Customer accounts created.', 'category' => $customers, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'returning_customers', 'label' => 'Returning Customers', 'description' => 'Distinct customers whose order that day was not their first ever.', 'category' => $customers, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'repeat_purchase_rate', 'label' => 'Repeat Purchase Rate', 'description' => 'Share of that day\'s orders placed by a returning customer.', 'category' => $customers, 'unit' => $percentage, 'calculation' => $derived],
            ['key' => 'lifetime_value', 'label' => 'Lifetime Value', 'description' => 'Average all-time spend of customers who ordered that day.', 'category' => $customers, 'unit' => $currency, 'calculation' => $derived],
            ['key' => 'inventory_value', 'label' => 'Inventory Value', 'description' => 'Current on-hand stock valued at cost, across every tracked item.', 'category' => $inventory, 'unit' => $currency, 'calculation' => $gauge],
            ['key' => 'top_products', 'label' => 'Top Products', 'description' => 'Products ranked by revenue that day.', 'category' => $leaderboard, 'unit' => $currency, 'calculation' => $leaderboardCalc],
            ['key' => 'top_categories', 'label' => 'Top Categories', 'description' => 'Categories ranked by revenue that day.', 'category' => $leaderboard, 'unit' => $currency, 'calculation' => $leaderboardCalc],
            ['key' => 'top_collections', 'label' => 'Top Collections', 'description' => 'Collections ranked by revenue that day.', 'category' => $leaderboard, 'unit' => $currency, 'calculation' => $leaderboardCalc],
            ['key' => 'top_discounts', 'label' => 'Top Discounts', 'description' => 'Discounts ranked by amount applied that day.', 'category' => $leaderboard, 'unit' => $currency, 'calculation' => $leaderboardCalc],
            ['key' => 'top_shipping_methods', 'label' => 'Top Shipping Methods', 'description' => 'Shipping methods ranked by revenue that day.', 'category' => $leaderboard, 'unit' => $currency, 'calculation' => $leaderboardCalc],
            ['key' => 'search_count', 'label' => 'Searches', 'description' => 'Searches performed.', 'category' => $search, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'zero_result_search_count', 'label' => 'Zero-Result Searches', 'description' => 'Searches that returned no results.', 'category' => $search, 'unit' => $count, 'calculation' => $countCalc],
            ['key' => 'search_click_count', 'label' => 'Search Clicks', 'description' => 'Products clicked from a search result.', 'category' => $search, 'unit' => $count, 'calculation' => $countCalc],
        ];
    }
}
