<?php

namespace App\Domain\Customers\Http\Controllers;

use App\Domain\Customers\Application\RecordCustomerOrderView;
use App\Domain\Customers\Application\ReorderFromOrder;
use App\Domain\Customers\Application\RequestCustomerReturn;
use App\Domain\Customers\Http\Requests\StoreCustomerReturnRequest;
use App\Domain\Customers\Http\Resources\CustomerOrderResource;
use App\Domain\Customers\Http\Resources\CustomerOrderSummaryResource;
use App\Domain\Customers\Support\CurrentCustomerContext;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Http\Resources\ReturnResource;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerOrderController extends Controller
{
    private const CART_TOKEN_COOKIE = 'storefront_cart_token';

    public function index(Request $request, CurrentCustomerContext $currentCustomerContext): JsonResponse
    {
        $orders = Order::query()
            ->where('customer_id', $currentCustomerContext->customer()->id)
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 20));

        return CustomerOrderSummaryResource::collection($orders)->response();
    }

    public function show(
        Order $order,
        CurrentCustomerContext $currentCustomerContext,
        RecordCustomerOrderView $recordCustomerOrderView,
    ): CustomerOrderResource {
        $customer = $currentCustomerContext->customer();
        $this->assertOwnership($customer->id, $order);

        $order->load([
            'items', 'shippingAddress', 'billingAddress', 'shippingLine', 'discountApplications',
            'payments', 'shipments', 'returns.items', 'refunds.items',
        ]);

        $recordCustomerOrderView->handle($customer, $order);

        return new CustomerOrderResource($order);
    }

    public function reorder(
        Request $request,
        Order $order,
        ReorderFromOrder $action,
        CurrentCustomerContext $currentCustomerContext,
    ): JsonResponse {
        $customer = $currentCustomerContext->customer();
        $this->assertOwnership($customer->id, $order);

        $order->load('items');
        // A body-supplied cart_token (used by the customer-portal client
        // when it's tracking the cart client-side) takes precedence over
        // the HttpOnly cookie the regular storefront /cart endpoints rely
        // on — either way GetOrCreateCart falls back to a fresh cart if
        // the token doesn't resolve to a live one.
        $cartToken = $request->string('cart_token')->value() ?: $request->cookie(self::CART_TOKEN_COOKIE);
        $result = $action->handle($order, $cartToken);

        $cart = $result['cart'];

        return response()->json([
            'data' => [
                'cart_token' => $cart->token,
                'skipped' => $result['skipped'],
            ],
        ])
            ->cookie(cookie(
                name: self::CART_TOKEN_COOKIE,
                value: $cart->token,
                minutes: 60 * 24 * 30,
                path: '/',
                domain: null,
                secure: app()->environment('production'),
                httpOnly: true,
                sameSite: 'lax',
            ));
    }

    public function storeReturn(
        StoreCustomerReturnRequest $request,
        Order $order,
        RequestCustomerReturn $action,
        CurrentCustomerContext $currentCustomerContext,
    ): JsonResponse {
        $customer = $currentCustomerContext->customer();
        $this->assertOwnership($customer->id, $order);

        $returnRequest = $action->handle($customer, $order, $request->validated('items'), $request->validated('notes'));
        $returnRequest->load(['items', 'events']);

        return (new ReturnResource($returnRequest))->response()->setStatusCode(201);
    }

    /**
     * 403, not 404: the order genuinely exists in this store (Order's own
     * BelongsToTenant scope already 404s a cross-store id via route model
     * binding before this method ever runs) — it simply isn't theirs.
     */
    private function assertOwnership(string $customerId, Order $order): void
    {
        if ($order->customer_id !== $customerId) {
            throw new AuthorizationException('This order does not belong to you.');
        }
    }
}
