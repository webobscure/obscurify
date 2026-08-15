<?php

namespace App\Domain\Customers\Http\Controllers;

use App\Domain\Customers\Application\RevokeCustomerSession;
use App\Domain\Customers\Http\Resources\CustomerSessionResource;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Customers\Support\CurrentCustomerContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CustomerSessionController extends Controller
{
    public function index(CurrentCustomerContext $currentCustomerContext): JsonResponse
    {
        $sessions = CustomerSession::query()
            ->where('customer_id', $currentCustomerContext->customer()->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get();

        return CustomerSessionResource::collection($sessions)->response();
    }

    public function destroy(
        CustomerSession $session,
        RevokeCustomerSession $action,
        CurrentCustomerContext $currentCustomerContext,
    ): Response {
        $action->handle($currentCustomerContext->customer(), $session);

        return response()->noContent();
    }
}
