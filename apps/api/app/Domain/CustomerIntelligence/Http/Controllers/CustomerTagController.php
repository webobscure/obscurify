<?php

namespace App\Domain\CustomerIntelligence\Http\Controllers;

use App\Domain\CustomerIntelligence\Application\AssignCustomerTag;
use App\Domain\CustomerIntelligence\Application\CreateCustomerTag;
use App\Domain\CustomerIntelligence\Application\DeleteCustomerTag;
use App\Domain\CustomerIntelligence\Application\RemoveCustomerTag;
use App\Domain\CustomerIntelligence\Http\Requests\AssignCustomerTagRequest;
use App\Domain\CustomerIntelligence\Http\Requests\StoreCustomerTagRequest;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerTagResource;
use App\Domain\CustomerIntelligence\Models\CustomerTag;
use App\Domain\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CustomerTagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tags = CustomerTag::query()
            ->withCount('assignments')
            ->orderBy('name')
            ->get();

        return CustomerTagResource::collection($tags);
    }

    public function store(StoreCustomerTagRequest $request, CreateCustomerTag $action): JsonResponse
    {
        $tag = $action->handle($request->validated());

        return (new CustomerTagResource($tag))->response()->setStatusCode(201);
    }

    public function destroy(CustomerTag $tag, DeleteCustomerTag $action): Response
    {
        $action->handle($tag);

        return response()->noContent();
    }

    public function assign(AssignCustomerTagRequest $request, Customer $customer, AssignCustomerTag $action): JsonResponse
    {
        $tag = CustomerTag::query()->findOrFail($request->validated('tag_id'));
        $action->handle($customer, $tag);

        return (new CustomerTagResource($tag))->response()->setStatusCode(201);
    }

    public function remove(Customer $customer, CustomerTag $tag, RemoveCustomerTag $action): Response
    {
        $action->handle($customer, $tag);

        return response()->noContent();
    }
}
