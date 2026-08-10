<?php

namespace App\Domain\Webhooks\Enums;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Exhausted = 'exhausted';
}
