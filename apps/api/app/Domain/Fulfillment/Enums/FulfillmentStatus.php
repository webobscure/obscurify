<?php

namespace App\Domain\Fulfillment\Enums;

/**
 * Not to be confused with App\Domain\Orders\Enums\FulfillmentStatus, which
 * is the Order's own 3-state rollup (Unfulfilled/Partial/Fulfilled) across
 * all of its Fulfillments. This is the Fulfillment entity's own detailed
 * warehouse-workflow state (spec section 3) — deliberately not collapsed
 * into the Order's rollup, since "picking" vs "packing" is meaningless at
 * the Order level but essential at the Fulfillment level.
 */
enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Allocated = 'allocated';
    case Picking = 'picking';
    case Packing = 'packing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
