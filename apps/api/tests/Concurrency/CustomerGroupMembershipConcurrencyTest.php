<?php

use App\Domain\CustomerIntelligence\Application\AddCustomerToGroup;
use App\Domain\CustomerIntelligence\Enums\CustomerGroupType;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerGroupMember;
use App\Domain\CustomerIntelligence\Models\CustomerSegmentMembership;
use App\Domain\Customers\Models\Customer;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Same fork-based real-concurrency pattern as
 * tests/Concurrency/CustomerTokenRefreshConcurrencyTest.php (spec section
 * 17: "Concurrency"). Proves AddCustomerToGroup's row lock actually
 * prevents the race it exists to prevent: without it, two concurrent
 * adds of the same customer to the same group could both pass the
 * "not already a member" check before either commits, and the second
 * INSERT would then hit customer_group_members' unique constraint as a
 * hard failure rather than a graceful no-op.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->customerId, $this->groupId] = app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $group = CustomerGroup::factory()->create(['type' => CustomerGroupType::Manual->value]);

        return [$customer->id, $group->id];
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets two simultaneous adds of the same customer to the same group both succeed without error, leaving exactly one membership', function () {
    $add = function () {
        return app(TenantContext::class)->scope($this->store, function () {
            $group = CustomerGroup::query()->findOrFail($this->groupId);
            $customer = Customer::query()->findOrFail($this->customerId);
            app(AddCustomerToGroup::class)->handle($group, $customer);

            return true;
        });
    };

    $results = runConcurrently([$add, $add]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    expect($succeeded)->toHaveCount(2);

    app(TenantContext::class)->scope($this->store, function () {
        expect(CustomerGroupMember::query()
            ->where('customer_group_id', $this->groupId)
            ->where('customer_id', $this->customerId)
            ->count())->toBe(1);

        expect(CustomerSegmentMembership::query()
            ->where('customer_id', $this->customerId)
            ->where('segmentable_id', $this->groupId)
            ->count())->toBe(1);

        expect(OutboxEvent::query()->where('event_type', 'CustomerEnteredSegment')->count())->toBe(1);
    });
});
