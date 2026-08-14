<?php

use App\Domain\Apps\Http\Controllers\AdminExtensionsController;
use App\Domain\Apps\Http\Controllers\AppController;
use App\Domain\Apps\Http\Controllers\Gateway\AppExtensionGatewayController;
use App\Domain\Apps\Http\Controllers\Gateway\AppWebhookGatewayController;
use App\Domain\Apps\Http\Controllers\Gateway\CustomerGatewayController;
use App\Domain\Apps\Http\Controllers\Gateway\InventoryGatewayController;
use App\Domain\Apps\Http\Controllers\Gateway\OrderGatewayController;
use App\Domain\Apps\Http\Controllers\Gateway\PaymentGatewayController;
use App\Domain\Apps\Http\Controllers\Gateway\ProductGatewayController;
use App\Domain\Apps\Http\Controllers\Gateway\ShippingGatewayController;
use App\Domain\Apps\Http\Controllers\InstalledAppController;
use App\Domain\Apps\Http\Controllers\OAuthController;
use App\Domain\Builder\Http\Controllers\BuilderPageController;
use App\Domain\Builder\Http\Controllers\BuilderPresetController;
use App\Domain\Builder\Http\Controllers\BuilderRevisionController;
use App\Domain\Builder\Http\Controllers\ThemeAssetController;
use App\Domain\Builder\Http\Controllers\ThemeCustomizerController;
use App\Domain\Catalog\Http\Controllers\CategoryController;
use App\Domain\Catalog\Http\Controllers\CategoryProductController;
use App\Domain\Catalog\Http\Controllers\ProductController;
use App\Domain\Catalog\Http\Controllers\ProductOptionController;
use App\Domain\Catalog\Http\Controllers\ProductOptionValueController;
use App\Domain\Catalog\Http\Controllers\ProductVariantController;
use App\Domain\Cms\Http\Controllers\AuthorController;
use App\Domain\Cms\Http\Controllers\BlogController;
use App\Domain\Cms\Http\Controllers\BlogPostController;
use App\Domain\Cms\Http\Controllers\BlogPostSeoController;
use App\Domain\Cms\Http\Controllers\MenuController;
use App\Domain\Cms\Http\Controllers\MenuItemController;
use App\Domain\Cms\Http\Controllers\PageController;
use App\Domain\Cms\Http\Controllers\PageTemplateController;
use App\Domain\Cms\Http\Controllers\PageVersionController;
use App\Domain\Cms\Http\Controllers\PageVersionSeoController;
use App\Domain\Cms\Http\Controllers\RedirectController;
use App\Domain\Collections\Http\Controllers\CollectionController;
use App\Domain\Collections\Http\Controllers\CollectionProductController;
use App\Domain\Financial\Http\Controllers\FakeRefundOutcomeController;
use App\Domain\Financial\Http\Controllers\RefundController;
use App\Domain\Fulfillment\Http\Controllers\FulfillmentController;
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
use App\Domain\Promotions\Http\Controllers\DiscountCodeController;
use App\Domain\Promotions\Http\Controllers\PromotionController;
use App\Domain\Returns\Http\Controllers\ReturnController;
use App\Domain\Shipping\Http\Controllers\FakeShipmentOutcomeController;
use App\Domain\Shipping\Http\Controllers\ShipmentController;
use App\Domain\Shipping\Http\Controllers\ShippingMethodController;
use App\Domain\Shipping\Http\Controllers\ShippingWebhookController;
use App\Domain\Shipping\Http\Controllers\ShippingZoneController;
use App\Domain\Storefront\Http\Controllers\StorefrontBlogController;
use App\Domain\Storefront\Http\Controllers\StorefrontCartController;
use App\Domain\Storefront\Http\Controllers\StorefrontCategoryController;
use App\Domain\Storefront\Http\Controllers\StorefrontCheckoutController;
use App\Domain\Storefront\Http\Controllers\StorefrontCollectionController;
use App\Domain\Storefront\Http\Controllers\StorefrontMenuController;
use App\Domain\Storefront\Http\Controllers\StorefrontOrderController;
use App\Domain\Storefront\Http\Controllers\StorefrontPageController;
use App\Domain\Storefront\Http\Controllers\StorefrontPaymentController;
use App\Domain\Storefront\Http\Controllers\StorefrontProductController;
use App\Domain\Storefront\Http\Controllers\StorefrontRedirectController;
use App\Domain\Storefront\Http\Controllers\StorefrontSitemapController;
use App\Domain\Storefront\Http\Controllers\StorefrontStoreController;
use App\Domain\Storefront\Http\Controllers\StorefrontThemeController;
use App\Domain\Stores\Http\Controllers\StoreController;
use App\Domain\Themes\Http\Controllers\ThemeController;
use App\Domain\Themes\Http\Controllers\ThemeSettingController;
use App\Domain\Themes\Http\Controllers\ThemeTemplateController;
use App\Domain\Webhooks\Http\Controllers\WebhookDeliveryController;
use App\Domain\Webhooks\Http\Controllers\WebhookSubscriptionController;
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
            Route::post('orders/{order}/fulfillments', [FulfillmentController::class, 'store']);
            Route::post('orders/{order}/returns', [ReturnController::class, 'store']);

            Route::get('payments', [PaymentController::class, 'index']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);

            // Refunds & Financial Ledger (Milestone 9) — provider-neutral;
            // reuses the existing FakePaymentProvider only (see
            // docs/architecture/financial.md). POST here creates the
            // Refund and, for a provider-backed refund, submits it —
            // completion always arrives through the shared payment/refund
            // webhook pipeline (or synchronously for a manual refund).
            Route::get('refunds', [RefundController::class, 'index']);
            Route::get('refunds/{refund}', [RefundController::class, 'show']);
            Route::post('orders/{order}/refunds', [RefundController::class, 'store']);
            Route::post('refunds/{refund}/cancel', [RefundController::class, 'cancel']);

            Route::get('shipping-methods', [ShippingMethodController::class, 'index']);
            Route::post('shipping-methods', [ShippingMethodController::class, 'store']);
            Route::patch('shipping-methods/{method}', [ShippingMethodController::class, 'update']);

            Route::get('shipping-zones', [ShippingZoneController::class, 'index']);
            Route::post('shipping-zones', [ShippingZoneController::class, 'store']);
            Route::patch('shipping-zones/{zone}', [ShippingZoneController::class, 'update']);

            // Discount & Promotion Engine (Milestone 10) — provider-neutral,
            // reusable from Checkout/Draft Orders/Admin/future APIs (see
            // docs/architecture/promotions.md). No destroy: a Promotion or
            // DiscountCode is deactivated via status, never deleted, so
            // existing DiscountApplication/PromotionUsage snapshots and FKs
            // stay intact — same pragmatic bar as shipping-zones/-methods.
            Route::get('promotions', [PromotionController::class, 'index']);
            Route::post('promotions', [PromotionController::class, 'store']);
            Route::get('promotions/{promotion}', [PromotionController::class, 'show']);
            Route::patch('promotions/{promotion}', [PromotionController::class, 'update']);
            Route::get('promotions/{promotion}/usage', [PromotionController::class, 'usage']);
            Route::post('promotions/preview', [PromotionController::class, 'preview']);

            Route::get('discount-codes', [DiscountCodeController::class, 'index']);
            Route::post('discount-codes', [DiscountCodeController::class, 'store']);
            Route::patch('discount-codes/{discountCode}', [DiscountCodeController::class, 'update']);

            // Platform Events + Webhooks (Milestone 11) — a merchant-owned
            // subscription to Platform Events (see docs/architecture/
            // webhooks.md). No destroy: deactivate via status, the same
            // pragmatic bar as promotions/shipping-zones.
            Route::get('webhook-subscriptions', [WebhookSubscriptionController::class, 'index']);
            Route::post('webhook-subscriptions', [WebhookSubscriptionController::class, 'store']);
            Route::get('webhook-subscriptions/{webhookSubscription}', [WebhookSubscriptionController::class, 'show']);
            Route::patch('webhook-subscriptions/{webhookSubscription}', [WebhookSubscriptionController::class, 'update']);
            Route::get('webhook-subscriptions/{webhookSubscription}/deliveries', [WebhookDeliveryController::class, 'index']);
            Route::post('webhook-deliveries/{webhookDelivery}/retry', [WebhookDeliveryController::class, 'retry']);

            // Apps SDK + OAuth + Extension Platform (Milestone 12) —
            // architecture only: no marketplace UI, no billing, no app
            // review, no public publishing. See docs/architecture/apps.md.
            Route::get('apps', [AppController::class, 'index']);
            Route::post('apps', [AppController::class, 'store']);
            Route::get('apps/{app}', [AppController::class, 'show']);
            Route::post('apps/{app}/install', [AppController::class, 'install']);

            Route::get('installed-apps', [InstalledAppController::class, 'index']);
            Route::get('installed-apps/{installedApp}', [InstalledAppController::class, 'show']);
            Route::post('installed-apps/{installedApp}/uninstall', [InstalledAppController::class, 'uninstall']);
            Route::get('installed-apps/{installedApp}/tokens', [InstalledAppController::class, 'tokens']);
            Route::get('installed-apps/{installedApp}/webhooks', [InstalledAppController::class, 'webhooks']);

            Route::get('admin-extensions', [AdminExtensionsController::class, 'index']);

            // OAuth consent step — authenticated in the admin (this
            // group's own auth:sanctum + tenant middleware), unlike
            // /oauth/token and /oauth/revoke below, which the app's own
            // server calls with no session at all.
            Route::get('oauth/authorize', [OAuthController::class, 'show']);
            Route::post('oauth/authorize', [OAuthController::class, 'approve']);

            // Theme Engine + Storefront Rendering (Milestone 13) — see
            // docs/architecture/themes.md. No destroy: an unwanted theme
            // is archived via status, never deleted, so ActiveTheme/
            // DuplicateTheme lineage never dangles.
            Route::get('themes', [ThemeController::class, 'index']);
            Route::post('themes', [ThemeController::class, 'store']);
            Route::get('themes/{theme}', [ThemeController::class, 'show']);
            Route::patch('themes/{theme}', [ThemeController::class, 'update']);
            Route::post('themes/{theme}/publish', [ThemeController::class, 'publish']);
            Route::post('themes/{theme}/duplicate', [ThemeController::class, 'duplicate']);
            Route::get('themes/{theme}/preview', [ThemeController::class, 'preview']);
            Route::get('themes/{theme}/versions', [ThemeController::class, 'versions']);
            Route::post('theme-versions/{themeVersion}/rollback', [ThemeController::class, 'rollback']);
            Route::get('theme-versions/{themeVersion}/settings', [ThemeSettingController::class, 'index']);
            Route::patch('theme-versions/{themeVersion}/settings', [ThemeSettingController::class, 'update']);
            Route::get('theme-versions/{themeVersion}/templates', [ThemeTemplateController::class, 'index']);
            Route::patch('theme-versions/{themeVersion}/templates/{type}', [ThemeTemplateController::class, 'update']);

            // CMS (Milestone 14) — see docs/architecture/cms.md. Pages
            // follow the exact draft/publish/rollback/duplicate lifecycle
            // Themes established (ADR-019/ADR-020); no destroy, same
            // "archive via status, never delete" reasoning. Menus/Blogs/
            // Authors/Redirects have no versioning invariant to protect,
            // so they support DELETE like Shipping/Promotions.
            Route::get('pages', [PageController::class, 'index']);
            Route::post('pages', [PageController::class, 'store']);
            Route::get('pages/{page}', [PageController::class, 'show']);
            Route::patch('pages/{page}', [PageController::class, 'update']);
            Route::post('pages/{page}/publish', [PageController::class, 'publish']);
            Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate']);
            Route::get('pages/{page}/preview', [PageController::class, 'preview']);
            Route::get('pages/{page}/versions', [PageController::class, 'versions']);
            Route::post('page-versions/{pageVersion}/rollback', [PageController::class, 'rollback']);
            Route::patch('page-versions/{pageVersion}/sections', [PageVersionController::class, 'updateSections']);
            Route::get('page-versions/{pageVersion}/seo', [PageVersionSeoController::class, 'show']);
            Route::patch('page-versions/{pageVersion}/seo', [PageVersionSeoController::class, 'update']);

            Route::get('page-templates', [PageTemplateController::class, 'index']);
            Route::post('page-templates', [PageTemplateController::class, 'store']);
            Route::patch('page-templates/{pageTemplate}', [PageTemplateController::class, 'update']);
            Route::delete('page-templates/{pageTemplate}', [PageTemplateController::class, 'destroy']);

            Route::get('menus', [MenuController::class, 'index']);
            Route::post('menus', [MenuController::class, 'store']);
            Route::get('menus/{menu}', [MenuController::class, 'show']);
            Route::patch('menus/{menu}', [MenuController::class, 'update']);
            Route::delete('menus/{menu}', [MenuController::class, 'destroy']);
            Route::post('menus/{menu}/items', [MenuItemController::class, 'store']);
            Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update']);
            Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy']);

            Route::get('authors', [AuthorController::class, 'index']);
            Route::post('authors', [AuthorController::class, 'store']);
            Route::patch('authors/{author}', [AuthorController::class, 'update']);
            Route::delete('authors/{author}', [AuthorController::class, 'destroy']);

            Route::get('blogs', [BlogController::class, 'index']);
            Route::post('blogs', [BlogController::class, 'store']);
            Route::get('blogs/{blog}', [BlogController::class, 'show']);
            Route::patch('blogs/{blog}', [BlogController::class, 'update']);
            Route::delete('blogs/{blog}', [BlogController::class, 'destroy']);
            Route::get('blogs/{blog}/posts', [BlogPostController::class, 'index']);
            Route::post('blogs/{blog}/posts', [BlogPostController::class, 'store']);
            Route::get('blog-posts/{blogPost}', [BlogPostController::class, 'show']);
            Route::patch('blog-posts/{blogPost}', [BlogPostController::class, 'update']);
            Route::post('blog-posts/{blogPost}/publish', [BlogPostController::class, 'publish']);
            Route::delete('blog-posts/{blogPost}', [BlogPostController::class, 'destroy']);
            Route::get('blog-posts/{blogPost}/seo', [BlogPostSeoController::class, 'show']);
            Route::patch('blog-posts/{blogPost}/seo', [BlogPostSeoController::class, 'update']);

            Route::get('redirects', [RedirectController::class, 'index']);
            Route::post('redirects', [RedirectController::class, 'store']);
            Route::patch('redirects/{redirect}', [RedirectController::class, 'update']);
            Route::delete('redirects/{redirect}', [RedirectController::class, 'destroy']);

            // Visual Page Builder + Theme Customizer (Milestone 15) — see
            // docs/architecture/page-builder.md. Builder stores
            // configuration only; publish/rollback/duplicate below are
            // thin passthroughs to Cms's own PublishPageVersion/
            // RollbackPage/DuplicatePage, not reimplementations.
            Route::get('builder/pages/{page}', [BuilderPageController::class, 'show']);
            Route::patch('builder/pages/{page}', [BuilderPageController::class, 'update']);
            Route::post('builder/pages/{page}/publish', [BuilderPageController::class, 'publish']);
            Route::post('builder/pages/{page}/duplicate', [BuilderPageController::class, 'duplicate']);
            Route::post('builder/pages/{page}/rollback', [BuilderPageController::class, 'rollback']);
            Route::post('builder/pages/{page}/undo', [BuilderPageController::class, 'undo']);
            Route::post('builder/pages/{page}/redo', [BuilderPageController::class, 'redo']);
            Route::get('builder/pages/{page}/revisions', [BuilderRevisionController::class, 'index']);
            Route::post('builder/pages/{page}/revisions/{revision}/restore', [BuilderRevisionController::class, 'restore']);

            Route::get('builder/presets', [BuilderPresetController::class, 'index']);
            Route::get('builder/theme-customizer', [ThemeCustomizerController::class, 'show']);

            Route::get('theme-versions/{themeVersion}/assets', [ThemeAssetController::class, 'index']);
            Route::post('theme-versions/{themeVersion}/assets', [ThemeAssetController::class, 'store']);
            Route::delete('theme-assets/{themeAsset}', [ThemeAssetController::class, 'destroy']);

            Route::get('shipments', [ShipmentController::class, 'index']);
            Route::get('shipments/{shipment}', [ShipmentController::class, 'show']);
            Route::post('shipments/{shipment}/cancel', [ShipmentController::class, 'cancel']);

            // Fulfillment Core (Milestone 7) — independent of Shipping
            // except for /complete, which creates the Shipment against a
            // ready Fulfillment (see FulfillmentController::complete).
            Route::get('fulfillments', [FulfillmentController::class, 'index']);
            Route::get('fulfillments/{fulfillment}', [FulfillmentController::class, 'show']);
            Route::patch('fulfillments/{fulfillment}', [FulfillmentController::class, 'update']);
            Route::post('fulfillments/{fulfillment}/allocate', [FulfillmentController::class, 'allocate']);
            Route::post('fulfillments/{fulfillment}/pick', [FulfillmentController::class, 'pick']);
            Route::post('fulfillments/{fulfillment}/pack', [FulfillmentController::class, 'pack']);
            Route::post('fulfillments/{fulfillment}/complete', [FulfillmentController::class, 'complete']);
            Route::post('fulfillments/{fulfillment}/cancel', [FulfillmentController::class, 'cancel']);

            // Returns & Reverse Logistics Core (Milestone 8) — independent
            // of Payments/Shipping/Fulfillment (see docs/architecture/
            // returns.md); reads OrderItem/ShipmentItem to validate
            // returnable quantity and writes InventoryMovement directly,
            // never through those domains' own Application classes.
            Route::get('returns', [ReturnController::class, 'index']);
            Route::get('returns/{returnRequest}', [ReturnController::class, 'show']);
            Route::patch('returns/{returnRequest}', [ReturnController::class, 'update']);
            Route::post('returns/{returnRequest}/approve', [ReturnController::class, 'approve']);
            Route::post('returns/{returnRequest}/reject', [ReturnController::class, 'reject']);
            Route::post('returns/{returnRequest}/receive', [ReturnController::class, 'receive']);
            Route::post('returns/{returnRequest}/inspect', [ReturnController::class, 'inspect']);
            Route::post('returns/{returnRequest}/complete', [ReturnController::class, 'complete']);
            Route::post('returns/{returnRequest}/cancel', [ReturnController::class, 'cancel']);
        });
    });

    // Provider-neutral: no auth, no tenant middleware — a webhook arrives
    // from outside the platform entirely. See PaymentWebhookController
    // and ProcessPaymentWebhook for how tenant/authorization is resolved
    // safely from the payload instead.
    Route::post('payments/webhooks/{provider}', [PaymentWebhookController::class, 'handle']);

    // Same reasoning as the payments webhook above — see
    // ShippingWebhookController / ProcessShippingWebhook.
    Route::post('shipping/webhooks/{provider}', [ShippingWebhookController::class, 'handle']);

    // OAuth 2.1 token exchange/refresh/revocation — called by a
    // third-party app's own server (client_id/client_secret in the
    // body), never by a browser session. Same "no auth/tenant
    // middleware, resolve tenant from the payload" reasoning as the
    // provider webhooks above — see ExchangeAuthorizationCode/
    // RefreshAppToken/RevokeAppToken.
    Route::post('oauth/token', [OAuthController::class, 'token']);
    Route::post('oauth/revoke', [OAuthController::class, 'revoke']);

    // Dev/test-only fake payment page backend (spec sections 9-12) —
    // registered only when the fake provider itself is enabled (see
    // config/payments.php); the controller re-checks the same flag as a
    // second, independent guard. Never available in production by
    // default.
    if (config('payments.fake.enabled')) {
        Route::get('fake-payments/{externalPaymentId}', [FakePaymentOutcomeController::class, 'show']);
        Route::post('fake-payments/{externalPaymentId}/outcome', [FakePaymentOutcomeController::class, 'outcome']);

        // Dev/test-only fake refund control page — same fake provider,
        // same flag, no separate refund-specific config.
        Route::get('fake-refunds/{externalRefundId}', [FakeRefundOutcomeController::class, 'show']);
        Route::post('fake-refunds/{externalRefundId}/outcome', [FakeRefundOutcomeController::class, 'outcome']);
    }

    // Dev/test-only fake shipment control page (spec section 20) — same
    // double-guard discipline as the fake payment page above.
    if (config('commerce.shipping.fake.enabled')) {
        Route::get('fake-shipments/{externalShipmentId}', [FakeShipmentOutcomeController::class, 'show']);
        Route::post('fake-shipments/{externalShipmentId}/outcome', [FakeShipmentOutcomeController::class, 'outcome']);
        Route::post('fake-shipments/{externalShipmentId}/invalid-signature', [FakeShipmentOutcomeController::class, 'invalidSignature']);
    }

    // Public storefront API: tenant is resolved from the request hostname
    // (see EnsureStorefrontTenantContext), never from a header or payload.
    // Deliberately not nested under auth:sanctum — storefront visitors are
    // anonymous; there is no customer auth yet.
    Route::prefix('storefront')->middleware('storefront.tenant')->group(function () {
        Route::get('/store', [StorefrontStoreController::class, 'show']);

        // Every themed page renders through this one endpoint (spec
        // section 10) — {template} is home/collection/product/cart/
        // search/404/page/blog (ThemeTemplateType).
        Route::get('/theme/{template}', [StorefrontThemeController::class, 'render']);

        // CMS (Milestone 14) — see docs/architecture/cms.md.
        Route::get('/pages/{slug}', [StorefrontPageController::class, 'show']);
        Route::get('/blogs/{blogSlug}/posts', [StorefrontBlogController::class, 'index']);
        Route::get('/blog/posts/{postSlug}', [StorefrontBlogController::class, 'show']);
        Route::get('/menus/{handle}', [StorefrontMenuController::class, 'show']);
        Route::get('/redirect', [StorefrontRedirectController::class, 'show']);
        Route::get('/sitemap.xml', [StorefrontSitemapController::class, 'show']);

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
        Route::get('/checkout/shipping-rates', [StorefrontCheckoutController::class, 'shippingRates']);
        Route::patch('/checkout/shipping', [StorefrontCheckoutController::class, 'selectShipping']);
        Route::post('/checkout/discount-code', [StorefrontCheckoutController::class, 'applyDiscountCode']);
        Route::delete('/checkout/discount-code', [StorefrontCheckoutController::class, 'removeDiscountCode']);
        Route::post('/checkout/complete', [StorefrontCheckoutController::class, 'complete']);

        Route::get('/orders/{order}', [StorefrontOrderController::class, 'show']);
        Route::post('/orders/{order}/payments', [StorefrontPaymentController::class, 'store']);
    });
});

