<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Exceptions\NoShippingMethodsAvailableException;
use App\Domain\Shipping\Support\ShippingProviderRegistry;
use App\Domain\Shipping\Support\ShippingRate;
use App\Domain\Shipping\Support\ShippingRateContext;
use Illuminate\Support\Collection;

/**
 * The full flow from spec section 9: address -> zone -> methods ->
 * provider.calculateRates() -> rates. Always live-recalculated, never
 * cached/trusted from a prior response (spec section 9: "do not calculate
 * from frontend").
 */
final class CalculateShippingRates
{
    public function __construct(
        private readonly ResolveShippingZone $resolveShippingZone,
        private readonly ResolveAvailableShippingMethods $resolveAvailableShippingMethods,
        private readonly ShippingProviderRegistry $registry,
    ) {}

    /**
     * @return Collection<int, ShippingRate>
     */
    public function handle(ShippingRateContext $context): Collection
    {
        $zone = $this->resolveShippingZone->handle($context);

        if ($zone === null) {
            throw NoShippingMethodsAvailableException::make();
        }

        $methods = $this->resolveAvailableShippingMethods->handle($zone);

        if ($methods->isEmpty()) {
            throw NoShippingMethodsAvailableException::make();
        }

        $rates = new Collection;

        foreach ($methods->groupBy('provider') as $providerCode => $methodsForProvider) {
            // An unregistered/disabled provider offers nothing rather than
            // erroring the whole rate lookup — see
            // ShippingProviderContract::calculateRates() docblock.
            if (! $this->registry->has($providerCode)) {
                continue;
            }

            $provider = $this->registry->resolve($providerCode);

            $rates = $rates->merge($provider->calculateRates($methodsForProvider, $context));
        }

        if ($rates->isEmpty()) {
            throw NoShippingMethodsAvailableException::make();
        }

        return $rates->values();
    }
}
