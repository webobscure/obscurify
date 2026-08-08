<?php

namespace App\Domain\Payments\Enums;

enum PaymentTransactionType: string
{
    case Authorization = 'authorization';
    case Capture = 'capture';
    case Payment = 'payment';
    case Cancel = 'cancel';
    case Refund = 'refund';
    case Webhook = 'webhook';
}
