<?php

namespace App\Domain\Customers\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The plaintext reset token is only ever put on the wire here — never
 * returned in an API response body (that would defeat the point of
 * proving inbox possession). No transactional mail provider is wired up
 * yet; MAIL_MAILER defaults to 'log' (see config/mail.php), so this
 * writes to storage/logs in dev/test rather than failing.
 */
final class CustomerPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $resetToken, public readonly string $storeName) {}

    public function build(): self
    {
        return $this->subject("Reset your password — {$this->storeName}")
            ->text('emails.customers.password-reset', [
                'resetToken' => $this->resetToken,
                'storeName' => $this->storeName,
            ]);
    }
}
