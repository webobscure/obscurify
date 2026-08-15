<?php

namespace App\Domain\CustomerIntelligence\Http\Controllers;

use App\Domain\CustomerIntelligence\Application\AddCustomerToGroup;
use App\Domain\CustomerIntelligence\Application\CreateCustomerGroup;
use App\Domain\CustomerIntelligence\Application\DeleteCustomerGroup;
use App\Domain\CustomerIntelligence\Application\RemoveCustomerFromGroup;
use App\Domain\CustomerIntelligence\Application\UpdateCustomerGroup;
use App\Domain\CustomerIntelligence\Http\Requests\AddCustomerToGroupRequest;
use App\Domain\CustomerIntelligence\Http\Requests\StoreCustomerGroupRequest;
use App\Domain\CustomerIntelligence\Http\Requests\UpdateCustomerGroupRequest;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerGroupResource;
use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use App\Domain\CustomerIntelligence\Support\SegmentRuleTreeLoader;
use App\Domain\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CustomerGroupController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $groups = CustomerGroup::query()
            ->withCount('computedMemberships')
            ->orderBy('name')
            ->get();

        return CustomerGroupResource::collection($groups);
    }

    public function show(CustomerGroup $group): CustomerGroupResource
    {
        $group->loadCount('computedMemberships');
        SegmentRuleTreeLoader::load($group->rootRules);

        return new CustomerGroupResource($group);
    }

    public function store(StoreCustomerGroupRequest $request, CreateCustomerGroup $action): JsonResponse
    {
        $group = $action->handle($request->validated());
        SegmentRuleTreeLoader::load($group->rootRules);

        return (new CustomerGroupResource($group))->response()->setStatusCode(201);
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $group, UpdateCustomerGroup $action): CustomerGroupResource
    {
        $group = $action->handle($group, $request->validated());
        SegmentRuleTreeLoader::load($group->rootRules);

        return new CustomerGroupResource($group);
    }

    public function destroy(CustomerGroup $group, DeleteCustomerGroup $action): Response
    {
        $action->handle($group);

        return response()->noContent();
    }

    public function addMember(AddCustomerToGroupRequest $request, CustomerGroup $group, AddCustomerToGroup $action): Response
    {
        $customer = Customer::query()->findOrFail($request->validated('customer_id'));
        $action->handle($group, $customer);

        return response()->noContent(201);
    }

    public function removeMember(CustomerGroup $group, Customer $customer, RemoveCustomerFromGroup $action): Response
    {
        $action->handle($group, $customer);

        return response()->noContent();
    }
}
