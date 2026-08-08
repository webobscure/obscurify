<?php

namespace App\Domain\Payments\Http\Controllers;

use App\Domain\Payments\Application\ProcessPaymentWebhook;
use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payments\Support\PaymentProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provider-neutral webhook entrypoint (spec section 13) — this class must
 * never contain a fake-provider (or any other provider) conditional.
 * Everything provider-specific lives behind PaymentProviderContract; this
 * controller only sequences the generic steps:
 *
 *   provider code → registry → verifyWebhook() → parseWebhook() → handler
 *
 * No auth, no tenant middleware: a webhook arrives from outside the
 * platform entirely. Tenant resolution happens inside
 * ProcessPaymentWebhook, from the payload's own (provider,
 * external_payment_id) — never from anything client/provider-supplied as
 * an authorization claim.
 */
final class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider, PaymentProviderRegistry $registry, ProcessPaymentWebhook $action): JsonResponse
    {
        $providerImpl = $registry->resolve($provider);

        if (! $providerImpl->verifyWebhook($request)) {
            throw InvalidWebhookSignatureException::make();
        }

        $event = $providerImpl->parseWebhook($request);

        $action->handle($provider, $event, $request->getContent());

        return response()->json(['data' => ['received' => true]]);
    }
}
