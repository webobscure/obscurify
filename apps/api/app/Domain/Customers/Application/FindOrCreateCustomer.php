<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Models\Customer;
use Illuminate\Support\Str;

/**
 * Matching key is (active store, normalized email) — a store-scoped
 * lookup, never a DB-level unique constraint (see customers migration):
 * genuinely different guests can share an email in one store (e.g. a
 * shared family address used loosely), and enforcing uniqueness would
 * make that a hard error instead of a merchant/CRM concern.
 *
 * No email at all: always create a fresh Customer rather than trying to
 * match on phone/name, which are far weaker identity signals and would
 * risk merging two different people's orders together.
 */
final class FindOrCreateCustomer
{
    /**
     * @param  array{email?: string|null, phone?: string|null, first_name?: string|null, last_name?: string|null}  $data
     */
    public function handle(array $data): Customer
    {
        $email = $this->normalizeEmail($data['email'] ?? null);

        if ($email !== null) {
            $existing = Customer::query()->where('email', $email)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return Customer::query()->create([
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
        ]);
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return Str::lower(trim($email));
    }
}
