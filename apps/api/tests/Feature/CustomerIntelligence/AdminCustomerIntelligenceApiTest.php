<?php

use App\Domain\CustomerIntelligence\Application\CreateCustomerGroup;
use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use App\Domain\CustomerIntelligence\Models\CustomerTag;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('creates a customer group via the admin API, adds a member, and lists it back', function () {
    $created = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/customer-groups', ['name' => 'Wholesale', 'type' => 'manual'], tenantHeader($this->store))
        ->assertCreated();

    $groupId = $created->json('data.id');

    $customerId = app(TenantContext::class)->scope($this->store, fn () => Customer::factory()->create()->id);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/customer-groups/{$groupId}/members", ['customer_id' => $customerId], tenantHeader($this->store))
        ->assertStatus(201);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customer-groups/{$groupId}", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.member_count', 1);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$customerId}/groups", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.0.id', $groupId);
});

it('rejects rules on a manual group, and rejects deleting a protected group', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/customer-groups', [
            'name' => 'Invalid',
            'type' => 'manual',
            'rules' => [['field' => 'order_count', 'operator' => 'greater_than', 'value' => 1]],
        ], tenantHeader($this->store))
        ->assertStatus(422)
        ->assertJsonValidationErrors('rules');

    $protectedGroupId = app(TenantContext::class)->scope(
        $this->store,
        fn () => app(CreateCustomerGroup::class)->handle(['name' => 'System VIP', 'type' => 'protected'])->id,
    );

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/customer-groups/{$protectedGroupId}", [], tenantHeader($this->store))
        ->assertStatus(422)
        ->assertJsonPath('error', 'protected_group');
});

it('creates a nested-rule customer segment via the admin API and evaluates its stored rule tree', function () {
    $created = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/customer-segments', [
            'name' => 'High Value Nordics',
            'rules' => [
                [
                    'boolean_operator' => 'and',
                    'children' => [
                        ['field' => SegmentRuleField::TotalSpent->value, 'operator' => SegmentRuleOperator::GreaterThan->value, 'value' => 1000],
                        [
                            'boolean_operator' => 'or',
                            'children' => [
                                ['field' => SegmentRuleField::CountryCode->value, 'operator' => SegmentRuleOperator::Equals->value, 'value' => 'SE'],
                                ['field' => SegmentRuleField::CountryCode->value, 'operator' => SegmentRuleOperator::Equals->value, 'value' => 'NO'],
                            ],
                        ],
                    ],
                ],
            ],
        ], tenantHeader($this->store))
        ->assertCreated();

    $created->assertJsonPath('data.rules.0.boolean_operator', 'and')
        ->assertJsonCount(2, 'data.rules.0.children')
        ->assertJsonPath('data.rules.0.children.1.boolean_operator', 'or')
        ->assertJsonCount(2, 'data.rules.0.children.1.children');
});

it('rejects a malformed rule tree (both a condition and a group on the same node)', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/customer-segments', [
            'name' => 'Bad segment',
            'rules' => [
                ['field' => 'order_count', 'operator' => 'greater_than', 'value' => 1, 'boolean_operator' => 'and', 'children' => []],
            ],
        ], tenantHeader($this->store))
        ->assertStatus(422)
        ->assertJsonValidationErrors('rules.0');
});

it('creates a tag, blocks deleting a system tag, and blocks manually assigning/removing one', function () {
    $customer = app(TenantContext::class)->scope($this->store, function () {
        $c = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $c->id, 'total_amount' => 1000]);
        Payment::query()->create([
            'order_id' => $order->id, 'provider' => 'fake', 'status' => PaymentStatus::Paid->value,
            'currency' => 'USD', 'amount' => 1000, 'authorized_amount' => 1000, 'captured_amount' => 1000,
            'refunded_amount' => 0, 'external_payment_id' => 'p1',
        ]);
        app(RecomputeCustomerMetrics::class)->handle($c->id);

        return $c;
    });

    $systemTagId = app(TenantContext::class)->scope(
        $this->store,
        fn () => CustomerTag::query()->where('slug', 'first-order')->firstOrFail()->id,
    );

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/customer-tags/{$systemTagId}", [], tenantHeader($this->store))
        ->assertStatus(422)
        ->assertJsonPath('error', 'system_tag');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/customers/{$customer->id}/tags", ['tag_id' => $systemTagId], tenantHeader($this->store))
        ->assertStatus(422)
        ->assertJsonPath('error', 'system_tag');

    $manualTag = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/customer-tags', ['name' => 'Influencer'], tenantHeader($this->store))
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/customers/{$customer->id}/tags", ['tag_id' => $manualTag->json('data.id')], tenantHeader($this->store))
        ->assertStatus(201);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$customer->id}/tags", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonCount(2, 'data'); // first-order (system) + Influencer (manual)
});

it('admin customer search filters by tag, group, segment, and metric thresholds', function () {
    [$highSpender, $lowSpender] = app(TenantContext::class)->scope($this->store, function () {
        $high = Customer::factory()->create(['email' => 'high@example.test']);
        $order = Order::factory()->create(['customer_id' => $high->id, 'total_amount' => 50000]);
        Payment::query()->create([
            'order_id' => $order->id, 'provider' => 'fake', 'status' => PaymentStatus::Paid->value,
            'currency' => 'USD', 'amount' => 50000, 'authorized_amount' => 50000, 'captured_amount' => 50000,
            'refunded_amount' => 0, 'external_payment_id' => 'p1',
        ]);
        app(RecomputeCustomerMetrics::class)->handle($high->id);

        $low = Customer::factory()->create(['email' => 'low@example.test']);
        app(RecomputeCustomerMetrics::class)->handle($low->id);

        return [$high, $low];
    });

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customers?tag=first-order', tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.0.email', 'high@example.test')
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customers?min_total_spent=10000', tenantHeader($this->store))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $highSpender->id);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customers?search=low@', tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.0.id', $lowSpender->id);
});
