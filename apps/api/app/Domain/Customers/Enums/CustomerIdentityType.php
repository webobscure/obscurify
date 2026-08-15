<?php

namespace App\Domain\Customers\Enums;

/**
 * Only EmailPassword exists today — social/OAuth customer login is
 * explicitly out of scope for this milestone (see
 * docs/adr/022-customer-identity.md). The enum exists so a future
 * identity type is a new case plus a new row per Customer, never a
 * schema change.
 */
enum CustomerIdentityType: string
{
    case EmailPassword = 'email_password';
}
