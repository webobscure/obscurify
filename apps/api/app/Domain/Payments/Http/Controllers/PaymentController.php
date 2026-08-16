<?php

namespace App\Domain\Payments\Http\Controllers;

use App\Domain\Payments\Http\Resources\PaymentResource;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only this milestone — no cancel/refund actions (spec section 35).
 */
final class PaymentController extends Controller
{
    /**
     * Scoped to the active tenant by Payment's BelongsToTenant global
     * scope — this can never return another store's payments.
     */
    public function index(): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with(['order'])
            ->orderByDesc('created_at')
            ->paginate();

        return PaymentResource::collection($payments);
    }

    /**
     * $payment is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's payment.
     */
    public function show(Payment $payment): PaymentResource
    {
        $payment->load(['order', 'attempts', 'transactions', 'fiscalReceipt']);

        return new PaymentResource($payment);
    }
}
