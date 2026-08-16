<?php

namespace App\Domain\RussianCommerce\Http\Controllers;

use App\Domain\RussianCommerce\Application\ProcessFiscalizationCallback;
use App\Domain\RussianCommerce\Exceptions\InvalidFiscalizationCallbackSignatureException;
use App\Domain\RussianCommerce\Support\FiscalizationProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provider-neutral callback entrypoint (mirrors PaymentWebhookController
 * exactly) — no auth, no tenant middleware: a callback arrives from
 * outside the platform entirely. Tenant resolution happens inside
 * ProcessFiscalizationCallback, from the callback's own (provider,
 * external_receipt_id) — never from anything client/provider-supplied
 * as an authorization claim.
 */
final class FiscalizationCallbackController extends Controller
{
    public function handle(
        Request $request,
        string $provider,
        FiscalizationProviderRegistry $registry,
        ProcessFiscalizationCallback $action,
    ): JsonResponse {
        $providerImpl = $registry->resolve($provider);

        if (! $providerImpl->verifyCallback($request)) {
            throw InvalidFiscalizationCallbackSignatureException::make();
        }

        $event = $providerImpl->parseCallback($request);

        $action->handle($provider, $event);

        return response()->json(['data' => ['received' => true]]);
    }
}
