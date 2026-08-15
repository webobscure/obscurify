<?php

namespace App\Domain\Customers\Enums;

enum CustomerActionTokenPurpose: string
{
    case PasswordReset = 'password_reset';
    case EmailVerification = 'email_verification';
}
