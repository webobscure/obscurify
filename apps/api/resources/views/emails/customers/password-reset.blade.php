{{ __('emails.password_reset.subject', ['store' => $storeName]) }}

{{ __('emails.password_reset.body') }}

{{ $resetToken }}

{{ __('emails.password_reset.ignore') }}
