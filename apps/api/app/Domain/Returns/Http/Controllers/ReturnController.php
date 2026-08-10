<?php

namespace App\Domain\Returns\Http\Controllers;

use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Application\ApproveReturn;
use App\Domain\Returns\Application\CancelReturn;
use App\Domain\Returns\Application\CompleteReturn;
use App\Domain\Returns\Application\InspectReturn;
use App\Domain\Returns\Application\ReceiveReturn;
use App\Domain\Returns\Application\RejectReturn;
use App\Domain\Returns\Application\RequestReturn;
use App\Domain\Returns\Http\Requests\InspectReturnRequest;
use App\Domain\Returns\Http\Requests\RejectReturnRequest;
use App\Domain\Returns\Http\Requests\StoreReturnRequest;
use App\Domain\Returns\Http\Requests\UpdateReturnRequest;
use App\Domain\Returns\Http\Resources\ReturnResource;
use App\Domain\Returns\Models\ReturnRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReturnController extends Controller
{
    private const array WITH = ['items.inspection', 'items.disposition', 'events'];

    /**
     * Scoped to the active tenant by ReturnRequest's BelongsToTenant global
     * scope — this can never return another store's returns.
     */
    public function index(): AnonymousResourceCollection
    {
        $returns = ReturnRequest::query()
            ->with(self::WITH)
            ->orderByDesc('created_at')
            ->paginate();

        return ReturnResource::collection($returns);
    }

    /**
     * $returnRequest is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's return.
     */
    public function show(ReturnRequest $returnRequest): ReturnResource
    {
        $returnRequest->load(self::WITH);

        return new ReturnResource($returnRequest);
    }

    /**
     * $order is resolved via tenant-scoped route model binding.
     */
    public function store(StoreReturnRequest $request, Order $order, RequestReturn $action): JsonResponse
    {
        $data = $request->validated();

        $returnRequest = $action->handle($order, $data['items'], $data['notes'] ?? null, $data['customer_id'] ?? null);

        return (new ReturnResource($returnRequest->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(UpdateReturnRequest $request, ReturnRequest $returnRequest): ReturnResource
    {
        $returnRequest->update($request->validated());

        return new ReturnResource($returnRequest->load(self::WITH));
    }

    public function approve(ReturnRequest $returnRequest, ApproveReturn $action): ReturnResource
    {
        $returnRequest = $action->handle($returnRequest);

        return new ReturnResource($returnRequest->load(self::WITH));
    }

    public function reject(RejectReturnRequest $request, ReturnRequest $returnRequest, RejectReturn $action): ReturnResource
    {
        $returnRequest = $action->handle($returnRequest, $request->validated()['reason'] ?? null);

        return new ReturnResource($returnRequest->load(self::WITH));
    }

    public function receive(ReturnRequest $returnRequest, ReceiveReturn $action): ReturnResource
    {
        $returnRequest = $action->handle($returnRequest);

        return new ReturnResource($returnRequest->load(self::WITH));
    }

    public function inspect(InspectReturnRequest $request, ReturnRequest $returnRequest, InspectReturn $action): ReturnResource
    {
        $returnRequest = $action->handle($returnRequest, $request->validated()['items'], $request->user()?->id);

        return new ReturnResource($returnRequest->load(self::WITH));
    }

    public function complete(ReturnRequest $returnRequest, CompleteReturn $action): ReturnResource
    {
        $returnRequest = $action->handle($returnRequest);

        return new ReturnResource($returnRequest->load(self::WITH));
    }

    public function cancel(ReturnRequest $returnRequest, CancelReturn $action): ReturnResource
    {
        $returnRequest = $action->handle($returnRequest);

        return new ReturnResource($returnRequest->load(self::WITH));
    }
}
