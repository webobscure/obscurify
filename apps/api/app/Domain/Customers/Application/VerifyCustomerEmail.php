<?php

namespace App\Domain\Customers\Application;

use App\Domain\Customers\Enums\CustomerActionTokenPurpose;
use App\Domain\Customers\Exceptions\InvalidActionTokenException;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerActionToken;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

final class VerifyCustomerEmail
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(string $plainToken): Customer
    {
        $tokenHash = hash('sha256', $plainToken);

        return DB::transaction(function () use ($tokenHash) {
            $token = CustomerActionToken::query()
                ->where('token_hash', $tokenHash)
                ->where('purpose', CustomerActionTokenPurpose::EmailVerification->value)
                ->lockForUpdate()
                ->first();

            if ($token === null || ! $token->isUsable()) {
                throw InvalidActionTokenException::make();
            }

            $token->update(['used_at' => now()]);

            $customer = Customer::query()->findOrFail($token->customer_id);
            $customer->update(['verified_at' => now()]);

            $this->recordOutboxEvent->handle('CustomerVerified', 'Customer', $customer->id, [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
            ]);

            return $customer;
        });
    }
}
