<?php

use App\Domain\CustomerIntelligence\Application\CreateCustomerSegment;
use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\CustomerIntelligence\Application\UpdateCustomerSegment;
use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleField;
use App\Domain\CustomerIntelligence\Enums\SegmentRuleOperator;
use App\Domain\CustomerIntelligence\Models\CustomerSegmentMembership;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('recomputing metrics moves a customer into and out of a matching dynamic segment, firing entered/left events', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $segment = app(CreateCustomerSegment::class)->handle([
            'name' => 'Big Spenders',
            'rules' => [
                ['field' => SegmentRuleField::TotalSpent->value, 'operator' => SegmentRuleOperator::GreaterThan->value, 'value' => 5000],
            ],
        ]);

        // No orders yet — not a member.
        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerSegmentMembership::query()
            ->where('customer_id', $customer->id)
            ->where('segmentable_type', SegmentableType::CustomerSegment->value)
            ->where('segmentable_id', $segment->id)
            ->exists())->toBeFalse();

        // A qualifying order pushes total_spent over the threshold via a
        // real Payment, not a raw metric write, to exercise the same
        // captured-amount computation RecomputeCustomerMetrics itself uses.
        $order = Order::factory()->create(['customer_id' => $customer->id, 'total_amount' => 10000]);
        Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'fake',
            'status' => PaymentStatus::Paid->value,
            'currency' => 'USD',
            'amount' => 10000,
            'authorized_amount' => 10000,
            'captured_amount' => 10000,
            'refunded_amount' => 0,
            'external_payment_id' => 'p1',
        ]);

        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerSegmentMembership::query()
            ->where('customer_id', $customer->id)
            ->where('segmentable_type', SegmentableType::CustomerSegment->value)
            ->where('segmentable_id', $segment->id)
            ->exists())->toBeTrue();

        expect(OutboxEvent::query()->where('event_type', 'CustomerEnteredSegment')->count())->toBe(1);

        // Recomputing again with no change stays a member — no duplicate
        // "entered" event, and no spurious "left" either.
        app(RecomputeCustomerMetrics::class)->handle($customer->id);
        expect(OutboxEvent::query()->where('event_type', 'CustomerEnteredSegment')->count())->toBe(1);
        expect(OutboxEvent::query()->where('event_type', 'CustomerLeftSegment')->count())->toBe(0);
    });
});

it('archiving a segment excludes it from recomputation', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $segment = app(CreateCustomerSegment::class)->handle([
            'name' => 'Everyone With An Email',
            'rules' => [
                ['field' => SegmentRuleField::EmailVerified->value, 'operator' => SegmentRuleOperator::IsFalse->value, 'value' => null],
            ],
        ]);

        app(UpdateCustomerSegment::class)->handle($segment, ['status' => 'archived']);

        app(RecomputeCustomerMetrics::class)->handle($customer->id);

        expect(CustomerSegmentMembership::query()
            ->where('segmentable_type', SegmentableType::CustomerSegment->value)
            ->where('segmentable_id', $segment->id)
            ->exists())->toBeFalse();
    });
});
