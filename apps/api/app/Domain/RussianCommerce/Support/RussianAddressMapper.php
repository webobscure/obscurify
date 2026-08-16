<?php

namespace App\Domain\RussianCommerce\Support;

/**
 * Spec section 2: "Keep it compatible with international addresses. Do
 * not replace existing generic address snapshots if unnecessary. Add
 * adapters/mapping where required." CustomerAddress/OrderAddress/
 * CheckoutAddress (Milestones 3/9/16) already have their own generic
 * shape — country_code/region/city/postal_code/address_line1/
 * address_line2 — used across the whole platform, not just the Russian
 * market. This mapper is the seam between that generic shape and
 * RussianAddress's finer granularity, used only where a caller
 * genuinely needs the Russian-specific fields (StoreLegalProfile today);
 * the generic address tables themselves are untouched.
 *
 * `toGenericLines()` is lossless (every RussianAddress field maps into
 * address_line1/address_line2 in a fixed, parseable order).
 * `fromGenericLines()` is necessarily best-effort: a flattened
 * "street, house, building, apartment" string can't be reliably split
 * back into its granular parts without the exact separator convention
 * `toGenericLines()` itself used — see that method's docblock for the
 * convention this class guarantees round-trips correctly.
 */
final class RussianAddressMapper
{
    private const string SEPARATOR = '; ';

    /**
     * Encodes street/house/building/apartment into address_line1 (in
     * that fixed, semicolon-separated order) and leaves address_line2
     * for anything free-text (raw_address, if present) — round-trips
     * exactly through fromGenericLines() when the input came from
     * toGenericLines() in the first place.
     *
     * @return array{address_line1: string|null, address_line2: string|null, city: string|null, region: string|null, postal_code: string|null, country_code: string}
     */
    public function toGenericLines(RussianAddress $address): array
    {
        $line1Parts = array_filter([
            $address->street,
            $address->house !== null ? "д. {$address->house}" : null,
            $address->building !== null ? "корп. {$address->building}" : null,
            $address->apartment !== null ? "кв. {$address->apartment}" : null,
        ]);

        return [
            'address_line1' => $line1Parts === [] ? null : implode(self::SEPARATOR, $line1Parts),
            'address_line2' => $address->district !== null || $address->settlement !== null
                ? implode(self::SEPARATOR, array_filter([$address->district, $address->settlement]))
                : $address->rawAddress,
            'city' => $address->city,
            'region' => $address->region,
            'postal_code' => $address->postalCode,
            'country_code' => $address->countryCode,
        ];
    }

    /**
     * Best-effort reconstruction — see class docblock. Fields that
     * cannot be reliably recovered (house/building/apartment split back
     * out of a free-text line1) are left null; the original flattened
     * text is preserved verbatim in `rawAddress` so no information is
     * silently lost, only its structure.
     *
     * @param  array{address_line1?: string|null, address_line2?: string|null, city?: string|null, region?: string|null, postal_code?: string|null, country_code?: string|null}  $generic
     */
    public function fromGenericLines(array $generic): RussianAddress
    {
        $raw = implode(', ', array_filter([$generic['address_line1'] ?? null, $generic['address_line2'] ?? null]));

        return new RussianAddress(
            countryCode: $generic['country_code'] ?? 'RU',
            postalCode: $generic['postal_code'] ?? null,
            region: $generic['region'] ?? null,
            city: $generic['city'] ?? null,
            rawAddress: $raw !== '' ? $raw : null,
        );
    }
}
