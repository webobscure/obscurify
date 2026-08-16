<?php

namespace App\Domain\RussianCommerce\Support;

/**
 * Spec section 2 — a normalized Russian address, granular enough for
 * fiscal/legal use (region/district/settlement/house/building/apartment
 * split out, not flattened into two free-text lines). Deliberately a
 * plain value object, not an Eloquent model or a new table: it's always
 * *owned* by something else (StoreLegalProfile.legal_address/actual_address,
 * stored as jsonb — see that model), the same way Money is a shape, not
 * a row. `country_code` defaults to "RU" but isn't hardcoded elsewhere —
 * a non-Russian legal address is representable, just less granular
 * (region/district/settlement left null, everything in `street`/`raw_address`).
 */
final readonly class RussianAddress
{
    public function __construct(
        public string $countryCode = 'RU',
        public ?string $postalCode = null,
        public ?string $region = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $settlement = null,
        public ?string $street = null,
        public ?string $house = null,
        public ?string $building = null,
        public ?string $apartment = null,
        public ?string $rawAddress = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            countryCode: (string) ($data['country_code'] ?? 'RU'),
            postalCode: $data['postal_code'] ?? null,
            region: $data['region'] ?? null,
            district: $data['district'] ?? null,
            city: $data['city'] ?? null,
            settlement: $data['settlement'] ?? null,
            street: $data['street'] ?? null,
            house: $data['house'] ?? null,
            building: $data['building'] ?? null,
            apartment: $data['apartment'] ?? null,
            rawAddress: $data['raw_address'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'country_code' => $this->countryCode,
            'postal_code' => $this->postalCode,
            'region' => $this->region,
            'district' => $this->district,
            'city' => $this->city,
            'settlement' => $this->settlement,
            'street' => $this->street,
            'house' => $this->house,
            'building' => $this->building,
            'apartment' => $this->apartment,
            'raw_address' => $this->rawAddress,
        ];
    }

    /**
     * A single human-readable line — used wherever only a flat string
     * is needed (e.g. a fiscal receipt's printed seller address).
     */
    public function toSingleLine(): string
    {
        if ($this->rawAddress !== null && $this->rawAddress !== '') {
            return $this->rawAddress;
        }

        $parts = array_filter([
            $this->postalCode,
            $this->region,
            $this->district,
            $this->city,
            $this->settlement,
            $this->street,
            $this->house !== null ? "д. {$this->house}" : null,
            $this->building !== null ? "корп. {$this->building}" : null,
            $this->apartment !== null ? "кв. {$this->apartment}" : null,
        ]);

        return implode(', ', $parts);
    }
}
