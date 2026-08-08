<?php

namespace App\Shared\Commerce\Enums;

/**
 * Shared between CheckoutAddress and OrderAddress — both are "one row
 * per type" snapshots, so the vocabulary is deliberately identical.
 */
enum AddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';
}
