<?php

namespace App\Domain\CustomerIntelligence\Http\Controllers;

use App\Domain\CustomerIntelligence\Application\CreateCustomerSegment;
use App\Domain\CustomerIntelligence\Application\DeleteCustomerSegment;
use App\Domain\CustomerIntelligence\Application\UpdateCustomerSegment;
use App\Domain\CustomerIntelligence\Http\Requests\StoreCustomerSegmentRequest;
use App\Domain\CustomerIntelligence\Http\Requests\UpdateCustomerSegmentRequest;
use App\Domain\CustomerIntelligence\Http\Resources\CustomerSegmentResource;
use App\Domain\CustomerIntelligence\Models\CustomerSegment;
use App\Domain\CustomerIntelligence\Support\SegmentRuleTreeLoader;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CustomerSegmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $segments = CustomerSegment::query()
            ->withCount('computedMemberships')
            ->orderBy('name')
            ->get();

        return CustomerSegmentResource::collection($segments);
    }

    public function show(CustomerSegment $segment): CustomerSegmentResource
    {
        $segment->loadCount('computedMemberships');
        SegmentRuleTreeLoader::load($segment->rootRules);

        return new CustomerSegmentResource($segment);
    }

    public function store(StoreCustomerSegmentRequest $request, CreateCustomerSegment $action): JsonResponse
    {
        $segment = $action->handle($request->validated());
        SegmentRuleTreeLoader::load($segment->rootRules);

        return (new CustomerSegmentResource($segment))->response()->setStatusCode(201);
    }

    public function update(UpdateCustomerSegmentRequest $request, CustomerSegment $segment, UpdateCustomerSegment $action): CustomerSegmentResource
    {
        $segment = $action->handle($segment, $request->validated());
        SegmentRuleTreeLoader::load($segment->rootRules);

        return new CustomerSegmentResource($segment);
    }

    public function destroy(CustomerSegment $segment, DeleteCustomerSegment $action): Response
    {
        $action->handle($segment);

        return response()->noContent();
    }
}
