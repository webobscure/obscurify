<?php

namespace App\Domain\RussianCommerce\Support;

use Illuminate\Support\Carbon;

/**
 * Spec section 10 — invoice *preparation* data for an order paid by
 * bank transfer, stored inside `Payment.method_metadata` when
 * `Payment.payment_method === bank_transfer`. Deliberately not a legally
 * final accounting document (spec: "Do not generate legally final
 * accounting documents yet") — `invoiceNumber` is a simple sequential
 * reference this store can quote to the payer, not a fiscal invoice
 * number in the 54-FZ sense (that's FiscalReceipt's job once a bank
 * transfer's payment is confirmed and fiscalization is requested).
 */
final readonly class BankTransferMetadata
{
    public function __construct(
        public string $invoiceNumber,
        public string $sellerLegalName,
        public string $sellerInn,
        public ?string $sellerKpp,
        public string $paymentPurpose,
        public ?Carbon $dueDate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceNumber: (string) $data['invoice_number'],
            sellerLegalName: (string) $data['seller_legal_name'],
            sellerInn: (string) $data['seller_inn'],
            sellerKpp: $data['seller_kpp'] ?? null,
            paymentPurpose: (string) $data['payment_purpose'],
            dueDate: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'bank_transfer',
            'invoice_number' => $this->invoiceNumber,
            'seller_legal_name' => $this->sellerLegalName,
            'seller_inn' => $this->sellerInn,
            'seller_kpp' => $this->sellerKpp,
            'payment_purpose' => $this->paymentPurpose,
            'due_date' => $this->dueDate?->toDateString(),
        ];
    }
}
