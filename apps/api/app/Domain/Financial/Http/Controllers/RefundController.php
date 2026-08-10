<?php

namespace App\Domain\Financial\Http\Controllers;

use App\Domain\Financial\Application\CancelRefund;
use App\Domain\Financial\Application\RequestRefund;
use App\Domain\Financial\Http\Requests\StoreRefundRequest;
use App\Domain\Financial\Http\Resources\RefundResource;
use App\Domain\Financial\Models\Refund;
use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Shared\Commerce\Application\IdempotencyKeyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

final class RefundController extends Controller
{
    private const array WITH = ['items'];

    /**
     * Scoped to the active tenant by Refund's BelongsToTenant global
     * scope — this can never return another store's refunds.
     */
    public function index(): AnonymousResourceCollection
    {
        $refunds = Refund::query()
            ->with(self::WITH)
            ->orderByDesc('created_at')
            ->paginate();

        return RefundResource::collection($refunds);
    }

    /**
     * $refund is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's refund.
     */
    public function show(Refund $refund): RefundResource
    {
        $refund->load(self::WITH);

        return new RefundResource($refund);
    }

    /**
     * Requires an Idempotency-Key header (spec section 13) — same
     * reasoning and same infrastructure as
     * StorefrontPaymentController::store(): duplicate-refund prevention
     * is too important to make optional, and reuses IdempotencyKeyStore
     * rather than building a second implementation.
     *
     * $order is resolved via tenant-scoped route model binding.
     */
    public function store(StoreRefundRequest $request, Order $order, RequestRefund $action, IdempotencyKeyStore $idempotencyKeyStore): JsonResponse
    {
        $data = $request->validated();

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key header is required.',
            ]);
        }

        $requestHash = hash('sha256', json_encode([$order->id, $data], JSON_THROW_ON_ERROR));

        $result = $idempotencyKeyStore->handle('refund.create', $key, $requestHash, function () use ($order, $data, $action) {
            $refund = $action->handle(
                $order,
                $data['items'] ?? [],
                $data['shipping_amount'] ?? 0,
                $data['adjustment_amount'] ?? 0,
                $data['reason'] ?? null,
                $data['provider'] ?? null,
            );

            return [
                'status' => 201,
                'body' => (new RefundResource($refund->load(self::WITH)))->resolve(),
            ];
        });

        return response()->json(['data' => $result['body']], $result['status']);
    }

    public function cancel(Refund $refund, CancelRefund $action): RefundResource
    {
        $refund = $action->handle($refund);

        return new RefundResource($refund->load(self::WITH));
    }
}
