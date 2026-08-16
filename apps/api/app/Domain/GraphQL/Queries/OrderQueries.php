<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\Customers\Application\RecordCustomerOrderView;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\GraphQL\Types\CommonTypes;
use App\Domain\Orders\Models\Order;
use GraphQL\Type\Definition\Type;

/**
 * `orders`/`order` — a Customer sees only their own orders
 * (CustomerOrderController parity, including the `RecordCustomerOrderView`
 * side effect on single-order reads); a Merchant sees every order in
 * the active store (OrderController parity). Both share one query field
 * rather than two, since the only real difference is the scoping filter
 * — the underlying Order rows and type are identical either way.
 */
final class OrderQueries
{
    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('orders', [
            'type' => $types->get('OrderConnection'),
            'args' => ['page' => Type::int(), 'perPage' => Type::int()],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $query = Order::query()->where('store_id', $context->store->id);

                if ($context->isCustomer()) {
                    $query->where('customer_id', $context->requireCustomer()->id);
                } elseif (! $context->isMerchant()) {
                    throw GraphQLUserError::forbidden('You must be logged in to view orders.');
                }

                $orders = $query->orderByDesc('created_at')->paginate($args['perPage'] ?? 15, ['*'], 'page', $args['page'] ?? 1);

                return ['data' => $orders->items(), 'pageInfo' => CommonTypes::resolvePageInfo($orders)];
            },
        ]);

        $queries->register('order', [
            'type' => $types->get('Order'),
            'args' => ['id' => Type::nonNull(Type::id())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $order = Order::query()
                    ->where('store_id', $context->store->id)
                    ->where('id', $args['id'])
                    ->with(['items', 'shippingAddress', 'billingAddress'])
                    ->first();

                if ($order === null) {
                    throw GraphQLUserError::notFound('Order');
                }

                if ($context->isCustomer()) {
                    $customer = $context->requireCustomer();

                    if ($order->customer_id !== $customer->id) {
                        throw GraphQLUserError::forbidden('This order does not belong to you.');
                    }

                    app(RecordCustomerOrderView::class)->handle($customer, $order);
                } elseif (! $context->isMerchant()) {
                    throw GraphQLUserError::forbidden('You must be logged in to view this order.');
                }

                return $order;
            },
        ]);
    }
}
