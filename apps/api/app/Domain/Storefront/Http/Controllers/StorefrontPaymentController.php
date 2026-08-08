<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Application\CreatePayment;
use App\Domain\Storefront\Http\Resources\PaymentResource;
use App\Http\Controllers\Controller;
use App\Shared\Commerce\Application\IdempotencyKeyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `$order` is resolved via tenant-scoped route model binding — a
 * cross-tenant order id 404s before this method body ever runs. Ownership
 * within the tenant is the order's own ULID (no customer auth exists yet,
 * see spec section 24 and the Milestone 3 storefront order-confirmation
 * endpoint this mirrors): knowing the id is what authorizes paying it.
 */
final class StorefrontPaymentController extends Controller
{
    /**
     * Requires an Idempotency-Key header, same reasoning as
     * StorefrontCheckoutController::complete() — duplicate-payment
     * prevention is too important to make optional. Reuses the existing
     * IdempotencyKeyStore infrastructure from Milestone 3 rather than
     * building a second implementation (spec section 18).
     */
    public function store(Request $request, Order $order, CreatePayment $action, IdempotencyKeyStore $idempotencyKeyStore): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
        ]);

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key header is required.',
            ]);
        }

        $requestHash = hash('sha256', $order->id.'|'.$data['provider']);

        $result = $idempotencyKeyStore->handle('payment.create', $key, $requestHash, function () use ($order, $data, $action) {
            $payment = $action->handle($order, $data['provider']);

            return [
                'status' => 201,
                'body' => (new PaymentResource($payment))->resolve(),
            ];
        });

        return response()->json(['data' => $result['body']], $result['status']);
    }
}
