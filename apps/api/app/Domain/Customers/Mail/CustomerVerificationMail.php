<?php

namespace App\Domain\Customers\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The caller sets the recipient's locale via the fluent
 * `Mail::to($x)->locale($localeCode)->queue(new self(...))` — Laravel's
 * `PendingMail::fill()` copies that onto this Mailable's own `$locale`
 * property BEFORE `send()`/`queue()` runs, which is what makes
 * `send()`'s `withLocale($this->locale, ...)` wrap `build()` (and this
 * class's `__()` calls) in the right `App::setLocale()` context. Setting
 * `->locale()` from *inside* `build()` would be too late — `send()`
 * already reads `$this->locale` before `build()` ever executes.
 */
final class CustomerVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $verificationToken, public readonly string $storeName) {}

    public function build(): self
    {
        return $this->subject(__('emails.verify_email.subject', ['store' => $this->storeName]))
            ->text('emails.customers.verify-email', [
                'verificationToken' => $this->verificationToken,
                'storeName' => $this->storeName,
            ]);
    }
}
