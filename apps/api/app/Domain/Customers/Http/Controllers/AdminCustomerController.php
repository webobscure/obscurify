<?php

namespace App\Domain\Customers\Http\Controllers;

use App\Domain\CustomerIntelligence\Enums\SegmentableType;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerGroupResource;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerMetricResource;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerSegmentResource;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerSnapshotResource;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerTagResource;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use App\Domain\CustomerIntelligence\Models\CustomerSegmentMembership;
use App\Domain\Customers\Http\Requests\SearchCustomersRequest;
use App\Domain\Customers\Http\Resources\CustomerActivityEventResource;
use App\Domain\Customers\Http\Resources\CustomerAddressResource;
use App\Domain\Customers\Http\Resources\CustomerResource;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Http\Resources\OrderResource;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Http\Resources\ReturnResource;
use App\Http\Controllers\Controller;
use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Merchant-admin customer management (spec section 12) — nested under
 * `auth:sanctum` + `tenant` like every other admin resource, entirely
 * separate from the customer-portal controllers in this same domain
 * (which authenticate via `customer-token` instead). Read-only: there is
 * no admin "edit customer" action in this milestone, since profile edits
 * belong to the customer themselves (see CustomerAccountController).
 */
final class AdminCustomerController extends Controller
{
    /**
     * Search (spec section 10): tags, groups, segments, metrics — every
     * membership filter reads the CustomerSegmentMembership/
     * CustomerTagAssignment cache tables directly rather than
     * re-evaluating SegmentRuleEngine per candidate row, which is what
     * makes filtering a whole customer list by segment affordable (see
     * docs/architecture/customer-intelligence.md).
     */
    public function index(SearchCustomersRequest $request): AnonymousResourceCollection
    {
        $query = Customer::query()->orderByDesc('created_at');

        if ($search = $request->validated('search')) {
            $query->where(fn ($q) => $q
                ->where('email', 'ilike', "%{$search}%")
                ->orWhere('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%"));
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($tag = $request->validated('tag')) {
            $query->whereHas('tagAssignments.tag', fn ($q) => $q->where('slug', $tag));
        }

        if ($groupId = $request->validated('group_id')) {
            // AddCustomerToGroup mirrors manual membership into
            // CustomerSegmentMembership too (see that class's docblock),
            // so this one table covers manual and dynamic/protected
            // groups alike — no need to separately check
            // CustomerGroupMember here.
            $query->whereIn('id', CustomerSegmentMembership::query()
                ->where('segmentable_type', SegmentableType::CustomerGroup->value)
                ->where('segmentable_id', $groupId)
                ->pluck('customer_id'));
        }

        if ($segmentId = $request->validated('segment_id')) {
            $query->whereIn('id', CustomerSegmentMembership::query()
                ->where('segmentable_type', SegmentableType::CustomerSegment->value)
                ->where('segmentable_id', $segmentId)
                ->pluck('customer_id'));
        }

        if ($request->validated('min_total_spent') !== null || $request->validated('max_total_spent') !== null
            || $request->validated('min_order_count') !== null || $request->validated('min_lifetime_value') !== null) {
            $query->whereHas('metric', function ($q) use ($request) {
                if (($min = $request->validated('min_total_spent')) !== null) {
                    $q->where('total_spent_amount', '>=', $min);
                }
                if (($max = $request->validated('max_total_spent')) !== null) {
                    $q->where('total_spent_amount', '<=', $max);
                }
                if (($minOrders = $request->validated('min_order_count')) !== null) {
                    $q->where('order_count', '>=', $minOrders);
                }
                if (($minLtv = $request->validated('min_lifetime_value')) !== null) {
                    $q->where('lifetime_value_amount', '>=', $minLtv);
                }
            });
        }

        return CustomerResource::collection($query->paginate());
    }

    public function show(Customer $customer): CustomerResource
    {
        $customer->load('addresses');

        return new CustomerResource($customer);
    }

    public function orders(Customer $customer): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['customer'])
            ->orderByDesc('number')
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function returns(Customer $customer): AnonymousResourceCollection
    {
        $returns = $customer->returns()
            ->with(['items', 'events'])
            ->orderByDesc('created_at')
            ->paginate();

        return ReturnResource::collection($returns);
    }

    public function addresses(Customer $customer): AnonymousResourceCollection
    {
        return CustomerAddressResource::collection($customer->addresses()->orderByDesc('created_at')->get());
    }

    /**
     * Spec section 12's "Activity timeline" reads directly off the
     * existing OutboxEvent table filtered to this Customer's aggregate —
     * the same "reuse the platform event log as the audit trail" pattern
     * Apps used for its own audit surface (docs/adr/018-app-platform.md),
     * rather than a bespoke per-domain event table. See
     * docs/adr/022-customer-identity.md.
     */
    public function activity(Customer $customer): AnonymousResourceCollection
    {
        $events = OutboxEvent::query()
            ->where('aggregate_type', 'Customer')
            ->where('aggregate_id', $customer->id)
            ->orderByDesc('occurred_at')
            ->paginate();

        return CustomerActivityEventResource::collection($events);
    }

    /**
     * Spec section 13: GET /customers/{id}/metrics. A customer with no
     * orders yet has no CustomerMetric row at all (RecomputeCustomerMetrics
     * only ever upserts one once there's something to compute) — reported
     * as null rather than a row of zeros, so the admin UI can distinguish
     * "never computed" from "computed, currently zero."
     */
    public function metrics(Customer $customer): JsonResource
    {
        $metric = CustomerMetric::query()->where('customer_id', $customer->id)->first();

        return $metric === null
            ? JsonResource::make(null)
            : new CustomerMetricResource($metric);
    }

    /**
     * The metrics trend behind the admin's "Customer Timeline" (spec
     * section 12) — see CaptureCustomerSnapshot; populated only once the
     * background recomputation command has run at least once.
     */
    public function metricsHistory(Customer $customer): AnonymousResourceCollection
    {
        $snapshots = $customer->snapshots()->orderBy('captured_at')->get();

        return CustomerSnapshotResource::collection($snapshots);
    }

    /**
     * Spec section 13: GET /customers/{id}/groups — every group (manual
     * or computed) this customer currently belongs to, read from the
     * CustomerSegmentMembership cache rather than re-evaluating rules.
     */
    public function groups(Customer $customer): AnonymousResourceCollection
    {
        $groupIds = CustomerSegmentMembership::query()
            ->where('customer_id', $customer->id)
            ->where('segmentable_type', SegmentableType::CustomerGroup->value)
            ->pluck('segmentable_id');

        $groups = CustomerGroup::query()->whereIn('id', $groupIds)->orderBy('name')->get();

        return CustomerGroupResource::collection($groups);
    }

    /**
     * Spec section 13: GET /customers/{id}/segments.
     */
    public function segments(Customer $customer): AnonymousResourceCollection
    {
        $segmentIds = CustomerSegmentMembership::query()
            ->where('customer_id', $customer->id)
            ->where('segmentable_type', SegmentableType::CustomerSegment->value)
            ->pluck('segmentable_id');

        $segments = CustomerSegment::query()->whereIn('id', $segmentIds)->orderBy('name')->get();

        return CustomerSegmentResource::collection($segments);
    }

    public function tags(Customer $customer): AnonymousResourceCollection
    {
        $tags = $customer->tagAssignments()->with('tag')->get()->pluck('tag')->filter()->sortBy('name')->values();

        return CustomerTagResource::collection($tags);
    }
}
