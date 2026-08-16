<?php

use App\Domain\Catalog\Application\AttachProductToCategory;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Application\AttachProductToCollection;
use App\Domain\Collections\Models\Collection;
use App\Domain\Customers\Models\Customer;
use App\Domain\GraphQL\DataLoaders\OrderLoader;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-dataloader.localhost';
    domainForStore($this->store, $this->host);
});

it('batches Order.customer into one query regardless of how many orders are returned (Customers loader)', function () {
    app(TenantContext::class)->scope($this->store, function () {
        for ($i = 1; $i <= 8; $i++) {
            $customer = Customer::factory()->create(['email' => "customer{$i}@example.test"]);
            Order::query()->create([
                'number' => $i, 'customer_id' => $customer->id, 'currency' => 'USD',
                'items_subtotal_amount' => 1000, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
                'total_amount' => 1000, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
                'email' => "customer{$i}@example.test",
            ]);
        }
    });

    $token = $this->user->createToken('test')->plainTextToken;
    $customerQueries = 0;

    DB::listen(function ($query) use (&$customerQueries) {
        if (str_contains($query->sql, 'select * from "customers"')) {
            $customerQueries++;
        }
    });

    $response = $this->postJson('http://any-host.localhost/api/graphql', [
        'query' => 'query { orders { data { number customer { email } } } }',
    ], array_merge(['Authorization' => "Bearer {$token}"], tenantHeader($this->store)));

    $response->assertOk();
    expect(collect($response->json('data.orders.data'))->pluck('customer.email')->all())
        ->toBe(array_map(fn ($i) => "customer{$i}@example.test", range(1, 8)));

    // One batched query for all 8 orders' customers, not 8 individual ones.
    expect($customerQueries)->toBe(1);
});

it('batches Product.collections and Product.categories into one query each across N products (Collections/Categories loaders)', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $collection = Collection::factory()->create(['status' => 'active']);
        $category = Category::factory()->create();

        for ($i = 1; $i <= 6; $i++) {
            $product = Product::factory()->create(['title' => "Loader Product {$i}", 'status' => 'active']);
            app(AttachProductToCollection::class)->handle($collection, $product);
            app(AttachProductToCategory::class)->handle($category, $product);
        }
    });

    $collectionQueries = 0;
    $categoryQueries = 0;

    DB::listen(function ($query) use (&$collectionQueries, &$categoryQueries) {
        if (str_contains($query->sql, 'collection_products')) {
            $collectionQueries++;
        }
        if (str_contains($query->sql, 'product_categories')) {
            $categoryQueries++;
        }
    });

    $response = graphqlRequest($this->host, 'query { products(perPage: 10) { data { title collections { title } categories { title } } } }');

    $response->assertOk();
    expect(collect($response->json('data.products.data'))->pluck('title')->sort()->values()->all())
        ->toBe(collect(range(1, 6))->map(fn ($i) => "Loader Product {$i}")->sort()->values()->all());

    foreach ($response->json('data.products.data') as $product) {
        expect($product['collections'])->not->toBeEmpty();
        expect($product['categories'])->not->toBeEmpty();
    }

    expect($collectionQueries)->toBe(1);
    expect($categoryQueries)->toBe(1);
});

it('OrderLoader batches Order lookups by id into a single query (unit-level, not yet wired to a resolver)', function () {
    $orderIds = app(TenantContext::class)->scope($this->store, function () {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = Order::query()->create([
                'number' => $i, 'currency' => 'USD',
                'items_subtotal_amount' => 500, 'shipping_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0,
                'total_amount' => 500, 'order_status' => 'open', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
                'email' => "order{$i}@example.test",
            ])->id;
        }

        return $ids;
    });

    app(TenantContext::class)->scope($this->store, function () use ($orderIds) {
        $loader = app(OrderLoader::class);
        $deferreds = collect($orderIds)->map(fn ($id) => $loader->load($id))->all();

        $orderQueries = 0;
        DB::listen(function ($query) use (&$orderQueries) {
            if (str_contains($query->sql, 'select * from "orders"')) {
                $orderQueries++;
            }
        });

        $adapter = new SyncPromiseAdapter;
        $wrapped = array_map(fn ($deferred) => $adapter->convertThenable($deferred), $deferreds);
        $resolved = $adapter->wait($adapter->all($wrapped));

        expect(collect($resolved)->pluck('number')->sort()->values()->all())->toBe(range(1, 5));
        expect($orderQueries)->toBe(1);
    });
});
