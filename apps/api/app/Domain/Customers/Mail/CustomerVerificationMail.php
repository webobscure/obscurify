<?php

namespace App\Domain\Customers\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class CustomerVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $verificationToken, public readonly string $storeName) {}

    public function build(): self
    {
        return $this->subject("Verify your email — {$this->storeName}")
            ->text('emails.customers.verify-email', [
                'verificationToken' => $this->verificationToken,
                'storeName' => $this->storeName,
            ]);
    }
}
