<?php

use App\Domain\Catalog\Http\Controllers\CategoryController;
use App\Domain\Catalog\Http\Controllers\CategoryProductController;
use App\Domain\Catalog\Http\Controllers\ProductController;
use App\Domain\Catalog\Http\Controllers\ProductOptionController;
use App\Domain\Catalog\Http\Controllers\ProductOptionValueController;
use App\Domain\Catalog\Http\Controllers\ProductVariantController;
use App\Domain\Collections\Http\Controllers\CollectionController;
use App\Domain\Collections\Http\Controllers\CollectionProductController;
use App\Domain\Identity\Http\Controllers\AuthController;
use App\Domain\Inventory\Http\Controllers\InventoryController;
use App\Domain\Locations\Http\Controllers\LocationController;
use App\Domain\Media\Http\Controllers\MediaController;
use App\Domain\Media\Http\Controllers\ProductMediaController;
use App\Domain\Media\Http\Controllers\ProductVariantMediaController;
use App\Domain\Orders\Http\Controllers\OrderController;
use App\Domain\Payments\Http\Controllers\FakePaymentOutcomeController;
use App\Domain\Payments\Http\Controllers\PaymentController;
use App\Domain\Payments\Http\Controllers\PaymentWebhookController;
use App\Domain\Storefront\Http\Controllers\StorefrontCartController;
use App\Domain\Storefront\Http\Controllers\StorefrontCategoryController;
use App\Domain\Storefront\Http\Controllers\StorefrontCheckoutController;
use App\Domain\Storefront\Http\Controllers\StorefrontCollectionController;
use App\Domain\Storefront\Http\Controllers\StorefrontOrderController;
use App\Domain\Storefront\Http\Controllers\StorefrontPaymentController;
use App\Domain\Storefront\Http\Controllers\StorefrontProductController;
use App\Domain\Storefront\Http\Controllers\StorefrontStoreController;
use App\Domain\Stores\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/stores', [StoreController::class, 'index']);
        Route::post('/stores', [StoreController::class, 'store']);
        Route::get('/stores/{store}', [StoreController::class, 'show']);
        Route::post('/stores/{store}/activate', [StoreController::class, 'activate']);

        Route::middleware('tenant')->group(function () {
            Route::apiResource('products', ProductController::class)->except(['create', 'edit']);

            Route::prefix('products/{product}')->group(function () {
                Route::get('options', [ProductOptionController::class, 'index']);
                Route::post('options', [ProductOptionController::class, 'store']);
                Route::patch('options/{option}', [ProductOptionController::class, 'update']);
                Route::delete('options/{option}', [ProductOptionController::class, 'destroy']);

                Route::post('options/{option}/values', [ProductOptionValueController::class, 'store']);
                Route::patch('options/{option}/values/{value}', [ProductOptionValueController::class, 'update']);
                Route::delete('options/{option}/values/{value}', [ProductOptionValueController::class, 'destroy']);

                Route::get('variants', [ProductVariantController::class, 'index']);
                Route::post('variants', [ProductVariantController::class, 'store']);
                Route::patch('variants/{variant}', [ProductVariantController::class, 'update']);
                Route::delete('variants/{variant}', [ProductVariantController::class, 'destroy']);

                Route::post('media', [ProductMediaController::class, 'store']);
                Route::post('variants/{variant}/media', [ProductVariantMediaController::class, 'store']);
            });

            Route::patch('media/{media}', [MediaController::class, 'update']);
            Route::delete('media/{media}', [MediaController::class, 'destroy']);

            Route::apiResource('collections', CollectionController::class)->except(['create', 'edit']);
            Route::post('collections/{collection}/products/{product}', [CollectionProductController::class, 'store']);
            Route::delete('collections/{collection}/products/{product}', [CollectionProductController::class, 'destroy']);

            Route::apiResource('categories', CategoryController::class)->except(['create', 'edit']);
            Route::post('categories/{category}/products/{product}', [CategoryProductController::class, 'store']);
            Route::delete('categories/{category}/products/{product}', [CategoryProductController::class, 'destroy']);

            Route::get('locations', [LocationController::class, 'index']);
            Route::post('locations', [LocationController::class, 'store']);
            Route::patch('locations/{location}', [LocationController::class, 'update']);

            Route::get('inventory', [InventoryController::class, 'index']);
            Route::post('inventory/{item}/adjust', [InventoryController::class, 'adjust']);

            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{order}', [OrderController::class, 'show']);

            Route::get('payments', [PaymentController::class, 'index']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);
        });
    });

    // Provider-neutral: no auth, no tenant middleware — a webhook arrives
    // from outside the platform entirely. See PaymentWebhookController
    // and ProcessPaymentWebhook for how tenant/authorization is resolved
    // safely from the payload instead.
    Route::post('payments/webhooks/{provider}', [PaymentWebhookController::class, 'handle']);

    // Dev/test-only fake payment page backend (spec sections 9-12) —
    // registered only when the fake provider itself is enabled (see
    // config/payments.php); the controller re-checks the same flag as a
    // second, independent guard. Never available in production by
    // default.
    if (config('payments.fake.enabled')) {
        Route::get('fake-payments/{externalPaymentId}', [FakePaymentOutcomeController::class, 'show']);
        Route::post('fake-payments/{externalPaymentId}/outcome', [FakePaymentOutcomeController::class, 'outcome']);
    }

    // Public storefront API: tenant is resolved from the request hostname
    // (see EnsureStorefrontTenantContext), never from a header or payload.
    // Deliberately not nested under auth:sanctum — storefront visitors are
    // anonymous; there is no customer auth yet.
    Route::prefix('storefront')->middleware('storefront.tenant')->group(function () {
        Route::get('/store', [StorefrontStoreController::class, 'show']);

        Route::get('/products', [StorefrontProductController::class, 'index']);
        Route::get('/products/{slug}', [StorefrontProductController::class, 'show']);

        Route::get('/collections', [StorefrontCollectionController::class, 'index']);
        Route::get('/collections/{slug}', [StorefrontCollectionController::class, 'show']);

        Route::get('/categories', [StorefrontCategoryController::class, 'index']);
        Route::get('/categories/{slug}', [StorefrontCategoryController::class, 'show']);

        Route::get('/cart', [StorefrontCartController::class, 'show']);
        Route::post('/cart/items', [StorefrontCartController::class, 'addItem']);
        Route::patch('/cart/items/{item}', [StorefrontCartController::class, 'updateItem']);
        Route::delete('/cart/items/{item}', [StorefrontCartController::class, 'removeItem']);

        Route::post('/checkout', [StorefrontCheckoutController::class, 'store']);
        Route::patch('/checkout', [StorefrontCheckoutController::class, 'update']);
        Route::post('/checkout/complete', [StorefrontCheckoutController::class, 'complete']);

        Route::get('/orders/{order}', [StorefrontOrderController::class, 'show']);
        Route::post('/orders/{order}/payments', [StorefrontPaymentController::class, 'store']);
    });
});
