<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Enums\ShippingZoneStatus;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Models\ShippingZoneRegion;
use App\Domain\Shipping\Support\ShippingRateContext;
use Illuminate\Support\Collection;

/**
 * Deliberately simple destination matching (spec section 3) — not a
 * worldwide tax/address engine. Among every active zone with at least one
 * matching region row, the *most specific* match wins: a postal-code
 * pattern beats a region-only match, which beats a country-only match.
 * Ties (same specificity) break on the zone's creation order, oldest
 * first — deterministic without needing a merchant-facing priority field
 * this milestone.
 */
final class ResolveShippingZone
{
    public function handle(ShippingRateContext $context): ?ShippingZone
    {
        $zones = ShippingZone::query()
            ->where('status', ShippingZoneStatus::Active)
            ->with('regions')
            ->orderBy('created_at')
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($zones as $zone) {
            $score = $this->bestMatchScore($zone->regions, $context);

            if ($score > $bestScore) {
                $best = $zone;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, ShippingZoneRegion>  $regions
     */
    private function bestMatchScore($regions, ShippingRateContext $context): int
    {
        $score = -1;

        foreach ($regions as $region) {
            if (! $this->matches($region, $context)) {
                continue;
            }

            $candidate = 1;
            $candidate += $region->region !== null ? 1 : 0;
            $candidate += $region->postal_code_pattern !== null ? 1 : 0;

            $score = max($score, $candidate);
        }

        return $score;
    }

    private function matches(ShippingZoneRegion $region, ShippingRateContext $context): bool
    {
        if (strcasecmp($region->country_code, $context->countryCode) !== 0) {
            return false;
        }

        if ($region->region !== null) {
            if ($context->region === null || strcasecmp($region->region, $context->region) !== 0) {
                return false;
            }
        }

        if ($region->postal_code_pattern !== null) {
            if ($context->postalCode === null || ! str_starts_with($context->postalCode, $region->postal_code_pattern)) {
                return false;
            }
        }

        return true;
    }
}
