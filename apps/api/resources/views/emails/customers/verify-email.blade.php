{{ __('emails.verify_email.subject', ['store' => $storeName]) }}

{{ __('emails.verify_email.body') }}

{{ $verificationToken }}

{{ __('emails.verify_email.ignore') }}