// The Apps SDK REST API gateway (spec section 7: "Introduce: /api/apps/v1")
// — deliberately its own top-level namespace, sibling to /api/v1, not
// nested under it: this is a third-party integration surface with its
// own versioning lifecycle, authenticated by an AppToken bearer token
// (AuthenticateAppToken) and never by the merchant admin's Sanctum
// session. Every route additionally requires its own scope
// (EnsureAppScope) — see docs/architecture/apps.md.
Route::prefix('apps/v1')->middleware('app-token')->group(function () {
    Route::get('products', [ProductGatewayController::class, 'index'])->middleware('app-scope:products.read');
    Route::get('products/{product}', [ProductGatewayController::class, 'show'])->middleware('app-scope:products.read');

    Route::get('orders', [OrderGatewayController::class, 'index'])->middleware('app-scope:orders.read');
    Route::get('orders/{order}', [OrderGatewayController::class, 'show'])->middleware('app-scope:orders.read');

    Route::get('customers', [CustomerGatewayController::class, 'index'])->middleware('app-scope:customers.read');
    Route::get('customers/{customer}', [CustomerGatewayController::class, 'show'])->middleware('app-scope:customers.read');

    Route::get('inventory', [InventoryGatewayController::class, 'index'])->middleware('app-scope:inventory.read');
    Route::post('inventory/{item}/adjust', [InventoryGatewayController::class, 'adjust'])->middleware('app-scope:inventory.write');

    Route::get('payments', [PaymentGatewayController::class, 'index'])->middleware('app-scope:payments.read');

    Route::get('shipping-methods', [ShippingGatewayController::class, 'index'])->middleware('app-scope:shipping.read');

    Route::get('webhooks', [AppWebhookGatewayController::class, 'index'])->middleware('app-scope:webhooks.read');
    Route::post('webhooks', [AppWebhookGatewayController::class, 'store'])->middleware('app-scope:webhooks.write');

    // Extension-point registration configures the app's own presence
    // rather than reading store data — no scope required beyond a
    // valid token (see AppExtensionGatewayController).
    Route::get('extensions', [AppExtensionGatewayController::class, 'index']);
    Route::post('extensions', [AppExtensionGatewayController::class, 'store']);
});
