<?php

namespace App\Domain\Shipping\Http\Controllers;

use App\Domain\Shipping\Application\ProcessShippingWebhook;
use App\Domain\Shipping\Exceptions\InvalidShippingWebhookSignatureException;
use App\Domain\Shipping\Support\ShippingProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provider-neutral webhook entrypoint (spec section 22) — mirrors
 * PaymentWebhookController exactly, including its "must never contain a
 * fake-provider conditional" discipline. No auth, no tenant middleware: a
 * webhook arrives from outside the platform entirely. Tenant resolution
 * happens inside ProcessShippingWebhook, from the payload's own (provider,
 * external_shipment_id) — never from anything client/provider-supplied as
 * an authorization claim (spec section 24).
 */
final class ShippingWebhookController extends Controller
{
    public function handle(Request $request, string $provider, ShippingProviderRegistry $registry, ProcessShippingWebhook $action): JsonResponse
    {
        $providerImpl = $registry->resolve($provider);

        if (! $providerImpl->verifyWebhook($request)) {
            throw InvalidShippingWebhookSignatureException::make();
        }

        $event = $providerImpl->parseWebhook($request);

        $action->handle($provider, $event, $request->getContent());

        return response()->json(['data' => ['received' => true]]);
    }
}
