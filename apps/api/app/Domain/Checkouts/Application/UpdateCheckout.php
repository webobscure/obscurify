<?php

namespace App\Domain\Checkouts\Application;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Shared\Commerce\Enums\AddressType;
use Illuminate\Validation\ValidationException;

final class UpdateCheckout
{
    /**
     * @param  array{email?: string|null, phone?: string|null, shipping_address?: array<string, mixed>, billing_address?: array<string, mixed>, billing_same_as_shipping?: bool}  $data
     */
    public function handle(Checkout $checkout, array $data): Checkout
    {
        if ($checkout->status->value !== 'open') {
            throw ValidationException::withMessages([
                'checkout' => 'This checkout is no longer open.',
            ]);
        }

        $contact = array_filter([
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn ($value) => $value !== null);

        if ($contact !== []) {
            $checkout->update($contact);
        }

        $billingSameAsShipping = $data['billing_same_as_shipping'] ?? true;

        if (isset($data['shipping_address'])) {
            $this->upsertAddress($checkout, AddressType::Shipping, $data['shipping_address']);

            if ($billingSameAsShipping) {
                $this->upsertAddress($checkout, AddressType::Billing, $data['shipping_address']);
            }
        }

        if (! $billingSameAsShipping && isset($data['billing_address'])) {
            $this->upsertAddress($checkout, AddressType::Billing, $data['billing_address']);
        }

        return $checkout->fresh(['addresses']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertAddress(Checkout $checkout, AddressType $type, array $data): void
    {
        CheckoutAddress::query()->updateOrCreate(
            ['checkout_id' => $checkout->id, 'type' => $type->value],
            $data,
        );
    }
}
