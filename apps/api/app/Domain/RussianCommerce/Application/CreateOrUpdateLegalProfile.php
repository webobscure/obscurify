<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Enums\LegalEntityType;
use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use App\Domain\RussianCommerce\Support\InnKppValidator;
use App\Domain\Stores\Models\Store;
use Illuminate\Validation\ValidationException;

/**
 * One profile per store — upserted, never duplicated (spec section 1).
 * Validates INN/KPP against the real ФНС checksum, not just format,
 * before ever persisting (spec section 23: "INN/KPP validation where
 * appropriate").
 */
final class CreateOrUpdateLegalProfile
{
    public function __construct(private readonly InnKppValidator $validator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Store $store, array $data): StoreLegalProfile
    {
        $type = LegalEntityType::from($data['legal_entity_type']);

        if (! $this->validator->isValidInn($data['inn'], $type)) {
            throw ValidationException::withMessages([
                'inn' => 'The INN is not valid for the given legal entity type.',
            ]);
        }

        if ($type === LegalEntityType::LegalEntity) {
            if (empty($data['kpp']) || ! $this->validator->isValidKpp($data['kpp'])) {
                throw ValidationException::withMessages([
                    'kpp' => 'A valid 9-digit KPP is required for a legal entity.',
                ]);
            }
        } else {
            // An IndividualEntrepreneur/SelfEmployed person never has a
            // KPP (spec section 1: "kpp nullable") — silently dropped
            // rather than rejected, since a client naively re-submitting
            // the same form after switching legal_entity_type shouldn't
            // have to remember to clear it themselves.
            $data['kpp'] = null;
        }

        if (! empty($data['ogrn']) && ! $this->validator->isValidOgrn($data['ogrn'])) {
            throw ValidationException::withMessages(['ogrn' => 'OGRN must be 13 digits.']);
        }

        if (! empty($data['ogrnip']) && ! $this->validator->isValidOgrnip($data['ogrnip'])) {
            throw ValidationException::withMessages(['ogrnip' => 'OGRNIP must be 15 digits.']);
        }

        return StoreLegalProfile::query()->updateOrCreate(
            ['store_id' => $store->id],
            $data,
        );
    }
}
